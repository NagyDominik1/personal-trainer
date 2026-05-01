<?php
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Workout.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db      = Database::getInstance();
$workout = new Workout();
$userId  = (int) $_SESSION['user_id'];

// Save / unsave
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wid    = (int) ($_POST['workout_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'save')   $workout->save($userId, $wid);
    if ($action === 'unsave') $workout->unsave($userId, $wid);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Detail view
$detail = null;
$days   = [];
$saved  = false;
if (isset($_GET['id'])) {
    $detail = $workout->getById((int)$_GET['id']);
    if ($detail) {
        $days = $workout->getDays($detail['id']);
        foreach ($days as &$day) {
            $day['exercises'] = $workout->getDayExercises($day['id']);
        }
        unset($day);
        $saved = $workout->isSaved($userId, $detail['id']);
    }
}

// List view — category filter + keyword search
$catFilter  = (int) ($_GET['cat'] ?? 0);
$search     = trim($_GET['search'] ?? '');
$categories = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$allWorkouts = $workout->getAll();
if ($catFilter) {
    $allWorkouts = array_filter($allWorkouts, fn($w) => $w['category_id'] == $catFilter);
}
if ($search !== '') {
    $needle      = mb_strtolower($search);
    $allWorkouts = array_filter($allWorkouts, function ($w) use ($needle) {
        $haystack = mb_strtolower(
            $w['title'] . ' ' .
            ($w['category_name'] ?? '') . ' ' .
            $w['first_name'] . ' ' . $w['last_name']
        );
        return str_contains($haystack, $needle);
    });
}

// Saved workout IDs for this user
$stmt = $db->prepare('SELECT workout_id FROM user_workouts WHERE user_id = :uid');
$stmt->execute([':uid' => $userId]);
$savedIds = array_column($stmt->fetchAll(), 'workout_id');

if (!function_exists('browseCatColor')) {
    function browseCatColor(string $name): string {
        $map = [
            'strength'    => 'oklch(52% 0.13 42)',
            'cardio'      => 'oklch(48% 0.13 25)',
            'flexibility' => 'oklch(44% 0.10 152)',
            'balance'     => 'oklch(50% 0.10 240)',
            'hiit'        => 'oklch(46% 0.13 15)',
            'recovery'    => 'oklch(50% 0.08 190)',
            'weight loss' => 'oklch(48% 0.11 320)',
            'conditioning'=> 'oklch(46% 0.13 15)',
            'full body'   => 'oklch(46% 0.09 55)',
        ];
        return $map[strtolower($name)] ?? 'oklch(44% 0.04 50)';
    }
}

$pageTitle   = 'Browse Workouts';
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ── Entrance animations ───────────────────────────────── */
@keyframes riseUp   { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
@keyframes slideDown{ from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
@keyframes popIn    { from { opacity:0; transform:scale(0.94); } to { opacity:1; transform:scale(1); } }
.rise       { animation: riseUp    0.45s cubic-bezier(.2,0,.2,1) both; }
.pop        { animation: popIn     0.4s  cubic-bezier(.2,0,.2,1) both; }
.slide-down { animation: slideDown 0.4s  cubic-bezier(.2,0,.2,1) both; }

/* ── Page heading ──────────────────────────────────────── */
.br-eyebrow {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .69rem;
    letter-spacing: .14em; text-transform: uppercase;
    color: rgba(255,255,255,.55); margin-bottom: 6px;
}
.br-h1 {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 2.375rem;
    color: #fff; letter-spacing: .02em; line-height: 1;
    text-shadow: 0 1px 8px rgba(0,0,0,.3); margin: 0 0 20px;
}

/* ── Search bar ────────────────────────────────────────── */
.br-search-wrap {
    display: flex; gap: 8px; max-width: 480px;
    margin-bottom: 18px;
}
.br-search-input {
    flex: 1; padding: 10px 16px;
    background: rgba(255,255,255,.75);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1.5px solid rgba(255,255,255,.55);
    border-radius: 10px; font-size: .9rem;
    font-family: 'Barlow', sans-serif;
    color: oklch(18% 0.03 50); outline: none;
    transition: border-color .15s, background .15s;
}
.br-search-input:focus {
    background: rgba(255,255,255,.9);
    border-color: oklch(58% 0.14 42);
}
.br-search-input::placeholder { color: oklch(52% 0.03 50); }
.br-btn {
    padding: 10px 20px;
    background: oklch(58% 0.14 42);
    color: #fff; border: none; border-radius: 10px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .94rem;
    letter-spacing: .06em; text-transform: uppercase;
    cursor: pointer; transition: background .15s;
    white-space: nowrap;
}
.br-btn:hover { background: oklch(54% 0.14 42); }
.br-btn-clear {
    padding: 10px 16px;
    background: rgba(255,255,255,.25);
    color: #fff; border: 1.5px solid rgba(255,255,255,.4);
    border-radius: 10px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .88rem;
    letter-spacing: .04em; text-transform: uppercase;
    cursor: pointer; text-decoration: none;
    transition: background .15s;
}
.br-btn-clear:hover { background: rgba(255,255,255,.35); color: #fff; }

/* ── Category pills ────────────────────────────────────── */
.br-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; }
.br-pill {
    padding: 5px 14px;
    border-radius: 20px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .78rem;
    letter-spacing: .07em; text-transform: uppercase;
    text-decoration: none; cursor: pointer;
    transition: all .15s;
}
.br-pill--inactive {
    background: rgba(255,255,255,.22);
    border: 1.5px solid rgba(255,255,255,.35);
    color: rgba(255,255,255,.85);
}
.br-pill--inactive:hover {
    background: rgba(255,255,255,.34);
    color: #fff;
}
.br-pill--active {
    background: oklch(58% 0.14 42);
    border: 1.5px solid oklch(58% 0.14 42);
    color: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,.18);
}

/* ── Result count ──────────────────────────────────────── */
.br-result-count {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: .78rem; letter-spacing: .1em;
    text-transform: uppercase;
    color: rgba(255,255,255,.55); margin-bottom: 16px;
}

/* ── Workout cards grid ────────────────────────────────── */
.br-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.br-card {
    background: rgba(255,255,255,.62);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,.48);
    border-radius: 16px;
    padding: 22px 22px 18px;
    box-shadow: 0 3px 14px rgba(0,0,0,.1);
    display: flex; flex-direction: column;
    transition: all .2s;
    position: relative; overflow: hidden;
}
.br-card:hover {
    background: rgba(255,255,255,.8);
    box-shadow: 0 10px 30px rgba(0,0,0,.16);
    transform: translateY(-3px);
}
.br-card-saved-ribbon {
    position: absolute; top: 14px; right: -24px;
    background: oklch(72% 0.14 88);
    color: oklch(22% 0.06 88);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .65rem;
    letter-spacing: .1em; text-transform: uppercase;
    padding: 3px 32px;
    transform: rotate(45deg);
}
.br-card-cat {
    color: #fff;
    padding: 2px 9px; border-radius: 4px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .66rem;
    letter-spacing: .08em; text-transform: uppercase;
    display: inline-block; margin-bottom: 10px;
}
.br-card-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1.3rem;
    letter-spacing: .03em; text-transform: uppercase;
    color: oklch(16% 0.03 50); line-height: 1.1;
    margin-bottom: 6px;
}
.br-card-trainer {
    font-size: .78rem; color: oklch(48% 0.03 50);
    margin-bottom: 14px;
}
.br-card-actions { display: flex; gap: 8px; margin-top: auto; }
.br-card-view {
    padding: 7px 16px;
    border: 1.5px solid rgba(0,0,0,.15);
    border-radius: 8px;
    background: rgba(255,255,255,.6);
    color: oklch(28% 0.04 50);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .82rem;
    letter-spacing: .06em; text-transform: uppercase;
    text-decoration: none; cursor: pointer;
    transition: all .15s;
}
.br-card-view:hover {
    background: rgba(255,255,255,.9);
    color: oklch(18% 0.03 50);
    border-color: rgba(0,0,0,.25);
}
.br-card-save {
    padding: 7px 16px;
    border-radius: 8px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .82rem;
    letter-spacing: .06em; text-transform: uppercase;
    cursor: pointer; transition: all .15s; border: none;
}
.br-card-save--save {
    background: oklch(58% 0.14 42);
    color: #fff;
}
.br-card-save--save:hover { background: oklch(54% 0.14 42); }
.br-card-save--unsave {
    background: oklch(72% 0.14 88);
    color: oklch(22% 0.06 88);
}
.br-card-save--unsave:hover { background: oklch(68% 0.13 88); }

/* ── Empty state ───────────────────────────────────────── */
.br-empty {
    background: rgba(255,255,255,.55);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,.4);
    border-radius: 14px;
    padding: 60px 28px; text-align: center;
}
.br-empty-h {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1.375rem;
    text-transform: uppercase; letter-spacing: .04em;
    color: oklch(38% 0.04 50); margin-bottom: 8px;
}
.br-empty-s { font-size: .85rem; color: oklch(52% 0.03 50); }

