<?php
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Exercise.php';
require_once __DIR__ . '/../../classes/Workout.php';
require_once __DIR__ . '/../../classes/Mailer.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireRole('trainer');

$db      = Database::getInstance();
$workout = new Workout();
$exClass = new Exercise();
$userId  = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $id = $workout->create([
            'user_id'     => $userId,
            'category_id' => $_POST['category_id'],
            'title'       => trim($_POST['title']),
            'description' => trim($_POST['description']),
            'difficulty'  => $_POST['difficulty'] ?: null,
        ]);
        if ($id) {
            header('Location: ' . BASE_URL . '/pages/trainer/workouts.php?id=' . $id);
            exit;
        }

    } elseif ($action === 'delete_workout') {
        $workout->delete((int)$_POST['workout_id'], $userId);
        header('Location: ' . BASE_URL . '/pages/trainer/workouts.php');
        exit;

    } elseif ($action === 'add_day') {
        $wid  = (int)$_POST['workout_id'];
        $days = $workout->getDays($wid);
        $num  = count($days) + 1;
        $workout->addDay($wid, $num, trim($_POST['day_title']));
        header('Location: ' . BASE_URL . '/pages/trainer/workouts.php?id=' . $wid);
        exit;

    } elseif ($action === 'delete_day') {
        $wid = (int)$_POST['workout_id'];
        $workout->deleteDay((int)$_POST['day_id'], $userId);
        header('Location: ' . BASE_URL . '/pages/trainer/workouts.php?id=' . $wid);
        exit;

    } elseif ($action === 'add_exercise') {
        $wid = (int)$_POST['workout_id'];
        $workout->addExerciseToDay((int)$_POST['day_id'], (int)$_POST['exercise_id']);
        header('Location: ' . BASE_URL . '/pages/trainer/workouts.php?id=' . $wid);
        exit;

    } elseif ($action === 'remove_exercise') {
        $wid = (int)$_POST['workout_id'];
        $workout->removeExerciseFromDay((int)$_POST['assignment_id']);
        header('Location: ' . BASE_URL . '/pages/trainer/workouts.php?id=' . $wid);
        exit;

    } elseif ($action === 'toggle_publish') {
        $wid     = (int)$_POST['workout_id'];
        $current = $workout->getById($wid);
        $workout->togglePublish($wid, $userId);

        // Only notify when going from draft → published
        if ($current && $current['user_id'] === $userId && !$current['is_published']) {
            $trainerName = $_SESSION['user_name'] ?? 'Your trainer';
            $interested  = $db->prepare('
                SELECT DISTINCT u.email, u.first_name, u.last_name
                FROM user_workouts uw
                JOIN workouts w ON uw.workout_id = w.id
                JOIN users u ON uw.user_id = u.id
                WHERE w.user_id = :tid AND uw.user_id != :tid
            ');
            $interested->execute([':tid' => $userId]);
            foreach ($interested->fetchAll() as $u) {
                Mailer::sendNewWorkout(
                    $u['email'],
                    $u['first_name'] . ' ' . $u['last_name'],
                    $trainerName,
                    $current['title'],
                    $wid
                );
            }
        }
        header('Location: ' . BASE_URL . '/pages/trainer/workouts.php');
        exit;

    } elseif ($action === 'edit_workout') {
        $wid = (int)$_POST['workout_id'];
        $workout->update($wid, [
            'user_id'     => $userId,
            'category_id' => $_POST['category_id'] ?: null,
            'title'       => trim($_POST['title']),
            'description' => trim($_POST['description']),
            'difficulty'  => $_POST['difficulty'] ?: null,
        ]);
        header('Location: ' . BASE_URL . '/pages/trainer/workouts.php?id=' . $wid);
        exit;

    } elseif ($action === 'edit_day') {
        $wid = (int)$_POST['workout_id'];
        $workout->updateDay((int)$_POST['day_id'], trim($_POST['day_title']), $userId);
        header('Location: ' . BASE_URL . '/pages/trainer/workouts.php?id=' . $wid);
        exit;
    }
}

$categories  = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$myExercises = $exClass->getAllByTrainer($userId);

$detail = null;
$days   = [];
if (isset($_GET['id'])) {
    $detail = $workout->getById((int)$_GET['id']);
    if (!$detail || $detail['user_id'] !== $userId) $detail = null;
    if ($detail) {
        $days = $workout->getDays($detail['id']);
        foreach ($days as &$day) {
            $day['exercises'] = $workout->getDayExercises($day['id']);
        }
        unset($day);
    }
}

