<?php
/**
 * book.php - Room booking form (registered guests only)
 *
 * - Guest name, email and contact number come from the logged-in account.
 * - The visitor chooses room, dates, number of guests and optional requests.
 * - JavaScript shows the live nights/total; the SERVER recalculates everything
 *   and never trusts the price or total sent by the browser.
 * - The booking is stored as 'Pending' until the administrator confirms it.
 *
 * Accepts optional GET parameters: room_id, check_in, check_out, guests.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/components.php';

require_login();

if (is_admin()) {
    flash_set('info', 'Administrator accounts manage bookings and cannot make guest bookings.');
    redirect(url('admin/bookings.php'));
}

/* ---- Active rooms (for the drop-down and server-side checks) ---- */
$room_rows = $pdo->query(
    "SELECT room_id, room_name, room_type, price_per_night, max_guests
     FROM rooms WHERE room_status = 'active' ORDER BY room_name ASC"
)->fetchAll();

if (!$room_rows) {
    flash_set('warning', 'No rooms are open for booking at the moment. Please check back later.');
    redirect(url('rooms.php'));
}

$rooms_by_id = [];
foreach ($room_rows as $r) {
    $rooms_by_id[(int) $r['room_id']] = $r;
}
$max_guests_all = (int) max(array_column($room_rows, 'max_guests'));

/* ---- Logged-in guest's details ---- */
$stmt = $pdo->prepare('SELECT name, email, contact_number FROM users WHERE user_id = ? LIMIT 1');
$stmt->execute([current_user_id()]);
$guest = $stmt->fetch();

if (!$guest) {                       // account was removed while the session was active
    logout_user();
    flash_set('danger', 'Your account could not be found. Please register again.');
    redirect(url('auth/register.php'));
}

/* ---- Form values (POST when submitting, GET when pre-filled from another page) ---- */
$is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
$source  = $is_post ? $_POST : $_GET;

$room_id  = (int) ($source['room_id'] ?? 0);
$check_in = trim((string) ($source['check_in'] ?? ''));
$check_out = trim((string) ($source['check_out'] ?? ''));
$guests   = max(1, (int) ($source['guests'] ?? 2));
$special  = trim((string) ($source['special_requests'] ?? ''));

$errors = [];

if ($is_post) {
    require_post_csrf();

    $room = $rooms_by_id[$room_id] ?? null;
    if ($room === null) {
        $errors[] = 'Please select a valid room.';
    }

    // Validates dates, range, maximum stay and (if the room is known) capacity
    $errors = array_merge(
        $errors,
        validate_stay($check_in, $check_out, $guests, $room !== null ? (int) $room['max_guests'] : null)
    );

    if (mb_strlen($special) > 500) {
        $errors[] = 'Special requests must not exceed 500 characters.';
    }

    if (!$errors && $room !== null) {
        // 1) The room must be free of CONFIRMED bookings for these dates
        if (!is_room_available($pdo, $room_id, $check_in, $check_out)) {
            $errors[] = 'Sorry, ' . $room['room_name'] . ' is no longer available for those dates. '
                      . 'Please choose different dates or another room.';
        }

        // 2) Stop accidental duplicates: the guest already holds an active request for this room/dates
        if (!$errors) {
            $dup = $pdo->prepare(
                "SELECT COUNT(*) FROM bookings
                 WHERE user_id = ? AND room_id = ?
                   AND booking_status IN ('Pending','Confirmed')
                   AND check_in < ? AND check_out > ?"
            );
            $dup->execute([current_user_id(), $room_id, $check_out, $check_in]);
            if ((int) $dup->fetchColumn() > 0) {
                $errors[] = 'You already have a pending or confirmed booking for this room that overlaps these dates. '
                          . 'Please check My Bookings.';
            }
        }
    }

    if (!$errors && $room !== null) {
        // Everything is recalculated on the server
        $nights = calculate_nights($check_in, $check_out);
        $total  = calculate_total($room['price_per_night'], $nights);

        try {
            $ins = $pdo->prepare(
                "INSERT INTO bookings
                    (user_id, room_id, guests, check_in, check_out, total_amount, booking_status, special_requests)
                 VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)"
            );
            $ins->execute([
                current_user_id(),
                $room_id,
                $guests,
                $check_in,
                $check_out,
                $total,
                $special !== '' ? $special : null,
            ]);

            $ref = booking_ref((int) $pdo->lastInsertId());
            flash_set(
                'success',
                "Booking request {$ref} submitted! {$room['room_name']}, {$nights} night(s), total "
                . format_lkr($total) . '. The villa will confirm it shortly.'
            );
            redirect(url('my-bookings.php'));
        } catch (PDOException $ex) {
            error_log('Booking insert failed: ' . $ex->getMessage());
            $errors[] = 'We could not save your booking because of a system error. Please try again.';
        }
    }
}

/* ---- Initial summary (JavaScript keeps it live afterwards) ---- */
$summary_room   = $rooms_by_id[$room_id] ?? null;
$summary_nights = calculate_nights($check_in, $check_out);
$summary_total  = ($summary_room && $summary_nights > 0)
    ? calculate_total($summary_room['price_per_night'], $summary_nights)
    : 0.0;

