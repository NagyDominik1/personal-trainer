<?php
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Workout.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireRole('admin');

$workout = new Workout();
$msg     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_hidden') {
    $workout->toggleHidden((int)$_POST['workout_id']);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$workouts = $workout->getAllForAdmin();
$hidden   = count(array_filter($workouts, fn($w) => $w['is_hidden']));
$visible  = count($workouts) - $hidden;

if (!function_exists('modDiffColor')) {
    function modDiffColor(?string $d): string {
        return match($d) { 'beginner'=>'oklch(44% 0.12 152)', 'intermediate'=>'oklch(52% 0.13 88)', 'advanced'=>'oklch(50% 0.15 20)', default=>'' };
    }
    function modCatColor(?string $n): string {
        $m = ['strength'=>'oklch(52% 0.13 42)','cardio'=>'oklch(48% 0.13 25)','flexibility'=>'oklch(44% 0.10 152)','balance'=>'oklch(50% 0.10 240)','hiit'=>'oklch(46% 0.13 15)','recovery'=>'oklch(50% 0.08 190)','weight loss'=>'oklch(48% 0.11 320)','conditioning'=>'oklch(46% 0.13 15)','full body'=>'oklch(46% 0.09 55)'];
        return $m[strtolower($n ?? '')] ?? 'oklch(44% 0.04 50)';
    }
}

$pageTitle   = 'Moderation';
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.mod-header { display:grid;grid-template-columns:1fr 1fr 1fr;gap:24px;align-items:end;margin-bottom:36px }
.mod-heading-text .mod-eyebrow { font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.69rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.55);margin-bottom:6px }
.mod-heading-text h1 { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:2.375rem;color:#fff;letter-spacing:.02em;line-height:1;text-shadow:0 1px 8px rgba(0,0,0,.3);margin:0 0 6px }
.mod-heading-text p { color:rgba(255,255,255,.55);font-size:.84rem;margin:0 }
.mod-meta-card { border-radius:14px;padding:22px 24px 18px;position:relative;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.14);transition:transform .2s;color:#fff }
.mod-meta-card:hover { transform:translateY(-2px) }
.mod-meta-card::after { content:'';position:absolute;right:-14px;bottom:-14px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.07);pointer-events:none }
.mod-meta-card .mc-label { font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.66rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.65);margin-bottom:12px }
.mod-meta-card .mc-value { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:3rem;line-height:1;letter-spacing:-.02em;margin-bottom:6px }
.mod-meta-card .mc-sub { font-size:.78rem;color:rgba(255,255,255,.65) }

