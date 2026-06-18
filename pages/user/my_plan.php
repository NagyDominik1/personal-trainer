<?php
/**
 * User personal workout plan builder.
 * Users can create private plans from the exercise library,
 * organise them into days, and track progress — without publishing.
 */

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Workout.php';
require_once __DIR__ . '/../../classes/Coaching.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db       = Database::getInstance();
$workout  = new Workout();
$coaching = new Coaching();
$userId   = (int) $_SESSION['user_id'];

// The user's active coach, if any (used for the banner + plan badges).
$activeCoach = $coaching->getActiveForClient($userId);

/* ─────────────────────────────────────────────────────────
   POST handler — all state-changing actions
   ───────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_plan') {
        $title = trim($_POST['title'] ?? '');
        if ($title !== '') {
            $newId = $workout->create([
                'user_id'     => $userId,
                'category_id' => (int)($_POST['category_id'] ?? 0) ?: null,
                'title'       => $title,
                'description' => trim($_POST['description'] ?? ''),
                'difficulty'  => $_POST['difficulty'] ?? null,
            ]);
            // Personal plans are never published — redirect to edit view
            header('Location: ' . BASE_URL . '/pages/user/my_plan.php?id=' . $newId);
            exit;
        }
    }

    if ($action === 'delete_plan') {
        $pid = (int)($_POST['plan_id'] ?? 0);
        // Only delete if it belongs to this user
        $stmt = $db->prepare('DELETE FROM workouts WHERE id = :id AND user_id = :uid AND is_published = 0');
        $stmt->execute([':id' => $pid, ':uid' => $userId]);
        header('Location: ' . BASE_URL . '/pages/user/my_plan.php');
        exit;
    }

    if ($action === 'add_day') {
        $wid   = (int)($_POST['plan_id']  ?? 0);
        $title = trim($_POST['day_title'] ?? 'New Day');

        // Verify ownership
        $stmt = $db->prepare('SELECT id FROM workouts WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $wid, ':uid' => $userId]);
        if ($stmt->fetch()) {
            $dayCount = (int) $db->prepare('SELECT COUNT(*) FROM workout_days WHERE workout_id = :wid')
                ->execute([':wid' => $wid]) ? $db->query("SELECT COUNT(*) FROM workout_days WHERE workout_id = $wid")->fetchColumn() : 0;
            $workout->addDay($wid, $dayCount + 1, $title ?: 'Day ' . ($dayCount + 1));
        }
        header('Location: ' . BASE_URL . '/pages/user/my_plan.php?id=' . $wid);
        exit;
    }

    if ($action === 'delete_day') {
        $dayId = (int)($_POST['day_id']  ?? 0);
        $wid   = (int)($_POST['plan_id'] ?? 0);
        $workout->deleteDay($dayId, $userId);
        header('Location: ' . BASE_URL . '/pages/user/my_plan.php?id=' . $wid);
        exit;
    }

    // Fallback redirect
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

/* ─────────────────────────────────────────────────────────
   Route: detail view vs list view
   ───────────────────────────────────────────────────────── */
$planId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$plan   = null;
$days   = [];

