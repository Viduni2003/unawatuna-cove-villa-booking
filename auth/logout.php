<?php
/**
 * auth/logout.php - logs the user out.
 * Accepts POST only (the Logout button in the navbar is a small form with a CSRF token),
 * so another site cannot log a user out by embedding a link or image.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('index.php'));
}

require_post_csrf();

logout_user();
flash_set('success', 'You have been logged out successfully.');
redirect(url('auth/login.php'));