$workouts    = $workout->getAllByTrainer($userId);
$pageTitle   = 'My Workouts';
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';

// Stats for the cards
$totalPrograms = count($workouts);
$totalPublished = count(array_filter($workouts, fn($w) => $w['is_published']));
$totalDraft     = $totalPrograms - $totalPublished;

if (!function_exists('difficultyLabel')) {
    function difficultyLabel(?string $d): string {
        return match($d) { 'beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced', default => '' };
    }
    function difficultyColor(?string $d): string {
        return match($d) { 'beginner' => 'oklch(44% 0.12 152)', 'intermediate' => 'oklch(52% 0.13 88)', 'advanced' => 'oklch(50% 0.15 20)', default => '' };
    }
}

if (!function_exists('wkCatColor')) {
    function wkCatColor(string $name): string {
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

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ── Entrance animations ───────────────────────────────── */
@keyframes slideIn  { from { transform: translateX(100%); } to { transform: translateX(0); } }
@keyframes fadeIn   { from { opacity: 0; } to { opacity: 1; } }
@keyframes riseUp   { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes slideDown{ from { opacity: 0; transform: translateY(-12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes popIn    { from { opacity: 0; transform: scale(0.94); } to { opacity: 1; transform: scale(1); } }

.rise       { animation: riseUp    0.45s cubic-bezier(.2,0,.2,1) both; }
.pop        { animation: popIn     0.4s  cubic-bezier(.2,0,.2,1) both; }
.slide-down { animation: slideDown 0.4s  cubic-bezier(.2,0,.2,1) both; }

/* ── Shared tokens ─────────────────────────────────────── */
:root {
    --accent: oklch(58% 0.14 42);
    --accent-dark: oklch(44% 0.11 36);
    --green-dark: oklch(28% 0.07 152);
    --green-mid:  oklch(38% 0.09 152);
}

/* ── Top layout: 3 columns ─────────────────────────────── */
.wk-top {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 24px;
    margin-bottom: 40px;
    align-items: end;
}
@media (max-width: 900px) {
    .wk-top { grid-template-columns: 1fr 1fr; }
    .wk-top .wk-heading { grid-column: 1 / -1; }
}
@media (max-width: 600px) {
    .wk-top { grid-template-columns: 1fr; }
}

/* ── Heading block ─────────────────────────────────────── */
.wk-section-eyebrow {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600;
    font-size: .69rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: rgba(255,255,255,.55);
    margin-bottom: 6px;
}
.wk-page-h1 {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: 2.375rem;
    color: #fff;
    letter-spacing: .02em;
    line-height: 1;
    text-shadow: 0 1px 8px rgba(0,0,0,.3);
    margin: 0 0 8px;
}
.wk-page-sub {
    color: rgba(255,255,255,.55);
    font-size: .8rem;
    margin-bottom: 20px;
}
.btn-new-wk {
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 11px 22px;
    cursor: pointer;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: 1rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    box-shadow: 0 3px 12px rgba(0,0,0,.15);
    transition: all .15s;
}
.btn-new-wk:hover {
    background: oklch(54% 0.14 42);
    box-shadow: 0 6px 20px rgba(0,0,0,.22);
    transform: translateY(-1px);
}

/* ── Stat cards ────────────────────────────────────────── */
.stat-card {
    border-radius: 14px;
    padding: 26px 26px 22px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0,0,0,.14);
    transition: box-shadow .2s, transform .2s;
}
.stat-card:hover {
    box-shadow: 0 12px 32px rgba(0,0,0,.22);
    transform: translateY(-2px);
}
.stat-card::after {
    content: '';
    position: absolute;
    right: -16px;
    bottom: -16px;
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: rgba(255,255,255,.07);
    pointer-events: none;
}
.stat-card--a { background: linear-gradient(135deg, oklch(52% 0.13 42), oklch(44% 0.11 36)); }
.stat-card--b { background: linear-gradient(135deg, oklch(38% 0.09 152), oklch(30% 0.07 155)); }
.stat-card-label {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600;
    font-size: .66rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: rgba(255,255,255,.65);
    margin-bottom: 14px;
}
.stat-card-value {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: 3.25rem;
    line-height: 1;
    color: #fff;
    margin-bottom: 8px;
    letter-spacing: -.02em;
}
.stat-card-sub { font-size: .8rem; color: rgba(255,255,255,.65); }

/* ── List section ──────────────────────────────────────── */
.wk-list-label {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600;
    font-size: .69rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: rgba(255,255,255,.6);
    margin-bottom: 14px;
}
.wk-list { display: flex; flex-direction: column; gap: 10px; }

/* ── Individual workout row ────────────────────────────── */
.wk-row {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px 28px;
    background: rgba(255,255,255,.58);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,.45);
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(0,0,0,.08);
    position: relative;
    overflow: hidden;
    transition: all .18s;
}
.wk-row:hover {
    background: rgba(255,255,255,.78);
    border-color: rgba(255,255,255,.95);
    box-shadow: 0 10px 30px rgba(0,0,0,.15);
    transform: translateX(4px);
}
.wk-row-watermark {
    position: absolute;
    right: 90px;
    top: 50%;
    transform: translateY(-50%);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800;
    font-size: 6.25rem;
    line-height: 1;
    color: var(--accent);
    opacity: .06;
    pointer-events: none;
    user-select: none;
    letter-spacing: -.04em;
}
.wk-row-num {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: .8rem;
    color: var(--accent);
    opacity: .7;
    letter-spacing: .06em;
    min-width: 28px;
    flex-shrink: 0;
}
.wk-row-icon {
    width: 42px;
    height: 42px;
    border-radius: 11px;
    flex-shrink: 0;
    background: rgba(139,90,43,.1);
    color: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
}
.wk-row-body { flex: 1; min-width: 0; }
.wk-row-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: 1.5rem;
    letter-spacing: .03em;
    text-transform: uppercase;
    color: oklch(16% 0.03 50);
    line-height: 1;
}
.wk-row-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 6px;
    flex-wrap: wrap;
}
.wk-cat-badge {
    color: #fff;
    padding: 2px 9px;
    border-radius: 4px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600;
    font-size: .66rem;
    letter-spacing: .08em;
    text-transform: uppercase;
}
.wk-row-date { font-size: .75rem; color: oklch(52% 0.03 50); }
.wk-row-desc {
    font-size: .75rem;
    color: oklch(52% 0.03 50);
    font-style: italic;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 280px;
}
.wk-row-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
    opacity: 0;
    transition: opacity .15s;
}
.wk-row:hover .wk-row-actions { opacity: 1; }
.wk-btn-manage {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    border: 1.5px solid rgba(139,90,43,.53);
    border-radius: 7px;
    background: rgba(139,90,43,.07);
    color: var(--accent);
    cursor: pointer;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: .75rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    text-decoration: none;
    transition: all .15s;
}
.wk-btn-manage:hover { background: rgba(139,90,43,.16); color: var(--accent); }
.wk-btn-del {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    border: 1.5px solid rgba(180,40,40,.35);
    border-radius: 7px;
    background: rgba(180,40,40,.07);
    color: oklch(46% 0.15 15);
    cursor: pointer;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: .75rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    transition: all .15s;
}
.wk-btn-del:hover, .wk-btn-del--confirm {
    background: oklch(52% 0.13 15);
    border-color: oklch(52% 0.13 15);
    color: #fff;
}
.wk-row-arrow { color: var(--accent); opacity: .3; flex-shrink: 0; transition: opacity .18s; }
.wk-row:hover .wk-row-arrow { opacity: .6; }

/* ── Empty state ───────────────────────────────────────── */
.wk-empty {
    background: rgba(255,255,255,.55);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,.4);
    border-radius: 14px;
    padding: 60px 28px;
    text-align: center;
}
.wk-empty-h {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: 1.375rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: oklch(38% 0.04 50);
    margin-bottom: 8px;
}
.wk-empty-s { font-size: .8rem; color: oklch(55% 0.03 50); margin-bottom: 22px; }

