<?php
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Workout.php';
require_once __DIR__ . '/../../classes/Coaching.php';
require_once __DIR__ . '/../../classes/Mailer.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Public page — no login required. Guests see a limited view.
$isGuest = !isLoggedIn();

$db       = Database::getInstance();
$workout  = new Workout();
$coaching = new Coaching();

$trainerId = (int)($_GET['id'] ?? 0);
// Redirect back to landing for guests, workouts page for logged-in users
$fallback = $isGuest
    ? BASE_URL . '/'
    : BASE_URL . '/pages/user/workouts.php';
if (!$trainerId) { header('Location: ' . $fallback); exit; }

$trainerStmt = $db->prepare('SELECT * FROM users WHERE id = :id AND role = "trainer" AND is_approved = 1 AND is_banned = 0');
$trainerStmt->execute([':id' => $trainerId]);
$trainer = $trainerStmt->fetch();
if (!$trainer) { header('Location: ' . $fallback); exit; }

/* ── Coaching: who is viewing, and the hire POST handler ──────── */
$viewerId   = $isGuest ? 0 : (int) $_SESSION['user_id'];
$viewerRole = $_SESSION['user_role'] ?? '';
// Only plain users (not the trainer/admin) can hire a coach.
$canHire    = !$isGuest && $viewerRole === 'user';

if (!$isGuest && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'hire' && $canHire) {
        $message = trim($_POST['message'] ?? '');
        [$ok, $err] = $coaching->request($viewerId, $trainerId, $message);
        if ($ok) {
            // Notify the trainer of the new request.
            Mailer::sendCoachRequest(
                $trainer['email'],
                $trainer['first_name'] . ' ' . $trainer['last_name'],
                $_SESSION['user_name'] ?? 'A member',
                $message
            );
            $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Request sent! ' . htmlspecialchars($trainer['first_name']) . ' will review it.'];
        } else {
            $_SESSION['flash'] = ['type' => 'err', 'msg' => $err];
        }
    } elseif ($action === 'cancel_request' && $canHire) {
        $coaching->cancelRequest($viewerId);
        $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Request cancelled.'];
    } elseif ($action === 'end_coaching' && $canHire) {
        $coaching->end((int)($_POST['coaching_id'] ?? 0), $viewerId);
        $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Coaching ended.'];
    }
    header('Location: ' . BASE_URL . '/pages/trainer/profile.php?id=' . $trainerId);
    exit;
}

// Resolve the coaching state for button rendering.
$activeCoach   = $canHire ? $coaching->getActiveForClient($viewerId) : false;
$pendingReq    = $canHire ? $coaching->getPendingForClient($viewerId) : false;
$flash         = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$workouts    = $workout->getPublishedByTrainer($trainerId);
$totalSaves  = array_sum(array_column($workouts, 'save_count'));
$allRatings  = array_filter(array_column($workouts, 'avg_rating'));
$avgRating   = $allRatings ? round(array_sum($allRatings) / count($allRatings), 1) : null;
$totalReviews = array_sum(array_column($workouts, 'review_count'));

$nameParts = [$trainer['first_name'], $trainer['last_name']];
$initials  = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));

if (!function_exists('tpCatColor')) {
    function tpCatColor(?string $n): string {
        $m = ['strength'=>'oklch(52% 0.13 42)','cardio'=>'oklch(48% 0.13 25)','flexibility'=>'oklch(44% 0.10 152)','balance'=>'oklch(50% 0.10 240)','hiit'=>'oklch(46% 0.13 15)','recovery'=>'oklch(50% 0.08 190)','weight loss'=>'oklch(48% 0.11 320)','conditioning'=>'oklch(46% 0.13 15)','full body'=>'oklch(46% 0.09 55)'];
        return $m[strtolower($n ?? '')] ?? 'oklch(44% 0.04 50)';
    }
    function tpDiffColor(?string $d): string {
        return match($d) { 'beginner'=>'oklch(44% 0.12 152)', 'intermediate'=>'oklch(52% 0.13 88)', 'advanced'=>'oklch(50% 0.15 20)', default=>'' };
    }
    function tpStars(float $avg): string {
        $out = '<span style="color:oklch(72% 0.14 88);letter-spacing:-.04em">';
        for ($i = 1; $i <= 5; $i++) $out .= $i <= round($avg) ? '★' : '<span style="color:rgba(0,0,0,.18)">★</span>';
        return $out . '</span>';
    }
}

