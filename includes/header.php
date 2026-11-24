<?php
/**
 * includes/header.php
 * Call after including auth.php. Optional: set $page_title before including.
 */
$page_title = $page_title ?? 'Welcome';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(VILLA_NAME) ?> - a beachfront villa in Unawatuna, Sri Lanka. Check availability and book your stay online.">
    <title><?= e($page_title) ?> | <?= e(VILLA_NAME) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-ocean sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('index.php')) ?>">
            <i class="bi bi-water me-2"></i><?= e(VILLA_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('index.php') ?>" href="<?= e(url('index.php')) ?>">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('rooms.php') ?>" href="<?= e(url('rooms.php')) ?>">Rooms</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('availability.php') ?>" href="<?= e(url('availability.php')) ?>">Check Availability</a>
                </li>
                <?php if (is_logged_in() && !is_admin()): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= nav_active('my-bookings.php') ?>" href="<?= e(url('my-bookings.php')) ?>">My Bookings</a>
                    </li>
                <?php endif; ?>
                <?php if (is_admin()): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= nav_active('admin/index.php') ?>" href="<?= e(url('admin/index.php')) ?>">Admin Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= nav_active('admin/rooms.php') ?>" href="<?= e(url('admin/rooms.php')) ?>">Manage Rooms</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= nav_active('admin/bookings.php') ?>" href="<?= e(url('admin/bookings.php')) ?>">Manage Bookings</a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-lg-center flex-column flex-lg-row gap-2">
                <?php if (!is_admin()): ?>
                    <a class="btn btn-warning fw-semibold" href="<?= e(url('book.php')) ?>">
                        <i class="bi bi-calendar-check me-1"></i>Book Now
                    </a>
                <?php endif; ?>

                <?php if (is_logged_in()): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i><?= e(current_user_name()) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (is_admin()): ?>
                                <li><a class="dropdown-item" href="<?= e(url('admin/index.php')) ?>">Admin Dashboard</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="<?= e(url('my-bookings.php')) ?>">My Bookings</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="<?= e(url('auth/logout.php')) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a class="btn btn-outline-light" href="<?= e(url('auth/login.php')) ?>">Login</a>
                    <a class="btn btn-light" href="<?= e(url('auth/register.php')) ?>">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<?php $flashes = flash_get_all(); ?>
<?php if ($flashes): ?>
    <div class="container mt-3" id="flashArea">
        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main class="flex-grow-1">