/* ── Drawer ────────────────────────────────────────────── */
.drawer-backdrop {
    position: fixed; inset: 0;
    background: rgba(10,5,2,.48);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    z-index: 200; opacity: 0; pointer-events: none;
    transition: opacity .22s;
}
.drawer-backdrop.open { opacity: 1; pointer-events: auto; }
.drawer {
    position: fixed; top: 0; right: 0; bottom: 0; width: 420px;
    background: rgba(252,247,241,.97);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    box-shadow: -8px 0 48px rgba(0,0,0,.22);
    z-index: 201;
    display: flex; flex-direction: column;
    transform: translateX(100%);
    transition: transform .28s cubic-bezier(.4,0,.2,1);
    overflow-y: auto;
}
.drawer.open { transform: translateX(0); }
.drawer-hdr {
    padding: 28px 28px 20px;
    border-bottom: 1px solid rgba(0,0,0,.08);
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.drawer-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800; font-size: 1.5rem;
    letter-spacing: .06em; text-transform: uppercase;
    color: oklch(18% 0.03 50);
    display: flex; align-items: center; gap: 10px;
}
.drawer-title-icon {
    width: 34px; height: 34px; border-radius: 9px;
    background: rgba(139,90,43,.09);
    color: var(--accent);
    display: flex; align-items: center; justify-content: center;
}
.drawer-close {
    background: rgba(0,0,0,.07); border: none; border-radius: 8px;
    width: 34px; height: 34px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: oklch(44% 0.03 50); font-size: 1rem;
    transition: background .15s;
}
.drawer-close:hover { background: rgba(0,0,0,.13); }
.drawer-form { flex: 1; padding: 26px 28px 32px; display: flex; flex-direction: column; gap: 20px; }
.drawer-field { display: flex; flex-direction: column; gap: 6px; }
.drawer-lbl {
    font-family: 'Barlow', sans-serif; font-weight: 600;
    font-size: .8rem; color: oklch(28% 0.04 50);
}
.drawer-lbl .req { color: var(--accent); }
.drawer-input, .drawer-select, .drawer-textarea {
    width: 100%; padding: 9px 12px;
    background: rgba(255,255,255,.85);
    border: 1.5px solid rgba(0,0,0,.12);
    border-radius: 8px; font-size: .875rem;
    font-family: 'Barlow', sans-serif;
    color: oklch(18% 0.03 50); outline: none;
    transition: border-color .15s;
}
.drawer-input:focus, .drawer-select:focus, .drawer-textarea:focus {
    border-color: var(--accent);
}
.drawer-textarea { resize: vertical; min-height: 90px; }
.drawer-submit {
    margin-top: 8px;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    color: #fff; border: none; border-radius: 10px;
    padding: 13px 20px; cursor: pointer;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800; font-size: 1.06rem;
    letter-spacing: .08em; text-transform: uppercase;
    box-shadow: 0 4px 18px rgba(0,0,0,.18);
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; transition: opacity .2s;
}
.drawer-submit:hover { opacity: .9; }

