<?php
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Mailer.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireRole('admin');

$db  = Database::getInstance();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // Fetch trainer before any destructive action
    $trainerStmt = $db->prepare('SELECT email, first_name, last_name FROM users WHERE id = :id AND role = "trainer"');
    $trainerStmt->execute([':id' => $id]);
    $trainerRow = $trainerStmt->fetch();

    if ($action === 'approve') {
        $db->prepare('UPDATE users SET is_approved = 1 WHERE id = :id AND role = "trainer"')
           ->execute([':id' => $id]);
        $msg = 'Trainer approved.';
        if ($trainerRow) {
            Mailer::sendTrainerApproved($trainerRow['email'], $trainerRow['first_name'] . ' ' . $trainerRow['last_name']);
        }
    } elseif ($action === 'reject') {
        if ($trainerRow) {
            Mailer::sendTrainerRejected($trainerRow['email'], $trainerRow['first_name'] . ' ' . $trainerRow['last_name']);
        }
        $db->prepare('DELETE FROM users WHERE id = :id AND role = "trainer" AND is_approved = 0')
           ->execute([':id' => $id]);
        $msg = 'Trainer removed.';
    } elseif ($action === 'revoke') {
        $db->prepare('UPDATE users SET is_approved = 0 WHERE id = :id AND role = "trainer"')
           ->execute([':id' => $id]);
        $msg = 'Approval revoked.';
    }
}

$pending  = $db->query('SELECT * FROM users WHERE role = "trainer" AND is_active = 1 AND is_approved = 0 AND is_banned = 0 ORDER BY created_at DESC')->fetchAll();
$approved = $db->query('SELECT * FROM users WHERE role = "trainer" AND is_approved = 1 ORDER BY first_name')->fetchAll();

$bodyClass = 'photo-bg bg-clay';
$pageTitle = 'Trainers';
require_once __DIR__ . '/../../includes/header.php';

function trainerInitials(array $t): string {
    return strtoupper(substr($t['first_name'], 0, 1) . substr($t['last_name'], 0, 1));
}
?>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4 pop">
        <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Title -->
<div class="slide-down" style="animation-delay:0ms;margin-bottom:24px">
    <div class="page-sup" style="font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.69rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.55);margin-bottom:6px">Admin</div>
    <h1 style="font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:2.375rem;color:#fff;letter-spacing:.02em;line-height:1;text-shadow:0 1px 8px rgba(0,0,0,.3);margin:0 0 6px">Approve Trainers</h1>
    <p style="color:rgba(255,255,255,.55);font-size:.84rem;margin:0">Review and manage trainer accounts.</p>
</div>

<!-- 3 stat cards -->
<div class="trainer-page-header" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:36px">

    <div class="trainer-meta-card pop" style="background:linear-gradient(135deg,oklch(52% 0.13 88),oklch(44% 0.11 78));animation-delay:60ms">
        <div class="mc-label">Pending Approval</div>
        <div class="mc-value" data-countup="<?= count($pending) ?>">0</div>
        <div class="mc-sub">trainers awaiting review</div>
    </div>

    <div class="trainer-meta-card pop" style="background:linear-gradient(135deg,oklch(38% 0.09 152),oklch(30% 0.07 155));animation-delay:140ms">
        <div class="mc-label">Approved Trainers</div>
        <div class="mc-value" data-countup="<?= count($approved) ?>">0</div>
        <div class="mc-sub">active trainer accounts</div>
    </div>

    <div class="trainer-meta-card pop" style="background:linear-gradient(135deg,oklch(52% 0.13 42),oklch(44% 0.11 36));animation-delay:220ms">
        <div class="mc-label">Total</div>
        <div class="mc-value" data-countup="<?= count($pending) + count($approved) ?>">0</div>
        <div class="mc-sub">trainer accounts registered</div>
    </div>

</div>

<!-- ── Pending ──────────────────────────────────────────────── -->
<div class="trainer-section-heading">
    Pending Approval
    <?php if ($pending): ?>
        <span class="trainer-section-count"><?= count($pending) ?></span>
    <?php endif; ?>
</div>

<div class="trainer-list">
<?php if (!$pending): ?>
    <div class="trainer-empty rise" style="animation-delay:300ms">No trainers pending approval.</div>
