<?php
/**
 * rooms.php - lists all active rooms with search and filters
 * (room type, minimum capacity, maximum price, facility, sorting).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/components.php';

/* ---- Options for the filter drop-downs ---- */
$types = $pdo->query(
    "SELECT DISTINCT room_type FROM rooms WHERE room_status = 'active' ORDER BY room_type"
)->fetchAll(PDO::FETCH_COLUMN);

$all_facilities = [];
foreach ($pdo->query("SELECT facilities FROM rooms WHERE room_status = 'active'")->fetchAll(PDO::FETCH_COLUMN) as $csv) {
    foreach (facilities_list((string) $csv) as $f) {
        $all_facilities[$f] = $f;
    }
}
natcasesort($all_facilities);

$max_guests = max_guest_capacity($pdo);

/* ---- Read and sanitise filters ---- */
$type      = trim((string) ($_GET['type'] ?? ''));
$facility  = trim((string) ($_GET['facility'] ?? ''));
$guests    = (int) ($_GET['guests'] ?? 0);
$sort      = (string) ($_GET['sort'] ?? 'price_asc');
$max_price = trim((string) ($_GET['max_price'] ?? ''));
$max_price_value = ($max_price !== '' && is_numeric($max_price) && (float) $max_price > 0) ? (float) $max_price : 0.0;

/* ---- Build the query with prepared-statement parameters ---- */
$where  = ["room_status = 'active'"];
$params = [];

if ($type !== '') {
    $where[]  = 'room_type = ?';
    $params[] = $type;
}
if ($guests > 0) {
    $where[]  = 'max_guests >= ?';
    $params[] = $guests;
}
if ($max_price_value > 0) {
    $where[]  = 'price_per_night <= ?';
    $params[] = $max_price_value;
}
if ($facility !== '') {
    // facilities are stored as a comma-separated list; ignore spaces after commas
    $where[]  = "FIND_IN_SET(?, REPLACE(facilities, ', ', ',')) > 0";
    $params[] = $facility;
}

$order = match ($sort) {
    'price_desc' => 'price_per_night DESC',
    'capacity'   => 'max_guests DESC, price_per_night ASC',
    default      => 'price_per_night ASC',
};

$stmt = $pdo->prepare('SELECT * FROM rooms WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

$filters_active = ($type !== '' || $facility !== '' || $guests > 0 || $max_price_value > 0);

$page_title = 'Rooms & Accommodation';
require __DIR__ . '/includes/header.php';
?>

<div class="page-banner">
    <div class="container">
        <h1 class="fw-bold mb-1">Rooms &amp; Accommodation</h1>
        <p class="mb-0">Choose from our garden rooms, sea-view suites and private pool villa.</p>
    </div>
</div>

<div class="container py-4">

    <!-- ===== Filters ===== -->
    <form method="get" action="" class="card card-body shadow-sm border-0 mb-4" id="filterForm">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-3 col-lg-2">
                <label for="type" class="form-label small fw-semibold">Room type</label>
                <select name="type" id="type" class="form-select">
                    <option value="">All types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= e($t) ?>"<?= $t === $type ? ' selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="guests" class="form-label small fw-semibold">Guests</label>
                <select name="guests" id="guests" class="form-select">
                    <option value="0">Any</option>
                    <?php for ($i = 1; $i <= $max_guests; $i++): ?>
                        <option value="<?= $i ?>"<?= $i === $guests ? ' selected' : '' ?>><?= $i ?>+</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="max_price" class="form-label small fw-semibold">Max price (LKR)</label>
                <input type="number" name="max_price" id="max_price" class="form-control" min="0" step="500"
                       placeholder="e.g. 20000" value="<?= $max_price_value > 0 ? e($max_price_value) : '' ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="facility" class="form-label small fw-semibold">Facility</label>
                <select name="facility" id="facility" class="form-select">
                    <option value="">Any</option>
                    <?php foreach ($all_facilities as $f): ?>
                        <option value="<?= e($f) ?>"<?= $f === $facility ? ' selected' : '' ?>><?= e($f) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="sort" class="form-label small fw-semibold">Sort by</label>
                <select name="sort" id="sort" class="form-select">
                    <option value="price_asc"<?= $sort === 'price_asc' ? ' selected' : '' ?>>Price: low to high</option>
                    <option value="price_desc"<?= $sort === 'price_desc' ? ' selected' : '' ?>>Price: high to low</option>
                    <option value="capacity"<?= $sort === 'capacity' ? ' selected' : '' ?>>Largest capacity</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-ocean flex-fill">Filter</button>
                <?php if ($filters_active): ?>
                    <a href="<?= e(url('rooms.php')) ?>" class="btn btn-outline-secondary" title="Clear filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <p class="text-muted"><?= count($rooms) ?> room<?= count($rooms) === 1 ? '' : 's' ?> found</p>

    <?php if ($rooms): ?>
        <div class="row g-4">
            <?php foreach ($rooms as $room): ?>
                <?= render_room_card($room) ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            No rooms match your filters. <a href="<?= e(url('rooms.php')) ?>" class="alert-link">Clear the filters</a>
            to see all rooms.
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
