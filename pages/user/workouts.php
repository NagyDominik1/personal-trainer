<?php
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Workout.php';
require_once __DIR__ . '/../../classes/Mailer.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db      = Database::getInstance();
$workout = new Workout();
$userId  = (int) $_SESSION['user_id'];

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wid    = (int) ($_POST['workout_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'save')   $workout->save($userId, $wid);
    if ($action === 'unsave') $workout->unsave($userId, $wid);

    if ($action === 'toggle_day') {
        $workout->toggleDayComplete($userId, $wid, (int)$_POST['day_id']);
    }

    if ($action === 'submit_review') {
        $rating  = max(1, min(5, (int)($_POST['rating'] ?? 0)));
        $comment = trim($_POST['comment'] ?? '');
        if ($rating) {
            $workout->submitReview($userId, $wid, $rating, $comment);

            // Notify the trainer who owns this workout
            $ownerStmt = $db->prepare('
                SELECT u.email, u.first_name, u.last_name, w.title
                FROM workouts w JOIN users u ON w.user_id = u.id
                WHERE w.id = :wid AND w.user_id != :uid
            ');
            $ownerStmt->execute([':wid' => $wid, ':uid' => $userId]);
            $owner = $ownerStmt->fetch();
            if ($owner) {
                Mailer::sendReviewReceived(
                    $owner['email'],
                    $owner['first_name'] . ' ' . $owner['last_name'],
                    $_SESSION['user_name'] ?? 'A user',
                    $owner['title'],
                    $rating,
                    $comment
                );
            }
        }
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Detail view
$detail        = null;
$days          = [];
$saved         = false;
$completedDays = [];
$reviews       = [];
$myReview      = false;
$myNote        = '';

if (isset($_GET['id'])) {
    $detail = $workout->getById((int)$_GET['id']);
    if ($detail) {
        $days = $workout->getDays($detail['id']);
        foreach ($days as &$day) {
            $day['exercises'] = $workout->getDayExercises($day['id']);
        }
        unset($day);
        $saved         = $workout->isSaved($userId, $detail['id']);
        $completedDays = $workout->getCompletedDays($userId, $detail['id']);
        $reviews       = $workout->getReviews($detail['id']);
        $myReview      = $workout->getUserReview($userId, $detail['id']);
        if ($saved) $myNote = $workout->getNote($userId, $detail['id']);
    }
}

// List view — category filter + difficulty filter + keyword search
$catFilter  = (int) ($_GET['cat'] ?? 0);
$diffFilter = $_GET['diff'] ?? '';
$search     = trim($_GET['search'] ?? '');
$categories = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$allWorkouts = $workout->getAll();
if ($catFilter) {
    $allWorkouts = array_filter($allWorkouts, fn($w) => $w['category_id'] == $catFilter);
}
if ($diffFilter && in_array($diffFilter, ['beginner','intermediate','advanced'])) {
    $allWorkouts = array_filter($allWorkouts, fn($w) => $w['difficulty'] === $diffFilter);
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

if (!function_exists('starsHtml')) {
    function starsHtml(float $avg): string {
        $out = '<span class="stars-inline">';
        for ($i = 1; $i <= 5; $i++) {
            $out .= $i <= round($avg) ? '★' : '<span class="dim">★</span>';
        }
        return $out . '</span>';
    }
}

if (!function_exists('browseDiffColor')) {
    function browseDiffColor(?string $d): string {
        return match($d) { 'beginner' => 'oklch(44% 0.12 152)', 'intermediate' => 'oklch(52% 0.13 88)', 'advanced' => 'oklch(50% 0.15 20)', default => '' };
    }
    function browseDiffLabel(?string $d): string {
        return match($d) { 'beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced', default => '' };
    }
    function youtubeEmbedId(string $url): ?string {
        preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m);
        return $m[1] ?? null;
    }
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
    margin-bottom: 10px;
}
.br-card-meta {
    display: flex; gap: 10px; flex-wrap: wrap;
    margin-bottom: 14px;
}
.br-card-meta-pill {
    display: flex; align-items: center; gap: 4px;
    font-family: 'Barlow Condensed', sans-serif;
    font-size: .72rem; font-weight: 600;
    letter-spacing: .06em; text-transform: uppercase;
    color: oklch(42% 0.04 50);
    background: rgba(0,0,0,.07);
    border-radius: 5px; padding: 2px 8px;
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

/* ── Exercise expand rows ──────────────────────────────── */
.ex-row { cursor: pointer; }
.ex-row:hover td { background: rgba(0,0,0,.03); }
.ex-row td { vertical-align: middle; }
.ex-toggle {
    display: inline-flex; align-items: center; justify-content: center;
    width: 20px; height: 20px; border-radius: 4px;
    background: rgba(0,0,0,.07); color: oklch(40% 0.04 50);
    font-size: .65rem; transition: transform .2s, background .15s;
    margin-right: 8px; flex-shrink: 0;
}
.ex-row-open .ex-toggle { transform: rotate(90deg); background: oklch(58% 0.14 42); color: #fff; }
.ex-detail-row { display: none; }
.ex-detail-row.open { display: table-row; }
.ex-detail-cell {
    padding: 0 20px 14px 48px !important;
    background: rgba(255,255,255,.3) !important;
    border-top: none !important;
}
.ex-desc { font-size: .83rem; color: oklch(38% 0.03 50); margin-bottom: 8px; line-height: 1.5; }
.ex-video-link {
    display: inline-flex; align-items: center; gap: 5px;
    font-family: 'Barlow Condensed', sans-serif; font-weight: 700;
    font-size: .78rem; letter-spacing: .05em; text-transform: uppercase;
    color: oklch(48% 0.14 25); text-decoration: none;
    padding: 4px 10px; border-radius: 6px;
    background: oklch(48% 0.14 25 / 0.1);
    border: 1px solid oklch(48% 0.14 25 / 0.25);
    transition: background .15s;
}
.ex-video-link:hover { background: oklch(48% 0.14 25 / 0.18); color: oklch(40% 0.14 25); }

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

/* ── Progress bar ──────────────────────────────────────── */
.det-progress {
    background: rgba(255,255,255,.62); backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,.48); border-radius: 14px;
    padding: 18px 24px; margin-bottom: 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,.08);
    display: flex; align-items: center; gap: 18px;
}
.det-progress-text {
    font-family: 'Barlow Condensed', sans-serif; font-weight: 700;
    font-size: .88rem; letter-spacing: .06em; text-transform: uppercase;
    color: oklch(28% 0.04 50); white-space: nowrap; flex-shrink: 0;
}
.det-progress-track {
    flex: 1; height: 8px; background: rgba(0,0,0,.1);
    border-radius: 4px; overflow: hidden;
}
.det-progress-fill {
    height: 100%; border-radius: 4px;
    background: linear-gradient(90deg, oklch(44% 0.12 152), oklch(52% 0.14 152));
    transition: width .4s cubic-bezier(.4,0,.2,1);
}
.det-progress-pct {
    font-family: 'Barlow Condensed', sans-serif; font-weight: 700;
    font-size: .88rem; color: oklch(44% 0.12 152); flex-shrink: 0;
}

/* ── Day complete toggle ───────────────────────────────── */
.det-day-hdr {
    display: flex; align-items: center; justify-content: space-between;
}
.det-day-hdr-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1rem;
    text-transform: uppercase; letter-spacing: .04em;
    color: oklch(22% 0.04 50);
}
.det-day-hdr-title.done { color: oklch(44% 0.12 152); text-decoration: line-through; text-decoration-color: oklch(44% 0.12 152 / 0.5); }
.det-complete-btn {
    width: 30px; height: 30px; border-radius: 50%; border: 2px solid rgba(0,0,0,.15);
    background: rgba(255,255,255,.6); cursor: pointer; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    color: transparent; font-size: .85rem; font-weight: 700;
    transition: all .18s;
}
.det-complete-btn:hover { border-color: oklch(44% 0.12 152); color: oklch(44% 0.12 152); background: oklch(44% 0.12 152 / 0.08); }
.det-complete-btn.done { background: oklch(44% 0.12 152); border-color: oklch(44% 0.12 152); color: #fff; }

/* ── Stars display ─────────────────────────────────────── */
.stars-inline { color: oklch(72% 0.14 88); font-size: .85rem; letter-spacing: -.04em; }
.stars-inline .dim { color: rgba(0,0,0,.18); }
.br-card-rating {
    font-size: .75rem; color: oklch(44% 0.03 50);
    display: flex; align-items: center; gap: 4px; margin-bottom: 10px;
}

/* ── Notes section ─────────────────────────────────────── */
.det-notes {
    background: rgba(255,255,255,.62); backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,.48); border-radius: 14px;
    padding: 22px 24px; margin-bottom: 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,.08);
}
.det-notes-label {
    font-family: 'Barlow Condensed', sans-serif; font-weight: 700;
    font-size: .81rem; letter-spacing: .1em; text-transform: uppercase;
    color: oklch(38% 0.04 50); margin-bottom: 10px;
    display: flex; align-items: center; justify-content: space-between;
}
.det-notes-saved {
    font-family: 'Barlow', sans-serif; font-weight: 400; font-size: .72rem;
    color: oklch(44% 0.12 152); letter-spacing: 0; text-transform: none;
    opacity: 0; transition: opacity .3s;
}
.det-notes-area {
    width: 100%; padding: 10px 14px; resize: vertical; min-height: 80px;
    background: rgba(255,255,255,.7); border: 1.5px solid rgba(0,0,0,.1);
    border-radius: 8px; font-family: 'Barlow', sans-serif; font-size: .875rem;
    color: oklch(18% 0.03 50); outline: none; transition: border-color .15s;
    box-sizing: border-box;
}
.det-notes-area:focus { border-color: oklch(58% 0.14 42); background: rgba(255,255,255,.9); }

/* ── Reviews section ───────────────────────────────────── */
.det-reviews {
    background: rgba(255,255,255,.62); backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,.48); border-radius: 14px;
    padding: 22px 24px; margin-bottom: 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,.08);
}
.det-reviews-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 18px;
}
.det-reviews-title {
    font-family: 'Barlow Condensed', sans-serif; font-weight: 700;
    font-size: .81rem; letter-spacing: .1em; text-transform: uppercase;
    color: oklch(38% 0.04 50);
}
.det-avg {
    display: flex; align-items: center; gap: 8px;
}
.det-avg-num {
    font-family: 'Barlow Condensed', sans-serif; font-weight: 700;
    font-size: 1.8rem; color: oklch(28% 0.04 50); line-height: 1;
}
.det-avg-sub { font-size: .75rem; color: oklch(52% 0.03 50); }