.mod-list { display:flex;flex-direction:column;gap:10px }
.mod-row { display:flex;align-items:center;gap:20px;padding:18px 28px;background:rgba(255,255,255,.62);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.5);border-radius:14px;box-shadow:0 3px 12px rgba(0,0,0,.08);position:relative;overflow:hidden;transition:all .18s }
.mod-row:hover { background:rgba(255,255,255,.82);box-shadow:0 10px 28px rgba(0,0,0,.15);transform:translateX(4px) }
.mod-row.hidden-row { opacity:.7 }
.mod-row-num { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.8rem;letter-spacing:.06em;min-width:28px;flex-shrink:0;color:oklch(58% 0.14 42);opacity:.75 }
.mod-row-body { flex:1;min-width:0 }
.mod-row-title { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.4rem;letter-spacing:.03em;text-transform:uppercase;color:oklch(16% 0.03 50);line-height:1 }
.mod-row-meta { display:flex;align-items:center;gap:10px;margin-top:5px;flex-wrap:wrap }
.mod-badge { color:#fff;padding:2px 9px;border-radius:4px;font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.66rem;letter-spacing:.08em;text-transform:uppercase }
.mod-row-trainer { font-size:.78rem;color:oklch(50% 0.03 50) }
.mod-row-saves { font-size:.78rem;color:oklch(52% 0.13 88) }
.mod-status-badge { padding:3px 10px;border-radius:5px;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.69rem;letter-spacing:.1em;text-transform:uppercase;flex-shrink:0 }
.mod-row-actions { display:flex;gap:8px;flex-shrink:0;opacity:0;transition:opacity .15s }
.mod-row:hover .mod-row-actions { opacity:1 }
.mod-btn-hide { display:flex;align-items:center;gap:5px;padding:6px 14px;border-radius:7px;cursor:pointer;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.75rem;letter-spacing:.06em;text-transform:uppercase;transition:all .15s;border:1.5px solid rgba(180,40,40,.35);background:rgba(180,40,40,.07);color:oklch(46% 0.15 15) }
.mod-btn-hide:hover,.mod-btn-hide--confirm { background:oklch(52% 0.13 15);border-color:oklch(52% 0.13 15);color:#fff }
.mod-btn-show { display:flex;align-items:center;gap:5px;padding:6px 14px;border-radius:7px;cursor:pointer;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.75rem;letter-spacing:.06em;text-transform:uppercase;transition:all .15s;border:1.5px solid oklch(44% 0.12 152 / 0.4);background:oklch(44% 0.12 152 / 0.08);color:oklch(38% 0.10 152) }
.mod-btn-show:hover { background:oklch(44% 0.12 152 / 0.18) }
.mod-section-label { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.81rem;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.7);margin:0 0 14px }
</style>

<!-- Header + stat cards -->
<div class="mod-header">
    <div class="mod-heading-text slide-down" style="animation-delay:0ms">
        <div class="mod-eyebrow">Admin</div>
        <h1>Moderation</h1>
        <p>Hide or restore workout visibility.</p>
    </div>
    <div class="mod-meta-card pop" style="background:linear-gradient(135deg,oklch(38% 0.09 152),oklch(30% 0.07 155));animation-delay:100ms">
        <div class="mc-label">Visible Workouts</div>
        <div class="mc-value" data-countup="<?= $visible ?>"><?= $visible ?></div>
        <div class="mc-sub">publicly accessible</div>
    </div>
    <div class="mod-meta-card pop" style="background:linear-gradient(135deg,oklch(50% 0.15 20),oklch(42% 0.13 20));animation-delay:180ms">
        <div class="mc-label">Hidden</div>
        <div class="mc-value" data-countup="<?= $hidden ?>"><?= $hidden ?></div>
        <div class="mc-sub">removed from public view</div>
    </div>
</div>

<div class="mod-section-label">All Workouts — <?= count($workouts) ?> total</div>

<div class="mod-list">
<?php foreach ($workouts as $i => $w):
    $num    = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
    $hidden = (bool)$w['is_hidden'];
?>
<div class="mod-row rise <?= $hidden ? 'hidden-row' : '' ?>" style="animation-delay:<?= 200 + $i * 50 ?>ms">
    <div class="mod-row-num"><?= $num ?></div>
    <div class="mod-row-body">
        <div class="mod-row-title"><?= htmlspecialchars($w['title']) ?></div>
        <div class="mod-row-meta">
            <?php if ($w['category_name']): ?>
                <span class="mod-badge" style="background:<?= modCatColor($w['category_name']) ?>"><?= htmlspecialchars($w['category_name']) ?></span>
            <?php endif; ?>
            <?php if ($w['difficulty']): ?>
                <span class="mod-badge" style="background:<?= modDiffColor($w['difficulty']) ?>"><?= ucfirst($w['difficulty']) ?></span>
            <?php endif; ?>
            <span class="mod-row-trainer"><?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?></span>
            <?php if ($w['save_count'] > 0): ?>
                <span class="mod-row-saves">&#9733; <?= $w['save_count'] ?> saved</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($hidden): ?>
        <span class="mod-status-badge" style="background:rgba(180,40,40,.12);color:oklch(46% 0.15 15);border:1px solid rgba(180,40,40,.25)">Hidden</span>
    <?php elseif ($w['is_published']): ?>
        <span class="mod-status-badge" style="background:oklch(44% 0.12 152 / 0.12);color:oklch(38% 0.10 152);border:1px solid oklch(44% 0.12 152 / 0.3)">&#10003; Published</span>
    <?php else: ?>
        <span class="mod-status-badge" style="background:rgba(0,0,0,.06);color:oklch(50% 0.03 50);border:1px solid rgba(0,0,0,.1)">Draft</span>
    <?php endif; ?>

    <div class="mod-row-actions">
        <form method="POST">
            <input type="hidden" name="action"     value="toggle_hidden">
            <input type="hidden" name="workout_id" value="<?= $w['id'] ?>">
            <?php if ($hidden): ?>
                <button type="submit" class="mod-btn-show">&#10003; Restore</button>
            <?php else: ?>
                <button type="button" class="mod-btn-hide" onclick="confirmHide(this)">Hide</button>
            <?php endif; ?>
        </form>
    </div>
</div>
<?php endforeach; ?>
</div>

<script>
function confirmHide(btn) {
    if (btn.dataset.confirmed === '1') { btn.closest('form').submit(); return; }
    btn.dataset.confirmed = '1';
    btn.classList.add('mod-btn-hide--confirm');
    btn.textContent = 'Confirm Hide?';
    setTimeout(() => {
        if (btn.dataset.confirmed === '1') {
            btn.dataset.confirmed = '';
            btn.classList.remove('mod-btn-hide--confirm');
            btn.textContent = 'Hide';
        }
    }, 3000);
}
document.querySelectorAll('[data-countup]').forEach(el => {
    const t = parseInt(el.dataset.countup) || 0;
    if (!t) return;
    let c = 0, inc = t / (700/16);
    const i = setInterval(() => { c += inc; if (c >= t) { el.textContent = t; clearInterval(i); } else el.textContent = Math.floor(c); }, 16);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
