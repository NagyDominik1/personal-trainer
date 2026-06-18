<?php

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

requireLogin();

$role       = $_SESSION['user_role']        ?? 'user';
$isApproved = $_SESSION['user_is_approved'] ?? false;

$firstName = explode(' ', $_SESSION['user_name'] ?? 'there')[0];

$pageTitle    = 'Dashboard';
$topBarTitle  = date('l, j F');

// Rotate between the two backgrounds on each visit
if (!isset($_SESSION['bg_index'])) $_SESSION['bg_index'] = 0;
$_SESSION['bg_index'] = 1 - $_SESSION['bg_index'];
$bg = ['clay', 'tennis'][$_SESSION['bg_index']];
$bodyClass = "photo-bg bg-$bg";

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="page-header slide-down" style="animation-delay:0ms">
    <h2>Welcome back, <?= htmlspecialchars($firstName) ?>!</h2>
    <p>Here's an overview of your account today.</p>
</div>

<?php if ($role === 'trainer' && !$isApproved): ?>
    <div class="approval-banner slide-down" style="animation-delay:80ms">
        <i class="bi bi-hourglass-split"></i>
        <div>
            <strong>Pending administrator approval.</strong>
            Your trainer account is active but creating and publishing workouts is disabled
            until an administrator approves your profile.
        </div>
    </div>
<?php endif; ?>

<!-- ── Stat cards ─────────────────────────────────────────── -->
<p class="section-label">Overview</p>
<div class="stats-grid" id="stats-row">

    <?php if ($role === 'admin'):
        $publishedCount = Database::getInstance()->query('SELECT COUNT(*) FROM workouts WHERE is_published = 1 AND is_hidden = 0')->fetchColumn();
    ?>

        <div class="stat-card stat-card--warning pop" style="animation-delay:120ms">
            <p class="stat-label">Pending Approvals</p>
            <p class="stat-value" id="stat-pending-trainers">—</p>
            <p class="stat-sub">trainer accounts awaiting review</p>
        </div>
        <div class="stat-card stat-card--danger pop" style="animation-delay:200ms">
            <p class="stat-label">Banned Users</p>
            <p class="stat-value" id="stat-banned-users">—</p>
            <p class="stat-sub">accounts currently suspended</p>
        </div>
        <div class="stat-card stat-card--dark pop" style="animation-delay:280ms">
            <p class="stat-label">Total Members</p>
            <p class="stat-value" id="stat-total-users">—</p>
            <p class="stat-sub">registered users &amp; trainers</p>
        </div>
        <div class="stat-card stat-card--ivy pop" style="animation-delay:360ms">
            <p class="stat-label">Published Workouts</p>
            <p class="stat-value"><?= $publishedCount ?></p>
            <p class="stat-sub">live programs available to users</p>
        </div>

    <?php elseif ($role === 'trainer'): ?>

        <div class="stat-card stat-card--clay pop" style="animation-delay:120ms">
            <p class="stat-label">My Exercises</p>
            <p class="stat-value" id="stat-my-exercises">—</p>
            <p class="stat-sub">exercises you have created</p>
        </div>
        <div class="stat-card stat-card--ivy pop" style="animation-delay:200ms">
            <p class="stat-label">My Workouts</p>
            <p class="stat-value" id="stat-my-workouts">—</p>
            <p class="stat-sub">workout programs published</p>
        </div>

    <?php else: ?>

        <div class="stat-card stat-card--clay pop" style="animation-delay:120ms">
            <p class="stat-label">Saved Workouts</p>
            <p class="stat-value" id="stat-saved-workouts">—</p>
            <p class="stat-sub">workouts in your collection</p>
        </div>
        <div class="stat-card stat-card--blue pop" style="animation-delay:200ms">
            <p class="stat-label">Available Workouts</p>
            <p class="stat-value" id="stat-total-workouts">—</p>
            <p class="stat-sub">programs created by trainers</p>
        </div>

    <?php endif; ?>

</div>

