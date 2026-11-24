<?php
/**
 * room-details.php?id=ROOM_ID
 * Shows one room in detail, lists its already-confirmed dates and lets the
 * visitor check availability and total cost for chosen dates.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/components.php';

$room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$room = false;
if ($room_id !== false && $room_id !== null && $room_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_id = ? AND room_status = 'active' LIMIT 1");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
}

if (!$room) {
    flash_set('danger', 'That room could not be found.');
    redirect(url('rooms.php'));
}

/* ---- Dates that are already confirmed for this room (no guest details shown) ---- */
$stmt = $pdo->prepare(
    "SELECT check_in, check_out FROM bookings
     WHERE room_id = ? AND booking_status = 'Confirmed' AND check_out >= CURDATE()
     ORDER BY check_in ASC"
);
$stmt->execute([$room_id]);
$booked_ranges = $stmt->fetchAll();

/* ---- Optional availability check for chosen dates ---- */
$max_guests = (int) $room['max_guests'];
$check_in   = trim((string) ($_GET['check_in'] ?? ''));
$check_out  = trim((string) ($_GET['check_out'] ?? ''));
$guests     = max(1, (int) ($_GET['guests'] ?? 1));
$checked    = isset($_GET['check_in']) || isset($_GET['check_out']);

$errors    = [];
$available = null;
$nights    = 0;
$total     = 0.0;

if ($checked) {
    $errors = validate_stay($check_in, $check_out, $guests, $max_guests);
    if (!$errors) {
        $nights    = calculate_nights($check_in, $check_out);
        $total     = calculate_total($room['price_per_night'], $nights);
        $available = is_room_available($pdo, (int) $room['room_id'], $check_in, $check_out);
    }
}

$page_title = (string) $room['room_name'];
require __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(url('index.php')) ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= e(url('rooms.php')) ?>">Rooms</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($room['room_name']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- ===== Left: image and details ===== -->
        <div class="col-lg-7">
            <img src="<?= e(room_image_url((string) $room['image'])) ?>" class="img-fluid rounded shadow-sm w-100 detail-img"
                 alt="<?= e($room['room_name']) ?>">

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mt-4">
                <h1 class="h2 fw-bold mb-0"><?= e($room['room_name']) ?></h1>
                <span class="badge bg-info text-dark fs-6"><?= e($room['room_type']) ?></span>
            </div>
            <p class="text-muted mt-1"><i class="bi bi-people me-1"></i>Sleeps up to <?= $max_guests ?> guest(s)</p>

            <h2 class="h5 fw-bold mt-4">About this room</h2>
            <p><?= nl2br(e($room['description'])) ?></p>

            <h2 class="h5 fw-bold mt-4">Facilities</h2>
            <div class="row g-2">
                <?php foreach (facilities_list((string) $room['facilities']) as $facility): ?>
                    <div class="col-6 col-md-4">
                        <div class="facility-item">
                            <i class="bi <?= e(facility_icon($facility)) ?> me-2 text-ocean"></i><?= e($facility) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2 class="h5 fw-bold mt-4">Already booked dates</h2>
            <?php if ($booked_ranges): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($booked_ranges as $range): ?>
                        <li class="list-group-item px-0">
                            <i class="bi bi-calendar-x text-danger me-2"></i>
                            <?= e(format_date($range['check_in'])) ?> &ndash; <?= e(format_date($range['check_out'])) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="small text-muted mt-2">Pending requests are not shown; a room is blocked only once a booking is confirmed.</p>
            <?php else: ?>
                <p class="text-muted">There are no confirmed bookings for this room at the moment.</p>
            <?php endif; ?>
        </div>

        <!-- ===== Right: price and availability check ===== -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 sticky-lg-top booking-panel">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <span class="fs-3 fw-bold text-ocean"><?= e(format_lkr($room['price_per_night'])) ?></span>
                        <span class="text-muted">/ night</span>
                    </div>

                    <?php if ($errors): ?>
                        <div class="alert alert-danger py-2">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="get" action="" id="roomCheckForm" data-validate="search" novalidate>
                        <input type="hidden" name="id" value="<?= (int) $room['room_id'] ?>">

                        <div class="mb-3">
                            <label for="check_in" class="form-label fw-semibold">Check-in</label>
                            <input type="date" class="form-control" id="check_in" name="check_in"
                                   min="<?= e(date('Y-m-d')) ?>" value="<?= e($check_in) ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label for="check_out" class="form-label fw-semibold">Check-out</label>
                            <input type="date" class="form-control" id="check_out" name="check_out"
                                   min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" value="<?= e($check_out) ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label for="guests" class="form-label fw-semibold">Guests</label>
                            <select class="form-select" id="guests" name="guests" required>
                                <?php for ($i = 1; $i <= $max_guests; $i++): ?>
                                    <option value="<?= $i ?>"<?= $i === min($guests, $max_guests) ? ' selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <button type="submit" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-calendar-week me-1"></i>Check availability
                        </button>
                    </form>

                    <?php if ($available === true): ?>
                        <div class="alert alert-success mt-3 mb-0" id="availabilityResult">
                            <i class="bi bi-check-circle-fill me-1"></i>
                            <strong>Available!</strong>
                            <?= $nights ?> night<?= $nights > 1 ? 's' : '' ?>,
                            estimated total <strong><?= e(format_lkr($total)) ?></strong>.
                            <a href="<?= e(book_url((int) $room['room_id'], $check_in, $check_out, $guests)) ?>"
                               class="btn btn-ocean w-100 mt-3">Book this room</a>
                        </div>
                    <?php elseif ($available === false): ?>
                        <div class="alert alert-danger mt-3 mb-0" id="availabilityResult">
                            <i class="bi bi-x-circle-fill me-1"></i>
                            Sorry, this room is not available for those dates. Please try different dates or
                            <a href="<?= e(url('availability.php') . '?' . http_build_query(['check_in' => $check_in, 'check_out' => $check_out, 'guests' => $guests])) ?>"
                               class="alert-link">search other rooms</a>.
                        </div>
                    <?php else: ?>
                        <a href="<?= e(book_url((int) $room['room_id'])) ?>" class="btn btn-ocean w-100 mt-3">
                            <i class="bi bi-calendar-check me-1"></i>Book this room
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