/* Star picker */
.star-picker { display: flex; gap: 4px; margin-bottom: 12px; }
.star-picker span {
    font-size: 1.6rem; cursor: pointer; color: rgba(0,0,0,.18);
    transition: color .1s; line-height: 1; user-select: none;
}
.star-picker span.lit { color: oklch(72% 0.14 88); }

.det-review-comment {
    width: 100%; padding: 9px 12px; resize: vertical; min-height: 70px;
    background: rgba(255,255,255,.7); border: 1.5px solid rgba(0,0,0,.1);
    border-radius: 8px; font-family: 'Barlow', sans-serif; font-size: .875rem;
    color: oklch(18% 0.03 50); outline: none; transition: border-color .15s;
    box-sizing: border-box; margin-bottom: 10px;
}
.det-review-comment:focus { border-color: oklch(58% 0.14 42); }
.det-review-submit {
    padding: 8px 22px; background: oklch(58% 0.14 42); color: #fff;
    border: none; border-radius: 8px; cursor: pointer;
    font-family: 'Barlow Condensed', sans-serif; font-weight: 700;
    font-size: .88rem; letter-spacing: .06em; text-transform: uppercase;
    transition: background .15s;
}
.det-review-submit:hover { background: oklch(54% 0.14 42); }

/* Review list item */
.det-review-item {
    padding: 14px 0; border-top: 1px solid rgba(0,0,0,.07);
}
.det-review-item:first-child { border-top: none; }
.det-review-meta {
    display: flex; align-items: center; gap: 10px; margin-bottom: 5px;
}
.det-review-name {
    font-family: 'Barlow Condensed', sans-serif; font-weight: 700;
    font-size: .9rem; letter-spacing: .03em; color: oklch(22% 0.04 50);
}
.det-review-date { font-size: .72rem; color: oklch(55% 0.03 50); }
.det-review-text { font-size: .85rem; color: oklch(35% 0.03 50); line-height: 1.5; }
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
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
        <?php if ($detail['category_name']): ?>
            <span class="br-card-cat" style="background:<?= browseCatColor($detail['category_name']) ?>">
                <?= htmlspecialchars($detail['category_name']) ?>
            </span>
        <?php endif; ?>
        <?php if ($detail['difficulty']): ?>
            <span class="br-card-cat" style="background:<?= browseDiffColor($detail['difficulty']) ?>">
                <?= browseDiffLabel($detail['difficulty']) ?>
            </span>
        <?php endif; ?>
    </div>
    <div class="det-title"><?= htmlspecialchars($detail['title']) ?></div>
    <div class="det-trainer">By <a href="<?= BASE_URL ?>/pages/trainer/profile.php?id=<?= $detail['user_id'] ?>" style="color:inherit;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px"><?= htmlspecialchars($detail['first_name'] . ' ' . $detail['last_name']) ?></a></div>
    <?php if ($detail['description']): ?>
        <div class="det-desc"><?= nl2br(htmlspecialchars($detail['description'])) ?></div>
    <?php endif; ?>
