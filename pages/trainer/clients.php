<?php
/**
 * Trainer Clients page.
 *
 *   - List view        : pending coaching requests (accept/decline) + active clients
 *   - ?client=<id>     : a client's personal plans + create a new plan for them
 *   - ?plan=<id>       : edit a client's plan (days + exercises)
 *
 * A trainer may only act on clients with whom they have an ACTIVE coaching row.
 */

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Workout.php';
require_once __DIR__ . '/../../classes/Coaching.php';
require_once __DIR__ . '/../../classes/Mailer.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireRole('trainer');

$db        = Database::getInstance();
$workout   = new Workout();
$coaching  = new Coaching();
$trainerId = (int) $_SESSION['user_id'];

/** Load a plan only if this trainer is the active coach of its owner. */
function loadEditablePlan(Workout $workout, Coaching $coaching, int $trainerId, int $planId): array|false
{
    $plan = $workout->getById($planId);
    if (!$plan || (int)$plan['is_published'] === 1) return false;
    if (!$coaching->isActiveCoach($trainerId, (int)$plan['user_id'])) return false;
    return $plan;
}

/* ─────────────────────────────────────────────────────────
   POST handler
   ───────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'accept_request') {
        $cid = (int)($_POST['coaching_id'] ?? 0);
        // Fetch the request to know the client for the email.
        $stmt = $db->prepare('SELECT co.*, u.email, u.first_name, u.last_name FROM coaching co JOIN users u ON co.client_id = u.id WHERE co.id = :id AND co.trainer_id = :tid');
        $stmt->execute([':id' => $cid, ':tid' => $trainerId]);
        $req = $stmt->fetch();
        if ($req && $coaching->accept($cid, $trainerId)) {
            Mailer::sendCoachAccepted(
                $req['email'],
                $req['first_name'] . ' ' . $req['last_name'],
                $_SESSION['user_name'] ?? 'Your coach'
            );
            $_SESSION['flash'] = ['type' => 'ok', 'msg' => htmlspecialchars($req['first_name']) . ' is now your client.'];
        } else {
            $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Could not accept this request.'];
        }
        header('Location: ' . BASE_URL . '/pages/trainer/clients.php');
        exit;
    }

    if ($action === 'decline_request') {
        $coaching->decline((int)($_POST['coaching_id'] ?? 0), $trainerId);
        $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Request declined.'];
        header('Location: ' . BASE_URL . '/pages/trainer/clients.php');
        exit;
    }

    if ($action === 'end_coaching') {
        $coaching->end((int)($_POST['coaching_id'] ?? 0), $trainerId);
        $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Coaching ended.'];
        header('Location: ' . BASE_URL . '/pages/trainer/clients.php');
        exit;
    }

    if ($action === 'create_plan') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        if ($title !== '' && $coaching->isActiveCoach($trainerId, $clientId)) {
            $newId = $workout->createForClient($clientId, $trainerId, [
                'category_id' => (int)($_POST['category_id'] ?? 0) ?: null,
                'title'       => $title,
                'description' => trim($_POST['description'] ?? ''),
                'difficulty'  => $_POST['difficulty'] ?? null,
            ]);
            header('Location: ' . BASE_URL . '/pages/trainer/clients.php?plan=' . $newId);
            exit;
        }
        header('Location: ' . BASE_URL . '/pages/trainer/clients.php?client=' . $clientId);
        exit;
    }

    if ($action === 'edit_plan') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan   = loadEditablePlan($workout, $coaching, $trainerId, $planId);
        if ($plan) {
            $workout->updateMeta($planId, [
                'category_id' => (int)($_POST['category_id'] ?? 0) ?: null,
                'title'       => trim($_POST['title'] ?? $plan['title']),
                'description' => trim($_POST['description'] ?? ''),
                'difficulty'  => $_POST['difficulty'] ?? null,
            ]);
        }
        header('Location: ' . BASE_URL . '/pages/trainer/clients.php?plan=' . $planId);
        exit;
    }

    if ($action === 'delete_plan') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan   = loadEditablePlan($workout, $coaching, $trainerId, $planId);
        // Only delete plans the coach created for the client.
        if ($plan && (int)$plan['coach_id'] === $trainerId) {
            $workout->deleteById($planId);
            $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Plan deleted.'];
        }
        header('Location: ' . BASE_URL . '/pages/trainer/clients.php?client=' . (int)($_POST['client_id'] ?? 0));
        exit;
    }

    if ($action === 'add_day') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan   = loadEditablePlan($workout, $coaching, $trainerId, $planId);
        if ($plan) {
            $num = count($workout->getDays($planId)) + 1;
            $workout->addDay($planId, $num, trim($_POST['day_title'] ?? '') ?: 'Day ' . $num);
        }
        header('Location: ' . BASE_URL . '/pages/trainer/clients.php?plan=' . $planId);
        exit;
    }

    if ($action === 'edit_day') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan   = loadEditablePlan($workout, $coaching, $trainerId, $planId);
        if ($plan) {
            $workout->updateDayById((int)($_POST['day_id'] ?? 0), trim($_POST['day_title'] ?? ''));
        }
        header('Location: ' . BASE_URL . '/pages/trainer/clients.php?plan=' . $planId);
        exit;
    }

    if ($action === 'delete_day') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan   = loadEditablePlan($workout, $coaching, $trainerId, $planId);
        if ($plan) {
            $workout->deleteDayById((int)($_POST['day_id'] ?? 0));
        }
        header('Location: ' . BASE_URL . '/pages/trainer/clients.php?plan=' . $planId);
        exit;
    }

    header('Location: ' . BASE_URL . '/pages/trainer/clients.php');
    exit;
}

/* ─────────────────────────────────────────────────────────
   Routing for GET views
   ───────────────────────────────────────────────────────── */
