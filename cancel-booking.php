<?php
/**
 * cancel-booking.php - lets a guest cancel THEIR OWN pending booking.
 * POST only, CSRF protected. The UPDATE itself contains both user_id and
 * booking_status = 'Pending', so it is impossible to cancel somebody else's
 * booking or one that has already been confirmed/cancelled.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('my-bookings.php'));
}

require_post_csrf();

if (is_admin()) {
    flash_set('danger', 'Administrators manage bookings from the admin area.');
    redirect(url('admin/bookings.php'));
}

$booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

if ($booking_id === false || $booking_id === null || $booking_id < 1) {
    flash_set('danger', 'Invalid booking selected.');
    redirect(url('my-bookings.php'));
}

try {
    $stmt = $pdo->prepare(
        "UPDATE bookings
         SET booking_status = 'Cancelled'
         WHERE booking_id = ? AND user_id = ? AND booking_status = 'Pending'"
    );
    $stmt->execute([$booking_id, current_user_id()]);

    if ($stmt->rowCount() === 1) {
        flash_set('success', 'Booking ' . booking_ref($booking_id) . ' has been cancelled.');
    } else {
        // Not found, not yours, or no longer pending: the same message for all three,
        // so the page does not reveal whether other guests' booking IDs exist.
        flash_set('danger', 'That booking could not be cancelled. Only your own pending bookings can be cancelled.');
    }
} catch (PDOException $ex) {
    error_log('Cancel booking failed: ' . $ex->getMessage());
    flash_set('danger', 'A system error occurred. Please try again.');
}

redirect(url('my-bookings.php'));
