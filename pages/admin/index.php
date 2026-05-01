<?php
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireRole('admin');

$db  = Database::getInstance();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $stmt = $db->prepare('INSERT INTO categories (name, description) VALUES (:name, :desc)');
        $stmt->execute([':name' => trim($_POST['name']), ':desc' => trim($_POST['description'])]);
        $msg = 'Category added.';

    } elseif ($action === 'edit') {
        $stmt = $db->prepare('UPDATE categories SET name = :name, description = :desc WHERE id = :id');
        $stmt->execute([':name' => trim($_POST['name']), ':desc' => trim($_POST['description']), ':id' => (int)$_POST['id']]);
        $msg = 'Category updated.';

    } elseif ($action === 'delete') {
        $stmt = $db->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute([':id' => (int)$_POST['id']]);
        $msg = 'Category deleted.';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$categories = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();

function adminCatColor(string $name): string {
    $map = [
        'conditioning' => 'oklch(50% 0.10 240)',
        'flexibility'  => 'oklch(44% 0.10 152)',
        'hiit'         => 'oklch(46% 0.13 15)',
        'strength'     => 'oklch(52% 0.13 42)',
        'weight loss'  => 'oklch(48% 0.11 320)',
        'cardio'       => 'oklch(48% 0.13 25)',
        'balance'      => 'oklch(50% 0.10 240)',
        'recovery'     => 'oklch(50% 0.08 190)',
        'full body'    => 'oklch(46% 0.09 55)',
    ];
    return $map[strtolower($name)] ?? 'oklch(58% 0.14 42)';
}

$pageTitle   = 'Categories';
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
@keyframes riseUp    { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
@keyframes slideDown { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
@keyframes popIn     { from { opacity:0; transform:scale(0.94); } to { opacity:1; transform:scale(1); } }
.rise       { animation: riseUp    0.45s cubic-bezier(.2,0,.2,1) both; }
.pop        { animation: popIn     0.4s  cubic-bezier(.2,0,.2,1) both; }
.slide-down { animation: slideDown 0.4s  cubic-bezier(.2,0,.2,1) both; }

:root {
    --accent:      oklch(58% 0.14 42);
    --accent-dark: oklch(44% 0.11 36);
}

/* ── Heading ───────────────────────────────────────────── */
.cat-eyebrow {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .69rem;
    letter-spacing: .14em; text-transform: uppercase;
    color: rgba(255,255,255,.55); margin-bottom: 6px;
}
.cat-h1 {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 2.375rem;
    color: #fff; letter-spacing: .02em; line-height: 1;
    text-shadow: 0 1px 8px rgba(0,0,0,.3); margin: 0 0 6px;
}
.cat-sub { color: rgba(255,255,255,.55); font-size: .82rem; }

/* ── Add button ────────────────────────────────────────── */
.btn-add-cat {
    background: var(--accent); color: #fff;
    border: none; border-radius: 10px;
    padding: 11px 22px; cursor: pointer;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1rem;
    letter-spacing: .06em; text-transform: uppercase;
    display: inline-flex; align-items: center; gap: 7px;
    box-shadow: 0 3px 12px rgba(0,0,0,.15);
    transition: all .15s;
}
.btn-add-cat:hover {
    background: oklch(54% 0.14 42);
    box-shadow: 0 6px 20px rgba(0,0,0,.22);
    transform: translateY(-1px);
}

/* ── Flash message ─────────────────────────────────────── */
.cat-flash {
    background: rgba(255,255,255,.82);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(255,255,255,.6);
    border-radius: 10px;
    padding: 12px 20px;
    margin-bottom: 20px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .9rem;
    color: oklch(28% 0.07 152);
    display: flex; align-items: center; gap: 8px;
    animation: slideDown .35s cubic-bezier(.2,0,.2,1) both;
}

/* ── Category row ──────────────────────────────────────── */
.cat-list { display: flex; flex-direction: column; gap: 10px; }
.cat-row {
    display: flex; align-items: center; gap: 22px;
    padding: 18px 28px;
    background: rgba(255,255,255,.58);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,.45);
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(0,0,0,.08);
    position: relative; overflow: hidden;
    transition: all .18s;
}
.cat-row:hover {
    background: rgba(255,255,255,.78);
    border-color: rgba(255,255,255,.95);
    box-shadow: 0 10px 28px rgba(0,0,0,.15);
    transform: translateX(4px);
}
.cat-row-watermark {
    position: absolute; right: 70px; top: 50%;
    transform: translateY(-50%);
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800; font-size: 90px; line-height: 1;
    opacity: .06; pointer-events: none; user-select: none;
}
.cat-row-num {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .8rem;
    letter-spacing: .06em; min-width: 28px; flex-shrink: 0;
    opacity: .75;
}
.cat-row-icon {
    width: 40px; height: 40px; border-radius: 10px;
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
}
.cat-row-body { flex: 1; min-width: 0; }
.cat-row-name {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1.5rem;
    letter-spacing: .03em; text-transform: uppercase;
    color: oklch(16% 0.03 50); line-height: 1;
}
.cat-row-desc { font-size: .78rem; color: oklch(50% 0.03 50); margin-top: 4px; }
.cat-row-badge {
    color: #fff; padding: 3px 10px; border-radius: 4px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .66rem;
    letter-spacing: .08em; text-transform: uppercase;
    flex-shrink: 0;
}
.cat-row-actions {
    display: flex; gap: 8px; flex-shrink: 0;
    opacity: 0; transition: opacity .15s;
}
.cat-row:hover .cat-row-actions { opacity: 1; }
.cat-btn-edit {
    display: flex; align-items: center; gap: 5px;
    padding: 6px 14px;
    border: 1.5px solid rgba(139,90,43,.53);
    border-radius: 7px;
    background: rgba(139,90,43,.07);
    color: var(--accent);
    cursor: pointer;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .75rem;
    letter-spacing: .06em; text-transform: uppercase;
    transition: all .15s;
}
.cat-btn-edit:hover { background: rgba(139,90,43,.16); }
.cat-btn-del {
    display: flex; align-items: center; gap: 5px;
    padding: 6px 14px;
    border: 1.5px solid rgba(180,40,40,.35);
    border-radius: 7px;
    background: rgba(180,40,40,.07);
    color: oklch(46% 0.15 15);
    cursor: pointer;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: .75rem;
    letter-spacing: .06em; text-transform: uppercase;
    transition: all .15s;
}
.cat-btn-del:hover, .cat-btn-del--confirm {
    background: oklch(52% 0.13 15);
    border-color: oklch(52% 0.13 15);
    color: #fff;
}
.cat-row-arrow { opacity: .25; flex-shrink: 0; transition: opacity .18s; }
.cat-row:hover .cat-row-arrow { opacity: .55; }

/* ── Empty state ───────────────────────────────────────── */
.cat-empty {
    background: rgba(255,255,255,.55);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,.4);
    border-radius: 14px;
    padding: 60px 28px; text-align: center;
}
.cat-empty-h {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 700; font-size: 1.375rem;
    text-transform: uppercase; letter-spacing: .04em;
    color: oklch(38% 0.04 50); margin-bottom: 8px;
}
.cat-empty-s { font-size: .85rem; color: oklch(52% 0.03 50); margin-bottom: 22px; }

/* ── Drawer ────────────────────────────────────────────── */
.drawer-backdrop {
    position: fixed; inset: 0;
    background: rgba(10,5,2,.48);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    z-index: 200; opacity: 0; pointer-events: none;
    transition: opacity .22s;
}
.drawer-backdrop.open { opacity: 1; pointer-events: auto; }
.drawer {
    position: fixed; top: 0; right: 0; bottom: 0; width: 400px;
    background: rgba(252,247,241,.97);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    box-shadow: -8px 0 48px rgba(0,0,0,.22);
    z-index: 201;
    display: flex; flex-direction: column;
    transform: translateX(100%);
    transition: transform .28s cubic-bezier(.4,0,.2,1);
    overflow-y: auto;
}
.drawer.open { transform: translateX(0); }
.drawer-hdr {
    padding: 28px 28px 20px;
    border-bottom: 1px solid rgba(0,0,0,.08);
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.drawer-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800; font-size: 1.5rem;
    letter-spacing: .06em; text-transform: uppercase;
    color: oklch(18% 0.03 50);
    display: flex; align-items: center; gap: 10px;
}
.drawer-title-icon {
    width: 34px; height: 34px; border-radius: 9px;
    background: rgba(139,90,43,.09); color: var(--accent);
    display: flex; align-items: center; justify-content: center;
}
.drawer-close {
    background: rgba(0,0,0,.07); border: none; border-radius: 8px;
    width: 34px; height: 34px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: oklch(44% 0.03 50); font-size: 1rem;
    transition: background .15s;
}
.drawer-close:hover { background: rgba(0,0,0,.13); }
.drawer-form { flex: 1; padding: 26px 28px 32px; display: flex; flex-direction: column; gap: 20px; }
.drawer-field { display: flex; flex-direction: column; gap: 6px; }
.drawer-lbl {
    font-family: 'Barlow', sans-serif; font-weight: 600;
    font-size: .8rem; color: oklch(28% 0.04 50);
}
.drawer-lbl .req { color: var(--accent); }
.drawer-input, .drawer-textarea {
    width: 100%; padding: 9px 12px;
    background: rgba(255,255,255,.85);
    border: 1.5px solid rgba(0,0,0,.12);
    border-radius: 8px; font-size: .875rem;
    font-family: 'Barlow', sans-serif;
    color: oklch(18% 0.03 50); outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.drawer-input:focus, .drawer-textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px oklch(58% 0.14 42 / 0.15);
}
.drawer-textarea { resize: vertical; min-height: 90px; }
.drawer-preview {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px;
    background: rgba(0,0,0,.04);
    border-radius: 10px;
}
.drawer-preview-label {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: .72rem; color: oklch(50% 0.03 50);
    letter-spacing: .06em; text-transform: uppercase;
}
.drawer-preview-badge {
    color: #fff; padding: 3px 10px; border-radius: 4px;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 600; font-size: .68rem;
    letter-spacing: .08em; text-transform: uppercase;
}
.drawer-submit {
    margin-top: 8px;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    color: #fff; border: none; border-radius: 10px;
    padding: 13px 20px; cursor: pointer;
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800; font-size: 1.06rem;
    letter-spacing: .08em; text-transform: uppercase;
    box-shadow: 0 4px 18px rgba(0,0,0,.18);
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; transition: opacity .2s;
}
.drawer-submit:hover { opacity: .9; }
</style>

<!-- Heading row -->
<div class="slide-down" style="animation-delay:0ms;display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:36px;flex-wrap:wrap;gap:16px">
    <div>
        <div class="cat-eyebrow">Admin</div>
        <h1 class="cat-h1">Categories</h1>
        <p class="cat-sub">
            <?= count($categories) ?> categor<?= count($categories) !== 1 ? 'ies' : 'y' ?>
            — add, edit or remove workout categories.
        </p>
    </div>
    <button class="btn-add-cat" onclick="openDrawer()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Category
    </button>
</div>

<?php if ($msg): ?>
<div class="cat-flash">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<?php if ($categories): ?>
<div class="cat-list">
    <?php foreach ($categories as $i => $cat): ?>
    <?php
        $num   = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
        $color = adminCatColor($cat['name']);
    ?>
    <div class="cat-row rise" style="animation-delay:<?= 120 + $i * 70 ?>ms">
        <div class="cat-row-watermark" style="color:<?= $color ?>"><?= $num ?></div>
        <div class="cat-row-num" style="color:<?= $color ?>"><?= $num ?></div>

        <div class="cat-row-icon" style="background:<?= $color ?>22;color:<?= $color ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
        </div>

        <div class="cat-row-body">
            <div class="cat-row-name"><?= htmlspecialchars($cat['name']) ?></div>
            <?php if ($cat['description']): ?>
                <div class="cat-row-desc"><?= htmlspecialchars($cat['description']) ?></div>
            <?php endif; ?>
        </div>

        <span class="cat-row-badge" style="background:<?= $color ?>"><?= htmlspecialchars($cat['name']) ?></span>

        <div class="cat-row-actions">
            <button class="cat-btn-edit"
                onclick="openEdit(<?= $cat['id'] ?>, <?= htmlspecialchars(json_encode($cat['name'])) ?>, <?= htmlspecialchars(json_encode($cat['description'] ?? '')) ?>)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
            </button>
            <form method="POST" class="d-inline" data-del-form>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                <button type="button" class="cat-btn-del" onclick="confirmDel(this)">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    Delete
                </button>
            </form>
        </div>

        <div class="cat-row-arrow" style="color:<?= $color ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<div class="cat-empty rise" style="animation-delay:120ms">
    <div class="cat-empty-h">No categories yet</div>
    <div class="cat-empty-s">Add your first workout category to get started.</div>
    <button class="btn-add-cat" style="margin:0 auto" onclick="openDrawer()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Category
    </button>
</div>
<?php endif; ?>

<!-- Drawer backdrop -->
<div class="drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()"></div>

<!-- Drawer -->
<div class="drawer" id="drawer">
    <div class="drawer-hdr">
        <div class="drawer-title">
            <span class="drawer-title-icon" id="drawerIcon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </span>
            <span id="drawerHeading">New Category</span>
        </div>
        <button class="drawer-close" onclick="closeDrawer()">✕</button>
    </div>

    <form method="POST" class="drawer-form" id="drawerForm">
        <input type="hidden" name="action" id="drawerAction" value="add">
        <input type="hidden" name="id"     id="drawerCatId"  value="">

        <!-- Live preview -->
        <div class="drawer-preview" id="drawerPreview" style="display:none">
            <span class="drawer-preview-label">Preview:</span>
            <span class="drawer-preview-badge" id="drawerPreviewBadge"></span>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Name <span class="req">*</span></label>
            <input type="text" name="name" id="drawerName" class="drawer-input"
                   placeholder="e.g. Strength" required autofocus>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Description</label>
            <textarea name="description" id="drawerDesc" class="drawer-textarea"
                      placeholder="Describe this category…"></textarea>
        </div>

        <button type="submit" class="drawer-submit" id="drawerSubmit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span id="drawerSubmitLabel">Add Category</span>
        </button>
    </form>
</div>

<script>
const CAT_COLORS = {
    conditioning:'oklch(50% 0.10 240)', flexibility:'oklch(44% 0.10 152)',
    hiit:'oklch(46% 0.13 15)', strength:'oklch(52% 0.13 42)',
    'weight loss':'oklch(48% 0.11 320)', cardio:'oklch(48% 0.13 25)',
    balance:'oklch(50% 0.10 240)', recovery:'oklch(50% 0.08 190)',
    'full body':'oklch(46% 0.09 55)',
};
function colorFor(name) {
    return CAT_COLORS[name.trim().toLowerCase()] || 'oklch(58% 0.14 42)';
}

const nameInput = document.getElementById('drawerName');
const preview   = document.getElementById('drawerPreview');
const badge     = document.getElementById('drawerPreviewBadge');

nameInput.addEventListener('input', () => {
    const v = nameInput.value.trim();
    if (v) {
        preview.style.display = 'flex';
        badge.textContent = v;
        badge.style.background = colorFor(v);
    } else {
        preview.style.display = 'none';
    }
});

function openDrawer() {
    document.getElementById('drawerAction').value     = 'add';
    document.getElementById('drawerCatId').value      = '';
    document.getElementById('drawerHeading').textContent = 'New Category';
    document.getElementById('drawerSubmitLabel').textContent = 'Add Category';
    nameInput.value = '';
    document.getElementById('drawerDesc').value = '';
    preview.style.display = 'none';
    document.getElementById('drawer').classList.add('open');
    document.getElementById('drawerBackdrop').classList.add('open');
    setTimeout(() => nameInput.focus(), 300);
}

function openEdit(id, name, desc) {
    document.getElementById('drawerAction').value     = 'edit';
    document.getElementById('drawerCatId').value      = id;
    document.getElementById('drawerHeading').textContent = 'Edit Category';
    document.getElementById('drawerSubmitLabel').textContent = 'Save Changes';
    nameInput.value = name;
    document.getElementById('drawerDesc').value = desc;
    // trigger preview
    if (name) {
        preview.style.display = 'flex';
        badge.textContent = name;
        badge.style.background = colorFor(name);
    }
    document.getElementById('drawer').classList.add('open');
    document.getElementById('drawerBackdrop').classList.add('open');
    setTimeout(() => nameInput.focus(), 300);
}

function closeDrawer() {
    document.getElementById('drawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('open');
}

function confirmDel(btn) {
    if (btn.classList.contains('cat-btn-del--confirm')) {
        btn.closest('form').submit();
    } else {
        btn.classList.add('cat-btn-del--confirm');
        btn.textContent = 'Confirm?';
        setTimeout(() => {
            if (btn.classList.contains('cat-btn-del--confirm')) {
                btn.classList.remove('cat-btn-del--confirm');
                btn.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg> Delete`;
            }
        }, 3000);
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
