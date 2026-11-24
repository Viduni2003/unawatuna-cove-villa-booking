<?php
/**
 * index.php - Home page
 * Villa introduction, quick availability search, highlights,
 * featured rooms, location and a clear "Book Now" call to action.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/components.php';

$featured = $pdo->query(
    "SELECT * FROM rooms WHERE room_status = 'active' ORDER BY room_id ASC LIMIT 3"
)->fetchAll();

$max_guests = max_guest_capacity($pdo);
$hero_file  = __DIR__ . '/assets/images/hero.jpg';
$hero_url   = is_file($hero_file) ? url('assets/images/hero.jpg') : '';

$page_title = 'Home';
require __DIR__ . '/includes/header.php';
?>

<!-- ===== Hero ===== -->
<section class="hero"<?php if ($hero_url): ?> style="--hero-image: url('<?= e($hero_url) ?>');"<?php endif; ?>>
    <div class="container hero-content text-white">
        <p class="text-uppercase fw-semibold mb-2 letter-wide">Unawatuna &middot; Southern Coast &middot; Sri Lanka</p>
        <h1 class="display-4 fw-bold mb-3">Wake up to the sound of the Indian Ocean</h1>
        <p class="lead mb-4">
            <?= e(VILLA_NAME) ?> is a relaxed coastal villa in Unawatuna where golden sand,
            calm turquoise waters and warm Sri Lankan hospitality welcome you every day.
        </p>
        <a href="<?= e(book_url()) ?>" class="btn btn-warning btn-lg fw-semibold me-2 mb-2">
            <i class="bi bi-calendar-check me-1"></i>Book Now
        </a>
        <a href="<?= e(url('rooms.php')) ?>" class="btn btn-outline-light btn-lg mb-2">Explore Rooms</a>
    </div>
</section>

<!-- ===== Quick availability search ===== -->
<section class="container search-wrap">
    <div class="search-card shadow-lg">
        <h2 class="h5 fw-bold mb-3"><i class="bi bi-search me-2 text-ocean"></i>Check room availability</h2>
        <?= render_search_form(['guests' => 2], $max_guests) ?>
    </div>
</section>

<!-- ===== Introduction ===== -->
<section class="container py-5">
    <div class="row align-items-center g-4">
        <div class="col-lg-6">
            <h2 class="section-title">Your coastal retreat in Unawatuna</h2>
            <p>
                Tucked near Unawatuna Beach, <?= e(VILLA_NAME) ?> offers six individually styled rooms
                surrounded by tropical greenery. Start your mornings with sea breezes, spend sunny
                afternoons swimming or snorkeling in the sheltered bay, and unwind in the evening
                with fresh coastal dining.
            </p>
            <p class="mb-0">
                Whether you are visiting as a couple, a family or a group of friends, we can help you
                plan visits to Jungle Beach, historic Galle Fort, turtle-watching spots and scenic
                day trips along the southern coast.
            </p>
        </div>
        <div class="col-lg-6">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3 col-lg-6">
                    <div class="feature-box h-100">
                        <i class="bi bi-umbrella feature-icon"></i>
                        <h3 class="h6 fw-bold mt-2">Near the Bay</h3>
                        <p class="small mb-0">Short walk to Unawatuna Beach</p>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-6">
                    <div class="feature-box h-100">
                        <i class="bi bi-wifi feature-icon"></i>
                        <h3 class="h6 fw-bold mt-2">Free Wi-Fi</h3>
                        <p class="small mb-0">Reliable internet across the villa</p>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-6">
                    <div class="feature-box h-100">
                        <i class="bi bi-cup-hot feature-icon"></i>
                        <h3 class="h6 fw-bold mt-2">Local Breakfast</h3>
                        <p class="small mb-0">Hoppers, curries and tropical fruit</p>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-6">
                    <div class="feature-box h-100">
                        <i class="bi bi-water feature-icon"></i>
                        <h3 class="h6 fw-bold mt-2">Pool &amp; Courtyard</h3>
                        <p class="small mb-0">Unwind in a lush garden setting</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== Featured rooms ===== -->
<section class="bg-light py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
            <div>
                <h2 class="section-title mb-1">Featured rooms</h2>
                <p class="text-muted mb-0">Comfortable stays for every kind of traveller.</p>
            </div>
            <a href="<?= e(url('rooms.php')) ?>" class="btn btn-outline-secondary">View all rooms</a>
        </div>

        <?php if ($featured): ?>
            <div class="row g-4">
                <?php foreach ($featured as $room): ?>
                    <?= render_room_card($room) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-0">Rooms will be listed here soon. Please check back later.</div>
        <?php endif; ?>
    </div>
</section>

<!-- ===== Location ===== -->
<section class="container py-5">
    <h2 class="section-title">Find us in Unawatuna</h2>
    <div class="row g-4">
        <div class="col-lg-5">
            <p>
                Unawatuna is one of Sri Lanka's best-known coastal destinations, famous for its
                crescent bay, coral reef snorkeling, laid-back beach cafes and easy access to Galle Fort.
            </p>
            <ul class="list-unstyled mb-0">
                <li class="mb-2"><i class="bi bi-geo-alt-fill text-ocean me-2"></i>Unawatuna Beach: 2 minutes on foot</li>
                <li class="mb-2"><i class="bi bi-geo-alt-fill text-ocean me-2"></i>Jungle Beach: approx. 12 minutes by tuk-tuk / coastal walk</li>
                <li class="mb-2"><i class="bi bi-geo-alt-fill text-ocean me-2"></i>Dalawella Beach (sea turtles): approx. 10 minutes by car</li>
                <li class="mb-2"><i class="bi bi-geo-alt-fill text-ocean me-2"></i>Galle Fort: approx. 15–20 minutes by car</li>
                <li class="mb-2"><i class="bi bi-geo-alt-fill text-ocean me-2"></i>Mirissa Beach: approx. 40 minutes by car</li>
            </ul>
        </div>
        <div class="col-lg-7">
            <div class="ratio ratio-16x9 rounded overflow-hidden shadow-sm">
                <iframe
                    title="Map showing Unawatuna, Sri Lanka"
                    src="https://www.openstreetmap.org/export/embed.html?bbox=80.2280%2C5.9960%2C80.2680%2C6.0230&amp;layer=mapnik&amp;marker=6.0097%2C80.2489"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </div>
</section>

<!-- ===== Call to action ===== -->
<section class="bg-ocean text-white py-5 text-center">
    <div class="container">
        <h2 class="fw-bold mb-3">Ready for your Unawatuna coastal escape?</h2>
        <p class="mb-4">Search available dates and send your booking request in minutes.</p>
        <a href="<?= e(book_url()) ?>" class="btn btn-warning btn-lg fw-semibold">
            <i class="bi bi-calendar-check me-1"></i>Book Now
        </a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>