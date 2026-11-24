<?php
/**
 * includes/footer.php
 * Closes <main> opened in header.php, then outputs the footer and scripts.
 */
?>
</main>

<footer class="bg-ocean-dark text-light pt-4 pb-3 mt-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-5">
                <h5 class="fw-bold"><i class="bi bi-water me-2"></i><?= e(VILLA_NAME) ?></h5>
                <p class="small mb-0">A serene coastal villa on the southern coast of Sri Lanka, just steps from Unawatuna Beach and the coral bay.</p>
            </div>
            <div class="col-md-3">
                <h6 class="fw-semibold">Quick Links</h6>
                <ul class="list-unstyled small mb-0">
                    <li><a class="footer-link" href="<?= e(url('rooms.php')) ?>">Rooms</a></li>
                    <li><a class="footer-link" href="<?= e(url('availability.php')) ?>">Check Availability</a></li>
                    <li><a class="footer-link" href="<?= e(url('book.php')) ?>">Book Now</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="fw-semibold">Contact</h6>
                <ul class="list-unstyled small mb-0">
                    <li><i class="bi bi-geo-alt me-2"></i>Beach Road, Unawatuna, Sri Lanka</li>
                    <li><i class="bi bi-telephone me-2"></i>+94 91 225 4890</li>
                    <li><i class="bi bi-envelope me-2"></i>info@unawatunacovevilla.lk</li>
                </ul>
            </div>
        </div>
        <hr class="border-light opacity-25 mt-4">
        <p class="text-center small mb-0">&copy; <?= date('Y') ?> <?= e(VILLA_NAME) ?>. Coastal stays in Unawatuna, Sri Lanka.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
</body>
</html>