$planId   = isset($_GET['plan'])   ? (int)$_GET['plan']   : 0;
$clientId = isset($_GET['client']) ? (int)$_GET['client'] : 0;

$editPlan = null;
$days     = [];
$client   = null;
$plans    = [];

if ($planId) {
    $editPlan = loadEditablePlan($workout, $coaching, $trainerId, $planId);
    if ($editPlan) {
        $days = $workout->getDays($editPlan['id']);
        foreach ($days as &$d) { $d['exercises'] = $workout->getDayExercises($d['id']); }
        unset($d);
        // Owner info for the header.
        $stmt = $db->prepare('SELECT id, first_name, last_name FROM users WHERE id = :id');
        $stmt->execute([':id' => $editPlan['user_id']]);
        $client = $stmt->fetch();
    }
}

if (!$editPlan && $clientId) {
    if ($coaching->isActiveCoach($trainerId, $clientId)) {
        $stmt = $db->prepare('SELECT id, first_name, last_name, email FROM users WHERE id = :id');
        $stmt->execute([':id' => $clientId]);
        $client = $stmt->fetch();
        $plans  = $workout->getPersonalPlans($clientId);
    }
}

// List view data
$pending = $coaching->pendingRequests($trainerId);
$clients = $coaching->activeClients($trainerId);
$categories = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if (!function_exists('clDiffColor')) {
    function clDiffColor(?string $d): string {
        return match($d) {
            'beginner'     => 'oklch(44% 0.12 152)',
            'intermediate' => 'oklch(52% 0.13 88)',
            'advanced'     => 'oklch(50% 0.15 20)',
            default        => 'oklch(44% 0.04 50)',
        };
    }
    function clInitials(string $f, string $l): string {
        return strtoupper(substr($f, 0, 1) . substr($l, 0, 1));
    }
}

$pageTitle   = $editPlan ? 'Edit Plan' : ($client ? 'Client' : 'My Clients');
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
@keyframes riseUp    { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
@keyframes slideDown { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
.rise       { animation: riseUp    .45s cubic-bezier(.2,0,.2,1) both; }
.slide-down { animation: slideDown .4s  cubic-bezier(.2,0,.2,1) both; }

.cl-eyebrow { font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.69rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.55);margin-bottom:6px }
.cl-h1 { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:2.375rem;color:#fff;letter-spacing:.02em;line-height:1;text-shadow:0 1px 8px rgba(0,0,0,.3);margin:0 0 24px }
.cl-label { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.81rem;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.7);margin:0 0 14px }

.cl-flash { border-radius:12px;padding:13px 18px;margin-bottom:18px;font-size:.9rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.12) }
.cl-flash--ok  { background:rgba(255,255,255,.93);color:oklch(38% 0.12 152);border:1px solid oklch(55% 0.14 152 / .5);border-left:4px solid oklch(52% 0.15 152) }
.cl-flash--err { background:rgba(255,255,255,.93);color:oklch(44% 0.16 20);border:1px solid oklch(58% 0.16 20 / .5);border-left:4px solid oklch(54% 0.18 20) }

.cl-card { background:rgba(255,255,255,.65);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.5);border-radius:16px;box-shadow:0 4px 18px rgba(0,0,0,.1) }

