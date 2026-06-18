<?php
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireRole('trainer');

$db     = Database::getInstance();
$userId = (int)$_SESSION['user_id'];

// Per-workout stats, ordered by saves descending
$wStmt = $db->prepare('
    SELECT w.id, w.title, w.is_published, w.created_at, w.difficulty,
        c.name AS category_name,
        (SELECT COUNT(*) FROM user_workouts WHERE workout_id = w.id)                AS save_count,
        (SELECT ROUND(AVG(rating),1) FROM workout_reviews WHERE workout_id = w.id)  AS avg_rating,
        (SELECT COUNT(*) FROM workout_reviews WHERE workout_id = w.id)              AS review_count
    FROM workouts w
    LEFT JOIN categories c ON w.category_id = c.id
    WHERE w.user_id = :uid
    ORDER BY save_count DESC, w.created_at DESC
');
$wStmt->execute([':uid' => $userId]);
$wkStats = $wStmt->fetchAll();

$totalSaves    = array_sum(array_column($wkStats, 'save_count'));
$totalReviews  = array_sum(array_column($wkStats, 'review_count'));
$allRatings    = array_filter(array_column($wkStats, 'avg_rating'));
$overallRating = $allRatings ? round(array_sum($allRatings) / count($allRatings), 1) : null;

// Recent reviews across all trainer workouts
$rStmt = $db->prepare('
    SELECT r.rating, r.comment, r.created_at,
           w.title AS workout_title, w.id AS workout_id,
           u.first_name, u.last_name
    FROM workout_reviews r
    JOIN workouts w ON r.workout_id = w.id
    JOIN users u ON r.user_id = u.id
    WHERE w.user_id = :uid
    ORDER BY r.created_at DESC
    LIMIT 30
');
$rStmt->execute([':uid' => $userId]);
$reviews = $rStmt->fetchAll();

if (!function_exists('anStars')) {
    function anStars(float $avg): string {
        $s = '<span style="letter-spacing:-.04em;font-size:.95rem">';
        for ($i = 1; $i <= 5; $i++)
            $s .= '<span style="color:' . ($i <= round($avg) ? 'oklch(72% 0.14 88)' : 'rgba(0,0,0,.15)') . '">★</span>';
        return $s . '</span>';
    }
    function anCatColor(?string $n): string {
        $m = ['strength'=>'oklch(52% 0.13 42)','cardio'=>'oklch(48% 0.13 25)','flexibility'=>'oklch(44% 0.10 152)','balance'=>'oklch(50% 0.10 240)','hiit'=>'oklch(46% 0.13 15)','recovery'=>'oklch(50% 0.08 190)','weight loss'=>'oklch(48% 0.11 320)','conditioning'=>'oklch(46% 0.13 15)','full body'=>'oklch(46% 0.09 55)'];
        return $m[strtolower($n ?? '')] ?? 'oklch(44% 0.04 50)';
    }
}

$pageTitle   = 'Analytics';
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
@keyframes riseUp    { from { opacity:0;transform:translateY(20px) } to { opacity:1;transform:none } }
@keyframes popIn     { from { opacity:0;transform:scale(0.94) }       to { opacity:1;transform:scale(1) } }
@keyframes slideDown { from { opacity:0;transform:translateY(-12px) } to { opacity:1;transform:none } }
.rise       { animation: riseUp    0.45s cubic-bezier(.2,0,.2,1) both }
.pop        { animation: popIn     0.4s  cubic-bezier(.2,0,.2,1) both }
.slide-down { animation: slideDown 0.4s  cubic-bezier(.2,0,.2,1) both }

:root { --accent:oklch(58% 0.14 42); --accent-dark:oklch(44% 0.11 36); }

/* ── Top grid: heading + 3 stat cards ─────────────────── */
.an-top {
    display: grid;
    grid-template-columns: 1.4fr 1fr 1fr 1fr;
    gap: 24px;
    margin-bottom: 36px;
    align-items: start;
}
@media (max-width:1060px) { .an-top { grid-template-columns:1fr 1fr; } .an-heading { grid-column:1/-1; } }
@media (max-width:600px)  { .an-top { grid-template-columns:1fr; } }

.an-eyebrow { font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.69rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.55);margin-bottom:6px }
.an-h1      { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:2.375rem;color:#fff;letter-spacing:.02em;line-height:1;text-shadow:0 1px 8px rgba(0,0,0,.3);margin:0 0 8px }
.an-sub     { color:rgba(255,255,255,.55);font-size:.8rem;margin:0 }

/* ── Stat cards ────────────────────────────────────────── */
.stat-card { border-radius:14px;padding:26px 26px 22px;position:relative;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.14);transition:box-shadow .2s,transform .2s }
.stat-card:hover { box-shadow:0 12px 32px rgba(0,0,0,.22);transform:translateY(-2px) }
.stat-card::after { content:'';position:absolute;right:-16px;bottom:-16px;width:90px;height:90px;border-radius:50%;background:rgba(255,255,255,.07);pointer-events:none }
.stat-card--a { background:linear-gradient(135deg,oklch(52% 0.13 42),oklch(44% 0.11 36)) }
.stat-card--b { background:linear-gradient(135deg,oklch(48% 0.10 240),oklch(40% 0.08 240)) }
.stat-card--c { background:linear-gradient(135deg,oklch(38% 0.09 152),oklch(30% 0.07 155)) }
.stat-card-label { font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.66rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.65);margin-bottom:14px }
.stat-card-value { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:3.25rem;line-height:1;color:#fff;margin-bottom:8px;letter-spacing:-.02em }
.stat-card-sub   { font-size:.8rem;color:rgba(255,255,255,.65) }

/* ── Section label ─────────────────────────────────────── */
.an-label { font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.69rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.6);margin-bottom:14px }

