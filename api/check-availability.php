<?php
/**
 * api/check-availability.php - JSON availability check used by the booking form.
 *
 * GET parameters: room_id, check_in (Y-m-d), check_out (Y-m-d), guests (optional)
 * Response:       {"ok":true,"available":true,"nights":3,"total":33000,...}
 *                 {"ok":false,"errors":["..."]}   (HTTP 404 / 422 / 405 / 500)
 *
 * It reveals only whether the room is free: no guest details are exposed,
 * so it does not require a login. It uses the same validation and the same
 * overlap rule as the rest of the application.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_out(['ok' => false, 'errors' => ['Method not allowed.']], 405);
}

$room_id   = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT);
$check_in  = trim((string) ($_GET['check_in'] ?? ''));
$check_out = trim((string) ($_GET['check_out'] ?? ''));
$guests    = max(1, (int) ($_GET['guests'] ?? 1));

if ($room_id === false || $room_id === null || $room_id < 1) {
    json_out(['ok' => false, 'errors' => ['Please select a room.']], 422);
}

try {
    $stmt = $pdo->prepare(
        "SELECT room_id, room_name, price_per_night, max_guests
         FROM rooms WHERE room_id = ? AND room_status = 'active' LIMIT 1"
    );
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();

    if (!$room) {
        json_out(['ok' => false, 'errors' => ['That room is not available for booking.']], 404);
    }

    $errors = validate_stay($check_in, $check_out, $guests, (int) $room['max_guests']);
    if ($errors) {
        json_out(['ok' => false, 'errors' => $errors], 422);
    }

    $nights    = calculate_nights($check_in, $check_out);
    $total     = calculate_total($room['price_per_night'], $nights);
    $available = is_room_available($pdo, (int) $room['room_id'], $check_in, $check_out);

    json_out([
        'ok'              => true,
        'available'       => $available,
        'room_name'       => $room['room_name'],
        'nights'          => $nights,
        'total'           => $total,
        'total_formatted' => format_lkr($total),
        'message'         => $available
            ? "{$room['room_name']} is available for {$nights} night(s)."
            : "{$room['room_name']} is already booked for some of those dates.",
    ]);
} catch (PDOException $ex) {
    error_log('Availability API error: ' . $ex->getMessage());
    json_out(['ok' => false, 'errors' => ['A system error occurred.']], 500);
}
