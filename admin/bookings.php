<?php
/**
 * admin/bookings.php - all bookings with filters, search and pagination.
 * Pending bookings that overlap an already CONFIRMED booking are flagged and
 * their Confirm button is disabled (the server enforces this again in
 * booking-action.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin();

const PER_PAGE = 10;

$allowed_status = ['Pending', 'Confirmed', 'Cancelled'];

/* ---- Read filters ---- */
$status = (string) ($_GET['status'] ?? '');
if (!in_array($status, $allowed_status, true)) {
    $status = '';
}
$room_filter = max(0, (int) ($_GET['room'] ?? 0));
$q           = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$page        = max(1, (int) ($_GET['page'] ?? 1));

/* ---- Build WHERE (every user value is a bound parameter) ---- */
$where  = ['1 = 1'];
$params = [];

if ($status !== '') {
    $where[]  = 'b.booking_status = ?';
    $params[] = $status;
}
if ($room_filter > 0) {
    $where[]  = 'b.room_id = ?';
    $params[] = $room_filter;
}
if ($q !== '') {
    $like     = '%' . addcslashes($q, '%_\\') . '%';
    $clause   = '(u.name LIKE ? OR u.email LIKE ?';
    $params[] = $like;
    $params[] = $like;
    if (preg_match('/^(?:VB-?)?0*([0-9]+)$/i', $q, $m)) {      // "VB-00012", "12"
        $clause  .= ' OR b.booking_id = ?';
        $params[] = (int) $m[1];
    }
    $where[] = $clause . ')';
}
$where_sql = implode(' AND ', $where);

/* ---- Pagination ---- */
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings b
     JOIN users u ON u.user_id = b.user_id
     JOIN rooms r ON r.room_id = b.room_id
     WHERE $where_sql"
);
$stmt->execute($params);
$total_rows  = (int) $stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_rows / PER_PAGE));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * PER_PAGE;