if ($planId) {
    // Load the plan — must belong to this user and be unpublished
    $stmt = $db->prepare('
        SELECT w.*, c.name AS category_name
        FROM workouts w
        LEFT JOIN categories c ON w.category_id = c.id
        WHERE w.id = :id AND w.user_id = :uid AND w.is_published = 0
    ');
    $stmt->execute([':id' => $planId, ':uid' => $userId]);
    $plan = $stmt->fetch();

    if ($plan) {
        $days = $workout->getDays($plan['id']);
        foreach ($days as &$day) {
            $day['exercises'] = $workout->getDayExercises($day['id']);
        }
        unset($day);
    }
}

// List view: all personal plans for this user
$plans = [];
if (!$plan) {
    $stmt = $db->prepare('
        SELECT w.*, c.name AS category_name,
            (SELECT COUNT(*) FROM workout_days WHERE workout_id = w.id) AS day_count
        FROM workouts w
        LEFT JOIN categories c ON w.category_id = c.id
        WHERE w.user_id = :uid AND w.is_published = 0
        ORDER BY w.created_at DESC
    ');
    $stmt->execute([':uid' => $userId]);
    $plans = $stmt->fetchAll();
}

$categories = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();

/* ─────────────────────────────────────────────────────────
   Helpers
   ───────────────────────────────────────────────────────── */
if (!function_exists('mpDiffColor')) {
    function mpDiffColor(?string $d): string {
        return match($d) {
            'beginner'     => 'oklch(44% 0.12 152)',
            'intermediate' => 'oklch(52% 0.13 88)',
            'advanced'     => 'oklch(50% 0.15 20)',
            default        => 'oklch(44% 0.04 50)',
        };
    }
}

$pageTitle   = $plan ? htmlspecialchars($plan['title']) : 'My Plans';
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ── Shared animations ─────────────────────────────────── */
@keyframes riseUp    { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
@keyframes slideDown { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
@keyframes popIn     { from { opacity:0; transform:scale(0.94); } to { opacity:1; transform:scale(1); } }
.rise       { animation: riseUp    0.45s cubic-bezier(.2,0,.2,1) both; }
.slide-down { animation: slideDown 0.4s  cubic-bezier(.2,0,.2,1) both; }
.pop        { animation: popIn     0.4s  cubic-bezier(.2,0,.2,1) both; }

/* ── Page heading ──────────────────────────────────────── */
.mp-eyebrow {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .69rem;
    letter-spacing: .14em; text-transform: uppercase;
    color: rgba(255,255,255,.55); margin-bottom: 6px;
}
.mp-h1 {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 2.375rem;
    color: #fff; letter-spacing: .02em; line-height: 1;
    text-shadow: 0 1px 8px rgba(0,0,0,.3); margin: 0 0 24px;
}

/* ── Create plan card ──────────────────────────────────── */
.mp-create-card {
    background: rgba(255,255,255,.65);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,.5);
    border-radius: 16px; padding: 26px 28px;
    box-shadow: 0 4px 18px rgba(0,0,0,.1);
    margin-bottom: 28px;
}
.mp-create-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1rem;
    letter-spacing: .08em; text-transform: uppercase;
    color: oklch(22% 0.04 50); margin-bottom: 16px;
}
.mp-form-row { display: flex; gap: 10px; flex-wrap: wrap; }
.mp-input, .mp-select, .mp-textarea {
    padding: 9px 13px;
    background: rgba(255,255,255,.8);
    border: 1.5px solid rgba(0,0,0,.12);
    border-radius: 9px;
    font-family: 'Barlow', sans-serif; font-size: .875rem;
    color: oklch(18% 0.03 50); outline: none;
    transition: border-color .15s, background .15s;
}
.mp-input:focus, .mp-select:focus, .mp-textarea:focus {
    background: #fff;
    border-color: oklch(58% 0.14 42);
}
.mp-input { flex: 1; min-width: 180px; }
.mp-select { min-width: 130px; }
.mp-textarea { width: 100%; resize: vertical; min-height: 70px; margin-top: 8px; }
.mp-btn-create {
    padding: 9px 22px;
    background: oklch(58% 0.14 42); color: #fff;
    border: none; border-radius: 9px; cursor: pointer;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .88rem;
    letter-spacing: .07em; text-transform: uppercase;
    transition: background .15s; white-space: nowrap;
}
.mp-btn-create:hover { background: oklch(54% 0.14 42); }

/* ── Plan list ─────────────────────────────────────────── */
.mp-list-label {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .81rem;
    letter-spacing: .12em; text-transform: uppercase;
    color: rgba(255,255,255,.7); margin-bottom: 14px;
}
.mp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.mp-plan-card {
    background: rgba(255,255,255,.62);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,.48);
    border-radius: 16px; padding: 22px 22px 18px;
    box-shadow: 0 3px 14px rgba(0,0,0,.1);
    display: flex; flex-direction: column;
    transition: all .2s;
}
.mp-plan-card:hover {
    background: rgba(255,255,255,.82);
    box-shadow: 0 10px 30px rgba(0,0,0,.16);
    transform: translateY(-3px);
}
.mp-plan-cat {
    color: #fff; padding: 2px 9px; border-radius: 4px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .66rem;
    letter-spacing: .08em; text-transform: uppercase;
    display: inline-block; margin-bottom: 8px;
}
.mp-plan-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1.3rem;
    letter-spacing: .03em; text-transform: uppercase;
    color: oklch(16% 0.03 50); line-height: 1.1; margin-bottom: 8px;
}
.mp-plan-meta {
    display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px;
}
.mp-plan-pill {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: .72rem; font-weight: 600;
    letter-spacing: .06em; text-transform: uppercase;
    color: oklch(42% 0.04 50);
    background: rgba(0,0,0,.07); border-radius: 5px; padding: 2px 8px;
}
.mp-plan-actions { display: flex; gap: 8px; margin-top: auto; }
.mp-btn-edit {
    padding: 7px 16px; border-radius: 8px;
    background: oklch(58% 0.14 42); color: #fff;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .82rem;
    letter-spacing: .06em; text-transform: uppercase;
    text-decoration: none; transition: background .15s;
}
.mp-btn-edit:hover { background: oklch(54% 0.14 42); color: #fff; }
.mp-btn-del {
    padding: 7px 14px; border-radius: 8px; border: none; cursor: pointer;
    background: oklch(50% 0.15 20 / 0.1);
    color: oklch(44% 0.14 20);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .82rem;
    letter-spacing: .06em; text-transform: uppercase;
    transition: background .15s;
}
.mp-btn-del:hover { background: oklch(50% 0.15 20 / 0.2); }
.mp-btn-del.confirming { background: oklch(50% 0.15 20); color: #fff; }

/* ── Coach banner ──────────────────────────────────────── */
.mp-coach {
    display: flex; align-items: center; gap: 14px;
    background: rgba(255,255,255,.68);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,.5);
    border-radius: 14px; padding: 16px 20px; margin-bottom: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,.1);
}
.mp-coach-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg,oklch(58% 0.14 42),oklch(44% 0.11 36));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Barlow Condensed', sans-serif; font-weight: 800;
    font-size: 1.05rem; color: #fff; flex-shrink: 0;
    border: 2px solid rgba(255,255,255,.35);
}
.mp-coach-title { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.1rem;letter-spacing:.02em;color:oklch(16% 0.03 50);line-height:1.1 }
.mp-coach-sub { font-size:.82rem;color:oklch(48% 0.03 50) }
.mp-coach-sub a { color:oklch(52% 0.13 42);font-weight:600;text-decoration:none }
.mp-coach-pill {
    font-family:'Barlow Condensed',sans-serif;font-size:.72rem;font-weight:600;
    letter-spacing:.06em;text-transform:uppercase;
    color:oklch(40% 0.10 70);background:oklch(72% 0.14 88 / .25);
    border-radius:5px;padding:2px 8px;
}

