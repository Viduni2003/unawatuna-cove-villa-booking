<?php
/**
 * admin/room-delete.php - POST handler for: delete, deactivate, activate.
 * A room with ANY booking history is never hard-deleted (it would destroy
 * booking records); it can only be deactivated.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin();

$back = url('admin/rooms.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($back);
}
require_post_csrf();

$room_id = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
$action  = (string) ($_POST['action'] ?? '');

if ($room_id === false || $room_id === null || $room_id < 1 || !in_array($action, ['delete', 'deactivate', 'activate'], true)) {
    flash_set('danger', 'Invalid request.');
    redirect($back);
}

$stmt = $pdo->prepare('SELECT room_id, room_name, image FROM rooms WHERE room_id = ? LIMIT 1');
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if (!$room) {
    flash_set('danger', 'That room no longer exists.');
    redirect($back);
}

try {
    if ($action === 'delete') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE room_id = ?');
        $stmt->execute([$room_id]);

        if ((int) $stmt->fetchColumn() > 0) {
            flash_set('warning', '"' . $room['room_name'] . '" has booking history and cannot be deleted. Deactivate it instead.');
        } else {
            $pdo->prepare('DELETE FROM rooms WHERE room_id = ?')->execute([$room_id]);
            delete_uploaded_room_image((string) $room['image']);
            flash_set('success', 'Room "' . $room['room_name'] . '" was deleted.');
        }
    } elseif ($action === 'deactivate') {
        $pdo->prepare("UPDATE rooms SET room_status = 'inactive' WHERE room_id = ?")->execute([$room_id]);

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE room_id = ? AND booking_status IN ('Pending','Confirmed') AND check_out >= ?"
        );
        $stmt->execute([$room_id, date('Y-m-d')]);
        $upcoming = (int) $stmt->fetchColumn();

        $msg = 'Room "' . $room['room_name'] . '" was deactivated and is hidden from guests.';
        if ($upcoming > 0) {
            $msg .= " It still has {$upcoming} pending/confirmed booking(s); review them under Manage Bookings.";
        }
        flash_set('success', $msg);
    } else {
        $pdo->prepare("UPDATE rooms SET room_status = 'active' WHERE room_id = ?")->execute([$room_id]);
        flash_set('success', 'Room "' . $room['room_name'] . '" is active again.');
    }
} catch (PDOException $ex) {
    // 23000 = integrity constraint (a booking was added between the check and the delete)
    if ($ex->getCode() === '23000') {
        flash_set('warning', 'That room is linked to bookings and cannot be deleted. Deactivate it instead.');
    } else {
        error_log('Room action failed: ' . $ex->getMessage());
        flash_set('danger', 'A system error occurred. Please try again.');
    }
}

redirect($back);
