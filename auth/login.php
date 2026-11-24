<?php
/**
 * auth/login.php - guest and administrator login
 * Uses password_verify(), regenerates the session id and applies a simple
 * lock-out after repeated failed attempts.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_SECONDS    = 300;
// Valid bcrypt hash used to keep response time similar when the email does not exist
const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

if (is_logged_in()) {
    redirect(is_admin() ? url('admin/index.php') : url('index.php'));
}

$errors   = [];
$email    = '';
$redirect = (string) ($_GET['redirect'] ?? $_POST['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    $locked_until = (int) ($_SESSION['login_locked_until'] ?? 0);

    if ($locked_until > time()) {
        $minutes = (int) ceil(($locked_until - time()) / 60);
        $errors['general'] = "Too many failed attempts. Please try again in {$minutes} minute(s).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif ($password === '') {
        $errors['password'] = 'Please enter your password.';
    } else {
        $stmt = $pdo->prepare('SELECT user_id, name, email, password, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $hash     = $user['password'] ?? DUMMY_HASH;
        $verified = password_verify($password, $hash) && $user !== false;

        if ($verified) {
            // Upgrade the stored hash if PHP's default algorithm/cost has changed
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $upd = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
                $upd->execute([password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);
            }

            login_user($user);   // clears old session data, including attempt counters
            flash_set('success', 'Welcome back, ' . $user['name'] . '!');

            $default = $user['role'] === 'admin' ? url('admin/index.php') : url('index.php');
            redirect(safe_redirect_target($redirect, $default));
        }

        // Failed attempt: count it and lock out after too many
        $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $_SESSION['login_locked_until'] = time() + LOCKOUT_SECONDS;
            $_SESSION['login_attempts']     = 0;
        }
        $errors['general'] = 'Incorrect email or password.';
    }
}

$page_title = 'Login';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 fw-bold mb-1">Welcome back</h1>
                    <p class="text-muted mb-4">Log in to book rooms and manage your reservations.</p>

                    <?php if (isset($errors['general'])): ?>
                        <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                    <?php endif; ?>

                    <form method="post" action="" id="loginForm" data-validate="login" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                   id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required>
                            <div class="invalid-feedback"><?= e($errors['email'] ?? '') ?></div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                   id="password" name="password" autocomplete="current-password" required>
                            <div class="invalid-feedback"><?= e($errors['password'] ?? '') ?></div>
                        </div>

                        <button type="submit" class="btn btn-ocean w-100 btn-lg">Log in</button>
                    </form>

                    <p class="text-center mt-4 mb-0">
                        New guest? <a href="<?= e(url('auth/register.php')) ?>">Create an account</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
