<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = Database::getInstance();

// Fetch all approved, active trainers with aggregate stats
$trainers = $db->query('
    SELECT u.id, u.first_name, u.last_name, u.bio,
        COUNT(DISTINCT w.id)                        AS workout_count,
        ROUND(AVG(wr.rating), 1)                    AS avg_rating,
        COUNT(DISTINCT wr.id)                       AS review_count,
        COALESCE(SUM(saves.cnt), 0)                 AS total_saves
    FROM users u
    LEFT JOIN workouts w
        ON w.user_id = u.id AND w.is_published = 1 AND w.is_hidden = 0
    LEFT JOIN workout_reviews wr
        ON wr.workout_id = w.id
    LEFT JOIN (
        SELECT workout_id, COUNT(*) AS cnt FROM user_workouts GROUP BY workout_id
    ) saves ON saves.workout_id = w.id
    WHERE u.role = "trainer" AND u.is_approved = 1 AND u.is_banned = 0
    GROUP BY u.id, u.first_name, u.last_name, u.bio
    ORDER BY workout_count DESC, u.first_name
')->fetchAll();

/**
 * Build two-letter initials from first and last name.
 */
function trainerInitials(string $first, string $last): string {
    return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
}

// Cycle through gradient colours for avatar backgrounds
$avatarGradients = [
    'linear-gradient(135deg,oklch(58% 0.14 42),oklch(44% 0.11 36))',
    'linear-gradient(135deg,oklch(50% 0.13 240),oklch(40% 0.10 250))',
    'linear-gradient(135deg,oklch(46% 0.13 15),oklch(38% 0.11 18))',
    'linear-gradient(135deg,oklch(44% 0.12 152),oklch(36% 0.10 155))',
    'linear-gradient(135deg,oklch(50% 0.13 88),oklch(42% 0.11 90))',
];

$pageTitle   = 'Our Trainers';
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Page heading ──────────────────────────────────────── */
.tr-eyebrow {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .69rem;
    letter-spacing: .14em; text-transform: uppercase;
    color: rgba(255,255,255,.55); margin-bottom: 6px;
}
.tr-h1 {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 2.375rem;
    color: #fff; letter-spacing: .02em; line-height: 1;
    text-shadow: 0 1px 8px rgba(0,0,0,.3); margin: 0 0 28px;
}

/* ── Trainer grid ──────────────────────────────────────── */
.tr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 18px;
}