/* ── Detail view ───────────────────────────────────────── */
.detail-header-card {
    background: rgba(255,255,255,.66);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,.5);
    border-radius: 16px;
    padding: 28px 32px;
    margin-bottom: 24px;
    box-shadow: 0 4px 28px rgba(0,0,0,.12);
}
.detail-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 2rem;
    letter-spacing: .04em; text-transform: uppercase;
    color: oklch(16% 0.03 50); margin-bottom: 8px;
}
.day-card {
    background: rgba(255,255,255,.62);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,.48);
    border-radius: 14px;
    margin-bottom: 14px;
    box-shadow: 0 3px 14px rgba(0,0,0,.09);
    overflow: hidden;
}
.day-card-hdr {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 22px;
    border-bottom: 1px solid rgba(0,0,0,.06);
    background: rgba(255,255,255,.3);
}
.day-card-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1.1rem;
    letter-spacing: .04em; text-transform: uppercase;
    color: oklch(20% 0.04 50);
}
.day-card-body { padding: 18px 22px; }
.add-day-card {
    background: rgba(255,255,255,.55);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,.4);
    border-radius: 14px;
    padding: 22px 24px;
    box-shadow: 0 3px 12px rgba(0,0,0,.07);
}

/* ── Day card table overrides ─────────────────────────── */
.day-card-body .table {
    background: transparent;
    margin-bottom: 14px;
}
.day-card-body .table thead tr {
    background: rgba(139,90,43,.08);
}
.day-card-body .table thead th {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: .72rem;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--accent);
    border-bottom: 1px solid rgba(139,90,43,.18);
    padding: 8px 10px;
    background: transparent;
}
.day-card-body .table tbody tr {
    background: transparent;
}
.day-card-body .table tbody tr:hover {
    background: rgba(255,255,255,.35);
}
.day-card-body .table td {
    border-color: rgba(0,0,0,.06);
    color: oklch(22% 0.04 50);
    padding: 8px 10px;
    vertical-align: middle;
}
.day-card-body .table .text-muted {
    color: oklch(50% 0.04 50) !important;
}
.day-card-body .table .btn-outline-danger {
    border-color: rgba(180,40,40,.35);
    color: oklch(46% 0.15 15);
    background: rgba(180,40,40,.05);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: .75rem;
    letter-spacing: .05em;
    text-transform: uppercase;
    padding: 3px 10px;
    border-radius: 6px;
}
.day-card-body .table .btn-outline-danger:hover {
    background: oklch(52% 0.13 15);
    border-color: oklch(52% 0.13 15);
    color: #fff;
}
/* Add exercise row */
.day-card-body .form-select {
    background-color: rgba(255,255,255,.7);
    border: 1.5px solid rgba(0,0,0,.12);
    border-radius: 8px;
    font-family: 'Barlow', sans-serif;
    font-size: .875rem;
    color: oklch(18% 0.03 50);
}
.day-card-body .form-select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(139,90,43,.12);
    background-color: rgba(255,255,255,.9);
}
.day-card-body .btn-primary {
    background: var(--accent);
    border-color: var(--accent);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: .85rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    border-radius: 8px;
}
.day-card-body .btn-primary:hover {
    background: oklch(54% 0.14 42);
    border-color: oklch(54% 0.14 42);
}
/* Add day card inputs */
.add-day-card .form-control {
    background-color: rgba(255,255,255,.7);
    border: 1.5px solid rgba(0,0,0,.12);
    border-radius: 8px;
    font-family: 'Barlow', sans-serif;
    font-size: .875rem;
    color: oklch(18% 0.03 50);
}
.add-day-card .form-control:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(139,90,43,.12);
    background-color: rgba(255,255,255,.9);
}
.add-day-card .btn-primary {
    background: var(--accent);
    border-color: var(--accent);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: .85rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    border-radius: 8px;
}
.add-day-card .btn-primary:hover {
    background: oklch(54% 0.14 42);
    border-color: oklch(54% 0.14 42);
}
/* Day rename inline form */
.day-card-hdr .form-control-sm {
    background-color: rgba(255,255,255,.75);
    border: 1.5px solid rgba(139,90,43,.35);
    border-radius: 7px;
    font-family: 'Barlow', sans-serif;
    font-size: .85rem;
    color: oklch(18% 0.03 50);
}
.day-card-hdr .form-control-sm:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(139,90,43,.12);
    background-color: rgba(255,255,255,.95);
}
.day-card-hdr .btn-primary {
    background: var(--accent);
    border-color: var(--accent);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700;
    font-size: .78rem;
    letter-spacing: .05em;
    text-transform: uppercase;
    border-radius: 6px;
}
.day-card-hdr .btn-primary:hover {
    background: oklch(54% 0.14 42);
    border-color: oklch(54% 0.14 42);
}
.day-card-hdr .btn-outline-secondary {
    border-color: rgba(0,0,0,.18);
    color: oklch(40% 0.03 50);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600;
    font-size: .78rem;
    letter-spacing: .04em;
    text-transform: uppercase;
    border-radius: 6px;
    background: rgba(255,255,255,.5);
}
.day-card-hdr .btn-outline-secondary:hover {
    background: rgba(255,255,255,.85);
    border-color: rgba(0,0,0,.28);
    color: oklch(25% 0.03 50);
}
</style>

