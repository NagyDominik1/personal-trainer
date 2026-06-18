<?php
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Workout.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$workout = new Workout();
$userId  = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unsave') {
    $workout->unsave($userId, (int)$_POST['workout_id']);
    header('Location: ' . BASE_URL . '/pages/user/saved.php');
    exit;
}

$saved = $workout->getSaved($userId);

if (!function_exists('savedCatColor')) {
    function savedCatColor(string $name): string {
        $map = [
            'strength'     => 'oklch(52% 0.13 42)',
            'cardio'       => 'oklch(48% 0.13 25)',
            'flexibility'  => 'oklch(44% 0.10 152)',
            'balance'      => 'oklch(50% 0.10 240)',
            'hiit'         => 'oklch(46% 0.13 15)',
            'recovery'     => 'oklch(50% 0.08 190)',
            'weight loss'  => 'oklch(48% 0.11 320)',
            'conditioning' => 'oklch(46% 0.13 15)',
            'full body'    => 'oklch(46% 0.09 55)',
        ];
        return $map[strtolower($name)] ?? 'oklch(44% 0.04 50)';
    }
}

$bodyClass   = 'photo-bg bg-clay';
$pageTitle   = 'Saved Workouts';
$topBarTitle = date('l, j F');
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.sv-eyebrow {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .69rem;
    letter-spacing: .14em; text-transform: uppercase;
    color: rgba(255,255,255,.55); margin-bottom: 6px;
}
.sv-h1 {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 2.375rem;
    color: #fff; letter-spacing: .02em; line-height: 1;
    text-shadow: 0 1px 8px rgba(0,0,0,.3); margin: 0 0 6px;
}
.sv-sub { color: rgba(255,255,255,.55); font-size: .84rem; margin: 0 0 28px; }

.sv-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.sv-card {
    background: rgba(255,255,255,.62);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,.48);
    border-radius: 16px;
    padding: 22px 22px 18px;
    box-shadow: 0 3px 14px rgba(0,0,0,.1);
    display: flex; flex-direction: column;
    transition: all .2s; position: relative; overflow: hidden;
}
.sv-card:hover {
    background: rgba(255,255,255,.8);
    box-shadow: 0 10px 30px rgba(0,0,0,.16);
    transform: translateY(-3px);
}
.sv-card-star {
    position: absolute; top: 14px; right: -24px;
    background: oklch(72% 0.14 88);
    color: oklch(22% 0.06 88);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .65rem;
    letter-spacing: .1em; text-transform: uppercase;
    padding: 3px 32px;
    transform: rotate(45deg);
}
.sv-card-cat {
    color: #fff; padding: 2px 9px; border-radius: 4px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .66rem;
    letter-spacing: .08em; text-transform: uppercase;
    display: inline-block; margin-bottom: 10px;
}
.sv-card-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1.3rem;
    letter-spacing: .03em; text-transform: uppercase;
    color: oklch(16% 0.03 50); line-height: 1.1; margin-bottom: 5px;
}
.sv-card-trainer {
    font-size: .78rem; color: oklch(48% 0.03 50); margin-bottom: 14px;
}
.sv-card-actions { display: flex; gap: 8px; margin-top: auto; }
.sv-btn-view {
    padding: 7px 16px;
    border: 1.5px solid rgba(0,0,0,.15); border-radius: 8px;
    background: rgba(255,255,255,.6); color: oklch(28% 0.04 50);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .82rem;
    letter-spacing: .06em; text-transform: uppercase;
    text-decoration: none; transition: all .15s;
}
.sv-btn-view:hover {
    background: rgba(255,255,255,.9);
    color: oklch(18% 0.03 50);
    border-color: rgba(0,0,0,.25);
}
.sv-btn-remove {
    padding: 7px 16px; border-radius: 8px; border: none;
    background: oklch(50% 0.15 20 / 0.12);
    color: oklch(44% 0.14 20);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .82rem;
    letter-spacing: .06em; text-transform: uppercase;
    cursor: pointer; transition: all .15s;
}
.sv-btn-remove:hover { background: oklch(50% 0.15 20 / 0.22); }
.sv-btn-remove.confirming {
    background: oklch(50% 0.15 20);
    color: #fff;
}