/* ── Trainer card ──────────────────────────────────────── */
.tr-card {
    background: rgba(255,255,255,.65);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,.5);
    border-radius: 18px;
    padding: 26px 26px 20px;
    box-shadow: 0 4px 18px rgba(0,0,0,.1);
    display: flex; flex-direction: column;
    transition: all .22s;
}
.tr-card:hover {
    background: rgba(255,255,255,.84);
    box-shadow: 0 12px 36px rgba(0,0,0,.17);
    transform: translateY(-4px);
}
.tr-card-top {
    display: flex; align-items: center; gap: 16px; margin-bottom: 14px;
}
.tr-avatar {
    width: 56px; height: 56px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800; font-size: 1.3rem; color: #fff;
    box-shadow: 0 3px 12px rgba(0,0,0,.22);
    border: 2.5px solid rgba(255,255,255,.35);
    flex-shrink: 0;
}
.tr-name {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800; font-size: 1.4rem;
    letter-spacing: .02em; color: oklch(16% 0.03 50);
    line-height: 1; margin-bottom: 4px;
}
.tr-badge {
    background: oklch(72% 0.14 88);
    color: oklch(22% 0.06 88);
    padding: 2px 9px; border-radius: 4px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .65rem;
    letter-spacing: .1em; text-transform: uppercase;
    display: inline-block;
}
.tr-bio {
    font-size: .83rem; color: oklch(38% 0.03 50);
    line-height: 1.5; margin-bottom: 14px; flex: 1;
    /* Clamp to 3 lines */
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.tr-no-bio {
    font-size: .83rem; color: oklch(58% 0.02 50);
    font-style: italic; margin-bottom: 14px; flex: 1;
}
.tr-stats {
    display: flex; gap: 18px; margin-bottom: 16px;
}
.tr-stat {
    display: flex; flex-direction: column;
}
.tr-stat-num {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800; font-size: 1.4rem;
    color: oklch(22% 0.04 50); line-height: 1;
}
.tr-stat-lbl {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: .65rem; letter-spacing: .08em;
    text-transform: uppercase; color: oklch(55% 0.03 50);
}
.tr-stars { color: oklch(72% 0.14 88); font-size: .8rem; letter-spacing: -.04em; }
.tr-stars .dim { color: rgba(0,0,0,.18); }
.tr-view-btn {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    padding: 9px 0;
    background: oklch(58% 0.14 42); color: #fff;
    border-radius: 10px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .88rem;
    letter-spacing: .07em; text-transform: uppercase;
    text-decoration: none; transition: background .15s;
}
.tr-view-btn:hover { background: oklch(54% 0.14 42); color: #fff; }

/* ── Empty state ───────────────────────────────────────── */
.tr-empty {
    background: rgba(255,255,255,.55);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,.4);
    border-radius: 14px; padding: 60px 28px; text-align: center;
}
.tr-empty-h {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1.375rem;
    text-transform: uppercase; letter-spacing: .04em;
    color: oklch(38% 0.04 50); margin-bottom: 8px;
}
.tr-empty-s { font-size: .85rem; color: oklch(52% 0.03 50); }

/* ── Animations ────────────────────────────────────────── */
@keyframes riseUp    { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
@keyframes slideDown { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
.rise       { animation: riseUp    0.45s cubic-bezier(.2,0,.2,1) both; }
.slide-down { animation: slideDown 0.4s  cubic-bezier(.2,0,.2,1) both; }
</style>

<!-- Page heading -->
<div class="slide-down" style="animation-delay:0ms;margin-bottom:28px">
    <div class="tr-eyebrow">FitTrainer</div>
    <h1 class="tr-h1">Our Trainers</h1>
</div>

<?php if ($trainers): ?>
<div class="tr-grid">
<?php foreach ($trainers as $i => $t):
    $gradient = $avatarGradients[$i % count($avatarGradients)];
    $initials  = trainerInitials($t['first_name'], $t['last_name']);
?>
    <div class="tr-card rise" style="animation-delay:<?= 80 + $i * 70 ?>ms">
        <!-- Header row: avatar + name -->
        <div class="tr-card-top">
            <div class="tr-avatar" style="background:<?= $gradient ?>">
                <?= htmlspecialchars($initials) ?>
            </div>
            <div>
                <div class="tr-name">
                    <?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?>
                </div>
                <span class="tr-badge">Trainer</span>
            </div>
        </div>

        <!-- Bio snippet -->
        <?php if ($t['bio']): ?>
            <p class="tr-bio"><?= htmlspecialchars($t['bio']) ?></p>
        <?php else: ?>
            <p class="tr-no-bio">No bio yet.</p>
        <?php endif; ?>

        <!-- Stats row -->
        <div class="tr-stats">
            <div class="tr-stat">
                <span class="tr-stat-num"><?= (int)$t['workout_count'] ?></span>
                <span class="tr-stat-lbl">Programs</span>
            </div>
            <div class="tr-stat">
                <span class="tr-stat-num"><?= (int)$t['total_saves'] ?></span>
                <span class="tr-stat-lbl">Total Saves</span>
            </div>
            <?php if ($t['avg_rating']): ?>
            <div class="tr-stat">
                <span class="tr-stat-num"><?= $t['avg_rating'] ?></span>
                <span class="tr-stat-lbl"><?= (int)$t['review_count'] ?> Reviews</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- View profile button -->
        <a href="<?= BASE_URL ?>/pages/trainer/profile.php?id=<?= $t['id'] ?>"
           class="tr-view-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            View Profile
        </a>
    </div>
<?php endforeach; ?>
</div>

<?php else: ?>
<div class="tr-empty rise" style="animation-delay:100ms">
    <div class="tr-empty-h">No trainers yet</div>
    <div class="tr-empty-s">Approved trainer profiles will appear here.</div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