<?php if (!$detail): ?>
<!-- ════════════════════════════════════════════════════════
     LIST VIEW
     ════════════════════════════════════════════════════════ -->

<!-- Top 3-column section -->
<div class="wk-top">

    <!-- Heading + new button -->
    <div class="wk-heading slide-down" style="animation-delay:0ms">
        <div class="wk-section-eyebrow">Programs</div>
        <h1 class="wk-page-h1">My Workouts</h1>
        <p class="wk-page-sub">Build and publish workout programs for your clients.</p>
        <button class="btn-new-wk" onclick="openDrawer()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Workout
        </button>
    </div>

    <!-- Stat card: Published -->
    <div class="stat-card stat-card--a pop" style="animation-delay:120ms">
        <div class="stat-card-label">Published</div>
        <div class="stat-card-value" data-count="<?= $totalPublished ?>">0</div>
        <div class="stat-card-sub"><?= $totalDraft ?> draft<?= $totalDraft != 1 ? 's' : '' ?> not yet visible</div>
    </div>

    <!-- Stat card: Total -->
    <div class="stat-card stat-card--b pop" style="animation-delay:220ms">
        <div class="stat-card-label">Total Programs</div>
        <div class="stat-card-value" data-count="<?= $totalPrograms ?>">0</div>
        <div class="stat-card-sub">workout programs created</div>
    </div>

</div>