/* ── Empty state ───────────────────────────────────────── */
.mp-empty {
    background: rgba(255,255,255,.55);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,.4);
    border-radius: 14px; padding: 50px 28px; text-align: center;
}
.mp-empty-h {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1.25rem;
    text-transform: uppercase; letter-spacing: .04em;
    color: oklch(38% 0.04 50); margin-bottom: 6px;
}
.mp-empty-s { font-size: .85rem; color: oklch(52% 0.03 50); }

/* ── Edit view ─────────────────────────────────────────── */
.mp-back {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px;
    background: rgba(255,255,255,.55);
    backdrop-filter: blur(10px);
    border: 1.5px solid rgba(255,255,255,.5); border-radius: 10px;
    color: oklch(28% 0.04 50);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .9rem;
    letter-spacing: .05em; text-transform: uppercase;
    text-decoration: none; transition: all .15s; margin-bottom: 20px;
}
.mp-back:hover { background: rgba(255,255,255,.82); color: oklch(18% 0.03 50); }

.mp-plan-header {
    background: rgba(255,255,255,.68);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,.5);
    border-radius: 16px; padding: 26px 30px;
    margin-bottom: 22px;
    box-shadow: 0 4px 24px rgba(0,0,0,.1);
}
.mp-plan-header-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 2rem;
    text-transform: uppercase; letter-spacing: .04em;
    color: oklch(16% 0.03 50); margin-bottom: 6px;
}
.mp-plan-header-sub {
    font-size: .85rem; color: oklch(48% 0.03 50);
}