/* ── Performance rows ──────────────────────────────────── */
.an-row {
    display:flex;align-items:center;gap:14px;flex-wrap:wrap;
    padding:18px 24px;
    background:rgba(255,255,255,.58);
    backdrop-filter:blur(18px);
    -webkit-backdrop-filter:blur(18px);
    border:1px solid rgba(255,255,255,.45);
    border-radius:14px;
    box-shadow:0 3px 12px rgba(0,0,0,.08);
    transition:all .18s;
    margin-bottom:10px;
}
.an-row:hover { background:rgba(255,255,255,.78);box-shadow:0 10px 30px rgba(0,0,0,.15);transform:translateX(4px) }
.an-row-body { flex:1;min-width:180px }
.an-row-title  { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.35rem;letter-spacing:.03em;text-transform:uppercase;color:oklch(16% 0.03 50);line-height:1;margin-bottom:5px }
.an-row-badges { display:flex;gap:5px;flex-wrap:wrap }
.an-badge { color:#fff;padding:2px 8px;border-radius:4px;font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.63rem;letter-spacing:.08em;text-transform:uppercase }
.an-status { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.66rem;letter-spacing:.1em;text-transform:uppercase;padding:3px 9px;border-radius:5px;flex-shrink:0;white-space:nowrap }
.an-status--pub   { background:oklch(44% 0.12 152 / 0.12);color:oklch(38% 0.10 152);border:1px solid oklch(44% 0.12 152 / 0.3) }
.an-status--draft { background:rgba(0,0,0,.06);color:oklch(50% 0.03 50);border:1px solid rgba(0,0,0,.1) }
.an-stat-pill { display:flex;align-items:center;gap:5px;font-family:'Barlow Condensed',sans-serif;font-size:.8rem;font-weight:700;letter-spacing:.04em;color:oklch(28% 0.04 50);background:rgba(0,0,0,.06);border-radius:7px;padding:5px 11px;white-space:nowrap;flex-shrink:0 }
.an-manage-link { display:flex;align-items:center;gap:4px;padding:5px 12px;border:1.5px solid rgba(139,90,43,.4);border-radius:7px;background:rgba(139,90,43,.07);color:var(--accent);font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.75rem;letter-spacing:.06em;text-transform:uppercase;text-decoration:none;white-space:nowrap;transition:all .15s;flex-shrink:0 }
.an-manage-link:hover { background:rgba(139,90,43,.16);color:var(--accent) }

/* ── Review cards ──────────────────────────────────────── */
.an-review-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px }
.an-rev-card {
    background:rgba(255,255,255,.62);
    backdrop-filter:blur(18px);
    -webkit-backdrop-filter:blur(18px);
    border:1px solid rgba(255,255,255,.48);
    border-radius:14px;
    padding:20px 22px;
    box-shadow:0 3px 14px rgba(0,0,0,.09);
}
.an-rev-top     { display:flex;align-items:center;justify-content:space-between;margin-bottom:10px }
.an-rev-date    { font-size:.72rem;color:oklch(56% 0.03 50) }
.an-rev-comment { font-size:.86rem;color:oklch(26% 0.04 50);line-height:1.55;margin-bottom:10px }
.an-rev-foot    { font-size:.75rem;color:oklch(50% 0.03 50) }
.an-rev-workout { color:var(--accent);font-weight:600;text-decoration:none }
.an-rev-workout:hover { text-decoration:underline }

/* ── Empty ─────────────────────────────────────────────── */
.an-empty { background:rgba(255,255,255,.55);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.4);border-radius:14px;padding:48px 28px;text-align:center;font-family:'Barlow Condensed',sans-serif;font-size:.95rem;text-transform:uppercase;letter-spacing:.04em;color:oklch(52% 0.03 50) }
</style>