</div>

<?php
$completedCount = count($completedDays);
$totalDays      = count($days);
$progressPct    = $totalDays > 0 ? round(($completedCount / $totalDays) * 100) : 0;
?>

<?php if ($days): ?>
<div class="det-progress rise" style="animation-delay:60ms">
    <div class="det-progress-text"><?= $completedCount ?> / <?= $totalDays ?> days</div>
    <div class="det-progress-track">
        <div class="det-progress-fill" style="width:<?= $progressPct ?>%"></div>
    </div>
    <div class="det-progress-pct"><?= $progressPct ?>%</div>
</div>
<?php endif; ?>

<?php if (!$days): ?>
    <p style="color:rgba(255,255,255,.7)">This workout has no days yet.</p>
<?php endif; ?>

<?php foreach ($days as $i => $day):
    $isDone = in_array($day['id'], $completedDays);
?>
<div class="det-day rise" style="animation-delay:<?= 80 + $i * 60 ?>ms">
    <div class="det-day-hdr" style="padding:13px 20px;border-bottom:1px solid rgba(0,0,0,.06);background:rgba(255,255,255,.28)">
        <span class="det-day-hdr-title<?= $isDone ? ' done' : '' ?>">
            Day <?= $day['day_number'] ?>: <?= htmlspecialchars($day['title']) ?>
        </span>
        <form method="POST" style="margin:0">
            <input type="hidden" name="action"     value="toggle_day">
            <input type="hidden" name="workout_id" value="<?= $detail['id'] ?>">
            <input type="hidden" name="day_id"     value="<?= $day['id'] ?>">
            <button type="submit" class="det-complete-btn<?= $isDone ? ' done' : '' ?>" title="<?= $isDone ? 'Mark incomplete' : 'Mark complete' ?>">✓</button>
        </form>
    </div>
    <?php if ($day['exercises']): ?>
    <table class="table table-sm mb-0" style="background:transparent">
        <thead style="background:rgba(255,255,255,.2)">
            <tr><th>Exercise</th><th style="width:90px">Duration</th></tr>
        </thead>
        <tbody>
        <?php foreach ($day['exercises'] as $ex):
            $hasDetail = $ex['description'] || $ex['video_url'];
            $exId = 'ex-' . $ex['assignment_id'];
        ?>
            <tr class="ex-row<?= $hasDetail ? '' : ' no-expand' ?>" <?= $hasDetail ? "onclick=\"toggleEx('$exId')\" id=\"row-$exId\"" : '' ?>>
                <td>
                    <?php if ($hasDetail): ?>
                        <span class="ex-toggle" id="tog-<?= $exId ?>">&#9654;</span>
                    <?php endif; ?>
                    <span class="fw-semibold"><?= htmlspecialchars($ex['title']) ?></span>
                </td>
                <td style="color:oklch(50% 0.03 50)"><?= $ex['duration_minutes'] ? $ex['duration_minutes'] . ' min' : '—' ?></td>
            </tr>
            <?php if ($hasDetail): ?>
            <tr class="ex-detail-row" id="<?= $exId ?>">
                <td colspan="2" class="ex-detail-cell">
                    <?php if ($ex['description']): ?>
                        <div class="ex-desc"><?= nl2br(htmlspecialchars($ex['description'])) ?></div>
                    <?php endif; ?>
                    <?php if ($ex['video_url']):
                        $ytId = youtubeEmbedId($ex['video_url']);
                    ?>
                        <?php if ($ytId): ?>
                            <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:8px;margin-top:8px">
                                <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($ytId) ?>"
                                    style="position:absolute;top:0;left:0;width:100%;height:100%;border:0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen loading="lazy"></iframe>
                            </div>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($ex['video_url']) ?>" target="_blank" rel="noopener" class="ex-video-link">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                Watch Video
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div style="padding:14px 20px;color:oklch(52% 0.03 50);font-size:.85rem">No exercises on this day.</div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- ── My Notes (only if saved) ─────────────────────────── -->
<?php if ($saved): ?>
<div class="det-notes rise" style="animation-delay:<?= 80 + count($days) * 60 ?>ms">
    <div class="det-notes-label">
        My Notes
        <span class="det-notes-saved" id="notesSavedMsg">&#10003; Saved</span>
    </div>
    <textarea class="det-notes-area" id="notesArea"
        placeholder="Personal notes about this workout — only you can see these…"
        maxlength="2000"><?= htmlspecialchars($myNote) ?></textarea>