.mp-day-card {
    background: rgba(255,255,255,.62);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,.48);
    border-radius: 14px; margin-bottom: 14px;
    box-shadow: 0 3px 12px rgba(0,0,0,.08); overflow: hidden;
}
.mp-day-hdr {
    padding: 13px 20px;
    background: rgba(255,255,255,.28);
    border-bottom: 1px solid rgba(0,0,0,.06);
    display: flex; align-items: center; justify-content: space-between;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1rem;
    text-transform: uppercase; letter-spacing: .04em;
    color: oklch(22% 0.04 50);
}
.mp-day-del {
    padding: 4px 12px; border-radius: 6px; border: none; cursor: pointer;
    background: oklch(50% 0.15 20 / 0.1); color: oklch(44% 0.14 20);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .7rem;
    letter-spacing: .06em; text-transform: uppercase;
    transition: background .15s;
}
.mp-day-del:hover { background: oklch(50% 0.15 20 / 0.2); }

.mp-ex-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 20px; border-top: 1px solid rgba(0,0,0,.05);
    font-size: .87rem; color: oklch(28% 0.04 50);
    background: rgba(255,255,255,.18);
}
.mp-ex-name { font-weight: 600; }
.mp-ex-dur  { font-size: .75rem; color: oklch(52% 0.03 50); margin-left: 8px; }
.mp-ex-remove {
    padding: 3px 10px; border-radius: 5px; border: none; cursor: pointer;
    background: transparent; color: oklch(52% 0.12 20);
    font-size: .75rem; font-weight: 700;
    font-family: 'Barlow Condensed', sans-serif;
    letter-spacing: .05em; text-transform: uppercase;
    transition: background .12s;
}
.mp-ex-remove:hover { background: oklch(50% 0.15 20 / 0.12); }
.mp-no-ex {
    padding: 12px 20px;
    font-size: .82rem; color: oklch(58% 0.02 50);
    font-style: italic;
    border-top: 1px solid rgba(0,0,0,.05);
}
.mp-add-ex-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 16px; margin: 10px 20px;
    background: rgba(255,255,255,.6);
    border: 1.5px dashed rgba(0,0,0,.18); border-radius: 8px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .78rem;
    letter-spacing: .07em; text-transform: uppercase;
    color: oklch(42% 0.04 50); cursor: pointer;
    transition: all .15s;
}
.mp-add-ex-btn:hover {
    background: rgba(255,255,255,.9);
    border-color: oklch(58% 0.14 42); color: oklch(48% 0.14 42);
}

