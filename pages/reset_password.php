<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$userObj  = new User();
$token    = trim($_GET['token'] ?? $_POST['token'] ?? '');
$user     = $token ? $userObj->findByResetToken($token) : null;
$errors   = [];
$done     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = trim($_POST['token']     ?? '');
    $password  =      $_POST['password']  ?? '';
    $password2 =      $_POST['password2'] ?? '';

    $user = $token ? $userObj->findByResetToken($token) : null;

    if (!$user) {
        $errors[] = 'This reset link is invalid or has expired. Please request a new one.';
    } else {
        if (empty($password)) {
            $errors['password'] = 'New password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($password !== $password2) {
            $errors['password2'] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $userObj->updatePassword($user['id'], $password);
            $userObj->clearResetToken($user['id']);
            $done = true;
        }
    }
}

$pageTitle = 'Reset Password';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Left brand panel ────────────────────────────────────── -->
<div class="auth-panel-left">
    <div class="auth-brand">Fit<span>Trainer</span></div>
    <div class="auth-panel-body">
        <h2 class="auth-headline">Choose a new<br>password.</h2>
        <p class="auth-subtext">
            Pick something strong and memorable. Your password is
            stored securely using bcrypt hashing.
        </p>
        <ul class="auth-features">
            <li><i class="bi bi-check-circle-fill"></i> Minimum 8 characters</li>
            <li><i class="bi bi-check-circle-fill"></i> Link is single-use</li>
            <li><i class="bi bi-check-circle-fill"></i> Encrypted with bcrypt</li>
        </ul>
    </div>
    <p class="auth-panel-footer">&copy; <?= date('Y') ?> FitTrainer App</p>
</div>

<!-- ── Right form panel ────────────────────────────────────── -->
<div class="auth-panel-right">
    <div class="auth-form-box">

        <h2>Set new password</h2>

        <?php if ($done): ?>

            <div class="alert alert-success">
                <h6 class="fw-bold mb-1">Password updated!</h6>
                <p class="mb-0">Your password has been changed successfully. You can now sign in.</p>
            </div>
            <a href="<?= BASE_URL ?>/pages/login.php" class="btn btn-primary w-100 mt-3">
                Sign in
            </a>

        <?php elseif (!$user && !$_POST): ?>

            <!-- Token missing or expired on first load -->
            <div class="alert alert-danger">
                <h6 class="fw-bold mb-1">Invalid or expired link</h6>
                <p class="mb-0">This password reset link is invalid or has already expired.</p>
            </div>
            <a href="<?= BASE_URL ?>/pages/forgot_password.php"
               class="btn btn-outline-secondary w-100 mt-3">
                Request a new link
            </a>

        <?php else: ?>

            <?php if (!empty($errors) && isset($errors[0])): ?>
                <div class="alert alert-danger py-2 mb-3">
                    <?= htmlspecialchars($errors[0]) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="mb-3">
                    <label for="password" class="form-label">
                        New password <span class="text-danger">*</span>
                    </label>
                    <input type="password"
                           class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                           id="password" name="password"
                           minlength="8" required autofocus autocomplete="new-password">
                    <div class="invalid-feedback">
                        <?= htmlspecialchars($errors['password'] ?? '') ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password2" class="form-label">
                        Confirm new password <span class="text-danger">*</span>
                    </label>
                    <input type="password"
                           class="form-control <?= isset($errors['password2']) ? 'is-invalid' : '' ?>"
                           id="password2" name="password2"
                           required autocomplete="new-password">
                    <div class="invalid-feedback">
                        <?= htmlspecialchars($errors['password2'] ?? '') ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Update password</button>
            </form>

        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
