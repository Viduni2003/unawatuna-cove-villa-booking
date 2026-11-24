<?php
/**
 * admin/booking-action.php - POST handler: confirm or cancel a booking.
 *
 * PREVENTING OVERLAPPING CONFIRMED BOOKINGS
 * Confirming runs inside a database transaction:
 *   1. lock the booking row            (SELECT ... FOR UPDATE)
 *   2. lock the ROOM row               -> two admins confirming bookings for the
 *                                         same room are forced to run one after the other
 *   3. check for a confirmed booking that overlaps these dates (also FOR UPDATE,
 *      so it always reads the latest committed data)
 *   4. only if there is none: set status = 'Confirmed', then COMMIT
 * Two stays overlap when: existing.check_in < new.check_out AND existing.check_out > new.check_in
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin();

/**
 * @return array{0:string,1:string} [flash type, message]
 */
function confirm_booking(PDO $pdo, int $booking_id): array
{
    $ref = booking_ref($booking_id);
    $pdo->beginTransaction();

    try {
        // 1) Lock and read the booking
        $stmt = $pdo->prepare(
            'SELECT booking_id, user_id, room_id, check_in, check_out, booking_status
             FROM bookings WHERE booking_id = ? FOR UPDATE'
        );
        $stmt->execute([$booking_id]);
        $b = $stmt->fetch();

        if (!$b) {
            $pdo->rollBack();
            return ['danger', 'Booking not found.'];
        }
        if ($b['booking_status'] !== 'Pending') {
            $pdo->rollBack();
            return ['warning', "{$ref} is already " . strtolower((string) $b['booking_status']) . '; only pending bookings can be confirmed.'];
        }

        // 2) Lock the room row so confirmations for the same room are serialised
        $stmt = $pdo->prepare('SELECT room_name, room_status FROM rooms WHERE room_id = ? FOR UPDATE');
        $stmt->execute([$b['room_id']]);
        $room = $stmt->fetch();

        if (!$room) {
            $pdo->rollBack();
            return ['danger', 'The room for this booking no longer exists.'];
        }
        if ($room['room_status'] !== 'active') {
            $pdo->rollBack();
            return ['warning', "Cannot confirm {$ref}: {$room['room_name']} is inactive. Re-activate the room first."];
        }
        if ($b['check_in'] < date('Y-m-d')) {
            $pdo->rollBack();
            return ['warning', "Cannot confirm {$ref}: the arrival date has already passed. Please cancel it."];
        }

        // 3) Any confirmed booking that overlaps?
        $stmt = $pdo->prepare(
            "SELECT booking_id, check_in, check_out FROM bookings
             WHERE room_id = ? AND booking_status = 'Confirmed'
               AND booking_id <> ? AND check_in < ? AND check_out > ?
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$b['room_id'], $booking_id, $b['check_out'], $b['check_in']]);
        $clash = $stmt->fetch();

        if ($clash) {
            $pdo->rollBack();
            return ['danger', "Cannot confirm {$ref}: {$room['room_name']} is already booked by confirmed booking "
                . booking_ref((int) $clash['booking_id']) . ' (' . format_date($clash['check_in']) . ' to '
                . format_date($clash['check_out']) . ').'];
        }

        // 4) Safe to confirm
        $stmt = $pdo->prepare("UPDATE bookings SET booking_status = 'Confirmed' WHERE booking_id = ? AND booking_status = 'Pending'");
        $stmt->execute([$booking_id]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }

    // Build the success message (outside the transaction)
    $stmt = $pdo->prepare('SELECT name FROM users WHERE user_id = ?');
    $stmt->execute([$b['user_id']]);
    $guest = (string) ($stmt->fetchColumn() ?: 'the guest');

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM bookings
         WHERE room_id = ? AND booking_status = 'Pending' AND booking_id <> ?
           AND check_in < ? AND check_out > ?"
    );
    $stmt->execute([$b['room_id'], $booking_id, $b['check_out'], $b['check_in']]);
    $competing = (int) $stmt->fetchColumn();

    $msg = "{$ref} confirmed for {$guest}. {$room['room_name']} is now blocked from "
         . format_date($b['check_in']) . ' to ' . format_date($b['check_out']) . '.';
    if ($competing > 0) {
        $msg .= " {$competing} other pending request(s) for overlapping dates can no longer be confirmed; please cancel them.";
    }
    return ['success', $msg];
}

/**
 * @return array{0:string,1:string}
 */
function cancel_booking(PDO $pdo, int $booking_id): array
{
    $ref  = booking_ref($booking_id);
    $stmt = $pdo->prepare(
        "UPDATE bookings SET booking_status = 'Cancelled'
         WHERE booking_id = ? AND booking_status IN ('Pending','Confirmed')"
    );
    $stmt->execute([$booking_id]);

    if ($stmt->rowCount() === 1) {
        return ['success', "{$ref} has been cancelled."];
    }
    return ['warning', "{$ref} could not be cancelled. It may not exist or is already cancelled."];
}

/* ---- Request handling ---- */
$default_return = url('admin/bookings.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($default_return);
}
require_post_csrf();

$return     = safe_redirect_target((string) ($_POST['return'] ?? ''), $default_return);
$booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$action     = (string) ($_POST['action'] ?? '');

if ($booking_id === false || $booking_id === null || $booking_id < 1 || !in_array($action, ['confirm', 'cancel'], true)) {
    flash_set('danger', 'Invalid request.');
    redirect($return);
}

try {
    [$type, $message] = $action === 'confirm'
        ? confirm_booking($pdo, $booking_id)
        : cancel_booking($pdo, $booking_id);
    flash_set($type, $message);
} catch (Throwable $ex) {
    error_log('Booking action failed: ' . $ex->getMessage());
    flash_set('danger', 'A system error occurred while updating the booking. Please try again.');
}

redirect($return);
