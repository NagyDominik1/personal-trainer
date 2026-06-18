<?php
/**
 * User Support — message the admin team.
 * Rate-limited to one message per calendar day (enforced in the Support class).
 */
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Support.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$support = new Support();
$userId  = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$ok, $err] = $support->send($userId, $_POST['message'] ?? '');
    $_SESSION['flash'] = $ok
        ? ['type' => 'ok',  'msg' => 'Message sent to the admin team. Thanks!']
        : ['type' => 'err', 'msg' => $err];
    header('Location: ' . BASE_URL . '/pages/user/support.php');
    exit;
}

$canSend  = $support->canSendToday($userId);
$messages = $support->forUser($userId);
$flash    = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle   = 'Support';
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
@keyframes riseUp    { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
@keyframes slideDown { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
.rise{animation:riseUp .45s cubic-bezier(.2,0,.2,1) both}.slide-down{animation:slideDown .4s cubic-bezier(.2,0,.2,1) both}
.sp-eyebrow{font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.69rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.55);margin-bottom:6px}
.sp-h1{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:2.375rem;color:#fff;letter-spacing:.02em;line-height:1;text-shadow:0 1px 8px rgba(0,0,0,.3);margin:0 0 24px}
.sp-flash{border-radius:12px;padding:13px 18px;margin-bottom:18px;font-size:.9rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.12)}
.sp-flash--ok{background:rgba(255,255,255,.93);color:oklch(38% 0.12 152);border:1px solid oklch(55% 0.14 152 / .5);border-left:4px solid oklch(52% 0.15 152)}
.sp-flash--err{background:rgba(255,255,255,.93);color:oklch(44% 0.16 20);border:1px solid oklch(58% 0.16 20 / .5);border-left:4px solid oklch(54% 0.18 20)}
.sp-card{background:rgba(255,255,255,.65);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.5);border-radius:16px;box-shadow:0 4px 18px rgba(0,0,0,.1);padding:24px 26px;margin-bottom:24px}
.sp-card-title{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1rem;letter-spacing:.08em;text-transform:uppercase;color:oklch(22% 0.04 50);margin-bottom:6px}
.sp-card-sub{font-size:.84rem;color:oklch(48% 0.03 50);margin-bottom:14px}
.sp-textarea{width:100%;min-height:120px;resize:vertical;padding:12px 14px;background:rgba(255,255,255,.85);border:1.5px solid rgba(0,0,0,.12);border-radius:10px;font-family:'Barlow',sans-serif;font-size:.9rem;color:oklch(18% 0.03 50);outline:none;transition:border-color .15s}
.sp-textarea:focus{border-color:oklch(58% 0.14 42);background:#fff}
.sp-btn{display:inline-flex;align-items:center;gap:7px;margin-top:12px;padding:11px 24px;background:oklch(58% 0.14 42);color:#fff;border:none;border-radius:10px;cursor:pointer;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.92rem;letter-spacing:.06em;text-transform:uppercase;transition:background .15s}
.sp-btn:hover{background:oklch(54% 0.14 42)}
.sp-limit{display:flex;align-items:center;gap:10px;background:oklch(72% 0.14 88 / .15);border:1px solid oklch(72% 0.14 88 / .3);border-radius:10px;padding:12px 16px;color:oklch(42% 0.10 70);font-size:.86rem;font-weight:600}
.sp-label{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.81rem;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.7);margin:0 0 14px}
.sp-msg{background:rgba(255,255,255,.6);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.45);border-radius:12px;padding:14px 18px;margin-bottom:10px}
.sp-msg-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
.sp-msg-date{font-size:.74rem;color:oklch(52% 0.03 50);font-family:'Barlow Condensed',sans-serif;letter-spacing:.04em;text-transform:uppercase}
.sp-msg-badge{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.64rem;letter-spacing:.08em;text-transform:uppercase;padding:2px 8px;border-radius:4px}
.sp-msg-badge--read{background:oklch(44% 0.12 152 / .15);color:oklch(36% 0.10 152)}
.sp-msg-badge--new{background:rgba(0,0,0,.07);color:oklch(48% 0.03 50)}
.sp-msg-body{font-size:.9rem;color:oklch(28% 0.03 50);line-height:1.5;white-space:pre-wrap}
.sp-empty{font-size:.85rem;color:rgba(255,255,255,.6);font-style:italic}
</style>

<?php if ($flash): ?>
<div class="sp-flash sp-flash--<?= $flash['type'] ?> slide-down"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<div class="slide-down" style="margin-bottom:24px">
    <div class="sp-eyebrow">Help</div>
    <h1 class="sp-h1">Support</h1>
</div>

<div class="sp-card pop">
    <div class="sp-card-title">Message the admin</div>
    <div class="sp-card-sub">Have a question, problem, or feedback? Send a note to the admin team. You can send one message per day.</div>

    <?php if ($canSend): ?>
    <form method="POST">
        <textarea name="message" class="sp-textarea" maxlength="2000" required
                  placeholder="Describe your question or issue…"></textarea>
        <button class="sp-btn"><i class="bi bi-send"></i> Send message</button>
    </form>
    <?php else: ?>
    <div class="sp-limit">
        <i class="bi bi-hourglass-split" style="font-size:1.1rem"></i>
        You've already sent a message today. Please come back tomorrow if you need more help.
    </div>
    <?php endif; ?>
</div>

<div class="sp-label">Your messages</div>
<?php if ($messages): ?>
    <?php foreach ($messages as $i => $m): ?>
    <div class="sp-msg rise" style="animation-delay:<?= 30 + $i * 40 ?>ms">
        <div class="sp-msg-top">
            <span class="sp-msg-date"><?= date('d M Y, H:i', strtotime($m['created_at'])) ?></span>
            <?php if ($m['is_read']): ?>
                <span class="sp-msg-badge sp-msg-badge--read">Seen by admin</span>
            <?php else: ?>
                <span class="sp-msg-badge sp-msg-badge--new">Sent</span>
            <?php endif; ?>
        </div>
        <div class="sp-msg-body"><?= htmlspecialchars($m['message']) ?></div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="sp-empty">You haven't sent any messages yet.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