.mp-add-day-card {
    background: rgba(255,255,255,.62);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px dashed rgba(255,255,255,.6);
    border-radius: 14px; padding: 20px 22px;
    margin-bottom: 14px;
}
.mp-add-day-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .81rem;
    letter-spacing: .1em; text-transform: uppercase;
    color: oklch(38% 0.04 50); margin-bottom: 12px;
}
.mp-add-day-row { display: flex; gap: 8px; }
.mp-add-day-input {
    flex: 1; padding: 8px 13px;
    background: rgba(255,255,255,.8);
    border: 1.5px solid rgba(0,0,0,.12); border-radius: 8px;
    font-family: 'Barlow', sans-serif; font-size: .875rem;
    color: oklch(18% 0.03 50); outline: none;
    transition: border-color .15s;
}
.mp-add-day-input:focus { border-color: oklch(58% 0.14 42); background: #fff; }
.mp-add-day-btn {
    padding: 8px 20px;
    background: oklch(58% 0.14 42); color: #fff;
    border: none; border-radius: 8px; cursor: pointer;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .85rem;
    letter-spacing: .07em; text-transform: uppercase;
    transition: background .15s; white-space: nowrap;
}
.mp-add-day-btn:hover { background: oklch(54% 0.14 42); }

/* ── Exercise picker modal ─────────────────────────────── */
.mp-picker-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); backdrop-filter: blur(4px);
    z-index: 1000; align-items: center; justify-content: center;
}
.mp-picker-overlay.open { display: flex; }
.mp-picker-modal {
    background: #fff; border-radius: 16px;
    width: min(480px, 94vw); max-height: 80vh;
    display: flex; flex-direction: column;
    box-shadow: 0 16px 60px rgba(0,0,0,.3);
    overflow: hidden;
}
.mp-picker-header {
    padding: 18px 22px 14px;
    border-bottom: 1px solid rgba(0,0,0,.08);
    display: flex; align-items: center; justify-content: space-between;
}
.mp-picker-heading {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1.1rem;
    letter-spacing: .04em; text-transform: uppercase;
    color: oklch(18% 0.03 50);
}
.mp-picker-close {
    background: none; border: none; cursor: pointer;
    font-size: 1.4rem; line-height: 1; color: oklch(50% 0.03 50);
    padding: 0 4px; transition: color .12s;
}
.mp-picker-close:hover { color: oklch(22% 0.04 50); }
.mp-picker-search {
    padding: 12px 22px; border-bottom: 1px solid rgba(0,0,0,.07);
}
.mp-picker-search input {
    width: 100%; padding: 8px 12px;
    background: oklch(97% 0.005 50);
    border: 1.5px solid rgba(0,0,0,.1); border-radius: 8px;
    font-family: 'Barlow', sans-serif; font-size: .875rem;
    color: oklch(18% 0.03 50); outline: none;
    transition: border-color .15s;
}
.mp-picker-search input:focus { border-color: oklch(58% 0.14 42); background: #fff; }
.mp-picker-list { overflow-y: auto; flex: 1; }
.mp-picker-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 11px 22px; cursor: pointer;
    border-bottom: 1px solid rgba(0,0,0,.05);
    transition: background .12s;
}
.mp-picker-item:hover { background: oklch(97% 0.01 42); }
.mp-picker-item-name {
    font-weight: 600; font-size: .9rem; color: oklch(18% 0.03 50);
}
.mp-picker-item-meta {
    font-size: .75rem; color: oklch(55% 0.03 50); margin-top: 2px;
}
.mp-picker-add-btn {
    padding: 5px 14px; border: none; border-radius: 6px; cursor: pointer;
    background: oklch(58% 0.14 42); color: #fff;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .78rem;
    letter-spacing: .06em; text-transform: uppercase;
    transition: background .12s; flex-shrink: 0;
}
.mp-picker-add-btn:hover { background: oklch(54% 0.14 42); }
.mp-picker-add-btn:disabled { background: oklch(70% 0.04 50); cursor: default; }
.mp-picker-empty {
    padding: 28px 22px; text-align: center;
    font-size: .875rem; color: oklch(52% 0.03 50); font-style: italic;
}
</style>

<?php if ($plan): ?>
<!-- ════════════════════════════════════════════════════
     DETAIL / EDIT VIEW
     ════════════════════════════════════════════════════ -->

<a href="<?= BASE_URL ?>/pages/user/my_plan.php" class="mp-back">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    My Plans
</a>

<div class="mp-plan-header slide-down" style="animation-delay:0ms">
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
        <?php if ($plan['category_name']): ?>
            <span style="background:oklch(52% 0.13 42)" class="mp-plan-cat">
                <?= htmlspecialchars($plan['category_name']) ?>
            </span>
        <?php endif; ?>
        <?php if ($plan['difficulty']): ?>
            <span style="background:<?= mpDiffColor($plan['difficulty']) ?>" class="mp-plan-cat">
                <?= ucfirst($plan['difficulty']) ?>
            </span>
        <?php endif; ?>
    </div>
    <div class="mp-plan-header-title"><?= htmlspecialchars($plan['title']) ?></div>
    <?php if ($plan['description']): ?>
        <div class="mp-plan-header-sub"><?= nl2br(htmlspecialchars($plan['description'])) ?></div>
    <?php endif; ?>