/* ---- The page of bookings (LIMIT/OFFSET are integers, never user text) ---- */
$limit  = PER_PAGE;
$stmt = $pdo->prepare(
    "SELECT b.*, u.name AS guest_name, u.email AS guest_email, u.contact_number,
            r.room_name, r.room_status,
            EXISTS (
                SELECT 1 FROM bookings c
                WHERE c.room_id = b.room_id
                  AND c.booking_status = 'Confirmed'
                  AND c.booking_id <> b.booking_id
                  AND c.check_in < b.check_out
                  AND c.check_out > b.check_in
            ) AS has_conflict
     FROM bookings b
     JOIN users u ON u.user_id = b.user_id
     JOIN rooms r ON r.room_id = b.room_id
     WHERE $where_sql
     ORDER BY FIELD(b.booking_status, 'Pending', 'Confirmed', 'Cancelled'), b.check_in ASC, b.booking_id ASC
     LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

/* ---- Tab counts and room list ---- */
$counts = array_map('intval', $pdo->query(
    'SELECT booking_status, COUNT(*) FROM bookings GROUP BY booking_status'
)->fetchAll(PDO::FETCH_KEY_PAIR));
$all_count = array_sum($counts);

$room_options = $pdo->query('SELECT room_id, room_name FROM rooms ORDER BY room_name')->fetchAll();

/* ---- Link builder that keeps the current filters ---- */
$filters = ['status' => $status, 'room' => $room_filter, 'q' => $q];
$link = static function (array $override) use ($filters): string {
    $merged = array_filter(
        array_merge($filters, $override),
        static fn($v) => $v !== '' && $v !== null && $v !== 0
    );
    return url('admin/bookings.php') . ($merged ? '?' . http_build_query($merged) : '');
};

$return_to = $_SERVER['REQUEST_URI'] ?? url('admin/bookings.php');
$today     = date('Y-m-d');

$page_title = 'Manage Bookings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <div class="container">
        <h1 class="fw-bold mb-1">Manage Bookings</h1>
        <p class="mb-0">Review booking requests, confirm them or cancel them.</p>
    </div>
</div>

<div class="container py-4">

    <!-- ===== Status tabs ===== -->
    <ul class="nav nav-pills mb-3 flex-wrap gap-1">
        <li class="nav-item">
            <a class="nav-link<?= $status === '' ? ' active' : '' ?>" href="<?= e($link(['status' => '', 'page' => 0])) ?>">
                All <span class="badge bg-light text-dark ms-1"><?= $all_count ?></span>
            </a>
        </li>
        <?php foreach ($allowed_status as $s): ?>
            <li class="nav-item">
                <a class="nav-link<?= $status === $s ? ' active' : '' ?>" href="<?= e($link(['status' => $s, 'page' => 0])) ?>">
                    <?= e($s) ?> <span class="badge bg-light text-dark ms-1"><?= $counts[$s] ?? 0 ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- ===== Search and room filter ===== -->
    <form method="get" action="" class="row g-2 mb-4">
        <?php if ($status !== ''): ?>
            <input type="hidden" name="status" value="<?= e($status) ?>">
        <?php endif; ?>
        <div class="col-md-5">
            <input type="search" name="q" class="form-control" value="<?= e($q) ?>" maxlength="100"
                   placeholder="Search guest name, email or booking reference (e.g. VB-00005)" aria-label="Search bookings">
        </div>
        <div class="col-md-4">
            <select name="room" class="form-select" aria-label="Filter by room">
                <option value="0">All rooms</option>
                <?php foreach ($room_options as $ro): ?>
                    <option value="<?= (int) $ro['room_id'] ?>"<?= (int) $ro['room_id'] === $room_filter ? ' selected' : '' ?>>
                        <?= e($ro['room_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-ocean flex-fill"><i class="bi bi-search me-1"></i>Filter</button>
            <?php if ($q !== '' || $room_filter > 0): ?>
                <a href="<?= e($link(['q' => '', 'room' => 0, 'page' => 0])) ?>" class="btn btn-outline-secondary" title="Clear search and room filter">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>

    <p class="text-muted"><?= $total_rows ?> booking<?= $total_rows === 1 ? '' : 's' ?> found</p>

    <?php if ($bookings): ?>
        <div class="card shadow-sm border-0">
            <div class="table-responsive-stack">
                <table class="table align-middle mb-0 responsive-stack">
                    <thead class="table-light">
                        <tr>
                            <th>Ref</th>
                            <th>Guest</th>
                            <th>Room</th>
                            <th>Stay</th>
                            <th>Guests</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <?php
                                $ref      = booking_ref($b['booking_id']);
                                $nights   = calculate_nights($b['check_in'], $b['check_out']);
                                $conflict = (int) $b['has_conflict'] === 1;
                            ?>
                            <tr>
                                <td data-label="Ref">
                                    <strong><?= e($ref) ?></strong>
                                    <div class="small text-muted"><?= e(date('d M Y', strtotime((string) $b['booking_date']))) ?></div>
                                </td>
                                <td data-label="Guest">
                                    <div class="fw-semibold"><?= e($b['guest_name']) ?></div>
                                    <div class="small text-muted"><?= e($b['guest_email']) ?></div>
                                    <div class="small text-muted"><?= e($b['contact_number']) ?></div>
                                </td>
                                <td data-label="Room">
                                    <?= e($b['room_name']) ?>
                                    <?php if ($b['room_status'] === 'inactive'): ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Stay">
                                    <?= e(format_date($b['check_in'])) ?> &ndash; <?= e(format_date($b['check_out'])) ?>
                                    <div class="small text-muted"><?= $nights ?> night<?= $nights === 1 ? '' : 's' ?></div>
                                </td>
                                <td data-label="Guests"><?= (int) $b['guests'] ?></td>
                                <td data-label="Total" class="text-end fw-semibold"><?= e(format_lkr($b['total_amount'])) ?></td>
                                <td data-label="Status">
                                    <?= status_badge((string) $b['booking_status']) ?>
                                    <?php if ($b['booking_status'] === 'Pending' && $conflict): ?>
                                        <div class="small text-danger mt-1">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Overlaps a confirmed booking
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions">
                                    <?php if ($b['booking_status'] === 'Pending'): ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <form method="post" action="<?= e(url('admin/booking-action.php')) ?>"
                                                  data-confirm="Confirm booking <?= e($ref) ?> for <?= e($b['guest_name']) ?>? The room will be blocked for these dates.">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="booking_id" value="<?= (int) $b['booking_id'] ?>">
                                                <input type="hidden" name="action" value="confirm">
                                                <input type="hidden" name="return" value="<?= e($return_to) ?>">
                                                <button type="submit" class="btn btn-sm btn-success"
                                                        <?= $conflict ? 'disabled title="Another confirmed booking already occupies this room on these dates."' : '' ?>>
                                                    <i class="bi bi-check-lg me-1"></i>Confirm
                                                </button>
                                            </form>
                                            <form method="post" action="<?= e(url('admin/booking-action.php')) ?>"
                                                  data-confirm="Cancel booking <?= e($ref) ?> for <?= e($b['guest_name']) ?>?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="booking_id" value="<?= (int) $b['booking_id'] ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="return" value="<?= e($return_to) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-x-lg me-1"></i>Cancel
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif ($b['booking_status'] === 'Confirmed'): ?>
                                        <form method="post" action="<?= e(url('admin/booking-action.php')) ?>"
                                              data-confirm="Cancel the CONFIRMED booking <?= e($ref) ?> for <?= e($b['guest_name']) ?>? The room will become available again.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="booking_id" value="<?= (int) $b['booking_id'] ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="return" value="<?= e($return_to) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-x-lg me-1"></i>Cancel
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">&ndash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($b['special_requests'])): ?>
                                <tr class="request-row">
                                    <td colspan="8" class="small text-muted border-top-0 pt-0">
                                        <i class="bi bi-chat-left-text me-1"></i>
                                        <strong>Guest request:</strong> <?= e($b['special_requests']) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== Pagination ===== -->
        <?php if ($total_pages > 1): ?>
            <nav class="mt-4" aria-label="Bookings pages">
                <ul class="pagination justify-content-center flex-wrap">
                    <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                        <a class="page-link" href="<?= e($link(['page' => $page - 1])) ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                            <a class="page-link" href="<?= e($link(['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item<?= $page >= $total_pages ? ' disabled' : '' ?>">
                        <a class="page-link" href="<?= e($link(['page' => $page + 1])) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">No bookings match your filters.</div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
