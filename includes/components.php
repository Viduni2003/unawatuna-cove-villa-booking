<?php
/**
 * includes/components.php
 * Reusable HTML-building helpers shared by the public pages:
 *   - book_url()            builds links to the booking form
 *   - max_guest_capacity()  largest room capacity (for guest drop-downs)
 *   - facility_icon()       Bootstrap icon for a facility name
 *   - render_search_form()  the check-in / check-out / guests search form
 *   - render_room_card()    one room "card" used on several pages
 * Include AFTER includes/auth.php.
 */

declare(strict_types=1);

/** URL of the booking form, optionally pre-filled with a room and stay details. */
function book_url(int $room_id = 0, string $check_in = '', string $check_out = '', int $guests = 0): string
{
    $params = [];
    if ($room_id > 0) {
        $params['room_id'] = $room_id;
    }
    if ($check_in !== '') {
        $params['check_in'] = $check_in;
    }
    if ($check_out !== '') {
        $params['check_out'] = $check_out;
    }
    if ($guests > 0) {
        $params['guests'] = $guests;
    }
    return url('book.php') . ($params ? '?' . http_build_query($params) : '');
}

/** The highest guest capacity among active rooms (used for the guests drop-down). */
function max_guest_capacity(PDO $pdo): int
{
    $max = (int) $pdo->query("SELECT COALESCE(MAX(max_guests), 2) FROM rooms WHERE room_status = 'active'")->fetchColumn();
    return max($max, 1);
}

/** Picks a Bootstrap icon that suits a facility name. */
function facility_icon(string $facility): string
{
    $f = strtolower($facility);
    return match (true) {
        str_contains($f, 'wi-fi'), str_contains($f, 'wifi') => 'bi-wifi',
        str_contains($f, 'air')                             => 'bi-snow',
        str_contains($f, 'fan')                             => 'bi-fan',
        str_contains($f, 'pool')                            => 'bi-water',
        str_contains($f, 'beach')                           => 'bi-umbrella',
        str_contains($f, 'view'), str_contains($f, 'balcony') => 'bi-eye',
        str_contains($f, 'breakfast')                       => 'bi-cup-hot',
        str_contains($f, 'hot water')                       => 'bi-droplet-half',
        str_contains($f, 'bed')                             => 'bi-moon-stars',
        default                                             => 'bi-check2-circle',
    };
}

/**
 * The availability search form (GET -> availability.php).
 * The JavaScript in main.js finds it with form[data-validate="search"]
 * and the input names check_in, check_out and guests.
 *
 * @param array{check_in?:string,check_out?:string,guests?:int} $values pre-filled values
 */
function render_search_form(array $values, int $max_guests): string
{
    $today    = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $in       = (string) ($values['check_in'] ?? '');
    $out      = (string) ($values['check_out'] ?? '');
    $guests   = max(1, (int) ($values['guests'] ?? 2));

    ob_start();
    ?>
    <form method="get" action="<?= e(url('availability.php')) ?>" id="searchForm"
          data-validate="search" class="row g-3 align-items-end" novalidate>
        <div class="col-12 col-md-4">
            <label for="check_in" class="form-label fw-semibold">Check-in</label>
            <input type="date" class="form-control" id="check_in" name="check_in"
                   min="<?= e($today) ?>" value="<?= e($in) ?>" required>
            <div class="invalid-feedback"></div>
        </div>
        <div class="col-12 col-md-4">
            <label for="check_out" class="form-label fw-semibold">Check-out</label>
            <input type="date" class="form-control" id="check_out" name="check_out"
                   min="<?= e($tomorrow) ?>" value="<?= e($out) ?>" required>
            <div class="invalid-feedback"></div>
        </div>
        <div class="col-6 col-md-2">
            <label for="guests" class="form-label fw-semibold">Guests</label>
            <select class="form-select" id="guests" name="guests" required>
                <?php for ($i = 1; $i <= $max_guests; $i++): ?>
                    <option value="<?= $i ?>"<?= $i === $guests ? ' selected' : '' ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
            <div class="invalid-feedback"></div>
        </div>
        <div class="col-6 col-md-2">
            <button type="submit" class="btn btn-warning w-100 fw-semibold">
                <i class="bi bi-search me-1"></i>Search
            </button>
        </div>
    </form>
    <?php
    return (string) ob_get_clean();
}

/**
 * One room card (wrapped in a Bootstrap column).
 * When $stay contains dates, the card also shows the nights and the total cost.
 *
 * @param array $room database row from the rooms table
 * @param array{check_in?:string,check_out?:string,guests?:int,nights?:int} $stay
 */
function render_room_card(array $room, array $stay = []): string
{
    $id        = (int) $room['room_id'];
    $check_in  = (string) ($stay['check_in'] ?? '');
    $check_out = (string) ($stay['check_out'] ?? '');
    $guests    = (int) ($stay['guests'] ?? 0);
    $nights    = (int) ($stay['nights'] ?? 0);

    $details_params = ['id' => $id];
    if ($check_in !== '' && $check_out !== '') {
        $details_params += ['check_in' => $check_in, 'check_out' => $check_out];
        if ($guests > 0) {
            $details_params['guests'] = $guests;
        }
    }
    $details_link = url('room-details.php') . '?' . http_build_query($details_params);
    $book_link    = book_url($id, $check_in, $check_out, $guests);
    $badges       = array_slice(facilities_list((string) $room['facilities']), 0, 4);
    $short_desc   = mb_strimwidth((string) $room['description'], 0, 110, '...');

    ob_start();
    ?>
    <div class="col-md-6 col-lg-4">
        <div class="card room-card h-100 shadow-sm border-0">
            <img src="<?= e(room_image_url((string) $room['image'])) ?>" class="room-img"
                 alt="<?= e($room['room_name']) ?>" loading="lazy">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <h3 class="h5 fw-bold mb-0"><?= e($room['room_name']) ?></h3>
                    <span class="badge bg-info text-dark"><?= e($room['room_type']) ?></span>
                </div>
                <p class="text-muted small mb-2">
                    <i class="bi bi-people me-1"></i>Up to <?= (int) $room['max_guests'] ?> guest(s)
                </p>
                <p class="card-text small"><?= e($short_desc) ?></p>
                <div class="mb-3">
                    <?php foreach ($badges as $facility): ?>
                        <span class="badge rounded-pill facility-badge me-1 mb-1"><?= e($facility) ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="mt-auto">
                    <div class="mb-2">
                        <span class="fs-5 fw-bold text-ocean"><?= e(format_lkr($room['price_per_night'])) ?></span>
                        <span class="text-muted small">/ night</span>
                    </div>
                    <?php if ($nights > 0): ?>
                        <div class="small mb-2">
                            <?= $nights ?> night<?= $nights > 1 ? 's' : '' ?> total:
                            <strong><?= e(format_lkr(calculate_total($room['price_per_night'], $nights))) ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2">
                        <a href="<?= e($details_link) ?>" class="btn btn-outline-secondary flex-fill">View Details</a>
                        <a href="<?= e($book_link) ?>" class="btn btn-ocean flex-fill">Book</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