</div>

<!-- Days -->
<?php foreach ($days as $i => $day): ?>
<div class="mp-day-card rise" style="animation-delay:<?= 60 + $i * 60 ?>ms" id="day-<?= $day['id'] ?>">
    <div class="mp-day-hdr">
        <span>Day <?= $day['day_number'] ?>: <?= htmlspecialchars($day['title']) ?></span>
        <form method="POST" style="margin:0">
            <input type="hidden" name="action"   value="delete_day">
            <input type="hidden" name="day_id"   value="<?= $day['id'] ?>">
            <input type="hidden" name="plan_id"  value="<?= $plan['id'] ?>">
            <button type="button" class="mp-day-del" onclick="confirmDelDay(this)">Remove Day</button>
        </form>
    </div>

    <!-- Exercise list -->
    <div id="ex-list-<?= $day['id'] ?>">
    <?php if ($day['exercises']): ?>
        <?php foreach ($day['exercises'] as $ex): ?>
        <div class="mp-ex-row" id="ex-row-<?= $ex['assignment_id'] ?>">
            <span>
                <span class="mp-ex-name"><?= htmlspecialchars($ex['title']) ?></span>
                <?php if ($ex['duration_minutes']): ?>
                    <span class="mp-ex-dur"><?= $ex['duration_minutes'] ?> min</span>
                <?php endif; ?>
            </span>
            <button class="mp-ex-remove"
                    onclick="removeExercise(<?= $ex['assignment_id'] ?>)">Remove</button>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="mp-no-ex" id="no-ex-<?= $day['id'] ?>">No exercises yet — add some below.</div>
    <?php endif; ?>
    </div>

    <button class="mp-add-ex-btn" onclick="openPicker(<?= $day['id'] ?>)">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Exercise
    </button>
</div>
<?php endforeach; ?>

<!-- Add new day -->
<div class="mp-add-day-card rise" style="animation-delay:<?= 80 + count($days) * 60 ?>ms">
    <div class="mp-add-day-title">+ Add New Day</div>
    <form method="POST">
        <input type="hidden" name="action"  value="add_day">
        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
        <div class="mp-add-day-row">
            <input type="text" name="day_title" class="mp-add-day-input"
                   placeholder="Day title, e.g. Chest &amp; Arms…" maxlength="100">
            <button type="submit" class="mp-add-day-btn">Add Day</button>
        </div>
    </form>
</div>

<!-- ── Exercise Picker Modal ───────────────────────────── -->
<div class="mp-picker-overlay" id="pickerOverlay">
    <div class="mp-picker-modal">
        <div class="mp-picker-header">
            <span class="mp-picker-heading">Add Exercise</span>
            <button class="mp-picker-close" onclick="closePicker()">&times;</button>
        </div>
        <div class="mp-picker-search">
            <input type="text" id="pickerSearch" placeholder="Search exercises…"
                   oninput="filterPicker(this.value)">
        </div>
        <div class="mp-picker-list" id="pickerList">
            <div class="mp-picker-empty">Loading exercises…</div>
        </div>
    </div>
</div>

<script>
/* ── Exercise Picker ───────────────────────────────────── */

let allExercises = [];
let activeDayId  = null;

// Fetch all exercises once when picker first opens
async function loadExercises() {
    if (allExercises.length) return; // Already loaded
    const res  = await fetch('<?= BASE_URL ?>/api/plan_ajax.php?action=exercises');
    allExercises = await res.json();
}

function openPicker(dayId) {
    activeDayId = dayId;
    document.getElementById('pickerOverlay').classList.add('open');
    document.getElementById('pickerSearch').value = '';
    loadExercises().then(() => renderPicker(''));
    setTimeout(() => document.getElementById('pickerSearch').focus(), 80);
}

function closePicker() {
    document.getElementById('pickerOverlay').classList.remove('open');
    activeDayId = null;
}