/* Pending request card */
.cl-req { padding:18px 22px;margin-bottom:12px;display:flex;align-items:flex-start;gap:16px }
.cl-avatar { width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,oklch(58% 0.14 42),oklch(44% 0.11 36));display:flex;align-items:center;justify-content:center;font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:1.1rem;color:#fff;flex-shrink:0;border:2px solid rgba(255,255,255,.35) }
.cl-req-body { flex:1;min-width:0 }
.cl-req-name { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.2rem;letter-spacing:.02em;color:oklch(16% 0.03 50);line-height:1.1 }
.cl-req-email { font-size:.78rem;color:oklch(52% 0.03 50);margin-bottom:8px }
.cl-req-msg { font-size:.86rem;color:oklch(34% 0.03 50);font-style:italic;background:rgba(0,0,0,.04);border-radius:8px;padding:8px 12px;line-height:1.45 }
.cl-req-actions { display:flex;flex-direction:column;gap:8px;flex-shrink:0 }
.cl-btn { display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 18px;border:none;border-radius:9px;cursor:pointer;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.82rem;letter-spacing:.06em;text-transform:uppercase;text-decoration:none;transition:all .15s;white-space:nowrap }
.cl-btn--accept { background:oklch(46% 0.13 152);color:#fff }
.cl-btn--accept:hover { background:oklch(42% 0.13 152);color:#fff }
.cl-btn--decline { background:rgba(0,0,0,.06);color:oklch(44% 0.04 50) }
.cl-btn--decline:hover { background:oklch(50% 0.15 20 / .15);color:oklch(44% 0.14 20) }
.cl-btn--primary { background:oklch(58% 0.14 42);color:#fff }
.cl-btn--primary:hover { background:oklch(54% 0.14 42);color:#fff }
.cl-btn--ghost { background:rgba(255,255,255,.55);border:1.5px solid rgba(0,0,0,.12);color:oklch(30% 0.04 50) }
.cl-btn--ghost:hover { background:rgba(255,255,255,.85);color:oklch(18% 0.03 50) }
.cl-btn--danger { background:oklch(50% 0.15 20 / .1);color:oklch(44% 0.14 20) }
.cl-btn--danger:hover { background:oklch(50% 0.15 20 / .2) }

/* Client grid */
.cl-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px }
.cl-client { padding:20px 22px;display:flex;flex-direction:column;transition:all .2s }
.cl-client:hover { background:rgba(255,255,255,.84);transform:translateY(-3px);box-shadow:0 10px 30px rgba(0,0,0,.16) }
.cl-client-top { display:flex;align-items:center;gap:14px;margin-bottom:14px }
.cl-client-meta { font-size:.78rem;color:oklch(52% 0.03 50) }

/* Plan rows */
.cl-plan { padding:16px 20px;margin-bottom:10px;display:flex;align-items:center;gap:14px;transition:all .18s }
.cl-plan:hover { background:rgba(255,255,255,.82);transform:translateX(3px) }
.cl-plan-body { flex:1;min-width:0 }
.cl-plan-title { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.25rem;letter-spacing:.03em;text-transform:uppercase;color:oklch(16% 0.03 50);line-height:1.1 }
.cl-pill { font-family:'Barlow Condensed',sans-serif;font-size:.7rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:oklch(42% 0.04 50);background:rgba(0,0,0,.07);border-radius:5px;padding:2px 8px;margin-right:6px }
.cl-pill--coach { background:oklch(72% 0.14 88 / .25);color:oklch(40% 0.10 70) }
.cl-tag { color:#fff;padding:2px 9px;border-radius:4px;font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.66rem;letter-spacing:.08em;text-transform:uppercase;display:inline-block }

/* Back link */
.cl-back { display:inline-flex;align-items:center;gap:6px;padding:8px 18px;background:rgba(255,255,255,.55);backdrop-filter:blur(10px);border:1.5px solid rgba(255,255,255,.5);border-radius:10px;color:oklch(28% 0.04 50);font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.9rem;letter-spacing:.05em;text-transform:uppercase;text-decoration:none;transition:all .15s;margin-bottom:20px }
.cl-back:hover { background:rgba(255,255,255,.82);color:oklch(18% 0.03 50) }

/* Create / edit panel */
.cl-panel { padding:22px 26px;margin-bottom:22px }
.cl-panel-title { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1rem;letter-spacing:.08em;text-transform:uppercase;color:oklch(22% 0.04 50);margin-bottom:14px }
.cl-form-row { display:flex;gap:10px;flex-wrap:wrap }
.cl-input,.cl-select,.cl-textarea { padding:9px 13px;background:rgba(255,255,255,.85);border:1.5px solid rgba(0,0,0,.12);border-radius:9px;font-family:'Barlow',sans-serif;font-size:.875rem;color:oklch(18% 0.03 50);outline:none;transition:border-color .15s }
.cl-input:focus,.cl-select:focus,.cl-textarea:focus { background:#fff;border-color:oklch(58% 0.14 42) }
.cl-input { flex:1;min-width:180px }
.cl-select { min-width:130px }
.cl-textarea { width:100%;resize:vertical;min-height:64px;margin-top:8px }

/* Plan editor day cards (mirrors My Plan styling) */
.cl-plan-header { padding:24px 28px;margin-bottom:20px }
.cl-plan-header-title { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.9rem;text-transform:uppercase;letter-spacing:.04em;color:oklch(16% 0.03 50);margin-bottom:4px }
.cl-plan-header-sub { font-size:.84rem;color:oklch(48% 0.03 50) }
.cl-day { background:rgba(255,255,255,.62);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.48);border-radius:14px;margin-bottom:14px;box-shadow:0 3px 12px rgba(0,0,0,.08);overflow:hidden }
.cl-day-hdr { padding:13px 20px;background:rgba(255,255,255,.28);border-bottom:1px solid rgba(0,0,0,.06);display:flex;align-items:center;justify-content:space-between;gap:10px }
.cl-day-name { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1rem;text-transform:uppercase;letter-spacing:.04em;color:oklch(22% 0.04 50) }
.cl-day-tools { display:flex;gap:8px;align-items:center }
.cl-mini { padding:4px 12px;border-radius:6px;border:none;cursor:pointer;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.7rem;letter-spacing:.06em;text-transform:uppercase;transition:background .15s }
.cl-mini--edit { background:rgba(139,90,43,.1);color:oklch(48% 0.14 42) }
.cl-mini--edit:hover { background:rgba(139,90,43,.2) }
.cl-mini--del { background:oklch(50% 0.15 20 / .1);color:oklch(44% 0.14 20) }
.cl-mini--del:hover { background:oklch(50% 0.15 20 / .2) }
.cl-day-rename { display:none;gap:6px;align-items:center;flex:1 }
.cl-day-rename.open { display:flex }
.cl-day-rename input { flex:1;padding:6px 10px;background:#fff;border:1.5px solid oklch(58% 0.14 42);border-radius:7px;font-family:'Barlow',sans-serif;font-size:.85rem }
.cl-ex-row { display:flex;align-items:center;justify-content:space-between;padding:9px 20px;border-top:1px solid rgba(0,0,0,.05);font-size:.87rem;color:oklch(28% 0.04 50);background:rgba(255,255,255,.18) }
.cl-ex-name { font-weight:600 }
.cl-ex-dur { font-size:.75rem;color:oklch(52% 0.03 50);margin-left:8px }
.cl-ex-remove { padding:3px 10px;border-radius:5px;border:none;cursor:pointer;background:transparent;color:oklch(52% 0.12 20);font-size:.75rem;font-weight:700;font-family:'Barlow Condensed',sans-serif;letter-spacing:.05em;text-transform:uppercase;transition:background .12s }
.cl-ex-remove:hover { background:oklch(50% 0.15 20 / .12) }
.cl-no-ex { padding:12px 20px;font-size:.82rem;color:oklch(58% 0.02 50);font-style:italic;border-top:1px solid rgba(0,0,0,.05) }
.cl-add-ex { display:inline-flex;align-items:center;gap:6px;padding:7px 16px;margin:10px 20px;background:rgba(255,255,255,.6);border:1.5px dashed rgba(0,0,0,.18);border-radius:8px;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.78rem;letter-spacing:.07em;text-transform:uppercase;color:oklch(42% 0.04 50);cursor:pointer;transition:all .15s }
.cl-add-ex:hover { background:rgba(255,255,255,.9);border-color:oklch(58% 0.14 42);color:oklch(48% 0.14 42) }
.cl-add-day { background:rgba(255,255,255,.62);backdrop-filter:blur(16px);border:1px dashed rgba(255,255,255,.6);border-radius:14px;padding:20px 22px }
.cl-add-day-title { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.81rem;letter-spacing:.1em;text-transform:uppercase;color:oklch(38% 0.04 50);margin-bottom:12px }
.cl-empty { padding:46px 28px;text-align:center }
.cl-empty-h { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.2rem;text-transform:uppercase;letter-spacing:.04em;color:oklch(38% 0.04 50);margin-bottom:6px }
.cl-empty-s { font-size:.85rem;color:oklch(52% 0.03 50) }

/* Exercise picker modal */
.cl-picker-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center }
.cl-picker-overlay.open { display:flex }
.cl-picker-modal { background:#fff;border-radius:16px;width:min(480px,94vw);max-height:80vh;display:flex;flex-direction:column;box-shadow:0 16px 60px rgba(0,0,0,.3);overflow:hidden }
.cl-picker-hdr { padding:18px 22px 14px;border-bottom:1px solid rgba(0,0,0,.08);display:flex;align-items:center;justify-content:space-between }
.cl-picker-heading { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.1rem;letter-spacing:.04em;text-transform:uppercase;color:oklch(18% 0.03 50) }
.cl-picker-close { background:none;border:none;cursor:pointer;font-size:1.4rem;line-height:1;color:oklch(50% 0.03 50);padding:0 4px }
.cl-picker-search { padding:12px 22px;border-bottom:1px solid rgba(0,0,0,.07) }
.cl-picker-search input { width:100%;padding:8px 12px;background:oklch(97% 0.005 50);border:1.5px solid rgba(0,0,0,.1);border-radius:8px;font-family:'Barlow',sans-serif;font-size:.875rem;outline:none }
.cl-picker-search input:focus { border-color:oklch(58% 0.14 42);background:#fff }
.cl-picker-list { overflow-y:auto;flex:1 }
.cl-picker-item { display:flex;align-items:center;justify-content:space-between;padding:11px 22px;cursor:pointer;border-bottom:1px solid rgba(0,0,0,.05) }
.cl-picker-item:hover { background:oklch(97% 0.01 42) }
.cl-picker-item-name { font-weight:600;font-size:.9rem;color:oklch(18% 0.03 50) }
.cl-picker-item-meta { font-size:.75rem;color:oklch(55% 0.03 50);margin-top:2px }
.cl-picker-add { padding:5px 14px;border:none;border-radius:6px;cursor:pointer;background:oklch(58% 0.14 42);color:#fff;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.78rem;letter-spacing:.06em;text-transform:uppercase;flex-shrink:0 }
.cl-picker-empty { padding:28px 22px;text-align:center;font-size:.875rem;color:oklch(52% 0.03 50);font-style:italic }
</style>

<?php if ($flash): ?>
<div class="cl-flash cl-flash--<?= $flash['type'] ?> slide-down"><?= $flash['msg'] ?></div>
<?php endif; ?>

<?php if ($editPlan && $client): ?>
<!-- ════════════════════════════════════════════════════
     PLAN EDITOR
     ════════════════════════════════════════════════════ -->
<a href="<?= BASE_URL ?>/pages/trainer/clients.php?client=<?= (int)$client['id'] ?>" class="cl-back">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    <?= htmlspecialchars($client['first_name']) ?>'s plans
</a>

<div class="cl-card cl-plan-header slide-down">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
        <div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px">
                <?php if ($editPlan['category_name'] ?? null): ?>
                    <span class="cl-tag" style="background:oklch(52% 0.13 42)"><?= htmlspecialchars($editPlan['category_name']) ?></span>
                <?php endif; ?>
                <?php if ($editPlan['difficulty']): ?>
                    <span class="cl-tag" style="background:<?= clDiffColor($editPlan['difficulty']) ?>"><?= ucfirst($editPlan['difficulty']) ?></span>
                <?php endif; ?>
                <?php if ((int)$editPlan['coach_id'] === $trainerId): ?>
                    <span class="cl-tag" style="background:oklch(45% 0.10 70)">Coach-assigned</span>
                <?php endif; ?>
            </div>
            <div class="cl-plan-header-title"><?= htmlspecialchars($editPlan['title']) ?></div>
            <div class="cl-plan-header-sub">Editing for <?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></div>
            <?php if ($editPlan['description']): ?>
                <p style="margin:10px 0 0;font-size:.88rem;color:oklch(40% 0.03 50)"><?= nl2br(htmlspecialchars($editPlan['description'])) ?></p>
            <?php endif; ?>
        </div>
        <button class="cl-btn cl-btn--ghost" onclick="document.getElementById('editPlanForm').classList.toggle('d-none')">Edit details</button>
    </div>

    <!-- Edit plan meta -->
    <form method="POST" id="editPlanForm" class="d-none" style="margin-top:16px;border-top:1px solid rgba(0,0,0,.08);padding-top:16px">
        <input type="hidden" name="action" value="edit_plan">
        <input type="hidden" name="plan_id" value="<?= (int)$editPlan['id'] ?>">
        <div class="cl-form-row">
            <input type="text" name="title" class="cl-input" value="<?= htmlspecialchars($editPlan['title']) ?>" required>
            <select name="category_id" class="cl-select">
                <option value="">No category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $editPlan['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="difficulty" class="cl-select">
                <option value="">Any level</option>
                <option value="beginner"     <?= $editPlan['difficulty'] === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                <option value="intermediate" <?= $editPlan['difficulty'] === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                <option value="advanced"     <?= $editPlan['difficulty'] === 'advanced' ? 'selected' : '' ?>>Advanced</option>
            </select>
            <button class="cl-btn cl-btn--primary">Save</button>
        </div>
        <textarea name="description" class="cl-textarea" maxlength="1000" placeholder="Optional description…"><?= htmlspecialchars($editPlan['description'] ?? '') ?></textarea>
    </form>
</div>

<!-- Days -->
<?php foreach ($days as $i => $day): ?>
<div class="cl-day rise" style="animation-delay:<?= 40 + $i * 50 ?>ms">
    <div class="cl-day-hdr">
        <span class="cl-day-name" id="dayname-<?= $day['id'] ?>">Day <?= $day['day_number'] ?>: <?= htmlspecialchars($day['title']) ?></span>
        <form method="POST" class="cl-day-rename" id="dayrename-<?= $day['id'] ?>">
            <input type="hidden" name="action" value="edit_day">
            <input type="hidden" name="plan_id" value="<?= (int)$editPlan['id'] ?>">
            <input type="hidden" name="day_id" value="<?= $day['id'] ?>">
            <input type="text" name="day_title" value="<?= htmlspecialchars($day['title']) ?>" maxlength="100" required>
            <button class="cl-mini cl-mini--edit">Save</button>
            <button type="button" class="cl-mini cl-mini--del" onclick="toggleRename(<?= $day['id'] ?>)">Cancel</button>
        </form>
        <div class="cl-day-tools" id="daytools-<?= $day['id'] ?>">
            <button type="button" class="cl-mini cl-mini--edit" onclick="toggleRename(<?= $day['id'] ?>)">Rename</button>
            <form method="POST" onsubmit="return confirm('Delete this day and its exercises?')" style="margin:0">
                <input type="hidden" name="action" value="delete_day">
                <input type="hidden" name="plan_id" value="<?= (int)$editPlan['id'] ?>">
                <input type="hidden" name="day_id" value="<?= $day['id'] ?>">
                <button class="cl-mini cl-mini--del">Remove</button>
            </form>
        </div>
    </div>

    <div id="ex-list-<?= $day['id'] ?>">
    <?php if ($day['exercises']): ?>
        <?php foreach ($day['exercises'] as $ex): ?>
        <div class="cl-ex-row" id="ex-row-<?= $ex['assignment_id'] ?>">
            <span>
                <span class="cl-ex-name"><?= htmlspecialchars($ex['title']) ?></span>
                <?php if ($ex['duration_minutes']): ?><span class="cl-ex-dur"><?= $ex['duration_minutes'] ?> min</span><?php endif; ?>
            </span>
            <button class="cl-ex-remove" onclick="removeExercise(<?= $ex['assignment_id'] ?>)">Remove</button>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="cl-no-ex" id="no-ex-<?= $day['id'] ?>">No exercises yet — add some below.</div>
    <?php endif; ?>
    </div>

    <button class="cl-add-ex" onclick="openPicker(<?= $day['id'] ?>)">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Exercise
    </button>
</div>
<?php endforeach; ?>

<div class="cl-add-day rise" style="animation-delay:<?= 60 + count($days) * 50 ?>ms">
    <div class="cl-add-day-title">+ Add New Day</div>
    <form method="POST" class="cl-form-row">
        <input type="hidden" name="action" value="add_day">
        <input type="hidden" name="plan_id" value="<?= (int)$editPlan['id'] ?>">
        <input type="text" name="day_title" class="cl-input" placeholder="Day title, e.g. Chest &amp; Arms…" maxlength="100">
        <button class="cl-btn cl-btn--primary">Add Day</button>
    </form>
</div>

<!-- Exercise picker modal -->
<div class="cl-picker-overlay" id="pickerOverlay">
    <div class="cl-picker-modal">
        <div class="cl-picker-hdr">
            <span class="cl-picker-heading">Add Exercise</span>
            <button class="cl-picker-close" onclick="closePicker()">&times;</button>
        </div>
        <div class="cl-picker-search">
            <input type="text" id="pickerSearch" placeholder="Search exercises…" oninput="filterPicker(this.value)">
        </div>
        <div class="cl-picker-list" id="pickerList"><div class="cl-picker-empty">Loading exercises…</div></div>
    </div>
</div>

<script>
let allExercises = [];
let activeDayId  = null;

function toggleRename(id) {
    document.getElementById('dayrename-' + id).classList.toggle('open');
    document.getElementById('dayname-' + id).classList.toggle('d-none');
    document.getElementById('daytools-' + id).classList.toggle('d-none');
}

async function loadExercises() {
    if (allExercises.length) return;
    const res = await fetch('<?= BASE_URL ?>/api/plan_ajax.php?action=exercises');
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
document.getElementById('pickerOverlay').addEventListener('click', function (e) { if (e.target === this) closePicker(); });
function filterPicker(q) { renderPicker(q.toLowerCase()); }
function renderPicker(q) {
    const list = document.getElementById('pickerList');
    const filtered = q ? allExercises.filter(e => e.title.toLowerCase().includes(q) || (e.category_name || '').toLowerCase().includes(q)) : allExercises;
    if (!filtered.length) { list.innerHTML = '<div class="cl-picker-empty">No exercises found.</div>'; return; }
    list.innerHTML = filtered.map(e => `
        <div class="cl-picker-item">
            <div>
                <div class="cl-picker-item-name">${escHtml(e.title)}</div>
                <div class="cl-picker-item-meta">${e.category_name ? escHtml(e.category_name) + ' · ' : ''}${e.duration_minutes ? e.duration_minutes + ' min' : ''}</div>
            </div>
            <button class="cl-picker-add" onclick="addExercise(${e.id})">Add</button>
        </div>`).join('');
}
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
async function addExercise(exerciseId) {
    if (!activeDayId) return;
    const res = await fetch('<?= BASE_URL ?>/api/plan_ajax.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add_exercise', day_id: activeDayId, exercise_id: exerciseId }),
    });
    const data = await res.json();
    if (!data.ok) { alert('Could not add exercise.'); return; }
    const noEx = document.getElementById('no-ex-' + activeDayId);
    if (noEx) noEx.remove();
    const list = document.getElementById('ex-list-' + activeDayId);
    const ex = data.exercise;
    const row = document.createElement('div');
    row.className = 'cl-ex-row';
    row.id = 'ex-row-' + ex.assignment_id;
    row.innerHTML = `<span><span class="cl-ex-name">${escHtml(ex.title)}</span>${ex.duration_minutes ? `<span class="cl-ex-dur">${ex.duration_minutes} min</span>` : ''}</span><button class="cl-ex-remove" onclick="removeExercise(${ex.assignment_id})">Remove</button>`;
    list.appendChild(row);
    closePicker();
}
async function removeExercise(assignmentId) {
    const row = document.getElementById('ex-row-' + assignmentId);
    if (!row) return;
    const res = await fetch('<?= BASE_URL ?>/api/plan_ajax.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'remove_exercise', assignment_id: assignmentId }),
    });
    const data = await res.json();
    if (data.ok) row.remove();
}
</script>

<?php elseif ($client): ?>
<!-- ════════════════════════════════════════════════════
     CLIENT DETAIL — their plans + create new
     ════════════════════════════════════════════════════ -->
<a href="<?= BASE_URL ?>/pages/trainer/clients.php" class="cl-back">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    All clients
</a>

<div class="slide-down" style="margin-bottom:22px">
    <div class="cl-eyebrow">Client</div>
    <h1 class="cl-h1"><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></h1>
</div>

<!-- Create a plan for this client -->
<div class="cl-card cl-panel pop">
    <div class="cl-panel-title">Create a plan for <?= htmlspecialchars($client['first_name']) ?></div>
    <form method="POST">
        <input type="hidden" name="action" value="create_plan">
        <input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>">
        <div class="cl-form-row">
            <input type="text" name="title" class="cl-input" placeholder="Plan title, e.g. Strength Block…" maxlength="120" required>
            <select name="category_id" class="cl-select">
                <option value="">No category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="difficulty" class="cl-select">
                <option value="">Any level</option>
                <option value="beginner">Beginner</option>
                <option value="intermediate">Intermediate</option>
                <option value="advanced">Advanced</option>
            </select>
            <button class="cl-btn cl-btn--primary">Create</button>
        </div>
        <textarea name="description" class="cl-textarea" maxlength="1000" placeholder="Optional description…"></textarea>
    </form>
</div>

<div class="cl-label"><?= count($plans) ?> plan<?= count($plans) !== 1 ? 's' : '' ?></div>

<?php if ($plans): ?>
    <?php foreach ($plans as $i => $p): ?>
    <div class="cl-card cl-plan rise" style="animation-delay:<?= 40 + $i * 50 ?>ms">
        <div class="cl-plan-body">
            <div class="cl-plan-title"><?= htmlspecialchars($p['title']) ?></div>
            <div style="margin-top:6px">
                <?php if ((int)$p['coach_id'] === $trainerId): ?>
                    <span class="cl-pill cl-pill--coach">Coach-assigned</span>
                <?php else: ?>
                    <span class="cl-pill">Self-made</span>
                <?php endif; ?>
                <span class="cl-pill"><?= (int)$p['day_count'] ?> day<?= $p['day_count'] != 1 ? 's' : '' ?></span>
                <span class="cl-pill"><?= (int)$p['exercise_count'] ?> exercise<?= $p['exercise_count'] != 1 ? 's' : '' ?></span>
                <?php if ($p['difficulty']): ?><span class="cl-tag" style="background:<?= clDiffColor($p['difficulty']) ?>"><?= ucfirst($p['difficulty']) ?></span><?php endif; ?>
            </div>
        </div>
        <a href="?plan=<?= (int)$p['id'] ?>" class="cl-btn cl-btn--primary">Edit</a>
        <?php if ((int)$p['coach_id'] === $trainerId): ?>
        <form method="POST" onsubmit="return confirm('Delete this plan? This cannot be undone.')" style="margin:0">
            <input type="hidden" name="action" value="delete_plan">
            <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>">
            <button class="cl-btn cl-btn--danger">Delete</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="cl-card cl-empty rise">
        <div class="cl-empty-h">No plans yet</div>
        <div class="cl-empty-s"><?= htmlspecialchars($client['first_name']) ?> has no personal plans. Create one above.</div>
    </div>
<?php endif; ?>

<?php else: ?>
<!-- ════════════════════════════════════════════════════
     LIST VIEW — pending requests + active clients
     ════════════════════════════════════════════════════ -->
<div class="slide-down" style="margin-bottom:24px">
    <div class="cl-eyebrow">Coaching</div>
    <h1 class="cl-h1">My Clients</h1>
</div>

<?php if ($pending): ?>
<div class="cl-label"><?= count($pending) ?> pending request<?= count($pending) !== 1 ? 's' : '' ?></div>
<?php foreach ($pending as $i => $req): ?>
<div class="cl-card cl-req rise" style="animation-delay:<?= 40 + $i * 50 ?>ms">
    <div class="cl-avatar"><?= htmlspecialchars(clInitials($req['first_name'], $req['last_name'])) ?></div>
    <div class="cl-req-body">
        <div class="cl-req-name"><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></div>
        <div class="cl-req-email"><?= htmlspecialchars($req['email']) ?></div>
        <?php if ($req['message']): ?>
            <div class="cl-req-msg">"<?= htmlspecialchars($req['message']) ?>"</div>
        <?php endif; ?>
    </div>
    <div class="cl-req-actions">
        <form method="POST" style="margin:0">
            <input type="hidden" name="action" value="accept_request">
            <input type="hidden" name="coaching_id" value="<?= (int)$req['id'] ?>">
            <button class="cl-btn cl-btn--accept">Accept</button>
        </form>
        <form method="POST" style="margin:0">
            <input type="hidden" name="action" value="decline_request">
            <input type="hidden" name="coaching_id" value="<?= (int)$req['id'] ?>">
            <button class="cl-btn cl-btn--decline">Decline</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<div class="cl-label" style="margin-top:<?= $pending ? '28px' : '0' ?>"><?= count($clients) ?> active client<?= count($clients) !== 1 ? 's' : '' ?></div>

<?php if ($clients): ?>
<div class="cl-grid">
    <?php foreach ($clients as $i => $c): ?>
    <div class="cl-card cl-client rise" style="animation-delay:<?= 60 + $i * 50 ?>ms">
        <div class="cl-client-top">
            <div class="cl-avatar"><?= htmlspecialchars(clInitials($c['first_name'], $c['last_name'])) ?></div>
            <div>
                <div class="cl-req-name"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></div>
                <div class="cl-client-meta"><?= (int)$c['plan_count'] ?> plan<?= $c['plan_count'] != 1 ? 's' : '' ?></div>
            </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:auto">
            <a href="?client=<?= (int)$c['client_id'] ?>" class="cl-btn cl-btn--primary" style="flex:1">Manage plans</a>
            <form method="POST" onsubmit="return confirm('End coaching with this client?')" style="margin:0">
                <input type="hidden" name="action" value="end_coaching">
                <input type="hidden" name="coaching_id" value="<?= (int)$c['id'] ?>">
                <button class="cl-btn cl-btn--ghost">End</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="cl-card cl-empty rise">
    <div class="cl-empty-h">No active clients</div>
    <div class="cl-empty-s">When a member hires you and you accept, they'll appear here.</div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