<!-- ── Top: heading + 3 stat cards ──────────────────────── -->
<div class="an-top">

    <div class="an-heading slide-down" style="animation-delay:0ms">
        <div class="an-eyebrow">Trainer Dashboard</div>
        <h1 class="an-h1">Analytics</h1>
        <p class="an-sub">Saves, ratings and reviews across your programs.</p>
    </div>

    <div class="stat-card stat-card--a pop" style="animation-delay:80ms">
        <div class="stat-card-label">Total Saves</div>
        <div class="stat-card-value" data-count="<?= $totalSaves ?>">0</div>
        <div class="stat-card-sub">across <?= count($wkStats) ?> program<?= count($wkStats) != 1 ? 's' : '' ?></div>
    </div>

    <div class="stat-card stat-card--b pop" style="animation-delay:160ms">
        <div class="stat-card-label">Avg Rating</div>
        <div class="stat-card-value"><?= $overallRating ?? '—' ?></div>
        <div class="stat-card-sub"><?= $overallRating ? 'out of 5' : 'no ratings yet' ?></div>
    </div>

    <div class="stat-card stat-card--c pop" style="animation-delay:240ms">
        <div class="stat-card-label">Total Reviews</div>
        <div class="stat-card-value" data-count="<?= $totalReviews ?>">0</div>
        <div class="stat-card-sub">user feedback received</div>
    </div>

</div>

<!-- ── Program Performance ───────────────────────────────── -->
<div class="an-label">Program Performance</div>

<?php if ($wkStats): ?>
    <?php foreach ($wkStats as $i => $w): ?>
    <div class="an-row rise" style="animation-delay:<?= 320 + $i * 60 ?>ms">

        <div class="an-row-body">
            <div class="an-row-title"><?= htmlspecialchars($w['title']) ?></div>
            <div class="an-row-badges">
                <?php if ($w['category_name']): ?>
                    <span class="an-badge" style="background:<?= anCatColor($w['category_name']) ?>"><?= htmlspecialchars($w['category_name']) ?></span>
                <?php endif; ?>
                <?php if ($w['difficulty']): ?>
                    <span class="an-badge" style="background:<?= match($w['difficulty']) { 'beginner'=>'oklch(44% 0.12 152)', 'intermediate'=>'oklch(52% 0.13 88)', 'advanced'=>'oklch(50% 0.15 20)', default=>'oklch(44% 0.04 50)' } ?>"><?= ucfirst($w['difficulty']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <span class="an-status <?= $w['is_published'] ? 'an-status--pub' : 'an-status--draft' ?>">
            <?= $w['is_published'] ? '✓ Published' : 'Draft' ?>
        </span>

        <div class="an-stat-pill">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg>
            <?= $w['save_count'] ?> saved
        </div>

        <?php if ($w['avg_rating']): ?>
        <div class="an-stat-pill" style="background:oklch(72% 0.14 88 / 0.12);color:oklch(40% 0.10 88)">
            <?= anStars((float)$w['avg_rating']) ?>
            <span style="margin-left:2px"><?= $w['avg_rating'] ?></span>
        </div>
        <?php endif; ?>

        <?php if ($w['review_count'] > 0): ?>
        <div class="an-stat-pill">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            <?= $w['review_count'] ?> review<?= $w['review_count'] != 1 ? 's' : '' ?>
        </div>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>/pages/trainer/workouts.php?id=<?= $w['id'] ?>" class="an-manage-link">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            Manage
        </a>

    </div>
    <?php endforeach; ?>

<?php else: ?>
<div class="an-empty rise" style="animation-delay:320ms">No programs yet</div>
<?php endif; ?>

<!-- ── Recent Reviews ────────────────────────────────────── -->
<?php if ($reviews): ?>
<div class="an-label" style="margin-top:36px">
    Recent Reviews &mdash; <?= count($reviews) ?> most recent
</div>
<div class="an-review-grid">
    <?php foreach ($reviews as $i => $r): ?>
    <div class="an-rev-card rise" style="animation-delay:<?= 400 + $i * 40 ?>ms">
        <div class="an-rev-top">
            <?= anStars((float)$r['rating']) ?>
            <span class="an-rev-date"><?= date('j M Y', strtotime($r['created_at'])) ?></span>
        </div>
        <?php if (trim($r['comment'])): ?>
            <p class="an-rev-comment">&ldquo;<?= nl2br(htmlspecialchars($r['comment'])) ?>&rdquo;</p>
        <?php else: ?>
            <p class="an-rev-comment" style="color:oklch(62% 0.02 50);font-style:italic">No written comment.</p>
        <?php endif; ?>
        <div class="an-rev-foot">
            <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>
            &nbsp;&middot;&nbsp;
            <a href="<?= BASE_URL ?>/pages/trainer/workouts.php?id=<?= $r['workout_id'] ?>" class="an-rev-workout">
                <?= htmlspecialchars($r['workout_title']) ?>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php elseif ($wkStats): ?>
<div class="an-label" style="margin-top:36px">Recent Reviews</div>
<div class="an-empty rise" style="animation-delay:380ms">No reviews yet &mdash; publish your programs to start collecting feedback</div>
<?php endif; ?>

<script>
document.querySelectorAll('.stat-card-value[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count) || 0;
    if (!target) { el.textContent = '0'; return; }
    let cur = 0;
    const step = Math.ceil(target / 40);
    const timer = setInterval(() => {
        cur = Math.min(cur + step, target);
        el.textContent = cur;
        if (cur >= target) clearInterval(timer);
    }, 16);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
