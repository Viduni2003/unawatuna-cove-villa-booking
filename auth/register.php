<?php
/**
 * auth/register.php - guest registration
 * Validates on the server, hashes the password and always creates role = 'guest'.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect(url('index.php'));
}

$errors = [];
$old    = ['name' => '', 'email' => '', 'contact_number' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $name     = trim((string) ($_POST['name'] ?? ''));
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $contact  = trim((string) ($_POST['contact_number'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    $contact_clean = preg_replace('/[\s-]/', '', $contact);
    $old = ['name' => $name, 'email' => $email, 'contact_number' => $contact];

    // ---- Server-side validation ----
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['name'] = 'Name must be between 2 and 100 characters.';
    } elseif (!preg_match("/^[\p{L}\p{M} .'-]+$/u", $name)) {
        $errors['name'] = 'Name can only contain letters, spaces, dots, apostrophes and hyphens.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (!preg_match('/^\+?[0-9]{9,15}$/', (string) $contact_clean)) {
        $errors['contact_number'] = 'Enter a valid contact number (9-15 digits, optional leading +).';
    }

    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must be at least 8 characters and include a letter and a number.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Password must not exceed 72 characters.';
    }

    if ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // ---- Check the email is not already registered ----
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email address is already registered. Please log in instead.';
        }
    }

    // ---- Insert the new guest ----
    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, contact_number, password, role)
                 VALUES (?, ?, ?, ?, 'guest')"
            );
            $stmt->execute([$name, $email, $contact_clean, password_hash($password, PASSWORD_DEFAULT)]);

            flash_set('success', 'Registration successful! You can now log in.');
            redirect(url('auth/login.php'));
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {            // duplicate email (race condition)
                $errors['email'] = 'This email address is already registered.';
            } else {
                error_log('Registration error: ' . $ex->getMessage());
                $errors['general'] = 'Something went wrong while creating your account. Please try again.';
            }
        }
    }
}

$page_title = 'Register';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 fw-bold mb-1">Create your guest account</h1>
                    <p class="text-muted mb-4">Register to make and manage your bookings.</p>

                    <?php if (isset($errors['general'])): ?>
                        <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                    <?php endif; ?>

                    <form method="post" action="" id="registerForm" data-validate="register" novalidate>
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="name" class="form-label">Full name</label>
                            <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                   id="name" name="name" value="<?= e($old['name']) ?>" maxlength="100" required>
                            <div class="invalid-feedback"><?= e($errors['name'] ?? '') ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                   id="email" name="email" value="<?= e($old['email']) ?>" maxlength="150" required>
                            <div class="invalid-feedback"><?= e($errors['email'] ?? '') ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="contact_number" class="form-label">Contact number</label>
                            <input type="tel" class="form-control <?= isset($errors['contact_number']) ? 'is-invalid' : '' ?>"
                                   id="contact_number" name="contact_number" value="<?= e($old['contact_number']) ?>"
                                   placeholder="e.g. 0771234567 or +94771234567" maxlength="20" required>
                            <div class="invalid-feedback"><?= e($errors['contact_number'] ?? '') ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                   id="password" name="password" maxlength="72" autocomplete="new-password" required>
                            <div class="form-text">At least 8 characters, including a letter and a number.</div>
                            <div class="invalid-feedback"><?= e($errors['password'] ?? '') ?></div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm password</label>
                            <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                   id="confirm_password" name="confirm_password" maxlength="72" autocomplete="new-password" required>
                            <div class="invalid-feedback"><?= e($errors['confirm_password'] ?? '') ?></div>
                        </div>

                        <button type="submit" class="btn btn-ocean w-100 btn-lg">Register</button>
                    </form>

                    <p class="text-center mt-4 mb-0">
                        Already have an account? <a href="<?= e(url('auth/login.php')) ?>">Log in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
