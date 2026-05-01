<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/mail_config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$submitted = false;
$errors    = [];
$email     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $userObj = new User();
        $user    = $userObj->findByEmail($email);

        // Only send if the account exists, is active, and is not banned
        if ($user && $user['is_active'] && !$user['is_banned']) {
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);
            $userObj->setResetToken($user['id'], $token, $expiresAt);
            Mailer::sendPasswordReset(
                $email,
                $user['first_name'] . ' ' . $user['last_name'],
                $token
            );
        }

        // Always show success — never reveal whether the email exists
        $submitted = true;
    }
}

$pageTitle = 'Forgot Password';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Left brand panel ────────────────────────────────────── -->
<div class="auth-panel-left">
    <div class="auth-brand">Fit<span>Trainer</span></div>
    <div class="auth-panel-body">
        <h2 class="auth-headline">Forgot your<br>password?</h2>
        <p class="auth-subtext">
            No problem. Enter your email address and we will send you
            a secure link to reset it.
        </p>
        <ul class="auth-features">
            <li><i class="bi bi-check-circle-fill"></i> Link expires in 1 hour</li>
            <li><i class="bi bi-check-circle-fill"></i> Secure bcrypt password storage</li>
            <li><i class="bi bi-check-circle-fill"></i> No account? Register for free</li>
        </ul>
    </div>
    <p class="auth-panel-footer">&copy; <?= date('Y') ?> FitTrainer App</p>
</div>

<!-- ── Right form panel ────────────────────────────────────── -->
<div class="auth-panel-right">
    <div class="auth-form-box">

        <h2>Reset password</h2>
        <p class="auth-form-sub">
            Remember it after all?
            <a href="<?= BASE_URL ?>/pages/login.php" class="text-success fw-semibold">Sign in</a>
        </p>

        <?php if ($submitted): ?>

            <div class="alert alert-success">
                <h6 class="fw-bold mb-1">Check your inbox</h6>
                <p class="mb-0">
                    If an account exists for <strong><?= htmlspecialchars($email) ?></strong>,
                    a password reset link has been sent. The link expires in 1 hour.
                </p>
            </div>
            <a href="<?= BASE_URL ?>/pages/login.php" class="btn btn-outline-secondary w-100 mt-3">
                Back to sign in
            </a>

        <?php else: ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger py-2 mb-3">
                    <?php foreach ($errors as $e): ?>
                        <div><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-4">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= htmlspecialchars($email) ?>"
                           placeholder="you@example.com"
                           required autofocus autocomplete="email">
                </div>
                <button type="submit" class="btn btn-primary w-100">Send reset link</button>
            </form>

        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