<!-- Workout rows -->
<div class="wk-list-label">All Programs</div>

<?php if ($workouts): ?>
<div class="wk-list">
    <?php foreach ($workouts as $i => $w): ?>
    <?php $num = str_pad($i + 1, 2, '0', STR_PAD_LEFT); ?>
    <div class="wk-row rise" style="animation-delay:<?= 340 + $i * 80 ?>ms">
        <div class="wk-row-watermark"><?= $num ?></div>
        <div class="wk-row-num"><?= $num ?></div>

        <div class="wk-row-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        </div>

        <div class="wk-row-body">
            <div class="wk-row-title"><?= htmlspecialchars($w['title']) ?></div>
            <div class="wk-row-meta">
                <?php if ($w['category_name']): ?>
                    <span class="wk-cat-badge" style="background:<?= wkCatColor($w['category_name']) ?>">
                        <?= htmlspecialchars($w['category_name']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($w['difficulty']): ?>
                    <span class="wk-cat-badge" style="background:<?= difficultyColor($w['difficulty']) ?>">
                        <?= difficultyLabel($w['difficulty']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($w['day_count'] > 0): ?>
                    <span class="wk-row-date"><?= $w['day_count'] ?> day<?= $w['day_count'] != 1 ? 's' : '' ?> &middot; <?= $w['exercise_count'] ?> exercise<?= $w['exercise_count'] != 1 ? 's' : '' ?></span>
                <?php endif; ?>
                <?php if ($w['save_count'] > 0): ?>
                    <span class="wk-row-date" style="color:oklch(52% 0.13 88)">&#9733; <?= $w['save_count'] ?> saved</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Publish status badge (always visible) -->
        <?php if ($w['is_published']): ?>
            <span style="font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.69rem;letter-spacing:.1em;text-transform:uppercase;padding:3px 10px;border-radius:5px;background:oklch(44% 0.12 152 / 0.12);color:oklch(38% 0.10 152);border:1px solid oklch(44% 0.12 152 / 0.3);flex-shrink:0">
                &#10003; Published
            </span>
        <?php else: ?>
            <span style="font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.69rem;letter-spacing:.1em;text-transform:uppercase;padding:3px 10px;border-radius:5px;background:rgba(0,0,0,.06);color:oklch(50% 0.03 50);border:1px solid rgba(0,0,0,.1);flex-shrink:0">
                Draft
            </span>
        <?php endif; ?>

        <div class="wk-row-actions">
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="toggle_publish">
                <input type="hidden" name="workout_id" value="<?= $w['id'] ?>">
                <button type="submit" class="wk-btn-manage" style="<?= $w['is_published'] ? 'border-color:oklch(44% 0.12 152 / 0.4);color:oklch(38% 0.10 152)' : '' ?>">
                    <?= $w['is_published'] ? 'Unpublish' : 'Publish' ?>
                </button>
            </form>
            <a href="?id=<?= $w['id'] ?>" class="wk-btn-manage">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                Manage
            </a>
            <form method="POST" class="d-inline" data-del-form>
                <input type="hidden" name="action" value="delete_workout">
                <input type="hidden" name="workout_id" value="<?= $w['id'] ?>">
                <button type="button" class="wk-btn-del" data-del-btn onclick="confirmDelete(this)">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    Delete
                </button>
            </form>
        </div>

        <div class="wk-row-arrow">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<div class="wk-empty">
    <div class="wk-empty-h">No programs yet</div>
    <div class="wk-empty-s">Click "+ New Workout" to create your first program</div>
    <button class="btn-new-wk" style="margin:0 auto" onclick="openDrawer()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Workout
    </button>
</div>
<?php endif; ?>

<!-- Drawer backdrop -->
<div class="drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()"></div>

<!-- Drawer -->
<div class="drawer" id="drawer">
    <div class="drawer-hdr">
        <div class="drawer-title">
            <span class="drawer-title-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </span>
            New Workout
        </div>
        <button class="drawer-close" onclick="closeDrawer()">✕</button>
    </div>

    <form method="POST" class="drawer-form">
        <input type="hidden" name="action" value="create">

        <div class="drawer-field">
            <label class="drawer-lbl">Title <span class="req">*</span></label>
            <input type="text" name="title" class="drawer-input" placeholder="e.g. 6-Week Fat Burner" required autofocus>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Category</label>
            <select name="category_id" class="drawer-select">
                <option value="">— none —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Difficulty</label>
            <select name="difficulty" class="drawer-select">
                <option value="">— unspecified —</option>
                <option value="beginner">Beginner</option>
                <option value="intermediate">Intermediate</option>
                <option value="advanced">Advanced</option>
            </select>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Description</label>
            <textarea name="description" class="drawer-textarea" placeholder="Describe your workout program…"></textarea>
        </div>

        <button type="submit" class="drawer-submit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Create Workout
        </button>
    </form>
</div>

<script>
/* Counting animation for stat cards */
document.querySelectorAll('.stat-card-value[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count) || 0;
    if (target === 0) { el.textContent = '0'; return; }
    let current = 0;
    const step = Math.ceil(target / 40);
    const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = current;
        if (current >= target) clearInterval(timer);
    }, 16);
});

