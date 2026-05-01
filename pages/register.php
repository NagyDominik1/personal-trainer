<?php

/**
 * Registration page — split-panel layout matching login.php.
 * Left: brand panel.  Right: registration form.
 *
 * Server-side validation mirrors the client-side checks in main.js.
 * On success a confirmation message is shown (no redirect).
 */

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/mail_config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Mailer.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in — no reason to be here
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$errors  = [];
$success = false;

// Preserve form values on a failed submission
$old = [
    'first_name' => '',
    'last_name'  => '',
    'email'      => '',
    'phone'      => '',
    'is_trainer' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect and sanitise input
    $old = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name']  ?? ''),
        'email'      => trim($_POST['email']      ?? ''),
        'phone'      => trim($_POST['phone']      ?? ''),
        'is_trainer' => isset($_POST['is_trainer']),
    ];
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    // ── Server-side validation ────────────────────────────��────
    if (empty($old['first_name'])) {
        $errors['first_name'] = 'First name is required.';
    } elseif (strlen($old['first_name']) > 50) {
        $errors['first_name'] = 'First name must not exceed 50 characters.';
    }

    if (empty($old['last_name'])) {
        $errors['last_name'] = 'Last name is required.';
    } elseif (strlen($old['last_name']) > 50) {
        $errors['last_name'] = 'Last name must not exceed 50 characters.';
    }

    if (empty($old['email'])) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif ((new User())->findByEmail($old['email'])) {
        $errors['email'] = 'An account with this email already exists.';
    }

    if (!empty($old['phone']) && !preg_match('/^\+?[\d\s\-\(\)]{6,20}$/', $old['phone'])) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if ($password !== $password2) {
        $errors['password2'] = 'Passwords do not match.';
    }

    // ── Register if no errors ──────────────────────────────────
    if (empty($errors)) {
        $token = (new User())->register(array_merge($old, ['password' => $password]));

        if ($token !== false) {
            // Send activation email — failure is logged but does not block registration
            Mailer::sendActivation(
                $old['email'],
                $old['first_name'] . ' ' . $old['last_name'],
                $token
            );
            $success = true;
        } else {
            $errors['general'] = 'Registration failed. Please try again later.';
        }
    }
}

$pageTitle = 'Create Account';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Left brand panel ────────────────────────────────────── -->
<div class="auth-panel-left">

    <div class="auth-brand">Fit<span>Trainer</span></div>

    <div class="auth-panel-body">
        <h2 class="auth-headline">Join the<br>community<br>today.</h2>
        <p class="auth-subtext">
            Create a free account and start exploring expert workout programs
            designed for every fitness level.
        </p>
        <ul class="auth-features">
            <li><i class="bi bi-check-circle-fill"></i> Free to register</li>
            <li><i class="bi bi-check-circle-fill"></i> Browse 5 workout categories</li>
            <li><i class="bi bi-check-circle-fill"></i> Register as a trainer and create programs</li>
        </ul>
    </div>

    <p class="auth-panel-footer">&copy; <?= date('Y') ?> FitTrainer App</p>

</div>

<!-- ── Right form panel ────────────────────────────────────── -->
<div class="auth-panel-right">
    <div class="auth-form-box auth-form-box--wide">

        <h2>Create account</h2>
        <p class="auth-form-sub">
            Already have an account?
            <a href="<?= BASE_URL ?>/pages/login.php" class="text-success fw-semibold">Sign in</a>
        </p>

        <?php if ($success): ?>

            <!-- Success state — replaces the form -->
            <div class="alert alert-success">
                <h6 class="fw-bold mb-1">Registration successful!</h6>
                <p class="mb-0">
                    A confirmation email has been sent to
                    <strong><?= htmlspecialchars($old['email']) ?></strong>.
                    Click the activation link in your inbox to complete registration.
                </p>
                <?php if ($old['is_trainer']): ?>
                    <hr class="my-2">
                    <p class="mb-0 small">
                        As a <strong>trainer</strong>, your account also requires
                        <strong>administrator approval</strong> before you can publish workouts.
                        You will be notified once approved.
                    </p>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger py-2 mb-3">
                    <?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form id="register-form" method="POST" action="" novalidate>

                <!-- Name row -->
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label for="first_name" class="form-label">
                            First name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>"
                               id="first_name" name="first_name"
                               value="<?= htmlspecialchars($old['first_name']) ?>"
                               maxlength="50" required>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['first_name'] ?? 'First name is required.') ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label for="last_name" class="form-label">
                            Last name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>"
                               id="last_name" name="last_name"
                               value="<?= htmlspecialchars($old['last_name']) ?>"
                               maxlength="50" required>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['last_name'] ?? 'Last name is required.') ?>
                        </div>
                    </div>
                </div>

                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label">
                        Email address <span class="text-danger">*</span>
                    </label>
                    <input type="email"
                           class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                           id="email" name="email"
                           value="<?= htmlspecialchars($old['email']) ?>"
                           placeholder="you@example.com"
                           required autocomplete="email">
                    <div class="invalid-feedback">
                        <?= htmlspecialchars($errors['email'] ?? 'Please enter a valid email address.') ?>
                    </div>
                    <!-- Populated in real-time by Fetch API (main.js) -->
                    <div id="email-check" class="form-text"></div>
                </div>

                <!-- Phone -->
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone number</label>
                    <input type="tel"
                           class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                           id="phone" name="phone"
                           value="<?= htmlspecialchars($old['phone']) ?>"
                           placeholder="+1 234 567 8900">
                    <div class="invalid-feedback">
                        <?= htmlspecialchars($errors['phone'] ?? 'Please enter a valid phone number.') ?>
                    </div>
                </div>

                <!-- Password row -->
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label for="password" class="form-label">
                            Password <span class="text-danger">*</span>
                        </label>
                        <input type="password"
                               class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                               id="password" name="password"
                               minlength="8" required autocomplete="new-password">
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['password'] ?? 'Password must be at least 8 characters.') ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label for="password2" class="form-label">
                            Confirm password <span class="text-danger">*</span>
                        </label>
                        <input type="password"
                               class="form-control <?= isset($errors['password2']) ? 'is-invalid' : '' ?>"
                               id="password2" name="password2"
                               required autocomplete="new-password">
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['password2'] ?? 'Passwords do not match.') ?>
                        </div>
                    </div>
                </div>

                <!-- Trainer checkbox -->
                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               id="is-trainer" name="is_trainer" value="1"
                               <?= $old['is_trainer'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="is-trainer">
                            Register as a trainer
                        </label>
                    </div>
                    <!-- Shown / hidden by main.js when checkbox is toggled -->
                    <div id="trainer-notice"
                         class="approval-banner mt-2"
                         style="display: <?= $old['is_trainer'] ? 'flex' : 'none' ?>">
                        <i class="bi bi-info-circle"></i>
                        <span>Trainer accounts require administrator approval before you can publish workouts.</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Create account</button>

            </form>

        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
