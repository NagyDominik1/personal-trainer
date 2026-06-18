<?php
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireRole('admin');

$db = Database::getInstance();

$stats = [
    'users'    => $db->query('SELECT COUNT(*) FROM users WHERE role = "user" AND is_banned = 0')->fetchColumn(),
    'trainers' => $db->query('SELECT COUNT(*) FROM users WHERE role = "trainer" AND is_approved = 1')->fetchColumn(),
    'pending'  => $db->query('SELECT COUNT(*) FROM users WHERE role = "trainer" AND is_approved = 0 AND is_active = 1')->fetchColumn(),
    'workouts' => $db->query('SELECT COUNT(*) FROM workouts WHERE is_published = 1 AND is_hidden = 0')->fetchColumn(),
    'drafts'   => $db->query('SELECT COUNT(*) FROM workouts WHERE is_published = 0')->fetchColumn(),
    'hidden'   => $db->query('SELECT COUNT(*) FROM workouts WHERE is_hidden = 1')->fetchColumn(),
    'reviews'  => $db->query('SELECT COUNT(*) FROM workout_reviews')->fetchColumn(),
    'saves'    => $db->query('SELECT COUNT(*) FROM user_workouts')->fetchColumn(),
];

$topSaved = $db->query('
    SELECT w.title, u.first_name, u.last_name,
        COUNT(uw.workout_id) AS saves,
        ROUND(AVG(r.rating),1) AS avg_rating
    FROM workouts w
    JOIN user_workouts uw ON uw.workout_id = w.id
    LEFT JOIN users u ON w.user_id = u.id
    LEFT JOIN workout_reviews r ON r.workout_id = w.id
    GROUP BY w.id
    ORDER BY saves DESC
    LIMIT 5
')->fetchAll();

$recentUsers = $db->query('
    SELECT first_name, last_name, role, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 6
')->fetchAll();

$pageTitle   = 'Dashboard';
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.adm-eyebrow { font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.69rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.55);margin-bottom:6px }
.adm-h1 { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:2.375rem;color:#fff;letter-spacing:.02em;line-height:1;text-shadow:0 1px 8px rgba(0,0,0,.3);margin:0 0 28px }
.adm-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-bottom:32px }
@media(max-width:860px){.adm-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:560px){.adm-grid{grid-template-columns:1fr}}
.adm-card { border-radius:14px;padding:22px 24px 18px;position:relative;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.14);transition:transform .2s,box-shadow .2s;color:#fff }
.adm-card:hover { transform:translateY(-2px);box-shadow:0 12px 32px rgba(0,0,0,.22) }
.adm-card::after { content:'';position:absolute;right:-14px;bottom:-14px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.07);pointer-events:none }
.adm-card-label { font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.66rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.65);margin-bottom:12px }
.adm-card-value { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:3rem;line-height:1;letter-spacing:-.02em;margin-bottom:6px }
.adm-card-sub { font-size:.78rem;color:rgba(255,255,255,.65) }
.adm-panel { background:rgba(255,255,255,.68);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.5);border-radius:16px;padding:24px 28px;box-shadow:0 4px 24px rgba(0,0,0,.1);margin-bottom:24px }
.adm-panel-title { font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1rem;letter-spacing:.08em;text-transform:uppercase;color:oklch(22% 0.04 50);margin-bottom:18px;display:flex;align-items:center;gap:8px }
.adm-two { display:grid;grid-template-columns:1fr 1fr;gap:24px }
@media(max-width:800px){.adm-two{grid-template-columns:1fr}}
</style>

<div class="slide-down" style="animation-delay:0ms">
    <div class="adm-eyebrow">Admin</div>
    <h1 class="adm-h1">Dashboard</h1>
</div>

