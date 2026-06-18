<?php
/**
 * Admin — Support inbox.
 * Messages from users, grouped per sender. Admin can mark them read.
 */
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Support.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireRole('admin');

$support = new Support();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $support->markRead((int)($_POST['id'] ?? 0));
    } elseif ($action === 'mark_all_read') {
        $support->markAllRead();
    }
    header('Location: ' . BASE_URL . '/pages/admin/support.php');
    exit;
}

$all    = $support->allRecent();
$unread = 0;

// Group messages by sender (keeps newest-first ordering within each group).
$byUser = [];
foreach ($all as $m) {
    if (!$m['is_read']) $unread++;
    $uid = (int)$m['user_id'];
    if (!isset($byUser[$uid])) {
        $byUser[$uid] = [
            'name'   => $m['first_name'] . ' ' . $m['last_name'],
            'email'  => $m['email'],
            'role'   => $m['role'],
            'msgs'   => [],
            'unread' => 0,
        ];
    }
    $byUser[$uid]['msgs'][] = $m;
    if (!$m['is_read']) $byUser[$uid]['unread']++;
}
// Sort users: those with unread first, then by most recent message.
uasort($byUser, function ($a, $b) {
    if (($b['unread'] > 0) !== ($a['unread'] > 0)) return ($b['unread'] > 0) <=> ($a['unread'] > 0);
    return strtotime($b['msgs'][0]['created_at']) <=> strtotime($a['msgs'][0]['created_at']);
});

if (!function_exists('spInitials')) {
    function spInitials(string $n): string {
        $p = explode(' ', trim($n));
        return strtoupper(substr($p[0] ?? '', 0, 1) . substr($p[1] ?? '', 0, 1));
    }
}

$pageTitle   = 'Support';
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
@keyframes riseUp    { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
@keyframes slideDown { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
.rise{animation:riseUp .45s cubic-bezier(.2,0,.2,1) both}.slide-down{animation:slideDown .4s cubic-bezier(.2,0,.2,1) both}
.as-eyebrow{font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.69rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.55);margin-bottom:6px}
.as-h1{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:2.375rem;color:#fff;letter-spacing:.02em;line-height:1;text-shadow:0 1px 8px rgba(0,0,0,.3);margin:0 0 6px}
.as-head{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:24px}
.as-sub{color:rgba(255,255,255,.55);font-size:.84rem;margin:0}
.as-allbtn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:rgba(255,255,255,.7);border:1.5px solid rgba(255,255,255,.5);border-radius:10px;color:oklch(30% 0.04 50);font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.84rem;letter-spacing:.06em;text-transform:uppercase;cursor:pointer;transition:all .15s}
.as-allbtn:hover{background:#fff;color:oklch(18% 0.03 50)}
.as-user{background:rgba(255,255,255,.65);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.5);border-radius:16px;box-shadow:0 4px 18px rgba(0,0,0,.1);padding:20px 24px;margin-bottom:16px}
.as-user-top{display:flex;align-items:center;gap:14px;margin-bottom:14px}
.as-avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,oklch(50% 0.13 240),oklch(40% 0.10 250));display:flex;align-items:center;justify-content:center;font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:1.05rem;color:#fff;flex-shrink:0;border:2px solid rgba(255,255,255,.35)}
.as-name{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.2rem;letter-spacing:.02em;color:oklch(16% 0.03 50);line-height:1.1}
.as-email{font-size:.78rem;color:oklch(52% 0.03 50)}
.as-count{margin-left:auto;background:oklch(58% 0.14 42);color:#fff;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.7rem;letter-spacing:.06em;padding:3px 9px;border-radius:9px}
.as-msg{border-top:1px solid rgba(0,0,0,.07);padding:12px 0;display:flex;gap:12px;align-items:flex-start}
.as-msg-main{flex:1;min-width:0}
.as-msg-meta{display:flex;align-items:center;gap:10px;margin-bottom:4px}
.as-msg-date{font-size:.73rem;color:oklch(52% 0.03 50);font-family:'Barlow Condensed',sans-serif;letter-spacing:.04em;text-transform:uppercase}
.as-dot{width:8px;height:8px;border-radius:50%;background:oklch(58% 0.14 42);flex-shrink:0}
.as-msg-body{font-size:.9rem;color:oklch(26% 0.03 50);line-height:1.5;white-space:pre-wrap}
.as-read-btn{flex-shrink:0;padding:5px 12px;border:none;border-radius:7px;background:oklch(44% 0.12 152 / .12);color:oklch(36% 0.10 152);font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.72rem;letter-spacing:.05em;text-transform:uppercase;cursor:pointer;transition:background .15s}
.as-read-btn:hover{background:oklch(44% 0.12 152 / .24)}
.as-seen{flex-shrink:0;font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.7rem;letter-spacing:.05em;text-transform:uppercase;color:oklch(55% 0.02 50)}
.as-empty{background:rgba(255,255,255,.55);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.4);border-radius:14px;padding:54px 28px;text-align:center}
.as-empty-h{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.2rem;text-transform:uppercase;letter-spacing:.04em;color:oklch(38% 0.04 50);margin-bottom:6px}
.as-empty-s{font-size:.85rem;color:oklch(52% 0.03 50)}
</style>

<div class="as-head slide-down">
    <div>
        <div class="as-eyebrow">Admin</div>
        <h1 class="as-h1">Support</h1>
        <p class="as-sub"><?= count($all) ?> message<?= count($all) !== 1 ? 's' : '' ?> · <?= $unread ?> unread</p>
    </div>
    <?php if ($unread > 0): ?>
    <form method="POST" style="margin:0">
        <input type="hidden" name="action" value="mark_all_read">
        <button class="as-allbtn"><i class="bi bi-check2-all"></i> Mark all read</button>
    </form>
    <?php endif; ?>
</div>

<?php if ($byUser): ?>
    <?php $i = 0; foreach ($byUser as $u): $i++; ?>
    <div class="as-user rise" style="animation-delay:<?= 30 + $i * 40 ?>ms">
        <div class="as-user-top">
            <div class="as-avatar"><?= htmlspecialchars(spInitials($u['name'])) ?></div>
            <div>
                <div class="as-name"><?= htmlspecialchars($u['name']) ?></div>
                <div class="as-email"><?= htmlspecialchars($u['email']) ?> · <?= htmlspecialchars($u['role']) ?></div>
            </div>
            <?php if ($u['unread'] > 0): ?>
                <span class="as-count"><?= $u['unread'] ?> new</span>
            <?php endif; ?>
        </div>

        <?php foreach ($u['msgs'] as $m): ?>
        <div class="as-msg">
            <?php if (!$m['is_read']): ?><div class="as-dot" title="Unread"></div><?php endif; ?>
            <div class="as-msg-main">
                <div class="as-msg-meta">
                    <span class="as-msg-date"><?= date('d M Y, H:i', strtotime($m['created_at'])) ?></span>
                </div>
                <div class="as-msg-body"><?= htmlspecialchars($m['message']) ?></div>
            </div>
            <?php if (!$m['is_read']): ?>
            <form method="POST" style="margin:0">
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <button class="as-read-btn">Mark read</button>
            </form>
            <?php else: ?>
                <span class="as-seen">Read</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="as-empty rise">
        <div class="as-empty-h">No messages</div>
        <div class="as-empty-s">User support messages will appear here.</div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