.sv-empty {
    background: rgba(255,255,255,.55);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,.4);
    border-radius: 14px; padding: 60px 28px; text-align: center;
}
.sv-empty-h {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1.375rem;
    text-transform: uppercase; letter-spacing: .04em;
    color: oklch(38% 0.04 50); margin-bottom: 8px;
}
.sv-empty-s { font-size: .85rem; color: oklch(52% 0.03 50); margin-bottom: 18px; }
.sv-empty-link {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 22px; border-radius: 10px;
    background: oklch(58% 0.14 42); color: #fff;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .9rem;
    letter-spacing: .06em; text-transform: uppercase;
    text-decoration: none; transition: background .15s;
}
.sv-empty-link:hover { background: oklch(54% 0.14 42); color: #fff; }
</style>

<div class="slide-down" style="animation-delay:0ms">
    <div class="sv-eyebrow">My Collection</div>
    <h1 class="sv-h1">Saved Workouts</h1>
    <p class="sv-sub"><?= count($saved) ?> workout<?= count($saved) != 1 ? 's' : '' ?> saved</p>
</div>

<?php if (!$saved): ?>

<div class="sv-empty pop" style="animation-delay:100ms">
    <div class="sv-empty-h">Nothing saved yet</div>
    <div class="sv-empty-s">Browse trainer programs and save the ones you want to follow.</div>
    <a href="<?= BASE_URL ?>/pages/user/workouts.php" class="sv-empty-link">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Browse Workouts
    </a>
</div>

<?php else: ?>

<div class="sv-grid">
<?php foreach ($saved as $i => $w): ?>
    <div class="sv-card rise" style="animation-delay:<?= 80 + $i * 70 ?>ms">
        <div class="sv-card-star">&#9733; Saved</div>

        <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:2px">
            <?php if ($w['category_name']): ?>
                <span class="sv-card-cat" style="background:<?= savedCatColor($w['category_name']) ?>">
                    <?= htmlspecialchars($w['category_name']) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($w['difficulty'])):
                $dc = match($w['difficulty']) { 'beginner' => 'oklch(44% 0.12 152)', 'intermediate' => 'oklch(52% 0.13 88)', 'advanced' => 'oklch(50% 0.15 20)' };
                $dl = ucfirst($w['difficulty']);
            ?>
                <span class="sv-card-cat" style="background:<?= $dc ?>"><?= $dl ?></span>
            <?php endif; ?>
        </div>

        <div class="sv-card-title"><?= htmlspecialchars($w['title']) ?></div>
        <div class="sv-card-trainer">by <?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?></div>

        <div class="sv-card-actions">
            <a href="<?= BASE_URL ?>/pages/user/workouts.php?id=<?= $w['id'] ?>" class="sv-btn-view">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;vertical-align:-1px"><polyline points="9 18 15 12 9 6"/></svg>View
            </a>
            <form method="POST">
                <input type="hidden" name="action"     value="unsave">
                <input type="hidden" name="workout_id" value="<?= $w['id'] ?>">
                <button class="sv-btn-remove" type="button"
                    onclick="confirmRemove(this)">Remove</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<script>
function confirmRemove(btn) {
    if (btn.dataset.confirmed === '1') {
        btn.closest('form').submit();
        return;
    }
    btn.dataset.confirmed = '1';
    btn.textContent = 'Confirm Remove?';
    btn.classList.add('confirming');
    setTimeout(() => {
        if (btn.dataset.confirmed === '1') {
            btn.dataset.confirmed = '';
            btn.textContent = 'Remove';
            btn.classList.remove('confirming');
        }
    }, 3000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
