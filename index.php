<?php
/**
 * Application entry point.
 * Logged-in users → dashboard.
 * Guests → public landing page.
 */

require_once __DIR__ . '/config/db_config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Logged-in users skip the landing page
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$db = Database::getInstance();

// Fetch approved trainers with aggregate stats for the landing section
$trainers = $db->query('
    SELECT u.id, u.first_name, u.last_name, u.bio,
        COUNT(DISTINCT w.id)         AS workout_count,
        COALESCE(SUM(saves.cnt), 0)  AS total_saves
    FROM users u
    LEFT JOIN workouts w
        ON w.user_id = u.id AND w.is_published = 1 AND w.is_hidden = 0
    LEFT JOIN (
        SELECT workout_id, COUNT(*) AS cnt
        FROM user_workouts GROUP BY workout_id
    ) saves ON saves.workout_id = w.id
    WHERE u.role = "trainer" AND u.is_approved = 1 AND u.is_banned = 0
    GROUP BY u.id, u.first_name, u.last_name, u.bio
    ORDER BY workout_count DESC, u.first_name
    LIMIT 6
')->fetchAll();

// Avatar gradient palette — cycles through trainers
$gradients = [
    'linear-gradient(135deg,oklch(58% 0.14 42),oklch(44% 0.11 36))',
    'linear-gradient(135deg,oklch(50% 0.13 240),oklch(40% 0.10 250))',
    'linear-gradient(135deg,oklch(46% 0.13 15),oklch(38% 0.11 18))',
    'linear-gradient(135deg,oklch(44% 0.12 152),oklch(36% 0.10 155))',
    'linear-gradient(135deg,oklch(50% 0.13 88),oklch(42% 0.11 90))',
    'linear-gradient(135deg,oklch(48% 0.11 320),oklch(40% 0.09 325))',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitTrainer — Your Personal Training Platform</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@300;400;600;700;800&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- App stylesheet (external CSS — required) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css?v=<?= filemtime(__DIR__ . '/public/css/style.css') ?>">
</head>
<body class="public-layout">

<!-- ════════════════════════════════════════════════════
     NAV
     ════════════════════════════════════════════════════ -->
<nav class="pub-nav">
    <a href="<?= BASE_URL ?>/" class="pub-nav-brand">Fit<span>Trainer</span></a>
    <div class="pub-nav-right">
        <a href="<?= BASE_URL ?>/pages/login.php"    class="pub-nav-link">Sign in</a>
        <a href="<?= BASE_URL ?>/pages/register.php" class="pub-nav-btn">Get started</a>
    </div>
</nav>

<!-- ════════════════════════════════════════════════════
     HERO
     ════════════════════════════════════════════════════ -->
<section class="pub-hero">
    <div style="max-width:640px">
        <div class="pub-hero-eyebrow">Personal Training Platform</div>
        <h1 class="pub-hero-h1">Your fitness<br>journey<br>starts here.</h1>
        <p class="pub-hero-sub">
            Connect with certified trainers, follow structured workout programs
            by category, and track your progress — all in one place.
        </p>
        <div class="pub-hero-actions">
            <a href="<?= BASE_URL ?>/pages/register.php" class="pub-btn-primary">
                Get started — it's free
            </a>
            <a href="<?= BASE_URL ?>/pages/login.php" class="pub-btn-ghost">
                Sign in
            </a>
        </div>
    </div>

    <!-- Scroll hint -->
    <div class="pub-scroll-hint">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </div>
</section>

<!-- ════════════════════════════════════════════════════
     FEATURES
     ════════════════════════════════════════════════════ -->
<section class="pub-features">
    <div class="pub-container">
        <div class="pub-section-label">What you get</div>
        <h2 class="pub-section-h2">Everything you need to train smarter</h2>
        <div class="pub-features-grid">

            <div class="pub-feature-card">
                <div class="pub-feature-icon">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div class="pub-feature-title">Certified Trainers</div>
                <p class="pub-feature-desc">
                    Browse profiles of admin-approved personal trainers.
                    Each trainer brings their own expertise and methodology.
                </p>
            </div>

            <div class="pub-feature-card">
                <div class="pub-feature-icon">
                    <i class="bi bi-journal-text"></i>
                </div>
                <div class="pub-feature-title">Structured Programs</div>
                <p class="pub-feature-desc">
                    Filter workout programs by category — strength, cardio, HIIT,
                    flexibility and more — and find the right fit for your goals.
                </p>
            </div>

            <div class="pub-feature-card">
                <div class="pub-feature-icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="pub-feature-title">Track Your Progress</div>
                <p class="pub-feature-desc">
                    Save programs, tick off completed days, take personal notes,
                    and build your own custom plans from the exercise library.
                </p>
            </div>

        </div>
    </div>
</section>

<!-- ════════════════════════════════════════════════════
     TRAINERS
     ════════════════════════════════════════════════════ -->
<section class="pub-trainers-section">
    <div class="pub-container">
        <div class="pub-section-label" style="color:var(--accent-gold)">Our Team</div>
        <h2 class="pub-section-h2" style="color:#fff;margin-bottom:36px">
            Meet the trainers
        </h2>

        <?php if ($trainers): ?>
        <div class="pub-trainers-grid">
        <?php foreach ($trainers as $i => $t):
            $initials = strtoupper(substr($t['first_name'], 0, 1) . substr($t['last_name'], 0, 1));
            $gradient = $gradients[$i % count($gradients)];
        ?>
            <a href="<?= BASE_URL ?>/pages/trainer/profile.php?id=<?= $t['id'] ?>"
               class="pub-trainer-card">
                <div class="pub-trainer-top">
                    <div class="pub-trainer-avatar" style="background:<?= $gradient ?>">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                    <div>
                        <div class="pub-trainer-name">
                            <?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?>
                        </div>
                        <span class="pub-trainer-badge">Trainer</span>
                    </div>
                </div>

                <?php if ($t['bio']): ?>
                    <p class="pub-trainer-bio"><?= htmlspecialchars($t['bio']) ?></p>
                <?php else: ?>
                    <p class="pub-trainer-bio" style="font-style:italic;color:oklch(58% 0.02 50)">
                        No bio yet.
                    </p>
                <?php endif; ?>

                <div class="pub-trainer-stats">
                    <div>
                        <strong><?= (int)$t['workout_count'] ?></strong>
                        Programs
                    </div>
                    <div>
                        <strong><?= (int)$t['total_saves'] ?></strong>
                        Saves
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
        </div>

        <?php else: ?>
        <p style="color:rgba(255,255,255,0.5);text-align:center;font-style:italic">
            No approved trainers yet.
        </p>
        <?php endif; ?>

        <!-- CTA below trainers -->
        <div style="text-align:center;margin-top:40px">
            <a href="<?= BASE_URL ?>/pages/register.php" class="pub-btn-primary">
                Join and start training
            </a>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════════════════════
     FOOTER
     ════════════════════════════════════════════════════ -->
<footer class="pub-footer">
    &copy; <?= date('Y') ?> FitTrainer &nbsp;·&nbsp;
    <a href="<?= BASE_URL ?>/pages/login.php"
       style="color:rgba(255,255,255,0.45);text-decoration:none">Sign in</a>
    &nbsp;·&nbsp;
    <a href="<?= BASE_URL ?>/pages/register.php"
       style="color:rgba(255,255,255,0.45);text-decoration:none">Register</a>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