// Close when clicking outside the modal
document.getElementById('pickerOverlay').addEventListener('click', function (e) {
    if (e.target === this) closePicker();
});

function filterPicker(q) {
    renderPicker(q.toLowerCase());
}

function renderPicker(q) {
    const list = document.getElementById('pickerList');
    const filtered = q
        ? allExercises.filter(e =>
            e.title.toLowerCase().includes(q) ||
            (e.category_name || '').toLowerCase().includes(q))
        : allExercises;

    if (!filtered.length) {
        list.innerHTML = '<div class="mp-picker-empty">No exercises found.</div>';
        return;
    }

    list.innerHTML = filtered.map(e => `
        <div class="mp-picker-item">
            <div>
                <div class="mp-picker-item-name">${escHtml(e.title)}</div>
                <div class="mp-picker-item-meta">
                    ${e.category_name ? escHtml(e.category_name) + ' · ' : ''}
                    ${e.duration_minutes ? e.duration_minutes + ' min' : ''}
                </div>
            </div>
            <button class="mp-picker-add-btn"
                    onclick="addExercise(${e.id}, '${escHtml(e.title)}', ${e.duration_minutes || 0})">
                Add
            </button>
        </div>
    `).join('');
}

function escHtml(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function addExercise(exerciseId, title, duration) {
    if (!activeDayId) return;

    const res  = await fetch('<?= BASE_URL ?>/api/plan_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add_exercise', day_id: activeDayId, exercise_id: exerciseId }),
    });
    const data = await res.json();

    if (!data.ok) { alert('Could not add exercise.'); return; }

    // Remove the "no exercises" placeholder if present
    const noEx = document.getElementById('no-ex-' + activeDayId);
    if (noEx) noEx.remove();

    // Append the new exercise row to the list
    const list = document.getElementById('ex-list-' + activeDayId);
    const ex   = data.exercise;
    const row  = document.createElement('div');
    row.className = 'mp-ex-row';
    row.id = 'ex-row-' + ex.assignment_id;
    row.innerHTML = `
        <span>
            <span class="mp-ex-name">${escHtml(ex.title)}</span>
            ${ex.duration_minutes ? `<span class="mp-ex-dur">${ex.duration_minutes} min</span>` : ''}
        </span>
        <button class="mp-ex-remove" onclick="removeExercise(${ex.assignment_id})">Remove</button>
    `;
    list.appendChild(row);

    closePicker();
}

async function removeExercise(assignmentId) {
    const row = document.getElementById('ex-row-' + assignmentId);
    if (!row) return;

    const res  = await fetch('<?= BASE_URL ?>/api/plan_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'remove_exercise', assignment_id: assignmentId }),
    });
    const data = await res.json();
    if (data.ok) row.remove();
}

function confirmDelDay(btn) {
    if (btn.dataset.confirmed === '1') { btn.closest('form').submit(); return; }
    btn.dataset.confirmed = '1';
    btn.textContent = 'Confirm?';
    setTimeout(() => {
        if (btn.dataset.confirmed === '1') {
            btn.dataset.confirmed = '';
            btn.textContent = 'Remove Day';
        }
    }, 3000);
}
</script>

<?php else: ?>
<!-- ════════════════════════════════════════════════════
     LIST VIEW
     ════════════════════════════════════════════════════ -->

<div class="slide-down" style="animation-delay:0ms;margin-bottom:24px">
    <div class="mp-eyebrow">Personal Training</div>
    <h1 class="mp-h1">My Plans</h1>
</div>

<?php if ($activeCoach): ?>
<div class="mp-coach slide-down" style="animation-delay:30ms">
    <div class="mp-coach-avatar"><?= htmlspecialchars(strtoupper(substr($activeCoach['first_name'],0,1) . substr($activeCoach['last_name'],0,1))) ?></div>
    <div style="flex:1;min-width:0">
        <div class="mp-coach-title">Coached by <?= htmlspecialchars($activeCoach['first_name'] . ' ' . $activeCoach['last_name']) ?></div>
        <div class="mp-coach-sub">
            Your coach can view and edit your plans below.
            <a href="<?= BASE_URL ?>/pages/trainer/profile.php?id=<?= (int)$activeCoach['trainer_id'] ?>">View profile</a>
        </div>
    </div>
