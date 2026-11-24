<?php
/**
 * admin/index.php - administrator dashboard
 * Booking statistics, pending requests, upcoming arrivals and revenue per room.
 * Revenue figures count CONFIRMED bookings only.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin();

$today = date('Y-m-d');
$until = date('Y-m-d', strtotime('+30 days'));

/* ---- Headline numbers ---- */
$status_counts = array_map('intval', $pdo->query(
    'SELECT booking_status, COUNT(*) FROM bookings GROUP BY booking_status'
)->fetchAll(PDO::FETCH_KEY_PAIR));

$room_counts = array_map('intval', $pdo->query(
    'SELECT room_status, COUNT(*) FROM rooms GROUP BY room_status'
)->fetchAll(PDO::FETCH_KEY_PAIR));

$guest_count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'guest'")->fetchColumn();
$revenue     = (float) $pdo->query(
    "SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE booking_status = 'Confirmed'"
)->fetchColumn();

$pending   = $status_counts['Pending'] ?? 0;
$confirmed = $status_counts['Confirmed'] ?? 0;
$cancelled = $status_counts['Cancelled'] ?? 0;

/* ---- Oldest pending requests (they need attention first) ---- */
$pending_list = $pdo->query(
    "SELECT b.booking_id, b.check_in, b.check_out, b.total_amount, b.booking_date,
            u.name AS guest_name, r.room_name
     FROM bookings b
     JOIN users u ON u.user_id = b.user_id
     JOIN rooms r ON r.room_id = b.room_id
     WHERE b.booking_status = 'Pending'
     ORDER BY b.booking_date ASC, b.booking_id ASC
     LIMIT 8"
)->fetchAll();

/* ---- Confirmed arrivals in the next 30 days ---- */
$stmt = $pdo->prepare(
    "SELECT b.booking_id, b.check_in, b.check_out, b.guests,
            u.name AS guest_name, u.contact_number, r.room_name
     FROM bookings b
     JOIN users u ON u.user_id = b.user_id
     JOIN rooms r ON r.room_id = b.room_id
     WHERE b.booking_status = 'Confirmed' AND b.check_in BETWEEN ? AND ?
     ORDER BY b.check_in ASC
     LIMIT 8"
);
$stmt->execute([$today, $until]);
$arrivals = $stmt->fetchAll();

/* ---- Confirmed revenue by room ---- */
$by_room = $pdo->query(
    "SELECT r.room_name, COUNT(*) AS bookings, SUM(b.total_amount) AS revenue
     FROM bookings b
     JOIN rooms r ON r.room_id = b.room_id
     WHERE b.booking_status = 'Confirmed'
     GROUP BY r.room_id, r.room_name
     ORDER BY revenue DESC
     LIMIT 6"
)->fetchAll();

$page_title = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="fw-bold mb-1">Admin Dashboard</h1>
            <p class="mb-0">Welcome, <?= e(current_user_name()) ?>. Here is how the villa is doing.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= e(url('admin/room-form.php')) ?>" class="btn btn-warning fw-semibold">
                <i class="bi bi-plus-circle me-1"></i>Add room
            </a>
            <a href="<?= e(url('admin/bookings.php')) ?>" class="btn btn-outline-light">
                <i class="bi bi-journal-check me-1"></i>All bookings
            </a>
        </div>
    </div>
</div>

