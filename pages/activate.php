<?php

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$token   = trim($_GET['token'] ?? '');
$success = false;
$error   = '';

if (empty($token)) {
    $error = 'Invalid activation link — no token provided.';
} else {
    $success = (new User())->activateByToken($token);
    if (!$success) {
        $error = 'This activation link is invalid or has already been used.';
    }
}

$pageTitle = 'Account Activation';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-sm-10 col-md-6 text-center">
        <div class="card shadow-sm p-5">
            <?php if ($success): ?>
                <div class="mb-4 display-1">&#10003;</div>
                <h3 class="text-success">Account Activated!</h3>
                <p class="text-muted">Your account is now active. You can log in and start using the app.</p>
                <a href="<?= BASE_URL ?>/pages/login.php" class="btn btn-success mt-2">Go to Login</a>
            <?php else: ?>
                <div class="mb-4 display-1 text-danger">&#10007;</div>
                <h3 class="text-danger">Activation Failed</h3>
                <p class="text-muted"><?= htmlspecialchars($error) ?></p>
                <a href="<?= BASE_URL ?>/pages/register.php" class="btn btn-outline-secondary mt-2">
                    Back to Register
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
