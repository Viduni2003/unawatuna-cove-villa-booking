<?php
/**
 * availability.php - Room Availability Search
 * Visitors choose check-in, check-out and number of guests. The server validates
 * the input and lists active rooms that fit the party size and have NO overlapping
 * confirmed booking.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/components.php';

$max_guests = max_guest_capacity($pdo);

$check_in  = trim((string) ($_GET['check_in'] ?? ''));
$check_out = trim((string) ($_GET['check_out'] ?? ''));
$guests    = max(1, min(20, (int) ($_GET['guests'] ?? 2)));
$searched  = isset($_GET['check_in']) || isset($_GET['check_out']);

$errors = [];
$rooms  = [];
$nights = 0;

if ($searched) {
    $errors = validate_stay($check_in, $check_out, $guests);
    if (!$errors) {
        $nights = calculate_nights($check_in, $check_out);
        $rooms  = get_available_rooms($pdo, $check_in, $check_out, $guests);
    }
}

$stay = [
    'check_in'  => $check_in,
    'check_out' => $check_out,
    'guests'    => $guests,
    'nights'    => $nights,
];

$page_title = 'Check Availability';
require __DIR__ . '/includes/header.php';
?>

<div class="page-banner">
    <div class="container">
        <h1 class="fw-bold mb-1">Check Room Availability</h1>
        <p class="mb-0">Select your dates and number of guests to see the rooms you can book.</p>
    </div>
</div>

<div class="container py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <?= render_search_form(['check_in' => $check_in, 'check_out' => $check_out, 'guests' => $guests], $max_guests) ?>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <strong>Please fix the following:</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif ($searched): ?>
        <h2 class="h4 fw-bold mb-1">
            <?= count($rooms) ?> room<?= count($rooms) === 1 ? '' : 's' ?> available
        </h2>
        <p class="text-muted mb-4">
            <?= e(format_date($check_in)) ?> &ndash; <?= e(format_date($check_out)) ?>
            (<?= $nights ?> night<?= $nights > 1 ? 's' : '' ?>) &middot;
            <?= $guests ?> guest<?= $guests > 1 ? 's' : '' ?>
        </p>

        <?php if ($rooms): ?>
            <div class="row g-4">
                <?php foreach ($rooms as $room): ?>
                    <?= render_room_card($room, $stay) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                No rooms are available for those dates and party size. Try changing your dates, or book two
                smaller rooms if you are travelling with a larger group.
                <a href="<?= e(url('rooms.php')) ?>" class="alert-link">Browse all rooms</a>.
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info mb-0">
            <i class="bi bi-info-circle me-1"></i>Enter your travel dates above to see available rooms.
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
