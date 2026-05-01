<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$user    = new User();
$userId  = (int) $_SESSION['user_id'];
$current = $user->findById($userId);
$role    = $_SESSION['user_role'] ?? 'user';

$profileMsg  = '';
$profileType = 'success';
$pwMsg       = '';
$pwType      = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $ok = $user->updateProfile($userId, [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name'  => trim($_POST['last_name']  ?? ''),
            'phone'      => trim($_POST['phone']      ?? ''),
            'bio'        => trim($_POST['bio']        ?? ''),
        ]);
        if ($ok) {
            $_SESSION['user_name'] = trim($_POST['first_name']) . ' ' . trim($_POST['last_name']);
            $current     = $user->findById($userId);
            $profileMsg  = 'Profile updated successfully.';
        } else {
            $profileMsg  = 'Could not update profile.';
            $profileType = 'danger';
        }
    }

    if ($action === 'password') {
        $current_pw = $_POST['current_password'] ?? '';
        $new_pw     = $_POST['new_password']     ?? '';
        $confirm_pw = $_POST['confirm_password'] ?? '';

        if (!password_verify($current_pw, $current['password_hash'])) {
            $pwMsg  = 'Current password is incorrect.';
            $pwType = 'danger';
        } elseif (strlen($new_pw) < 8) {
            $pwMsg  = 'New password must be at least 8 characters.';
            $pwType = 'danger';
        } elseif ($new_pw !== $confirm_pw) {
            $pwMsg  = 'Passwords do not match.';
            $pwType = 'danger';
        } else {
            $user->updatePassword($userId, $new_pw);
            $pwMsg = 'Password changed successfully.';
        }
    }
}

// Build initials for avatar
$nameParts = explode(' ', $current['first_name'] . ' ' . $current['last_name']);
$initials  = strtoupper(substr($nameParts[0] ?? '', 0, 1) . substr($nameParts[1] ?? '', 0, 1));

$pageTitle   = 'Profile';
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Hero ──────────────────────────────────────────────── */
.prof-hero {
    display: flex;
    align-items: center;
    gap: 28px;
    margin-bottom: 36px;
}
.prof-avatar {
    width: 88px; height: 88px;
    border-radius: 50%;
    background: linear-gradient(135deg, oklch(58% 0.14 42), oklch(44% 0.11 36));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800; font-size: 2rem;
    color: #fff; letter-spacing: .02em;
    box-shadow: 0 6px 24px rgba(0,0,0,.28);
    border: 3px solid rgba(255,255,255,.35);
    flex-shrink: 0;
}
.prof-name {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800; font-size: 2.5rem;
    color: #fff; letter-spacing: .02em; line-height: 1;
    text-shadow: 0 2px 10px rgba(0,0,0,.3);
}
.prof-meta {
    display: flex; align-items: center; gap: 12px;
    margin-top: 8px; flex-wrap: wrap;
}
.prof-role-badge {
    background: oklch(72% 0.14 88);
    color: oklch(22% 0.06 88);
    padding: 3px 10px; border-radius: 4px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .69rem;
    letter-spacing: .1em; text-transform: uppercase;
}
.prof-phone { color: rgba(255,255,255,.6); font-size: .85rem; }
.prof-bio { color: rgba(255,255,255,.55); font-size: .82rem; margin-top: 6px; font-style: italic; max-width: 440px; }

/* ── Two-panel grid ────────────────────────────────────── */
.prof-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}
@media (max-width: 860px) { .prof-grid { grid-template-columns: 1fr; } }

/* ── Glass panel ───────────────────────────────────────── */
.prof-panel {
    background: rgba(255,255,255,.7);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,.55);
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 4px 24px rgba(0,0,0,.12);
}
.prof-panel-hdr {
    display: flex; align-items: center; gap: 10px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800; font-size: 1.25rem;
    letter-spacing: .06em; text-transform: uppercase;
    color: oklch(18% 0.03 50);
    margin-bottom: 24px;
}
.prof-panel-icon {
    width: 32px; height: 32px; border-radius: 8px;
    background: rgba(139,90,43,.1);
    color: oklch(58% 0.14 42);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.prof-panel-icon--lock {
    background: rgba(56,96,68,.1);
    color: oklch(38% 0.09 152);
}

/* ── Form fields ───────────────────────────────────────── */
.prof-fields { display: flex; flex-direction: column; gap: 18px; }
.prof-row { display: flex; gap: 14px; }
.prof-field { display: flex; flex-direction: column; gap: 6px; flex: 1; }
.prof-label {
    font-family: 'Barlow', sans-serif;
    font-weight: 600; font-size: .78rem;
    color: oklch(34% 0.04 50); letter-spacing: .01em;
}
.prof-input, .prof-textarea {
    width: 100%; padding: 10px 14px;
    background: rgba(255,255,255,.8);
    border: 1.5px solid rgba(0,0,0,.1);
    border-radius: 9px; font-size: .875rem;
    font-family: 'Barlow', sans-serif;
    color: oklch(18% 0.03 50); outline: none;
    transition: border-color .15s, background .15s, box-shadow .15s;
}
.prof-input:focus, .prof-textarea:focus {
    border-color: oklch(58% 0.14 42);
    background: rgba(255,255,255,.95);
    box-shadow: 0 0 0 3px oklch(58% 0.14 42 / .15);
}
.prof-textarea { resize: vertical; min-height: 80px; }

/* ── Buttons ───────────────────────────────────────────── */
.prof-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 10px 22px; border: none; border-radius: 9px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .94rem;
    letter-spacing: .06em; text-transform: uppercase;
    cursor: pointer; transition: all .2s;
    box-shadow: 0 3px 10px rgba(0,0,0,.14);
}
.prof-btn--save {
    background: oklch(58% 0.14 42); color: #fff;
}
.prof-btn--save:hover { background: oklch(54% 0.14 42); transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,.2); }
.prof-btn--pw {
    background: oklch(38% 0.09 152); color: #fff;
}
.prof-btn--pw:hover { background: oklch(34% 0.08 152); transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,.2); }

