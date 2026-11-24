<?php
/**
 * includes/functions.php
 * General helper functions used across the application.
 * Requires PHP 8.0+ (uses str_starts_with / str_contains).
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Colombo');

const VILLA_NAME = 'Unawatuna Cove Villa';
const MAX_NIGHTS = 30;               // longest stay allowed in one booking
const ROOM_IMAGE_DIR = __DIR__ . '/../assets/images/rooms';

/**
 * Work out the web path of the project folder (e.g. "/villa-booking")
 * so links work whether the project sits in htdocs/villa-booking or elsewhere.
 */
function detect_base_url(): string
{
    $doc  = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $root = realpath(__DIR__ . '/..');

    if ($doc === false || $root === false) {
        return '/villa-booking';
    }
    $doc  = str_replace('\\', '/', $doc);
    $root = str_replace('\\', '/', $root);

    if (stripos($root, $doc) !== 0) {
        return '/villa-booking';
    }
    return rtrim(substr($root, strlen($doc)), '/');
}

define('BASE_URL', detect_base_url());

/* ---------------------------------------------------------
 * Output / navigation helpers
 * ------------------------------------------------------- */

/** Escape output to prevent XSS. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build a URL relative to the project root. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Redirect and stop the script. */
function redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

/**
 * Only allow redirects back to pages inside this project (prevents open redirects).
 */
function safe_redirect_target(?string $target, string $default): string
{
    if ($target === null || $target === '') {
        return $default;
    }
    if (!str_starts_with($target, BASE_URL . '/')) {
        return $default;
    }
    if (preg_match('/[\r\n\\\\]/', $target) || str_contains($target, '//')) {
        return $default;
    }
    return $target;
}

/** Returns " active" when the current script matches the given path. */
function nav_active(string $path): string
{
    return ($_SERVER['SCRIPT_NAME'] ?? '') === BASE_URL . '/' . ltrim($path, '/') ? ' active' : '';
}