</div>
<?php endif; ?>

<!-- ── Reviews ──────────────────────────────────────────── -->
<div class="det-reviews rise" style="animation-delay:<?= 100 + count($days) * 60 ?>ms">
    <div class="det-reviews-header">
        <div class="det-reviews-title">Reviews</div>
        <?php if ($reviews): ?>
        <div class="det-avg">
            <?php
            $avgRating = round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1);
            ?>
            <div class="det-avg-num"><?= $avgRating ?></div>
            <div>
                <?= starsHtml($avgRating) ?>
                <div class="det-avg-sub"><?= count($reviews) ?> review<?= count($reviews) != 1 ? 's' : '' ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Review form -->
    <form method="POST" style="margin-bottom:<?= $reviews ? '20px' : '0' ?>;padding-bottom:<?= $reviews ? '20px' : '0' ?>;border-bottom:<?= $reviews ? '1px solid rgba(0,0,0,.07)' : 'none' ?>">
        <input type="hidden" name="action"     value="submit_review">
        <input type="hidden" name="workout_id" value="<?= $detail['id'] ?>">
        <input type="hidden" name="rating"     id="ratingInput" value="<?= $myReview ? $myReview['rating'] : '' ?>">
        <div style="font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.8rem;letter-spacing:.06em;text-transform:uppercase;color:oklch(38% 0.04 50);margin-bottom:8px">
            <?= $myReview ? 'Your Review' : 'Leave a Review' ?>
        </div>
        <div class="star-picker" id="starPicker">
            <?php for ($s = 1; $s <= 5; $s++): ?>
                <span data-val="<?= $s ?>" class="<?= ($myReview && $myReview['rating'] >= $s) ? 'lit' : '' ?>">★</span>
            <?php endfor; ?>
        </div>
        <textarea name="comment" class="det-review-comment"
            placeholder="Optional comment…"><?= htmlspecialchars($myReview['comment'] ?? '') ?></textarea>
        <button type="submit" class="det-review-submit" id="reviewSubmit" <?= !($myReview || true) ? 'disabled' : '' ?>>
            <?= $myReview ? 'Update Review' : 'Submit Review' ?>
        </button>
    </form>

    <!-- Review list -->
    <?php foreach ($reviews as $rev): ?>
    <div class="det-review-item">
        <div class="det-review-meta">
            <span class="det-review-name"><?= htmlspecialchars($rev['first_name']) ?></span>
            <?= starsHtml((float)$rev['rating']) ?>
            <span class="det-review-date"><?= date('d M Y', strtotime($rev['created_at'])) ?></span>
        </div>
        <?php if ($rev['comment']): ?>
            <div class="det-review-text"><?= nl2br(htmlspecialchars($rev['comment'])) ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (!$reviews): ?>
        <div style="font-size:.85rem;color:oklch(52% 0.03 50);font-style:italic">No reviews yet — be the first.</div>
    <?php endif; ?>