</div>
<?php else: ?>
<div class="mp-coach slide-down" style="animation-delay:30ms">
    <div class="mp-coach-avatar" style="background:rgba(0,0,0,.12);color:oklch(40% 0.04 50)"><i class="bi bi-person-plus"></i></div>
    <div style="flex:1;min-width:0">
        <div class="mp-coach-title">No coach yet</div>
        <div class="mp-coach-sub">
            Hire a trainer to get expert help building your plans.
            <a href="<?= BASE_URL ?>/pages/trainers.php">Browse trainers</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Create new plan -->
<div class="mp-create-card pop" style="animation-delay:60ms">
    <div class="mp-create-title">Create a New Plan</div>
    <form method="POST">
        <input type="hidden" name="action" value="create_plan">
        <div class="mp-form-row">
            <input type="text" name="title" class="mp-input"
                   placeholder="Plan title, e.g. Summer Cut…" maxlength="120" required>
            <select name="category_id" class="mp-select">
                <option value="">No category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="difficulty" class="mp-select">
                <option value="">Any level</option>
                <option value="beginner">Beginner</option>
                <option value="intermediate">Intermediate</option>
                <option value="advanced">Advanced</option>
            </select>
            <button type="submit" class="mp-btn-create">Create</button>
        </div>
        <textarea name="description" class="mp-textarea"
                  placeholder="Optional description…" maxlength="1000"></textarea>
    </form>
</div>

<!-- Existing plans -->
<?php if ($plans): ?>
    <div class="mp-list-label"><?= count($plans) ?> plan<?= count($plans) !== 1 ? 's' : '' ?></div>
    <div class="mp-grid">
    <?php foreach ($plans as $i => $p): ?>
        <div class="mp-plan-card rise" style="animation-delay:<?= 120 + $i * 70 ?>ms">
            <?php if ($p['category_name']): ?>
                <span class="mp-plan-cat" style="background:oklch(52% 0.13 42)">
                    <?= htmlspecialchars($p['category_name']) ?>
                </span>
            <?php endif; ?>
            <div class="mp-plan-title"><?= htmlspecialchars($p['title']) ?></div>
            <div class="mp-plan-meta">
                <?php if (!empty($p['coach_id'])): ?>
                    <span class="mp-coach-pill">From coach</span>
                <?php endif; ?>
                <?php if ($p['difficulty']): ?>
                    <span class="mp-plan-pill" style="background:<?= mpDiffColor($p['difficulty']) ?>;color:#fff">
                        <?= ucfirst($p['difficulty']) ?>
                    </span>
                <?php endif; ?>
                <span class="mp-plan-pill">
                    <?= (int)$p['day_count'] ?> day<?= $p['day_count'] != 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="mp-plan-actions">
                <a href="?id=<?= $p['id'] ?>" class="mp-btn-edit">Edit Plan</a>
                <form method="POST">
                    <input type="hidden" name="action"  value="delete_plan">
                    <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                    <button type="button" class="mp-btn-del" onclick="confirmDelPlan(this)">Delete</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

<?php else: ?>
    <div class="mp-empty rise" style="animation-delay:120ms">
        <div class="mp-empty-h">No plans yet</div>
        <div class="mp-empty-s">Create your first personal plan above.</div>
    </div>
<?php endif; ?>

<script>
function confirmDelPlan(btn) {
    if (btn.dataset.confirmed === '1') { btn.closest('form').submit(); return; }
    btn.dataset.confirmed = '1';
    btn.textContent = 'Confirm Delete?';
    btn.classList.add('confirming');
    setTimeout(() => {
        if (btn.dataset.confirmed === '1') {
            btn.dataset.confirmed = '';
            btn.textContent = 'Delete';
            btn.classList.remove('confirming');
        }
    }, 3000);
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