$pageTitle      = $trainer['first_name'] . ' ' . $trainer['last_name'];
$topBarTitle    = date('l, j F');
$bodyClass      = 'photo-bg bg-clay'; // used only when logged in
$isPublicPage   = $isGuest;           // signals header to use the public nav layout
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.tp-back { display:inline-flex;align-items:center;gap:6px;padding:8px 18px;background:rgba(255,255,255,.55);backdrop-filter:blur(10px);border:1.5px solid rgba(255,255,255,.5);border-radius:10px;color:oklch(28% 0.04 50);font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.9rem;letter-spacing:.05em;text-transform:uppercase;text-decoration:none;transition:all .15s;margin-bottom:22px;display:inline-flex }
.tp-back:hover { background:rgba(255,255,255,.8);color:oklch(18% 0.03 50); }

.tp-hero { background:rgba(255,255,255,.68);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.5);border-radius:16px;padding:32px 36px;margin-bottom:28px;box-shadow:0 4px 24px rgba(0,0,0,.1);display:flex;align-items:flex-start;gap:28px;flex-wrap:wrap }
.tp-avatar { width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,oklch(58% 0.14 42),oklch(44% 0.11 36));display:flex;align-items:center;justify-content:center;font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:1.75rem;color:#fff;box-shadow:0 4px 16px rgba(0,0,0,.22);border:3px solid rgba(255,255,255,.35);flex-shrink:0 }
.tp-name { font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:2.2rem;letter-spacing:.02em;color:oklch(16% 0.03 50);line-height:1;margin-bottom:6px }
.tp-badge { background:oklch(72% 0.14 88);color:oklch(22% 0.06 88);padding:3px 10px;border-radius:4px;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.69rem;letter-spacing:.1em;text-transform:uppercase }
.tp-bio { font-size:.88rem;color:oklch(40% 0.03 50);margin-top:10px;line-height:1.6;max-width:480px }
.tp-stats { display:flex;gap:28px;margin-top:16px;flex-wrap:wrap }
.tp-stat { text-align:center }
.tp-stat-num { font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:1.8rem;line-height:1;color:oklch(22% 0.04 50) }
.tp-stat-lbl { font-size:.72rem;color:oklch(52% 0.03 50);font-family:'Barlow Condensed',sans-serif;letter-spacing:.06em;text-transform:uppercase }

.tp-section-label { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.81rem;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.7);margin-bottom:14px }
.tp-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px }
.tp-card { background:rgba(255,255,255,.62);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.48);border-radius:16px;padding:22px 22px 18px;box-shadow:0 3px 14px rgba(0,0,0,.1);display:flex;flex-direction:column;transition:all .2s;position:relative;overflow:hidden }
.tp-card:hover { background:rgba(255,255,255,.8);box-shadow:0 10px 30px rgba(0,0,0,.16);transform:translateY(-3px) }
.tp-card-cat { color:#fff;padding:2px 9px;border-radius:4px;font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.66rem;letter-spacing:.08em;text-transform:uppercase;display:inline-block;margin-bottom:6px }
.tp-card-title { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.3rem;letter-spacing:.03em;text-transform:uppercase;color:oklch(16% 0.03 50);line-height:1.1;margin-bottom:8px }
.tp-card-meta { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px }
.tp-card-pill { display:flex;align-items:center;gap:4px;font-family:'Barlow Condensed',sans-serif;font-size:.72rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:oklch(42% 0.04 50);background:rgba(0,0,0,.07);border-radius:5px;padding:2px 8px }
.tp-card-view { display:inline-flex;align-items:center;gap:5px;padding:7px 16px;border:1.5px solid rgba(0,0,0,.15);border-radius:8px;background:rgba(255,255,255,.6);color:oklch(28% 0.04 50);font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.82rem;letter-spacing:.06em;text-transform:uppercase;text-decoration:none;transition:all .15s;margin-top:auto }
.tp-card-view:hover { background:rgba(255,255,255,.9);color:oklch(18% 0.03 50) }
.tp-empty { background:rgba(255,255,255,.55);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.4);border-radius:14px;padding:60px 28px;text-align:center;font-family:'Barlow Condensed',sans-serif;font-size:1rem;text-transform:uppercase;letter-spacing:.04em;color:oklch(52% 0.03 50) }

