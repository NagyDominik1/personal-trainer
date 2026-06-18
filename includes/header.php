<?php

/**
 * Shared page header.
 *
 * Renders two layouts depending on authentication state:
 *   - Logged-in  → full app shell with collapsible sidebar + top bar
 *   - Guest      → minimal full-screen split layout (used by login / register)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// Determine which sidebar link should be highlighted
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Personal Trainer App') ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts: Barlow Condensed + Barlow + Playfair Display -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@300;400;500;600;700&family=Barlow:wght@300;400;500;600&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- App styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css?v=<?= filemtime(__DIR__ . '/../public/css/style.css') ?>">

    <!-- Expose base URL before any scripts run -->
    <script>window.APP_BASE = '<?= BASE_URL ?>';</script>
</head>

<?php if (isLoggedIn()):
    // ── App layout (sidebar) ─────────────────────────────
    // Build two-letter initials for the sidebar avatar
    $nameParts = explode(' ', $_SESSION['user_name'] ?? '');
    $initials  = strtoupper(
        substr($nameParts[0] ?? '', 0, 1) .
        substr($nameParts[1] ?? '', 0, 1)
    );
    $role       = $_SESSION['user_role']        ?? 'user';
    $isApproved = $_SESSION['user_is_approved'] ?? false;

    // Pending coaching requests badge (trainers only).
    $coachPending = 0;
    if ($role === 'trainer' && class_exists('Database')) {
        try {
            $cstmt = Database::getInstance()->prepare('SELECT COUNT(*) FROM coaching WHERE trainer_id = :tid AND status = "pending"');
            $cstmt->execute([':tid' => (int)($_SESSION['user_id'] ?? 0)]);
            $coachPending = (int) $cstmt->fetchColumn();
        } catch (Throwable $e) { /* table may not exist yet */ }
    }

    // Unread support messages badge (admin only).
    $supportUnread = 0;
    if ($role === 'admin' && class_exists('Database')) {
        try {
            $supportUnread = (int) Database::getInstance()
                ->query('SELECT COUNT(*) FROM support_messages WHERE is_read = 0')->fetchColumn();
        } catch (Throwable $e) { /* table may not exist yet */ }
    }
?>

<body class="app-layout <?= htmlspecialchars($bodyClass ?? '') ?>">