<div class="container py-4">

    <!-- ===== Statistic cards ===== -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-2">
            <a class="stat-card stat-warning" href="<?= e(url('admin/bookings.php') . '?status=Pending') ?>">
                <i class="bi bi-hourglass-split stat-icon"></i>
                <div class="stat-number"><?= $pending ?></div>
                <div class="stat-label">Pending requests</div>
            </a>
        </div>
        <div class="col-6 col-lg-2">
            <a class="stat-card stat-success" href="<?= e(url('admin/bookings.php') . '?status=Confirmed') ?>">
                <i class="bi bi-check-circle stat-icon"></i>
                <div class="stat-number"><?= $confirmed ?></div>
                <div class="stat-label">Confirmed</div>
            </a>
        </div>
        <div class="col-6 col-lg-2">
            <a class="stat-card stat-muted" href="<?= e(url('admin/bookings.php') . '?status=Cancelled') ?>">
                <i class="bi bi-x-circle stat-icon"></i>
                <div class="stat-number"><?= $cancelled ?></div>
                <div class="stat-label">Cancelled</div>
            </a>
        </div>
        <div class="col-6 col-lg-2">
            <a class="stat-card stat-info" href="<?= e(url('admin/rooms.php')) ?>">
                <i class="bi bi-door-open stat-icon"></i>
                <div class="stat-number"><?= $room_counts['active'] ?? 0 ?></div>
                <div class="stat-label">Active rooms</div>
            </a>
        </div>
        <div class="col-6 col-lg-2">
            <div class="stat-card stat-info">
                <i class="bi bi-people stat-icon"></i>
                <div class="stat-number"><?= $guest_count ?></div>
                <div class="stat-label">Registered guests</div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="stat-card stat-success">
                <i class="bi bi-cash-coin stat-icon"></i>
                <div class="stat-number stat-number-sm"><?= e(number_format($revenue, 0)) ?></div>
                <div class="stat-label">Confirmed revenue (LKR)</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- ===== Pending requests ===== -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0">Pending requests</h2>
                        <a href="<?= e(url('admin/bookings.php') . '?status=Pending') ?>" class="small">Review all</a>
                    </div>
                    <?php if ($pending_list): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr><th>Ref</th><th>Guest</th><th>Room</th><th>Stay</th><th class="text-end">Total</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_list as $p): ?>
                                        <tr>
                                            <td><?= e(booking_ref($p['booking_id'])) ?></td>
                                            <td><?= e($p['guest_name']) ?></td>
                                            <td><?= e($p['room_name']) ?></td>
                                            <td class="small"><?= e(format_date($p['check_in'])) ?> &ndash; <?= e(format_date($p['check_out'])) ?></td>
                                            <td class="text-end"><?= e(format_lkr($p['total_amount'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($pending > count($pending_list)): ?>
                            <p class="small text-muted mt-2 mb-0">Showing the <?= count($pending_list) ?> oldest of <?= $pending ?> pending requests.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0"><i class="bi bi-emoji-smile me-1"></i>No pending requests. You are all caught up.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ===== Revenue by room ===== -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-3">Confirmed revenue by room</h2>
                    <?php if ($by_room): ?>
                        <?php $max_rev = max(array_map(static fn($r) => (float) $r['revenue'], $by_room)); ?>
                        <?php foreach ($by_room as $row): ?>
                            <?php $pct = $max_rev > 0 ? round(((float) $row['revenue'] / $max_rev) * 100) : 0; ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small">
                                    <span class="fw-semibold"><?= e($row['room_name']) ?> <span class="text-muted">(<?= (int) $row['bookings'] ?>)</span></span>
                                    <span><?= e(format_lkr($row['revenue'])) ?></span>
                                </div>
                                <div class="progress" style="height: 8px;" role="progressbar" aria-label="Revenue share"
                                     aria-valuenow="<?= (int) $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-ocean" style="width: <?= (int) $pct ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">Revenue appears here once bookings are confirmed.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ===== Upcoming arrivals ===== -->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-3">Confirmed arrivals in the next 30 days</h2>
                    <?php if ($arrivals): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr><th>Check-in</th><th>Check-out</th><th>Guest</th><th>Contact</th><th>Room</th><th>Guests</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($arrivals as $a): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= e(format_date($a['check_in'])) ?></td>
                                            <td><?= e(format_date($a['check_out'])) ?></td>
                                            <td><?= e($a['guest_name']) ?></td>
                                            <td><?= e($a['contact_number']) ?></td>
                                            <td><?= e($a['room_name']) ?></td>
                                            <td><?= (int) $a['guests'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No confirmed arrivals in the next 30 days.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