</div>

<script>
function toggleEx(id) {
    const detailRow = document.getElementById(id);
    const headerRow = document.getElementById('row-' + id);
    const open = detailRow.classList.toggle('open');
    if (headerRow) headerRow.classList.toggle('ex-row-open', open);
}

// Star picker
const picker = document.getElementById('starPicker');
const ratingInput = document.getElementById('ratingInput');
if (picker) {
    const stars = picker.querySelectorAll('span');
    stars.forEach(star => {
        star.addEventListener('mouseover', () => {
            const val = +star.dataset.val;
            stars.forEach(s => s.classList.toggle('lit', +s.dataset.val <= val));
        });
        star.addEventListener('mouseout', () => {
            const cur = +(ratingInput.value || 0);
            stars.forEach(s => s.classList.toggle('lit', +s.dataset.val <= cur));
        });
        star.addEventListener('click', () => {
            ratingInput.value = star.dataset.val;
            const val = +star.dataset.val;
            stars.forEach(s => s.classList.toggle('lit', +s.dataset.val <= val));
        });
    });
}

// Note auto-save on blur
const notesArea = document.getElementById('notesArea');
const notesSaved = document.getElementById('notesSavedMsg');
if (notesArea) {
    let saveTimer;
    notesArea.addEventListener('input', () => {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveNote, 1200);
    });
    notesArea.addEventListener('blur', saveNote);

    function saveNote() {
        clearTimeout(saveTimer);
        fetch('<?= BASE_URL ?>/api/save_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ workout_id: <?= $detail['id'] ?>, notes: notesArea.value })
        }).then(r => r.json()).then(d => {
            if (d.ok && notesSaved) {
                notesSaved.style.opacity = '1';
                setTimeout(() => notesSaved.style.opacity = '0', 2000);
            }
        });
    }
}
</script>

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
    <?php
    $baseQ = ($search ? 'search=' . urlencode($search) : '') . ($diffFilter ? ($search ? '&' : '') . 'diff=' . urlencode($diffFilter) : '');
    ?>
    <a href="?<?= $baseQ ?>"
       class="br-pill <?= !$catFilter ? 'br-pill--active' : 'br-pill--inactive' ?>">All</a>
    <?php foreach ($categories as $cat): ?>
        <a href="?cat=<?= $cat['id'] ?><?= $baseQ ? '&' . $baseQ : '' ?>"
           class="br-pill <?= $catFilter == $cat['id'] ? 'br-pill--active' : 'br-pill--inactive' ?>">
            <?= htmlspecialchars($cat['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Difficulty pills -->
<div class="br-pills pop" style="animation-delay:140ms;margin-top:-10px">
    <?php
    $catQ = ($catFilter ? 'cat=' . $catFilter : '') . ($search ? ($catFilter ? '&' : '') . 'search=' . urlencode($search) : '');
    ?>
    <a href="?<?= $catQ ?>"
       class="br-pill <?= !$diffFilter ? 'br-pill--active' : 'br-pill--inactive' ?>">Any Level</a>
    <?php foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $val => $label): ?>
        <a href="?diff=<?= $val ?><?= $catQ ? '&' . $catQ : '' ?>"
           class="br-pill <?= $diffFilter === $val ? 'br-pill--active' : 'br-pill--inactive' ?>"
           style="<?= $diffFilter === $val ? 'background:' . browseDiffColor($val) . ';border-color:' . browseDiffColor($val) : '' ?>">
            <?= $label ?>
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

        <?php if ($w['difficulty']): ?>
            <span class="br-card-cat" style="background:<?= browseDiffColor($w['difficulty']) ?>;margin-bottom:4px">
                <?= browseDiffLabel($w['difficulty']) ?>
            </span>
        <?php endif; ?>
        <div class="br-card-title"><?= htmlspecialchars($w['title']) ?></div>
        <div class="br-card-trainer">by <a href="<?= BASE_URL ?>/pages/trainer/profile.php?id=<?= $w['user_id'] ?>" style="color:inherit;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:2px"><?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?></a></div>

        <?php if (!empty($w['avg_rating'])): ?>
        <div class="br-card-rating">
            <?= starsHtml((float)$w['avg_rating']) ?>
            <span><?= $w['avg_rating'] ?> &middot; <?= $w['review_count'] ?> review<?= $w['review_count'] != 1 ? 's' : '' ?></span>
        </div>
        <?php endif; ?>

        <div class="br-card-meta">
            <?php if ($w['day_count'] > 0): ?>
            <span class="br-card-meta-pill">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?= $w['day_count'] ?> day<?= $w['day_count'] != 1 ? 's' : '' ?>
            </span>
            <?php endif; ?>
            <?php if ($w['exercise_count'] > 0): ?>
            <span class="br-card-meta-pill">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.5 6.5h11M6.5 17.5h11M3 9.5h18M3 14.5h18"/><rect x="1" y="8" width="4" height="8" rx="1"/><rect x="19" y="8" width="4" height="8" rx="1"/></svg>
                <?= $w['exercise_count'] ?> exercise<?= $w['exercise_count'] != 1 ? 's' : '' ?>
            </span>
            <?php endif; ?>
        </div>

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