/* Drawer open/close */
function openDrawer() {
    document.getElementById('drawer').classList.add('open');
    document.getElementById('drawerBackdrop').classList.add('open');
    document.querySelector('.drawer-input[name="title"]').focus();
}
function closeDrawer() {
    document.getElementById('drawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('open');
}

/* Inline delete confirm — turns "Delete" into "Confirm?" then submits */
function confirmDelete(btn) {
    if (btn.classList.contains('wk-btn-del--confirm')) {
        btn.closest('form').submit();
    } else {
        btn.classList.add('wk-btn-del--confirm');
        btn.textContent = 'Confirm?';
        /* Reset if user moves away */
        setTimeout(() => {
            btn.classList.remove('wk-btn-del--confirm');
            btn.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg> Delete`;
        }, 3000);
    }
}
</script>

<?php else: ?>
<!-- ════════════════════════════════════════════════════════
     DETAIL / MANAGE VIEW
     ════════════════════════════════════════════════════════ -->

<div class="mb-3">
    <a href="<?= BASE_URL ?>/pages/trainer/workouts.php" class="btn-new-wk" style="text-decoration:none;display:inline-flex">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Workouts
    </a>
</div>

<div class="detail-header-card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px">
        <div>
            <div class="detail-title"><?= htmlspecialchars($detail['title']) ?></div>
            <?php if ($detail['category_name']): ?>
                <span class="wk-cat-badge" style="background:<?= wkCatColor($detail['category_name']) ?>;display:inline-block;margin-bottom:10px">
                    <?= htmlspecialchars($detail['category_name']) ?>
                </span>
            <?php endif; ?>
            <?php if ($detail['description']): ?>
                <p style="color:oklch(38% 0.03 50);margin:0;font-size:.9rem"><?= nl2br(htmlspecialchars($detail['description'])) ?></p>
            <?php endif; ?>
        </div>
        <button onclick="openEditWorkoutDrawer()" class="wk-btn-manage" style="opacity:1;flex-shrink:0;margin-top:4px">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
        </button>
    </div>
</div>

<!-- Edit workout drawer backdrop -->
<div class="drawer-backdrop" id="editWkBackdrop" onclick="closeEditWorkoutDrawer()"></div>

<!-- Edit workout drawer -->
<div class="drawer" id="editWkDrawer">
    <div class="drawer-hdr">
        <div class="drawer-title">
            <span class="drawer-title-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </span>
            Edit Workout
        </div>
        <button class="drawer-close" onclick="closeEditWorkoutDrawer()">✕</button>
    </div>
    <form method="POST" class="drawer-form">
        <input type="hidden" name="action" value="edit_workout">
        <input type="hidden" name="workout_id" value="<?= $detail['id'] ?>">

        <div class="drawer-field">
            <label class="drawer-lbl">Title <span class="req">*</span></label>
            <input type="text" name="title" class="drawer-input"
                   value="<?= htmlspecialchars($detail['title']) ?>" required>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Category</label>
            <select name="category_id" class="drawer-select">
                <option value="">— none —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"
                        <?= $cat['id'] == $detail['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Difficulty</label>
            <select name="difficulty" class="drawer-select">
                <option value="">— unspecified —</option>
                <option value="beginner"     <?= ($detail['difficulty'] ?? '') === 'beginner'     ? 'selected' : '' ?>>Beginner</option>
                <option value="intermediate" <?= ($detail['difficulty'] ?? '') === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                <option value="advanced"     <?= ($detail['difficulty'] ?? '') === 'advanced'     ? 'selected' : '' ?>>Advanced</option>
            </select>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Description</label>
            <textarea name="description" class="drawer-textarea"><?= htmlspecialchars($detail['description'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="drawer-submit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Changes
        </button>
    </form>
</div>

<?php if (!$days): ?>
    <p style="color:rgba(255,255,255,.7);margin-bottom:16px">No days yet — add the first day below.</p>
<?php endif; ?>

<?php foreach ($days as $day): ?>
<div class="day-card">
    <div class="day-card-hdr">
        <!-- Static title (shown by default) -->
        <div class="day-card-title" id="day-title-<?= $day['id'] ?>">
            Day <?= $day['day_number'] ?>: <?= htmlspecialchars($day['title']) ?>
        </div>
        <!-- Inline edit form (hidden by default) -->
        <form method="POST" class="d-none align-items-center gap-2" id="day-edit-form-<?= $day['id'] ?>">
            <input type="hidden" name="action" value="edit_day">
            <input type="hidden" name="workout_id" value="<?= $detail['id'] ?>">
            <input type="hidden" name="day_id" value="<?= $day['id'] ?>">
            <input type="text" name="day_title" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($day['title']) ?>" required style="width:200px">
            <button class="btn btn-sm btn-primary">Save</button>
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="cancelEditDay(<?= $day['id'] ?>)">Cancel</button>
        </form>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="wk-btn-manage" style="opacity:1"
                    onclick="startEditDay(<?= $day['id'] ?>)">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Rename
            </button>
            <form method="POST" onsubmit="return confirm('Delete this day and all its exercises?')">
                <input type="hidden" name="action" value="delete_day">
                <input type="hidden" name="workout_id" value="<?= $detail['id'] ?>">
                <input type="hidden" name="day_id" value="<?= $day['id'] ?>">
                <button class="wk-btn-del" style="opacity:1">Delete Day</button>
            </form>
        </div>
    </div>
    <div class="day-card-body">
        <?php if ($day['exercises']): ?>
        <table class="table table-sm mb-3">
            <thead><tr><th>Exercise</th><th>Duration</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($day['exercises'] as $ex): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($ex['title']) ?></td>
                    <td class="text-muted"><?= $ex['duration_minutes'] ? $ex['duration_minutes'] . ' min' : '—' ?></td>
                    <td class="text-end">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="remove_exercise">
                            <input type="hidden" name="workout_id" value="<?= $detail['id'] ?>">
                            <input type="hidden" name="assignment_id" value="<?= $ex['assignment_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if ($myExercises): ?>
        <form method="POST" class="d-flex gap-2">
            <input type="hidden" name="action" value="add_exercise">
            <input type="hidden" name="workout_id" value="<?= $detail['id'] ?>">
            <input type="hidden" name="day_id" value="<?= $day['id'] ?>">
            <select name="exercise_id" class="form-select form-select-sm">
                <?php foreach ($myExercises as $ex): ?>
                    <option value="<?= $ex['id'] ?>"><?= htmlspecialchars($ex['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-primary text-nowrap">Add Exercise</button>
        </form>
        <?php else: ?>
            <p class="text-muted small mb-0">No exercises in your library yet. <a href="<?= BASE_URL ?>/pages/trainer/exercises.php">Add some first.</a></p>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<div class="add-day-card">
    <div style="font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1rem;text-transform:uppercase;letter-spacing:.04em;color:oklch(28% 0.04 50);margin-bottom:14px">Add Day</div>
    <form method="POST" class="d-flex gap-2">
        <input type="hidden" name="action" value="add_day">
        <input type="hidden" name="workout_id" value="<?= $detail['id'] ?>">
        <input type="text" name="day_title" class="form-control" placeholder="e.g. Upper Body" required>
        <button class="btn btn-primary text-nowrap">Add Day</button>
    </form>
</div>

<script>
function openEditWorkoutDrawer() {
    document.getElementById('editWkDrawer').classList.add('open');
    document.getElementById('editWkBackdrop').classList.add('open');
}
function closeEditWorkoutDrawer() {
    document.getElementById('editWkDrawer').classList.remove('open');
    document.getElementById('editWkBackdrop').classList.remove('open');
}
function startEditDay(id) {
    document.getElementById('day-title-' + id).classList.add('d-none');
    const form = document.getElementById('day-edit-form-' + id);
    form.classList.remove('d-none');
    form.classList.add('d-flex');
    form.querySelector('input[name="day_title"]').focus();
}
function cancelEditDay(id) {
    document.getElementById('day-title-' + id).classList.remove('d-none');
    const form = document.getElementById('day-edit-form-' + id);
    form.classList.add('d-none');
    form.classList.remove('d-flex');
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