$page_title = 'Book a Room';
require __DIR__ . '/includes/header.php';
?>

<div class="page-banner">
    <div class="container">
        <h1 class="fw-bold mb-1">Book Your Stay</h1>
        <p class="mb-0">Send a booking request. The villa will confirm it as soon as possible.</p>
    </div>
</div>

<div class="container py-4">
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <strong>We could not submit your booking:</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="" id="bookingForm" data-validate="booking"
          data-availability-url="<?= e(url('api/check-availability.php')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="row g-4">
            <!-- ===== Left column: form fields ===== -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-3"><i class="bi bi-person me-2 text-ocean"></i>Guest details</h2>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="guest_name" class="form-label">Name</label>
                                <input type="text" id="guest_name" class="form-control" value="<?= e($guest['name']) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="guest_email" class="form-label">Email</label>
                                <input type="email" id="guest_email" class="form-control" value="<?= e($guest['email']) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="guest_contact" class="form-label">Contact number</label>
                                <input type="text" id="guest_contact" class="form-control" value="<?= e($guest['contact_number']) ?>" readonly>
                            </div>
                        </div>
                        <p class="form-text mb-0 mt-2">These details come from your account.</p>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-3"><i class="bi bi-calendar-week me-2 text-ocean"></i>Stay details</h2>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="room_id" class="form-label fw-semibold">Room</label>
                                <select class="form-select" id="room_id" name="room_id" required>
                                    <option value="">-- Select a room --</option>
                                    <?php foreach ($room_rows as $r): ?>
                                        <option value="<?= (int) $r['room_id'] ?>"
                                                data-price="<?= e($r['price_per_night']) ?>"
                                                data-max="<?= (int) $r['max_guests'] ?>"
                                                data-name="<?= e($r['room_name']) ?>"
                                                <?= (int) $r['room_id'] === $room_id ? 'selected' : '' ?>>
                                            <?= e($r['room_name']) ?> (<?= e($r['room_type']) ?>) &ndash;
                                            <?= e(format_lkr($r['price_per_night'])) ?> / night, max <?= (int) $r['max_guests'] ?> guest(s)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="col-md-4">
                                <label for="check_in" class="form-label fw-semibold">Check-in</label>
                                <input type="date" class="form-control" id="check_in" name="check_in"
                                       min="<?= e(date('Y-m-d')) ?>" value="<?= e($check_in) ?>" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-4">
                                <label for="check_out" class="form-label fw-semibold">Check-out</label>
                                <input type="date" class="form-control" id="check_out" name="check_out"
                                       min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" value="<?= e($check_out) ?>" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-4">
                                <label for="guests" class="form-label fw-semibold">Guests</label>
                                <select class="form-select" id="guests" name="guests" required>
                                    <?php for ($i = 1; $i <= $max_guests_all; $i++): ?>
                                        <option value="<?= $i ?>"<?= $i === $guests ? ' selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="col-12">
                                <label for="special_requests" class="form-label">
                                    Special requests <span class="text-muted">(optional)</span>
                                </label>
                                <textarea class="form-control" id="special_requests" name="special_requests"
                                          rows="3" maxlength="500"
                                          placeholder="Arrival time, airport pick-up, dietary needs, celebrations..."><?= e($special) ?></textarea>
                                <div class="d-flex justify-content-between">
                                    <div class="invalid-feedback d-block"></div>
                                    <div class="form-text ms-auto"><span id="requestCount"><?= mb_strlen($special) ?></span>/500</div>
                                </div>
                            </div>
                        </div>

                        <div id="availabilityMsg" class="mt-3" role="status" aria-live="polite"></div>
                    </div>
                </div>
            </div>

            <!-- ===== Right column: live booking summary ===== -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 sticky-lg-top booking-panel">
                    <div class="card-body p-4">
                        <h2 class="h5 fw-bold mb-3">Booking summary</h2>
                        <dl class="row mb-0 summary-list">
                            <dt class="col-6">Room</dt>
                            <dd class="col-6 text-end" id="summaryRoom"><?= $summary_room ? e($summary_room['room_name']) : '&ndash;' ?></dd>

                            <dt class="col-6">Price / night</dt>
                            <dd class="col-6 text-end" id="summaryPrice"><?= $summary_room ? e(format_lkr($summary_room['price_per_night'])) : '&ndash;' ?></dd>

                            <dt class="col-6">Nights</dt>
                            <dd class="col-6 text-end" id="summaryNights"><?= $summary_nights > 0 ? $summary_nights : '&ndash;' ?></dd>
                        </dl>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Estimated total</span>
                            <span class="fs-4 fw-bold text-ocean" id="summaryTotal"><?= $summary_total > 0 ? e(format_lkr($summary_total)) : '&ndash;' ?></span>
                        </div>
                        <p class="small text-muted mt-2">
                            The final amount is calculated by the server when you submit. Payment is arranged directly with
                            the villa after your booking is confirmed.
                        </p>
                        <button type="submit" class="btn btn-ocean btn-lg w-100" id="submitBtn">
                            <i class="bi bi-send me-1"></i>Submit booking request
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
