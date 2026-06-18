<?php
/**
 * Admin — Coaching management.
 * View every active client⇄trainer relationship, drop it, or reassign the
 * client to a different approved trainer.
 */
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Coaching.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireRole('admin');

$db       = Database::getInstance();
$coaching = new Coaching();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cid    = (int)($_POST['coaching_id'] ?? 0);

    if ($action === 'drop') {
        $coaching->adminDrop($cid);
        $msg = 'Coaching relationship ended.';
    } elseif ($action === 'reassign') {
        [$ok, $err] = $coaching->adminReassign($cid, (int)($_POST['trainer_id'] ?? 0));
        $msg = $ok ? 'Client reassigned to the new trainer.' : $err;
    }
}

$relations = $coaching->allActive();
$trainers  = $db->query('SELECT id, first_name, last_name FROM users WHERE role = "trainer" AND is_approved = 1 AND is_banned = 0 ORDER BY first_name')->fetchAll();

if (!function_exists('coInitials')) {
    function coInitials(string $f, string $l): string {
        return strtoupper(substr($f, 0, 1) . substr($l, 0, 1));
    }
}

$pageTitle   = 'Coaching';
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
@keyframes riseUp    { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
@keyframes slideDown { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
.rise{animation:riseUp .45s cubic-bezier(.2,0,.2,1) both}.slide-down{animation:slideDown .4s cubic-bezier(.2,0,.2,1) both}
.co-eyebrow{font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.69rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.55);margin-bottom:6px}
.co-h1{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:2.375rem;color:#fff;letter-spacing:.02em;line-height:1;text-shadow:0 1px 8px rgba(0,0,0,.3);margin:0 0 6px}
.co-sub{color:rgba(255,255,255,.55);font-size:.84rem;margin:0 0 24px}
.co-card{background:rgba(255,255,255,.65);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.5);border-radius:16px;box-shadow:0 4px 18px rgba(0,0,0,.1);padding:18px 22px;margin-bottom:12px}
.co-row{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.co-pair{display:flex;align-items:center;gap:12px;flex:1;min-width:240px}
.co-avatar{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:1.05rem;color:#fff;flex-shrink:0;border:2px solid rgba(255,255,255,.35)}
.co-avatar--client{background:linear-gradient(135deg,oklch(50% 0.13 240),oklch(40% 0.10 250))}
.co-avatar--trainer{background:linear-gradient(135deg,oklch(58% 0.14 42),oklch(44% 0.11 36))}
.co-name{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.15rem;letter-spacing:.02em;color:oklch(16% 0.03 50);line-height:1.1}
.co-meta{font-size:.76rem;color:oklch(52% 0.03 50)}
.co-arrow{color:oklch(50% 0.04 50);opacity:.5;flex-shrink:0}
.co-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.co-select{padding:8px 11px;background:rgba(255,255,255,.85);border:1.5px solid rgba(0,0,0,.12);border-radius:9px;font-family:'Barlow',sans-serif;font-size:.83rem;color:oklch(18% 0.03 50);outline:none}
.co-select:focus{border-color:oklch(58% 0.14 42);background:#fff}
.co-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:none;border-radius:9px;cursor:pointer;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.8rem;letter-spacing:.06em;text-transform:uppercase;transition:all .15s;white-space:nowrap}
.co-btn--reassign{background:oklch(58% 0.14 42);color:#fff}
.co-btn--reassign:hover{background:oklch(54% 0.14 42)}
.co-btn--drop{background:oklch(50% 0.15 20 / .1);color:oklch(44% 0.14 20)}
.co-btn--drop:hover{background:oklch(50% 0.15 20 / .2)}
.co-empty{background:rgba(255,255,255,.55);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.4);border-radius:14px;padding:54px 28px;text-align:center}
.co-empty-h{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.2rem;text-transform:uppercase;letter-spacing:.04em;color:oklch(38% 0.04 50);margin-bottom:6px}
.co-empty-s{font-size:.85rem;color:oklch(52% 0.03 50)}
</style>

<?php if ($msg): ?>
<div class="alert alert-info alert-dismissible fade show mb-4 pop">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="slide-down" style="margin-bottom:24px">
    <div class="co-eyebrow">Admin</div>
    <h1 class="co-h1">Coaching</h1>
    <p class="co-sub"><?= count($relations) ?> active client &rarr; trainer relationship<?= count($relations) !== 1 ? 's' : '' ?>. Drop a relationship or move a client to another trainer.</p>
</div>

<?php if ($relations): ?>
    <?php foreach ($relations as $i => $r): ?>
    <div class="co-card rise" style="animation-delay:<?= 40 + $i * 50 ?>ms">
        <div class="co-row">
            <!-- Client -->
            <div class="co-pair">
                <div class="co-avatar co-avatar--client"><?= htmlspecialchars(coInitials($r['client_first'], $r['client_last'])) ?></div>
                <div>
                    <div class="co-name"><?= htmlspecialchars($r['client_first'] . ' ' . $r['client_last']) ?></div>
                    <div class="co-meta"><?= (int)$r['plan_count'] ?> plan<?= $r['plan_count'] != 1 ? 's' : '' ?> · client</div>
                </div>
            </div>

            <svg class="co-arrow" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>

            <!-- Trainer -->
            <div class="co-pair">
                <div class="co-avatar co-avatar--trainer"><?= htmlspecialchars(coInitials($r['trainer_first'], $r['trainer_last'])) ?></div>
                <div>
                    <div class="co-name"><?= htmlspecialchars($r['trainer_first'] . ' ' . $r['trainer_last']) ?></div>
                    <div class="co-meta">coach since <?= date('d M Y', strtotime($r['responded_at'] ?: $r['created_at'])) ?></div>
                </div>
            </div>

            <!-- Actions -->
            <div class="co-actions">
                <form method="POST" style="display:flex;gap:8px;align-items:center;margin:0">
                    <input type="hidden" name="action" value="reassign">
                    <input type="hidden" name="coaching_id" value="<?= (int)$r['id'] ?>">
                    <select name="trainer_id" class="co-select" required>
                        <option value="">Move to…</option>
                        <?php foreach ($trainers as $t): if ((int)$t['id'] === (int)$r['trainer_id']) continue; ?>
                            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="co-btn co-btn--reassign">Reassign</button>
                </form>
                <form method="POST" onsubmit="return confirm('Drop this coaching relationship?')" style="margin:0">
                    <input type="hidden" name="action" value="drop">
                    <input type="hidden" name="coaching_id" value="<?= (int)$r['id'] ?>">
                    <button class="co-btn co-btn--drop">Drop</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="co-empty rise">
        <div class="co-empty-h">No active coaching</div>
        <div class="co-empty-s">When a trainer accepts a client, the relationship will appear here.</div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