<!-- ════════════════════════════════════════════════════════════
     SIDEBAR
     Collapsed to 68 px icon-strip; expands to 240 px on hover.
     ════════════════════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

    <!-- Logo -->
    <div class="sidebar-header">
        <span class="sidebar-logo-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 20A7 7 0 014 13V6a7 7 0 0113 0v1a7 7 0 01-7 7h-1"/><path d="M11 20v-9"/>
            </svg>
        </span>
        <span class="sidebar-logo-text">FitTrainer</span>
    </div>

    <!-- Navigation links (role-aware) -->
    <nav class="sidebar-nav" aria-label="Main navigation">

        <!-- Dashboard — visible to everyone -->
        <a href="<?= BASE_URL ?>/pages/dashboard.php"
           class="sidebar-link<?= $currentPage === 'dashboard.php' ? ' active' : '' ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        <?php if ($role === 'admin'): ?>

            <div class="sidebar-section">Admin Panel</div>

            <a href="<?= BASE_URL ?>/pages/admin/dashboard.php"
               class="sidebar-link<?= $currentPage === 'dashboard.php' && strpos($_SERVER['PHP_SELF'], 'admin') !== false ? ' active' : '' ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Stats</span>
            </a>

            <a href="<?= BASE_URL ?>/pages/admin/index.php"
               class="sidebar-link<?= $currentPage === 'index.php' ? ' active' : '' ?>">
                <i class="bi bi-tags"></i>
                <span>Categories</span>
            </a>

            <a href="<?= BASE_URL ?>/pages/admin/trainers.php"
               class="sidebar-link<?= $currentPage === 'trainers.php' ? ' active' : '' ?>">
                <i class="bi bi-person-check"></i>
                <span>Trainers</span>
            </a>

            <a href="<?= BASE_URL ?>/pages/admin/users.php"
               class="sidebar-link<?= $currentPage === 'users.php' ? ' active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Users</span>
            </a>

            <a href="<?= BASE_URL ?>/pages/admin/workouts.php"
               class="sidebar-link<?= $currentPage === 'workouts.php' && strpos($_SERVER['PHP_SELF'], 'admin') !== false ? ' active' : '' ?>">
                <i class="bi bi-shield-check"></i>
                <span>Moderation</span>
            </a>

            <a href="<?= BASE_URL ?>/pages/admin/coaching.php"
               class="sidebar-link<?= $currentPage === 'coaching.php' ? ' active' : '' ?>">
                <i class="bi bi-link-45deg"></i>
                <span>Coaching</span>
            </a>

            <a href="<?= BASE_URL ?>/pages/admin/support.php"
               class="sidebar-link<?= $currentPage === 'support.php' && strpos($_SERVER['PHP_SELF'], 'admin') !== false ? ' active' : '' ?>">
                <i class="bi bi-chat-left-dots"></i>
                <span>Support</span>
                <?php if ($supportUnread > 0): ?>
                    <span style="margin-left:auto;background:oklch(58% 0.14 42);color:#fff;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.66rem;line-height:1;padding:3px 7px;border-radius:9px"><?= $supportUnread ?></span>
                <?php endif; ?>
            </a>

        <?php elseif ($role === 'trainer'): ?>

            <div class="sidebar-section">Manage</div>

            <!-- Greyed out if admin has not yet approved this trainer -->
            <a href="<?= BASE_URL ?>/pages/trainer/exercises.php"
               class="sidebar-link<?= $currentPage === 'exercises.php' ? ' active' : '' ?>
                      <?= !$isApproved ? ' sidebar-link--muted' : '' ?>">
                <i class="bi bi-activity"></i>
                <span>Exercises</span>
            </a>

            <a href="<?= BASE_URL ?>/pages/trainer/workouts.php"
               class="sidebar-link<?= $currentPage === 'workouts.php' ? ' active' : '' ?>
                      <?= !$isApproved ? ' sidebar-link--muted' : '' ?>">
                <i class="bi bi-journal-text"></i>
                <span>My Workouts</span>
            </a>

            <a href="<?= BASE_URL ?>/pages/trainer/clients.php"
               class="sidebar-link<?= $currentPage === 'clients.php' ? ' active' : '' ?>
                      <?= !$isApproved ? ' sidebar-link--muted' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Clients</span>
                <?php if ($coachPending > 0): ?>
                    <span style="margin-left:auto;background:oklch(58% 0.14 42);color:#fff;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.66rem;line-height:1;padding:3px 7px;border-radius:9px"><?= $coachPending ?></span>
                <?php endif; ?>
            </a>

            <a href="<?= BASE_URL ?>/pages/trainer/analytics.php"
               class="sidebar-link<?= $currentPage === 'analytics.php' ? ' active' : '' ?>
                      <?= !$isApproved ? ' sidebar-link--muted' : '' ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Analytics</span>
            </a>

        <?php else: ?>

            <div class="sidebar-section">Workouts</div>

            <a href="<?= BASE_URL ?>/pages/user/workouts.php"
               class="sidebar-link<?= $currentPage === 'workouts.php' ? ' active' : '' ?>">
                <i class="bi bi-search"></i>
                <span>Browse</span>
            </a>

            <a href="<?= BASE_URL ?>/pages/user/saved.php"
               class="sidebar-link<?= $currentPage === 'saved.php' ? ' active' : '' ?>">
                <i class="bi bi-bookmark-star"></i>
                <span>Saved</span>
            </a>

            <a href="<?= BASE_URL ?>/pages/user/my_plan.php"
               class="sidebar-link<?= $currentPage === 'my_plan.php' ? ' active' : '' ?>">
                <i class="bi bi-journal-plus"></i>
                <span>My Plans</span>
            </a>

            <div class="sidebar-section">Community</div>

            <a href="<?= BASE_URL ?>/pages/trainers.php"
               class="sidebar-link<?= $currentPage === 'trainers.php' ? ' active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Trainers</span>
            </a>

            <a href="<?= BASE_URL ?>/pages/user/support.php"
               class="sidebar-link<?= $currentPage === 'support.php' ? ' active' : '' ?>">
                <i class="bi bi-chat-left-dots"></i>
                <span>Support</span>
            </a>

        <?php endif; ?>

        <div class="sidebar-section">Account</div>

        <a href="<?= BASE_URL ?>/pages/profile.php"
           class="sidebar-link<?= $currentPage === 'profile.php' ? ' active' : '' ?>">
            <i class="bi bi-person-circle"></i>
            <span>Profile</span>
        </a>

    </nav>

    <!-- User card + logout at the bottom of the sidebar -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="sidebar-user-info">
                <span class="sidebar-user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></span>
                <span class="sidebar-user-role"><?= htmlspecialchars($role) ?></span>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/pages/logout.php" class="sidebar-link sidebar-link--danger">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </a>
    </div>

</aside>
<!-- /sidebar -->

<!-- ════════════════════════════════════════════════════════════
     MAIN WRAPPER  (everything to the right of the sidebar)
     ════════════════════════════════════════════════════════════ -->
<div class="main-wrapper" id="mainWrapper">

    <!-- Sticky top bar -->
    <header class="top-bar">
        <!-- Hamburger: only visible on mobile -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="top-bar-title"><?= htmlspecialchars($topBarTitle ?? $pageTitle ?? '') ?></h1>
        <div class="top-bar-right">
            <span class="role-badge"><?= htmlspecialchars($role) ?></span>
        </div>
    </header>

    <!-- Page content -->
    <main class="main-content">
        <div id="alert-container"></div>

<?php elseif ($isPublicPage ?? false): ?>

<!-- ════════════════════════════════════════════════════════════
     PUBLIC PAGE LAYOUT  (guest views a public page, e.g. trainer profile)
     ════════════════════════════════════════════════════════════ -->
<body class="public-layout">

<!-- Sticky nav -->
<nav class="pub-nav">
    <a href="<?= BASE_URL ?>/" class="pub-nav-brand">Fit<span>Trainer</span></a>
    <div class="pub-nav-right">
        <a href="<?= BASE_URL ?>/pages/login.php"    class="pub-nav-link">Sign in</a>
        <a href="<?= BASE_URL ?>/pages/register.php" class="pub-nav-btn">Get started</a>
    </div>
</nav>

<!-- Page content with clay-court background, offset for the fixed nav -->
<div class="pub-page-bg">

<?php else: ?>

<!-- ════════════════════════════════════════════════════════════
     AUTH LAYOUT  (login / register — no sidebar)
     ════════════════════════════════════════════════════════════ -->
<body class="auth-layout">

<?php endif; ?>
