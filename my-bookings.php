<?php
/**
 * my-bookings.php - the logged-in guest's own bookings.
 * The query always filters by the session's user_id, so a guest can never
 * see another guest's bookings.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/components.php';

require_login();

if (is_admin()) {
    redirect(url('admin/bookings.php'));
}

$user_id = current_user_id();

/* ---- Status filter (allow-list) ---- */
$allowed_status = ['Pending', 'Confirmed', 'Cancelled'];
$status         = (string) ($_GET['status'] ?? 'all');
if (!in_array($status, $allowed_status, true)) {
    $status = 'all';
}

/* ---- Counts for the filter tabs ---- */
$stmt = $pdo->prepare('SELECT booking_status, COUNT(*) FROM bookings WHERE user_id = ? GROUP BY booking_status');
$stmt->execute([$user_id]);
$counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_count = array_sum(array_map('intval', $counts));

/* ---- The bookings ---- */
$sql = "SELECT b.booking_id, b.guests, b.check_in, b.check_out, b.total_amount, b.booking_status,
               b.special_requests, b.booking_date,
               r.room_id, r.room_name, r.room_type, r.image
        FROM bookings b
        JOIN rooms r ON r.room_id = b.room_id
        WHERE b.user_id = ?";
$params = [$user_id];

if ($status !== 'all') {
    $sql     .= ' AND b.booking_status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY b.booking_date DESC, b.booking_id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$today = date('Y-m-d');

$page_title = 'My Bookings';
require __DIR__ . '/includes/header.php';
?>

<div class="page-banner">
    <div class="container">
        <h1 class="fw-bold mb-1">My Bookings</h1>
        <p class="mb-0">View your reservations and cancel requests that are still pending.</p>
    </div>
</div>

<div class="container py-4">

    <!-- ===== Status tabs ===== -->
    <ul class="nav nav-pills mb-4 flex-wrap gap-1">
        <li class="nav-item">
            <a class="nav-link<?= $status === 'all' ? ' active' : '' ?>" href="<?= e(url('my-bookings.php')) ?>">
                All <span class="badge bg-light text-dark ms-1"><?= $total_count ?></span>
            </a>
        </li>
        <?php foreach ($allowed_status as $s): ?>
            <li class="nav-item">
                <a class="nav-link<?= $status === $s ? ' active' : '' ?>"
                   href="<?= e(url('my-bookings.php') . '?status=' . $s) ?>">
                    <?= e($s) ?> <span class="badge bg-light text-dark ms-1"><?= (int) ($counts[$s] ?? 0) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($bookings): ?>
        <div class="card shadow-sm border-0">
            <div class="table-responsive-stack">
                <table class="table align-middle mb-0 responsive-stack">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Room</th>
                            <th>Stay</th>
                            <th>Guests</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <?php $nights = calculate_nights($b['check_in'], $b['check_out']); ?>
                            <tr>
                                <td data-label="Reference">
                                    <strong><?= e(booking_ref($b['booking_id'])) ?></strong><br>
                                    <span class="small text-muted">Booked <?= e(date('d M Y', strtotime((string) $b['booking_date']))) ?></span>
                                </td>
                                <td data-label="Room">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= e(room_image_url((string) $b['image'])) ?>" alt=""
                                             class="booking-thumb d-none d-md-block">
                                        <div>
                                            <a href="<?= e(url('room-details.php') . '?id=' . (int) $b['room_id']) ?>"
                                               class="fw-semibold text-decoration-none"><?= e($b['room_name']) ?></a>
                                            <div class="small text-muted"><?= e($b['room_type']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Stay">
                                    <?= e(format_date($b['check_in'])) ?> &ndash; <?= e(format_date($b['check_out'])) ?>
                                    <div class="small text-muted"><?= $nights ?> night<?= $nights === 1 ? '' : 's' ?></div>
                                </td>
                                <td data-label="Guests"><?= (int) $b['guests'] ?></td>
                                <td data-label="Total" class="text-end fw-semibold"><?= e(format_lkr($b['total_amount'])) ?></td>
                                <td data-label="Status">
                                    <?= status_badge((string) $b['booking_status']) ?>
                                    <?php if ($b['booking_status'] === 'Confirmed' && $b['check_out'] < $today): ?>
                                        <div class="small text-muted">Stay completed</div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Action">
                                    <?php if ($b['booking_status'] === 'Pending'): ?>
                                        <form method="post" action="<?= e(url('cancel-booking.php')) ?>"
                                              data-confirm="Cancel booking <?= e(booking_ref($b['booking_id'])) ?> for <?= e($b['room_name']) ?>? This cannot be undone.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="booking_id" value="<?= (int) $b['booking_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-x-circle me-1"></i>Cancel
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">&ndash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($b['special_requests'])): ?>
                                <tr class="request-row">
                                    <td colspan="7" class="small text-muted border-top-0 pt-0">
                                        <i class="bi bi-chat-left-text me-1"></i>
                                        <strong>Your request:</strong> <?= e($b['special_requests']) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="small text-muted mt-3">
            Only <strong>Pending</strong> bookings can be cancelled online. To change a confirmed booking, please contact the villa
            on +94 77 123 4567.
        </p>
    <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-5">
                <i class="bi bi-calendar2-x display-4 text-muted"></i>
                <h2 class="h5 mt-3">
                    <?= $status === 'all' ? 'You have no bookings yet' : 'No ' . e(strtolower($status)) . ' bookings' ?>
                </h2>
                <p class="text-muted">Find a room and send your first booking request.</p>
                <a href="<?= e(url('availability.php')) ?>" class="btn btn-ocean">Check availability</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