<?php else: ?>
    <?php foreach ($pending as $i => $t):
        $initials = trainerInitials($t);
        $delay    = 300 + $i * 70;
        $name     = htmlspecialchars($t['first_name'] . ' ' . $t['last_name']);
        $email    = htmlspecialchars($t['email']);
        $regDate  = date('d M Y', strtotime($t['created_at']));
    ?>
    <div class="trainer-card rise" style="animation-delay:<?= $delay ?>ms">
        <div class="trainer-card-watermark" style="color:var(--accent-gold)"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></div>
        <div class="trainer-card-num" style="color:var(--accent-gold)"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></div>
        <div class="trainer-avatar" style="background:linear-gradient(135deg,var(--accent-gold),oklch(58% 0.11 78))"><?= $initials ?></div>
        <div class="trainer-info">
            <div class="trainer-info-name"><?= $name ?></div>
            <div class="trainer-info-meta">
                <span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <?= $email ?>
                </span>
                <span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Registered <?= $regDate ?>
                </span>
            </div>
        </div>
        <span class="badge-pending">Pending</span>
        <div class="trainer-actions">
            <form method="POST" class="confirm-form" data-confirm-text="Confirm Approve?" data-btn-class="btn btn-sm btn-success">
                <input type="hidden" name="id"     value="<?= $t['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button type="button" class="btn btn-sm btn-success confirm-btn"
                    style="font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:0.81rem;letter-spacing:0.06em;text-transform:uppercase">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;vertical-align:-2px"><polyline points="20 6 9 17 4 12"/></svg>Approve
                </button>
            </form>
            <form method="POST" class="confirm-form" data-confirm-text="Confirm Reject?" data-btn-class="btn btn-sm btn-danger">
                <input type="hidden" name="id"     value="<?= $t['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <button type="button" class="btn btn-sm btn-outline-danger confirm-btn"
                    style="font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:0.81rem;letter-spacing:0.06em;text-transform:uppercase">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;vertical-align:-2px"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Reject
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- ── Approved ─────────────────────────────────────────────── -->
<div class="trainer-section-heading" style="margin-top:8px">Approved Trainers</div>

<div class="trainer-list">
<?php if (!$approved): ?>
    <div class="trainer-empty rise" style="animation-delay:400ms">No approved trainers yet.</div>
<?php else: ?>
    <?php foreach ($approved as $i => $t):
        $initials = trainerInitials($t);
        $delay    = 400 + $i * 70;
        $name     = htmlspecialchars($t['first_name'] . ' ' . $t['last_name']);
        $email    = htmlspecialchars($t['email']);
        $phone    = htmlspecialchars($t['phone'] ?? '');
    ?>
    <div class="trainer-card rise" style="animation-delay:<?= $delay ?>ms">
        <div class="trainer-card-watermark" style="color:var(--accent)"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></div>
        <div class="trainer-card-num" style="color:var(--accent)"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></div>
        <div class="trainer-avatar" style="background:linear-gradient(135deg,var(--accent),oklch(44% 0.11 36))"><?= $initials ?></div>
        <div class="trainer-info">
            <div class="trainer-info-name"><?= $name ?></div>
            <div class="trainer-info-meta">
                <span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <?= $email ?>
                </span>
                <?php if ($phone): ?>
                <span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.63A2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006.18 6.18l1.27-.82a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                    <?= $phone ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <span class="badge-approved">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Approved
        </span>
        <div class="trainer-revoke-wrap">
            <form method="POST" class="confirm-form" data-confirm-text="Confirm Revoke?" data-btn-class="btn btn-sm btn-danger">
                <input type="hidden" name="id"     value="<?= $t['id'] ?>">
                <input type="hidden" name="action" value="revoke">
                <button type="button" class="btn btn-sm btn-outline-danger confirm-btn"
                    style="font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:0.75rem;letter-spacing:0.06em;text-transform:uppercase">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:3px;vertical-align:-2px"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Revoke
                </button>
            </form>
        </div>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent);opacity:0.25;flex-shrink:0;transition:opacity 0.18s" class="trainer-chevron"><polyline points="9 18 15 12 9 6"/></svg>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<script>
// Two-step confirm buttons
document.querySelectorAll('.confirm-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const form        = this.closest('.confirm-form');
        const confirmText = form.dataset.confirmText;
        const btnClass    = form.dataset.btnClass;

        if (this.dataset.confirmed === '1') {
            form.submit();
            return;
        }

        // First click — switch to confirm state
        this.dataset.confirmed = '1';
        this.innerHTML = confirmText;
        this.className = btnClass;
        this.style.cssText = 'font-family:\'Barlow Condensed\',sans-serif;font-weight:700;font-size:0.81rem;letter-spacing:0.06em;text-transform:uppercase';

        // Auto-reset after 3 seconds
        setTimeout(() => {
            if (this.dataset.confirmed === '1') {
                this.dataset.confirmed = '';
                location.reload();
            }
        }, 3000);
    });
});

// Count-up animation for stat cards
document.querySelectorAll('[data-countup]').forEach(el => {
    const target = parseInt(el.dataset.countup) || 0;
    if (target === 0) return;
    let current = 0;
    const inc = target / (800 / 16);
    const t = setInterval(() => {
        current += inc;
        if (current >= target) { el.textContent = target; clearInterval(t); }
        else { el.textContent = Math.floor(current); }
    }, 16);
});

// Hover chevron opacity on approved cards
document.querySelectorAll('.trainer-card').forEach(card => {
    const chevron = card.querySelector('.trainer-chevron');
    if (!chevron) return;
    card.addEventListener('mouseenter', () => chevron.style.opacity = '0.5');
    card.addEventListener('mouseleave', () => chevron.style.opacity = '0.25');
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