/* ── Detail view ───────────────────────────────────────── */
.det-header {
    background: rgba(255,255,255,.68);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,.5);
    border-radius: 16px; padding: 28px 30px;
    margin-bottom: 20px;
    box-shadow: 0 4px 24px rgba(0,0,0,.1);
}
.det-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 2rem;
    text-transform: uppercase; letter-spacing: .04em;
    color: oklch(16% 0.03 50); margin-bottom: 8px;
}
.det-trainer { font-size: .85rem; color: oklch(48% 0.03 50); margin-bottom: 12px; }
.det-desc { font-size: .9rem; color: oklch(35% 0.03 50); }
.det-day {
    background: rgba(255,255,255,.62);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,.48);
    border-radius: 14px; margin-bottom: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.08); overflow: hidden;
}
.det-day-hdr {
    padding: 13px 20px;
    border-bottom: 1px solid rgba(0,0,0,.06);
    background: rgba(255,255,255,.28);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1rem;
    text-transform: uppercase; letter-spacing: .04em;
    color: oklch(22% 0.04 50);
}
.det-back {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px;
    background: rgba(255,255,255,.55);
    backdrop-filter: blur(10px);
    border: 1.5px solid rgba(255,255,255,.5);
    border-radius: 10px; color: oklch(28% 0.04 50);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .9rem;
    letter-spacing: .05em; text-transform: uppercase;
    text-decoration: none; transition: all .15s; margin-bottom: 18px;
}
.det-back:hover { background: rgba(255,255,255,.8); color: oklch(18% 0.03 50); }
.det-save-btn {
    padding: 8px 20px; border: none; border-radius: 10px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .9rem;
    letter-spacing: .06em; text-transform: uppercase;
    cursor: pointer; transition: all .15s;
}
.det-save-btn--save { background: oklch(58% 0.14 42); color: #fff; }
.det-save-btn--save:hover { background: oklch(54% 0.14 42); }
.det-save-btn--unsave { background: oklch(72% 0.14 88); color: oklch(22% 0.06 88); }
.det-save-btn--unsave:hover { background: oklch(68% 0.13 88); }
</style>

<?php if ($detail): ?>
<!-- ════════════════════════════════════════════════════
     DETAIL VIEW
     ════════════════════════════════════════════════════ -->

<div class="d-flex align-items-center gap-3 mb-0">
    <a href="<?= BASE_URL ?>/pages/user/workouts.php" class="det-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back
    </a>
    <form method="POST">
        <input type="hidden" name="workout_id" value="<?= $detail['id'] ?>">
        <?php if ($saved): ?>
            <input type="hidden" name="action" value="unsave">
            <button class="det-save-btn det-save-btn--unsave">&#9733; Saved — Remove</button>
        <?php else: ?>
            <input type="hidden" name="action" value="save">
            <button class="det-save-btn det-save-btn--save">&#9733; Save Workout</button>
        <?php endif; ?>
    </form>
</div>

<div class="det-header">
    <?php if ($detail['category_name']): ?>
        <span class="br-card-cat" style="background:<?= browseCatColor($detail['category_name']) ?>">
            <?= htmlspecialchars($detail['category_name']) ?>
        </span>
    <?php endif; ?>
    <div class="det-title"><?= htmlspecialchars($detail['title']) ?></div>
    <div class="det-trainer">By <?= htmlspecialchars($detail['first_name'] . ' ' . $detail['last_name']) ?></div>
    <?php if ($detail['description']): ?>
        <div class="det-desc"><?= nl2br(htmlspecialchars($detail['description'])) ?></div>
    <?php endif; ?>
</div>

<?php if (!$days): ?>
    <p style="color:rgba(255,255,255,.7)">This workout has no days yet.</p>
<?php endif; ?>

<?php foreach ($days as $i => $day): ?>
<div class="det-day rise" style="animation-delay:<?= $i * 60 ?>ms">
    <div class="det-day-hdr">Day <?= $day['day_number'] ?>: <?= htmlspecialchars($day['title']) ?></div>
    <?php if ($day['exercises']): ?>
    <table class="table table-sm mb-0" style="background:transparent">
        <thead style="background:rgba(255,255,255,.2)">
            <tr><th>Exercise</th><th>Duration</th></tr>
        </thead>
        <tbody>
        <?php foreach ($day['exercises'] as $ex): ?>
            <tr>
                <td class="fw-semibold"><?= htmlspecialchars($ex['title']) ?></td>
                <td class="text-muted"><?= $ex['duration_minutes'] ? $ex['duration_minutes'] . ' min' : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div style="padding:14px 20px;color:oklch(52% 0.03 50);font-size:.85rem">No exercises on this day.</div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php else: ?>
<!-- ════════════════════════════════════════════════════
     LIST VIEW
     ════════════════════════════════════════════════════ -->

<!-- Page heading -->
<div class="slide-down" style="animation-delay:0ms;margin-bottom:24px">
    <div class="br-eyebrow">Programs</div>
    <h1 class="br-h1">Browse Workouts</h1>

    <!-- Search bar -->
    <form method="GET" action="">
        <?php if ($catFilter): ?>
            <input type="hidden" name="cat" value="<?= $catFilter ?>">
        <?php endif; ?>
        <div class="br-search-wrap">
            <input type="text" name="search" class="br-search-input"
                   placeholder="Search by title, trainer or category…"
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="br-btn">Search</button>
            <?php if ($search): ?>
                <a href="?<?= $catFilter ? 'cat=' . $catFilter : '' ?>" class="br-btn-clear">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Category pills -->
<div class="br-pills pop" style="animation-delay:100ms">
    <a href="?<?= $search ? 'search=' . urlencode($search) : '' ?>"
       class="br-pill <?= !$catFilter ? 'br-pill--active' : 'br-pill--inactive' ?>">All</a>
    <?php foreach ($categories as $cat): ?>
        <a href="?cat=<?= $cat['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
           class="br-pill <?= $catFilter == $cat['id'] ? 'br-pill--active' : 'br-pill--inactive' ?>">
            <?= htmlspecialchars($cat['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($search || $catFilter): ?>
<div class="br-result-count">
    <?= count($allWorkouts) ?> result<?= count($allWorkouts) !== 1 ? 's' : '' ?>
    <?= $search ? '— "' . htmlspecialchars($search) . '"' : '' ?>
</div>
<?php endif; ?>

<!-- Workout cards -->
<?php if ($allWorkouts): ?>
<div class="br-grid">
    <?php foreach (array_values($allWorkouts) as $i => $w): ?>
    <div class="br-card rise" style="animation-delay:<?= 180 + $i * 70 ?>ms">
        <?php if (in_array($w['id'], $savedIds)): ?>
            <div class="br-card-saved-ribbon">Saved</div>
        <?php endif; ?>

        <?php if ($w['category_name']): ?>
            <span class="br-card-cat" style="background:<?= browseCatColor($w['category_name']) ?>">
                <?= htmlspecialchars($w['category_name']) ?>
            </span>
        <?php endif; ?>

        <div class="br-card-title"><?= htmlspecialchars($w['title']) ?></div>
        <div class="br-card-trainer">by <?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?></div>

        <div class="br-card-actions">
            <a href="?id=<?= $w['id'] ?>" class="br-card-view">View</a>
            <form method="POST">
                <input type="hidden" name="workout_id" value="<?= $w['id'] ?>">
                <?php if (in_array($w['id'], $savedIds)): ?>
                    <input type="hidden" name="action" value="unsave">
                    <button class="br-card-save br-card-save--unsave">&#9733; Unsave</button>
                <?php else: ?>
                    <input type="hidden" name="action" value="save">
                    <button class="br-card-save br-card-save--save">&#9733; Save</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<div class="br-empty">
    <div class="br-empty-h">No workouts found</div>
    <div class="br-empty-s">
        <?= $search ? 'Try a different search term or clear the filter.' : 'No workout programs available yet.' ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