<!-- Stat cards -->
<div class="adm-grid">
    <?php
    $cards = [
        ['Users',             $stats['users'],    $stats['users'].' active members',          'linear-gradient(135deg,oklch(52% 0.13 42),oklch(44% 0.11 36))'],
        ['Trainers',          $stats['trainers'], $stats['pending'].' pending approval',       'linear-gradient(135deg,oklch(38% 0.09 152),oklch(30% 0.07 155))'],
        ['Published Workouts',$stats['workouts'], $stats['drafts'].' drafts · '.$stats['hidden'].' hidden','linear-gradient(135deg,oklch(48% 0.13 25),oklch(40% 0.11 25))'],
        ['Total Saves',       $stats['saves'],    'across all workouts',                       'linear-gradient(135deg,oklch(52% 0.13 88),oklch(44% 0.11 78))'],
        ['Reviews',           $stats['reviews'],  'workout ratings submitted',                 'linear-gradient(135deg,oklch(46% 0.10 240),oklch(38% 0.09 240))'],
        ['Pending Trainers',  $stats['pending'],  'awaiting your approval',                    'linear-gradient(135deg,oklch(50% 0.15 20),oklch(42% 0.13 20))'],
    ];
    foreach ($cards as $i => [$label, $value, $sub, $bg]):
    ?>
    <div class="adm-card pop" style="background:<?= $bg ?>;animation-delay:<?= 60 + $i * 60 ?>ms">
        <div class="adm-card-label"><?= $label ?></div>
        <div class="adm-card-value" data-countup="<?= $value ?>">0</div>
        <div class="adm-card-sub"><?= $sub ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="adm-two">

    <!-- Top saved workouts -->
    <div class="adm-panel rise" style="animation-delay:420ms">
        <div class="adm-panel-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            Top Saved Programs
        </div>
        <?php if ($topSaved): ?>
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="border-bottom:2px solid rgba(0,0,0,.08)">
                    <th style="text-align:left;padding:0 0 10px;font-family:'Barlow Condensed',sans-serif;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:oklch(52% 0.03 50);font-weight:600">Workout</th>
                    <th style="text-align:right;padding:0 0 10px;font-family:'Barlow Condensed',sans-serif;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:oklch(52% 0.03 50);font-weight:600">Saves</th>
                    <th style="text-align:right;padding:0 0 10px;font-family:'Barlow Condensed',sans-serif;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:oklch(52% 0.03 50);font-weight:600">Rating</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($topSaved as $row): ?>
                <tr style="border-bottom:1px solid rgba(0,0,0,.06)">
                    <td style="padding:10px 0">
                        <div style="font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.95rem;text-transform:uppercase;letter-spacing:.02em;color:oklch(18% 0.03 50)"><?= htmlspecialchars($row['title']) ?></div>
                        <div style="font-size:.75rem;color:oklch(52% 0.03 50)"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                    </td>
                    <td style="text-align:right;padding:10px 0;font-family:'Barlow Condensed',sans-serif;font-weight:700;color:oklch(52% 0.13 88)">&#9733; <?= $row['saves'] ?></td>
                    <td style="text-align:right;padding:10px 0;font-size:.85rem;color:oklch(44% 0.03 50)"><?= $row['avg_rating'] ? $row['avg_rating'] . ' ★' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div style="font-size:.85rem;color:oklch(52% 0.03 50);font-style:italic">No saves yet.</div>
        <?php endif; ?>
    </div>

    <!-- Recent registrations -->
    <div class="adm-panel rise" style="animation-delay:480ms">
        <div class="adm-panel-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            Recent Registrations
        </div>
        <?php foreach ($recentUsers as $u): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid rgba(0,0,0,.06)">
            <div>
                <div style="font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.95rem;text-transform:uppercase;letter-spacing:.02em;color:oklch(18% 0.03 50)"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                <div style="font-size:.75rem;color:oklch(52% 0.03 50)"><?= date('d M Y', strtotime($u['created_at'])) ?></div>
            </div>
            <?php
            $bc = match($u['role']) { 'trainer' => 'oklch(52% 0.13 88)', 'admin' => 'oklch(50% 0.15 20)', default => 'oklch(44% 0.04 50)' };
            ?>
            <span style="font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.66rem;letter-spacing:.1em;text-transform:uppercase;color:#fff;background:<?= $bc ?>;padding:2px 9px;border-radius:4px"><?= htmlspecialchars($u['role']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<script>
document.querySelectorAll('[data-countup]').forEach(el => {
    const target = parseInt(el.dataset.countup) || 0;
    if (!target) return;
    let cur = 0, inc = target / (700 / 16);
    const t = setInterval(() => {
        cur += inc;
        if (cur >= target) { el.textContent = target; clearInterval(t); }
        else el.textContent = Math.floor(cur);
    }, 16);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
