<?php

/**
 * Shared page footer.
 * Closes the correct wrapper depending on whether the user is logged in.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
?>

<?php if (isLoggedIn()): ?>

        </main><!-- /.main-content -->

    </div><!-- /.main-wrapper -->

<?php endif; ?>

<!-- Bootstrap JS bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- App JavaScript -->
<script src="<?= BASE_URL ?>/public/js/main.js?v=<?= filemtime(__DIR__ . '/../public/js/main.js') ?>"></script>

</body>
</html>