<!-- ── Quick actions — editorial rows ───────────────────────── -->
<p class="section-label" style="margin-top: 36px;">Quick Actions</p>
<div class="editorial-list">

    <?php if ($role === 'admin'): ?>

        <a href="<?= BASE_URL ?>/pages/admin/index.php" class="editorial-row rise" style="animation-delay:320ms">
            <span class="editorial-num">01</span>
            <span class="editorial-watermark">01</span>
            <div class="editorial-icon"><i class="bi bi-tags"></i></div>
            <div class="editorial-body">
                <span class="editorial-title">Manage Categories</span>
                <span class="editorial-desc">Add, edit or remove workout categories</span>
            </div>
            <div class="editorial-arrow"><i class="bi bi-chevron-right"></i></div>
        </a>
        <a href="<?= BASE_URL ?>/pages/admin/trainers.php" class="editorial-row rise" style="animation-delay:400ms">
            <span class="editorial-num">02</span>
            <span class="editorial-watermark">02</span>
            <div class="editorial-icon"><i class="bi bi-person-check"></i></div>
            <div class="editorial-body">
                <span class="editorial-title">Approve Trainers</span>
                <span class="editorial-desc">Review and approve trainer accounts</span>
            </div>
            <div class="editorial-arrow"><i class="bi bi-chevron-right"></i></div>
        </a>
        <a href="<?= BASE_URL ?>/pages/admin/users.php" class="editorial-row rise" style="animation-delay:480ms">
            <span class="editorial-num">03</span>
            <span class="editorial-watermark">03</span>
            <div class="editorial-icon"><i class="bi bi-people"></i></div>
            <div class="editorial-body">
                <span class="editorial-title">Manage Users</span>
                <span class="editorial-desc">Ban or unban member accounts</span>
            </div>
            <div class="editorial-arrow"><i class="bi bi-chevron-right"></i></div>
        </a>

    <?php elseif ($role === 'trainer'): ?>

        <a href="<?= BASE_URL ?>/pages/trainer/exercises.php"
           class="editorial-row rise <?= !$isApproved ? 'editorial-row--disabled' : '' ?>"
           style="animation-delay:320ms">
            <span class="editorial-num">01</span>
            <span class="editorial-watermark">01</span>
            <div class="editorial-icon"><i class="bi bi-activity"></i></div>
            <div class="editorial-body">
                <span class="editorial-title">My Exercises</span>
                <span class="editorial-desc">
                    <?= $isApproved ? 'Create and manage your exercise library' : 'Awaiting admin approval' ?>
                </span>
            </div>
            <div class="editorial-arrow"><i class="bi bi-chevron-right"></i></div>
        </a>
        <a href="<?= BASE_URL ?>/pages/trainer/workouts.php"
           class="editorial-row rise <?= !$isApproved ? 'editorial-row--disabled' : '' ?>"
           style="animation-delay:400ms">
            <span class="editorial-num">02</span>
            <span class="editorial-watermark">02</span>
            <div class="editorial-icon"><i class="bi bi-journal-text"></i></div>
            <div class="editorial-body">
                <span class="editorial-title">My Workouts</span>
                <span class="editorial-desc">
                    <?= $isApproved ? 'Build and publish workout programs' : 'Awaiting admin approval' ?>
                </span>
            </div>
            <div class="editorial-arrow"><i class="bi bi-chevron-right"></i></div>
        </a>
        <a href="<?= BASE_URL ?>/pages/profile.php" class="editorial-row rise" style="animation-delay:480ms">
            <span class="editorial-num">03</span>
            <span class="editorial-watermark">03</span>
            <div class="editorial-icon"><i class="bi bi-person-circle"></i></div>
            <div class="editorial-body">
                <span class="editorial-title">Edit Profile</span>
                <span class="editorial-desc">Update your name, bio, and password</span>
            </div>
            <div class="editorial-arrow"><i class="bi bi-chevron-right"></i></div>
        </a>

    <?php else: ?>

        <a href="<?= BASE_URL ?>/pages/user/workouts.php" class="editorial-row rise" style="animation-delay:320ms">
            <span class="editorial-num">01</span>
            <span class="editorial-watermark">01</span>
            <div class="editorial-icon"><i class="bi bi-search"></i></div>
            <div class="editorial-body">
                <span class="editorial-title">Browse Workouts</span>
                <span class="editorial-desc">Search and filter workout programs by category</span>
            </div>
            <div class="editorial-arrow"><i class="bi bi-chevron-right"></i></div>
        </a>
        <a href="<?= BASE_URL ?>/pages/user/saved.php" class="editorial-row rise" style="animation-delay:400ms">
            <span class="editorial-num">02</span>
            <span class="editorial-watermark">02</span>
            <div class="editorial-icon"><i class="bi bi-bookmark-star"></i></div>
            <div class="editorial-body">
                <span class="editorial-title">Saved Workouts</span>
                <span class="editorial-desc">View the programs you have saved</span>
            </div>
            <div class="editorial-arrow"><i class="bi bi-chevron-right"></i></div>
        </a>
        <a href="<?= BASE_URL ?>/pages/user/my_plan.php" class="editorial-row rise" style="animation-delay:480ms">
            <span class="editorial-num">03</span>
            <span class="editorial-watermark">03</span>
            <div class="editorial-icon"><i class="bi bi-journal-plus"></i></div>
            <div class="editorial-body">
                <span class="editorial-title">My Plans</span>
                <span class="editorial-desc">Build a personal workout plan from exercises</span>
            </div>
            <div class="editorial-arrow"><i class="bi bi-chevron-right"></i></div>
        </a>
        <a href="<?= BASE_URL ?>/pages/trainers.php" class="editorial-row rise" style="animation-delay:560ms">
            <span class="editorial-num">04</span>
            <span class="editorial-watermark">04</span>
            <div class="editorial-icon"><i class="bi bi-people"></i></div>
            <div class="editorial-body">
                <span class="editorial-title">Our Trainers</span>
                <span class="editorial-desc">Browse trainer profiles and their programs</span>
            </div>
            <div class="editorial-arrow"><i class="bi bi-chevron-right"></i></div>
        </a>
        <a href="<?= BASE_URL ?>/pages/profile.php" class="editorial-row rise" style="animation-delay:640ms">
            <span class="editorial-num">05</span>
            <span class="editorial-watermark">05</span>
            <div class="editorial-icon"><i class="bi bi-person-circle"></i></div>
            <div class="editorial-body">
                <span class="editorial-title">Edit Profile</span>
                <span class="editorial-desc">Update your name, phone, and password</span>
            </div>
            <div class="editorial-arrow"><i class="bi bi-chevron-right"></i></div>
        </a>

    <?php endif; ?>

</div>

<script>window.USER_ROLE = '<?= htmlspecialchars($role) ?>';</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