/* ── Hire / coaching panel ─────────────────────────────── */
.tp-flash { display:flex;align-items:center;gap:9px;border-radius:12px;padding:13px 18px;margin-bottom:18px;font-size:.9rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.12) }
.tp-flash i { font-size:1.1rem;flex-shrink:0 }
.tp-flash--ok  { background:rgba(255,255,255,.93);color:oklch(38% 0.12 152);border:1px solid oklch(55% 0.14 152 / .5);border-left:4px solid oklch(52% 0.15 152) }
.tp-flash--ok i  { color:oklch(50% 0.15 152) }
.tp-flash--err { background:rgba(255,255,255,.93);color:oklch(44% 0.16 20);border:1px solid oklch(58% 0.16 20 / .5);border-left:4px solid oklch(54% 0.18 20) }
.tp-flash--err i { color:oklch(54% 0.18 20) }
.tp-hire { background:rgba(255,255,255,.68);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.5);border-radius:16px;padding:22px 26px;margin-bottom:28px;box-shadow:0 4px 18px rgba(0,0,0,.1) }
.tp-hire-state { display:flex;align-items:center;gap:14px;margin-bottom:14px }
.tp-hire-icon { width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0 }
.tp-hire-icon--ok   { background:oklch(44% 0.12 152 / .15);color:oklch(38% 0.10 152) }
.tp-hire-icon--wait { background:oklch(72% 0.14 88 / .22);color:oklch(45% 0.12 70) }
.tp-hire-title { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.15rem;letter-spacing:.03em;color:oklch(18% 0.03 50);line-height:1.1 }
.tp-hire-sub { font-size:.84rem;color:oklch(45% 0.03 50);line-height:1.5 }
.tp-hire-sub a { color:oklch(52% 0.13 42);font-weight:600;text-decoration:none }
.tp-hire-msg { width:100%;min-height:72px;resize:vertical;padding:10px 13px;background:rgba(255,255,255,.85);border:1.5px solid rgba(0,0,0,.12);border-radius:10px;font-family:'Barlow',sans-serif;font-size:.875rem;color:oklch(18% 0.03 50);outline:none;margin-bottom:12px;transition:border-color .15s }
.tp-hire-msg:focus { border-color:oklch(58% 0.14 42);background:#fff }
.tp-hire-btn { display:inline-flex;align-items:center;gap:7px;padding:10px 22px;background:oklch(58% 0.14 42);color:#fff;border:none;border-radius:10px;cursor:pointer;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.92rem;letter-spacing:.06em;text-transform:uppercase;transition:background .15s }
.tp-hire-btn:hover { background:oklch(54% 0.14 42) }
.tp-hire-btn--ghost { background:rgba(0,0,0,.06);color:oklch(40% 0.04 50) }
.tp-hire-btn--ghost:hover { background:rgba(0,0,0,.12) }
</style>

<a href="<?= $isGuest ? BASE_URL . '/' : BASE_URL . '/pages/user/workouts.php' ?>" class="tp-back">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    <?= $isGuest ? 'Home' : 'Browse' ?>
</a>

<div class="tp-hero slide-down" style="animation-delay:0ms">
    <div class="tp-avatar"><?= htmlspecialchars($initials) ?></div>
    <div style="flex:1;min-width:0">
        <div class="tp-name"><?= htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']) ?></div>
        <span class="tp-badge">Trainer</span>
        <?php if ($trainer['bio']): ?>
            <p class="tp-bio"><?= nl2br(htmlspecialchars($trainer['bio'])) ?></p>
        <?php endif; ?>
        <div class="tp-stats">
            <div class="tp-stat">
                <div class="tp-stat-num"><?= count($workouts) ?></div>
                <div class="tp-stat-lbl">Programs</div>
            </div>
            <div class="tp-stat">
                <div class="tp-stat-num"><?= $totalSaves ?></div>
                <div class="tp-stat-lbl">Total Saves</div>
            </div>
            <?php if ($avgRating): ?>
            <div class="tp-stat">
                <div class="tp-stat-num"><?= $avgRating ?></div>
                <div class="tp-stat-lbl"><?= $totalReviews ?> Review<?= $totalReviews != 1 ? 's' : '' ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($flash): ?>
<div class="tp-flash tp-flash--<?= $flash['type'] ?> slide-down">
    <i class="bi <?= $flash['type'] === 'ok' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
    <span><?= $flash['msg'] ?></span>
</div>
<?php endif; ?>

<?php if ($canHire): ?>
<!-- ── Hire / coaching panel ─────────────────────────────── -->
<div class="tp-hire slide-down" style="animation-delay:40ms">
    <?php if ($activeCoach && (int)$activeCoach['trainer_id'] === $trainerId): ?>
        <!-- This trainer is the viewer's active coach -->
        <div class="tp-hire-state">
            <div class="tp-hire-icon tp-hire-icon--ok"><i class="bi bi-check-lg"></i></div>
            <div>
                <div class="tp-hire-title">This is your coach</div>
                <div class="tp-hire-sub"><?= htmlspecialchars($trainer['first_name']) ?> can view and edit your personal plans.</div>
            </div>
        </div>
        <form method="POST" onsubmit="return confirm('End coaching with this trainer?')">
            <input type="hidden" name="action" value="end_coaching">
            <input type="hidden" name="coaching_id" value="<?= (int)$activeCoach['id'] ?>">
            <button class="tp-hire-btn tp-hire-btn--ghost">End coaching</button>
        </form>

    <?php elseif ($pendingReq && (int)$pendingReq['trainer_id'] === $trainerId): ?>
        <!-- Pending request to this trainer -->
        <div class="tp-hire-state">
            <div class="tp-hire-icon tp-hire-icon--wait"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="tp-hire-title">Request pending</div>
                <div class="tp-hire-sub">Waiting for <?= htmlspecialchars($trainer['first_name']) ?> to accept you as a client.</div>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="cancel_request">
            <button class="tp-hire-btn tp-hire-btn--ghost">Cancel request</button>
        </form>

    <?php elseif ($activeCoach): ?>
        <!-- Already coached by someone else -->
        <div class="tp-hire-state">
            <div class="tp-hire-icon tp-hire-icon--wait"><i class="bi bi-info-lg"></i></div>
            <div>
                <div class="tp-hire-title">You already have a coach</div>
                <div class="tp-hire-sub">
                    Coached by <a href="<?= BASE_URL ?>/pages/trainer/profile.php?id=<?= (int)$activeCoach['trainer_id'] ?>"><?= htmlspecialchars($activeCoach['first_name'] . ' ' . $activeCoach['last_name']) ?></a>.
                    End that first to hire someone new.
                </div>
            </div>
        </div>

    <?php elseif ($pendingReq): ?>
        <!-- Pending request to a different trainer -->
        <div class="tp-hire-state">
            <div class="tp-hire-icon tp-hire-icon--wait"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="tp-hire-title">You have a pending request</div>
                <div class="tp-hire-sub">Sent to <?= htmlspecialchars($pendingReq['first_name'] . ' ' . $pendingReq['last_name']) ?>. Cancel it from their profile to hire someone else.</div>
            </div>
        </div>

    <?php else: ?>
        <!-- Free to hire: show the form -->
        <div class="tp-hire-title" style="margin-bottom:6px">
            <i class="bi bi-person-plus" style="color:var(--accent,#8b5a2b);margin-right:6px"></i>
            Hire <?= htmlspecialchars($trainer['first_name']) ?> as your coach
        </div>
        <div class="tp-hire-sub" style="margin-bottom:12px">Send a short note about your goals. Once accepted, they can build and edit your personal plans.</div>
        <form method="POST">
            <input type="hidden" name="action" value="hire">
            <textarea name="message" class="tp-hire-msg" maxlength="1000"
                      placeholder="Hi! I'd like you to coach me. My goal is…"></textarea>
            <button class="tp-hire-btn">
                <i class="bi bi-send"></i> Send hire request
            </button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="tp-section-label"><?= count($workouts) ?> Published Program<?= count($workouts) != 1 ? 's' : '' ?></div>

<?php if ($workouts): ?>
<div class="tp-grid">
    <?php foreach ($workouts as $i => $w): ?>
    <div class="tp-card rise" style="animation-delay:<?= 80 + $i * 70 ?>ms">
        <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:4px">
            <?php if ($w['category_name']): ?>
                <span class="tp-card-cat" style="background:<?= tpCatColor($w['category_name']) ?>"><?= htmlspecialchars($w['category_name']) ?></span>
            <?php endif; ?>
            <?php if ($w['difficulty']): ?>
                <span class="tp-card-cat" style="background:<?= tpDiffColor($w['difficulty']) ?>"><?= ucfirst($w['difficulty']) ?></span>
            <?php endif; ?>
        </div>
        <div class="tp-card-title"><?= htmlspecialchars($w['title']) ?></div>
        <?php if ($w['avg_rating']): ?>
            <div style="margin-bottom:8px;font-size:.78rem;color:oklch(44% 0.03 50)"><?= tpStars((float)$w['avg_rating']) ?> <?= $w['avg_rating'] ?> &middot; <?= $w['review_count'] ?> review<?= $w['review_count'] != 1 ? 's' : '' ?></div>
        <?php endif; ?>
        <div class="tp-card-meta">
            <?php if ($w['day_count'] > 0): ?>
                <span class="tp-card-pill"><?= $w['day_count'] ?> day<?= $w['day_count'] != 1 ? 's' : '' ?></span>
            <?php endif; ?>
            <?php if ($w['save_count'] > 0): ?>
                <span class="tp-card-pill" style="color:oklch(52% 0.13 88)">&#9733; <?= $w['save_count'] ?> saved</span>
            <?php endif; ?>
        </div>
        <?php if ($isGuest): ?>
            <button class="tp-card-view tp-card-view--guest"
                    onclick="showLoginNudge()"
                    style="cursor:pointer;border:none;width:100%;text-align:left">
                <i class="bi bi-lock-fill" style="font-size:.7rem;margin-right:5px"></i>
                Sign in to view
            </button>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/pages/user/workouts.php?id=<?= $w['id'] ?>" class="tp-card-view">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                View Program
            </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="tp-empty rise" style="animation-delay:120ms">No published programs yet</div>
<?php endif; ?>

<?php if ($isGuest): ?>
<!-- ── Login nudge modal (guest only) ─────────────────── -->
<div class="modal fade pub-login-modal" id="loginNudgeModal"
     tabindex="-1" aria-labelledby="loginNudgeLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="loginNudgeLabel">
                    <i class="bi bi-lock-fill me-2" style="color:var(--accent)"></i>
                    Sign in to continue
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p>
                    Create a free account or sign in to view full workout programs,
                    save your favourites, and track your daily progress.
                </p>
                <ul style="font-size:.85rem;color:var(--text-muted);padding-left:1.2rem;margin:0">
                    <li>Browse workouts by category and difficulty</li>
                    <li>Save programs and check off completed days</li>
                    <li>Leave ratings and personal notes</li>
                    <li>Build your own custom plans</li>
                </ul>
            </div>

            <div class="modal-footer">
                <a href="<?= BASE_URL ?>/pages/login.php?redirect=<?= urlencode(BASE_URL . '/pages/trainer/profile.php?id=' . $trainer['id']) ?>"
                   class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
                </a>
                <a href="<?= BASE_URL ?>/pages/register.php"
                   class="btn btn-outline-secondary">
                    Create account
                </a>
            </div>

        </div>
    </div>
</div>

<script>
// Open the login nudge modal
function showLoginNudge() {
    var modal = new bootstrap.Modal(document.getElementById('loginNudgeModal'));
    modal.show();
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
