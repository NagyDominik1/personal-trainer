<?php

/**
 * Login page — split-panel layout.
 * Left: brand panel with feature highlights.
 * Right: login form with server-side validation.
 */

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect already-authenticated users straight to the dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$errors = [];
$email  = '';

// Safe internal redirect after login (e.g. from trainer profile page)
$redirect = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
// Only allow redirects within this app — must start with BASE_URL
if ($redirect && strpos($redirect, BASE_URL . '/') !== 0) {
    $redirect = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    // Basic presence check
    if (empty($email) || empty($password)) {
        $errors[] = 'Email and password are required.';
    } else {
        $user = (new User())->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Generic message to avoid leaking which field is wrong
            $errors[] = 'Invalid email or password.';
        } elseif (!$user['is_active']) {
            $errors[] = 'Your account is not activated yet. Please check your email.';
        } elseif ($user['is_banned']) {
            $errors[] = 'Your account has been suspended. Please contact support.';
        } else {
            // Store essential user data in session
            $_SESSION['user_id']          = $user['id'];
            $_SESSION['user_name']        = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role']        = $user['role'];
            $_SESSION['user_is_approved'] = (bool) $user['is_approved'];

            $destination = $redirect ?: BASE_URL . '/pages/dashboard.php';
            header('Location: ' . $destination);
            exit;
        }
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Left brand panel ────────────────────────────────────── -->
<div class="auth-panel-left">

    <a href="<?= BASE_URL ?>/" class="auth-back-home">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Home
    </a>

    <div class="auth-brand">Fit<span>Trainer</span></div>

    <div class="auth-panel-body">
        <h2 class="auth-headline">Your fitness<br>journey starts<br>here.</h2>
        <p class="auth-subtext">
            Connect with expert trainers, follow personalised workout programs,
            and track your progress — all in one place.
        </p>
        <ul class="auth-features">
            <li><i class="bi bi-check-circle-fill"></i> Certified personal trainers</li>
            <li><i class="bi bi-check-circle-fill"></i> Categorised workout programs</li>
            <li><i class="bi bi-check-circle-fill"></i> Save &amp; track your favourites</li>
        </ul>
    </div>

    <p class="auth-panel-footer">&copy; <?= date('Y') ?> FitTrainer App</p>

</div>

<!-- ── Right form panel ────────────────────────────────────── -->
<div class="auth-panel-right">
    <div class="auth-form-box">

        <h2>Sign in</h2>
        <p class="auth-form-sub">
            Don't have an account?
            <a href="<?= BASE_URL ?>/pages/register.php" class="text-success fw-semibold">Register here</a>
        </p>

        <?php if ($errors): ?>
            <div class="alert alert-danger py-2 mb-3">
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php if ($redirect): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= htmlspecialchars($email) ?>"
                       placeholder="you@example.com"
                       required autofocus autocomplete="email">
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label mb-0">Password</label>
                    <a href="<?= BASE_URL ?>/pages/forgot_password.php"
                       class="text-success" style="font-size:0.82rem;">Forgot password?</a>
                </div>
                <input type="password" class="form-control" id="password" name="password"
                       required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary w-100">Sign in</button>

        </form>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