/* ---------------------------------------------------------
 * Flash messages (one-time messages stored in the session)
 * ------------------------------------------------------- */

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get_all(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ---------------------------------------------------------
 * Formatting helpers
 * ------------------------------------------------------- */

function format_lkr(float|int|string $amount): string
{
    return 'LKR ' . number_format((float) $amount, 2);
}

function format_date(string $date): string
{
    $d = parse_date($date);
    return $d ? $d->format('d M Y') : '';
}

/** Human-friendly booking reference, e.g. booking #12 becomes "VB-00012". */
function booking_ref(int|string $booking_id): string
{
    return 'VB-' . str_pad((string) $booking_id, 5, '0', STR_PAD_LEFT);
}

/** Splits the comma-separated facilities column into a clean array. */
function facilities_list(string $csv): array
{
    return array_values(array_filter(array_map('trim', explode(',', $csv)), 'strlen'));
}

/** Bootstrap badge HTML for a booking/room status. */
function status_badge(string $status): string
{
    $class = match ($status) {
        'Pending'   => 'bg-warning text-dark',
        'Confirmed' => 'bg-success',
        'Cancelled' => 'bg-secondary',
        'active'    => 'bg-success',
        'inactive'  => 'bg-secondary',
        default     => 'bg-light text-dark',
    };
    return '<span class="badge ' . $class . '">' . e(ucfirst($status)) . '</span>';
}

/**
 * URL of a room image. Falls back to an inline placeholder if the file
 * has not been added yet, so the site still looks fine while testing.
 */
function room_image_url(string $file): string
{
    $file = basename($file);
    if ($file !== '' && is_file(__DIR__ . '/../assets/images/rooms/' . $file)) {
        return url('assets/images/rooms/' . $file);
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500">'
         . '<rect width="100%" height="100%" fill="#cfe8ee"/>'
         . '<text x="50%" y="50%" fill="#2b6f85" font-size="34" text-anchor="middle" '
         . 'font-family="sans-serif">Room image</text></svg>';
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

/**
 * Deletes a room image from disk, but ONLY if it was created by the upload
 * form (room_<16 hex chars>.jpg|png|webp). Your own sample images such as
 * room1.jpg are never touched.
 */
function delete_uploaded_room_image(string $file): void
{
    if (preg_match('/^room_[a-f0-9]{16}\.(jpg|png|webp)$/', $file) === 1) {
        $path = ROOM_IMAGE_DIR . '/' . $file;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/* ---------------------------------------------------------
 * Date, price and validation helpers
 * ------------------------------------------------------- */

/** Strictly parses a Y-m-d string. Returns null if invalid (e.g. 2026-02-31). */
function parse_date(string $value): ?DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if ($d !== false && $d->format('Y-m-d') === $value) {
        return $d;
    }
    return null;
}

/** Number of nights between two dates (0 if the range is invalid). */
function calculate_nights(string $check_in, string $check_out): int
{
    $in  = parse_date($check_in);
    $out = parse_date($check_out);
    if (!$in || !$out || $out <= $in) {
        return 0;
    }
    return (int) $in->diff($out)->days;
}

/** Total cost, always calculated on the server. */
function calculate_total(float|string $price_per_night, int $nights): float
{
    return round((float) $price_per_night * $nights, 2);
}

/**
 * Validates a stay request. Returns a list of error messages (empty = valid).
 */
function validate_stay(string $check_in, string $check_out, int $guests, ?int $max_guests = null): array
{
    $errors = [];
    $in     = parse_date($check_in);
    $out    = parse_date($check_out);
    $today  = new DateTimeImmutable('today');

    if (!$in) {
        $errors[] = 'Please select a valid check-in date.';
    }
    if (!$out) {
        $errors[] = 'Please select a valid check-out date.';
    }
    if ($in && $in < $today) {
        $errors[] = 'Check-in date cannot be in the past.';
    }
    if ($in && $out) {
        if ($out <= $in) {
            $errors[] = 'Check-out date must be after the check-in date.';
        } elseif ((int) $in->diff($out)->days > MAX_NIGHTS) {
            $errors[] = 'A single booking cannot exceed ' . MAX_NIGHTS . ' nights.';
        }
    }
    if ($guests < 1) {
        $errors[] = 'Please select at least one guest.';
    }
    if ($max_guests !== null && $guests > $max_guests) {
        $errors[] = 'This room accommodates a maximum of ' . $max_guests . ' guest(s).';
    }
    return $errors;
}

/* ---------------------------------------------------------
 * Availability helpers (checked against CONFIRMED bookings only)
 * ------------------------------------------------------- */

/**
 * True if no confirmed booking overlaps the requested dates.
 * Two stays overlap when: existing.check_in < new.check_out AND existing.check_out > new.check_in
 * (a guest checking out on the day another checks in is NOT an overlap).
 *
 * @param int|null $exclude_booking_id ignore this booking (used when confirming it)
 */
function is_room_available(PDO $pdo, int $room_id, string $check_in, string $check_out, ?int $exclude_booking_id = null): bool
{
    $sql = "SELECT COUNT(*) FROM bookings
            WHERE room_id = ?
              AND booking_status = 'Confirmed'
              AND check_in < ?
              AND check_out > ?";
    $params = [$room_id, $check_out, $check_in];

    if ($exclude_booking_id !== null) {
        $sql .= ' AND booking_id <> ?';
        $params[] = $exclude_booking_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() === 0;
}

/** Active rooms that fit the party size and are free for the dates. */
function get_available_rooms(PDO $pdo, string $check_in, string $check_out, int $guests): array
{
    $sql = "SELECT r.*
            FROM rooms r
            WHERE r.room_status = 'active'
              AND r.max_guests >= ?
              AND NOT EXISTS (
                    SELECT 1 FROM bookings b
                    WHERE b.room_id = r.room_id
                      AND b.booking_status = 'Confirmed'
                      AND b.check_in < ?
                      AND b.check_out > ?
              )
            ORDER BY r.price_per_night ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$guests, $check_out, $check_in]);
    return $stmt->fetchAll();
}
