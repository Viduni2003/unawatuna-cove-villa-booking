<?php
/**
 * includes/auth.php
 * Include this at the top of EVERY page. It:
 *  - loads the database connection and helper functions
 *  - starts a hardened session
 *  - provides login/role checks and CSRF protection
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

const SESSION_TIMEOUT = 1800;   // 30 minutes of inactivity

if (session_status() === PHP_SESSION_NONE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,      // JavaScript cannot read the session cookie
        'samesite' => 'Lax',     // basic protection against cross-site requests
    ]);
    session_start();
}

/* Automatic logout after inactivity */
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        logout_user();
        flash_set('warning', 'Your session expired due to inactivity. Please log in again.');
    } else {
        $_SESSION['last_activity'] = time();
    }
}

/* ---------------------------------------------------------
 * Session / role helpers
 * ------------------------------------------------------- */

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['user_role'] ?? '') === 'admin';
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_user_name(): string
{
    return (string) ($_SESSION['user_name'] ?? '');
}

/** Store the user in the session after a successful login. */
function login_user(array $user): void
{
    session_regenerate_id(true);          // prevents session fixation
    $_SESSION = [];                       // start clean (also resets CSRF token)
    $_SESSION['user_id']       = (int) $user['user_id'];
    $_SESSION['user_name']     = $user['name'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['last_activity'] = time();
}

/** Clear the session data and issue a fresh session id. */
function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Only logged-in users may continue; others are sent to the login page. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash_set('warning', 'Please log in to continue.');
        redirect(url('auth/login.php') . '?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
}

/** Only administrators may continue. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash_set('danger', 'You do not have permission to access that page.');
        redirect(url('index.php'));
    }
}

/* ---------------------------------------------------------
 * CSRF protection for all POST forms
 * ------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/** Stop the request if the CSRF token is missing or wrong. */
function require_post_csrf(): void
{
    if (!verify_csrf()) {
        http_response_code(403);
        exit('Invalid or expired form token. Please go back, refresh the page and try again.');
    }
}