/* ── Alert ─────────────────────────────────────────────── */
.prof-alert {
    padding: 10px 14px; border-radius: 8px;
    font-size: .82rem; margin-bottom: 16px;
}
.prof-alert--success { background: oklch(95% 0.04 152); border: 1px solid oklch(75% 0.1 152); color: oklch(30% 0.08 152); }
.prof-alert--danger  { background: oklch(95% 0.03 20);  border: 1px solid oklch(75% 0.12 20);  color: oklch(42% 0.14 20); }

/* ── Security tips ─────────────────────────────────────── */
.prof-tips {
    margin-top: 28px; padding-top: 20px;
    border-top: 1px solid rgba(0,0,0,.08);
}
.prof-tips-label {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .66rem;
    letter-spacing: .12em; text-transform: uppercase;
    color: oklch(55% 0.03 50); margin-bottom: 10px;
}
.prof-tip {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 6px;
}
.prof-tip-dot {
    width: 16px; height: 16px; border-radius: 50%;
    background: oklch(72% 0.14 88 / .2);
    color: oklch(72% 0.14 88);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.prof-tip span { font-size: .78rem; color: oklch(46% 0.03 50); }
</style>

<!-- Hero -->
<div class="prof-hero slide-down" style="animation-delay:0ms">
    <div class="prof-avatar"><?= htmlspecialchars($initials) ?></div>
    <div>
        <div class="prof-name"><?= htmlspecialchars($current['first_name'] . ' ' . $current['last_name']) ?></div>
        <div class="prof-meta">
            <span class="prof-role-badge"><?= htmlspecialchars($role) ?></span>
            <?php if ($current['phone']): ?>
                <span class="prof-phone"><?= htmlspecialchars($current['phone']) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($current['bio']): ?>
            <p class="prof-bio"><?= htmlspecialchars($current['bio']) ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Two panels -->
<div class="prof-grid">

    <!-- Personal Details -->
    <div class="prof-panel rise" style="animation-delay:120ms">
        <div class="prof-panel-hdr">
            <div class="prof-panel-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            </div>
            Personal Details
        </div>

        <?php if ($profileMsg): ?>
            <div class="prof-alert prof-alert--<?= $profileType ?>"><?= htmlspecialchars($profileMsg) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="profile">
            <div class="prof-fields">
                <div class="prof-row">
                    <div class="prof-field">
                        <label class="prof-label">First name</label>
                        <input type="text" name="first_name" class="prof-input"
                               value="<?= htmlspecialchars($current['first_name']) ?>" required>
                    </div>
                    <div class="prof-field">
                        <label class="prof-label">Last name</label>
                        <input type="text" name="last_name" class="prof-input"
                               value="<?= htmlspecialchars($current['last_name']) ?>" required>
                    </div>
                </div>
                <div class="prof-field">
                    <label class="prof-label">Phone</label>
                    <input type="text" name="phone" class="prof-input"
                           value="<?= htmlspecialchars($current['phone'] ?? '') ?>"
                           placeholder="e.g. +36 70 123 4567">
                </div>
                <div class="prof-field">
                    <label class="prof-label">Bio</label>
                    <textarea name="bio" class="prof-textarea"
                              placeholder="Tell your clients about yourself…"><?= htmlspecialchars($current['bio'] ?? '') ?></textarea>
                </div>
                <div>
                    <button type="submit" class="prof-btn prof-btn--save">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Change Password -->
    <div class="prof-panel rise" style="animation-delay:220ms">
        <div class="prof-panel-hdr">
            <div class="prof-panel-icon prof-panel-icon--lock">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            </div>
            Change Password
        </div>

        <?php if ($pwMsg): ?>
            <div class="prof-alert prof-alert--<?= $pwType ?>"><?= htmlspecialchars($pwMsg) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="password">
            <div class="prof-fields">
                <div class="prof-field">
                    <label class="prof-label">Current password</label>
                    <input type="password" name="current_password" class="prof-input" placeholder="••••••••" required autocomplete="current-password">
                </div>
                <div class="prof-field">
                    <label class="prof-label">New password</label>
                    <input type="password" name="new_password" class="prof-input" placeholder="Min. 8 characters" required minlength="8" autocomplete="new-password">
                </div>
                <div class="prof-field">
                    <label class="prof-label">Confirm new password</label>
                    <input type="password" name="confirm_password" class="prof-input" placeholder="••••••••" required autocomplete="new-password">
                </div>
                <div>
                    <button type="submit" class="prof-btn prof-btn--pw">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        Change Password
                    </button>
                </div>
            </div>
        </form>

        <!-- Security tips -->
        <div class="prof-tips">
            <div class="prof-tips-label">Security tips</div>
            <?php foreach (['Use at least 8 characters', 'Mix letters, numbers & symbols', 'Never reuse passwords'] as $tip): ?>
            <div class="prof-tip">
                <div class="prof-tip-dot">
                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <span><?= $tip ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
