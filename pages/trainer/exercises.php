<?php
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Exercise.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireRole('trainer');

$db       = Database::getInstance();
$exClass  = new Exercise();
$userId   = (int) $_SESSION['user_id'];

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        if ($title) {
            $exClass->create([
                'user_id'          => $userId,
                'category_id'      => $_POST['category_id'] ?: null,
                'title'            => $title,
                'description'      => trim($_POST['description'] ?? ''),
                'duration_minutes' => (int)($_POST['duration_minutes'] ?? 0) ?: null,
                'video_url'        => trim($_POST['video_url'] ?? ''),
            ]);
            header('Location: ' . BASE_URL . '/pages/trainer/exercises.php?added=1');
        } else {
            header('Location: ' . BASE_URL . '/pages/trainer/exercises.php?error=title');
        }
        exit;
    }

    if ($action === 'delete') {
        $exClass->delete((int)$_POST['id'], $userId);
        header('Location: ' . BASE_URL . '/pages/trainer/exercises.php');
        exit;
    }

    if ($action === 'edit') {
        $exClass->update((int)$_POST['id'], [
            'user_id'          => $userId,
            'category_id'      => $_POST['category_id'] ?: null,
            'title'            => trim($_POST['title'] ?? ''),
            'description'      => trim($_POST['description'] ?? ''),
            'duration_minutes' => (int)($_POST['duration_minutes'] ?? 0) ?: null,
            'video_url'        => trim($_POST['video_url'] ?? ''),
        ]);
        header('Location: ' . BASE_URL . '/pages/trainer/exercises.php?edited=1');
        exit;
    }
}

$exercises  = $exClass->getAllByTrainer($userId);
$categories = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();

// Map category name → badge colour
function catColor(string $name): string {
    $map = [
        'strength'    => 'oklch(52% 0.13 42)',
        'cardio'      => 'oklch(48% 0.13 25)',
        'flexibility' => 'oklch(44% 0.10 152)',
        'balance'     => 'oklch(50% 0.10 240)',
        'hiit'        => 'oklch(46% 0.13 15)',
        'recovery'    => 'oklch(50% 0.08 190)',
        'weight loss' => 'oklch(48% 0.13 25)',
        'conditioning'=> 'oklch(46% 0.13 15)',
    ];
    return $map[strtolower($name)] ?? 'oklch(44% 0.04 50)';
}

$openDrawer  = isset($_GET['error']);
$pageTitle   = 'My Exercises';
$topBarTitle = date('l, j F');
$bodyClass   = 'photo-bg bg-clay';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.page-heading-row{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:32px}
.lib-label{font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.69rem;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.55);margin-bottom:6px}
.page-h1{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:2.375rem;color:#fff;letter-spacing:.02em;line-height:1;text-shadow:0 1px 8px rgba(0,0,0,.3);margin:0}
.page-h1-sub{color:rgba(255,255,255,.55);font-size:.8rem;margin-top:6px}
.btn-new-ex{background:oklch(58% .14 42);color:#fff;border:none;border-radius:10px;padding:11px 22px;cursor:pointer;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1rem;letter-spacing:.06em;text-transform:uppercase;display:flex;align-items:center;gap:7px;box-shadow:0 3px 12px rgba(0,0,0,.15);transition:all .15s;white-space:nowrap}
.btn-new-ex:hover{background:oklch(54% .14 42);box-shadow:0 6px 20px rgba(0,0,0,.22);transform:translateY(-1px)}
.ex-table{background:rgba(255,255,255,.66);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.5);border-radius:16px;overflow:hidden;box-shadow:0 4px 28px rgba(0,0,0,.14)}
.ex-thead{display:grid;grid-template-columns:1fr 150px 110px 120px;padding:13px 28px;border-bottom:1px solid rgba(0,0,0,.08);background:rgba(255,255,255,.35)}
.ex-thead span{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.66rem;letter-spacing:.14em;text-transform:uppercase;color:oklch(44% .04 50)}
.ex-row{display:grid;grid-template-columns:1fr 150px 110px 120px;padding:16px 28px;border-bottom:1px solid rgba(0,0,0,.06);align-items:center;transition:background .15s}
.ex-row:last-of-type{border-bottom:none}
.ex-row:hover{background:rgba(255,255,255,.45)}
.ex-title{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.06rem;letter-spacing:.02em;color:oklch(16% .03 50);text-transform:uppercase}
.ex-video{font-size:.69rem;color:oklch(58% .14 42);text-decoration:none;display:inline-flex;align-items:center;gap:3px;margin-top:2px}
.cat-badge{color:#fff;padding:3px 10px;border-radius:4px;font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.69rem;letter-spacing:.08em;text-transform:uppercase}
.ex-dur{font-family:'Barlow Condensed',sans-serif;font-weight:600;font-size:.94rem;color:oklch(38% .04 50)}
.del-btn{background:rgba(0,0,0,.07);border:none;border-radius:7px;width:30px;height:30px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:oklch(52% .04 50);transition:all .15s;padding:0}
.del-btn:hover{background:oklch(52% .13 15);color:#fff}
.ex-tfoot{padding:9px 28px;border-top:1px solid rgba(0,0,0,.06);background:rgba(255,255,255,.28);font-family:'Barlow Condensed',sans-serif;font-size:.66rem;letter-spacing:.1em;text-transform:uppercase;color:oklch(56% .03 50)}
.ex-empty{padding:72px 28px;text-align:center}
.ex-empty-h{font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:1.375rem;text-transform:uppercase;letter-spacing:.04em;color:oklch(40% .04 50);margin-bottom:8px}
.ex-empty-s{font-size:.8rem;color:oklch(55% .03 50);margin-bottom:22px}
/* Drawer */
.drawer-backdrop{position:fixed;inset:0;background:rgba(10,5,2,.48);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px);z-index:200;opacity:0;pointer-events:none;transition:opacity .22s}
.drawer-backdrop.open{opacity:1;pointer-events:auto}
.drawer{position:fixed;top:0;right:0;bottom:0;width:400px;background:rgba(252,247,241,.97);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);box-shadow:-8px 0 48px rgba(0,0,0,.22);z-index:201;display:flex;flex-direction:column;transform:translateX(100%);transition:transform .28s cubic-bezier(.4,0,.2,1);overflow-y:auto}
.drawer.open{transform:translateX(0)}
.drawer-hdr{padding:28px 28px 20px;border-bottom:1px solid rgba(0,0,0,.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.drawer-title{font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:1.5rem;letter-spacing:.06em;text-transform:uppercase;color:oklch(18% .03 50);display:flex;align-items:center;gap:10px}
.drawer-title-icon{width:34px;height:34px;border-radius:9px;background:oklch(58% .14 42 / 9%);color:oklch(58% .14 42);display:flex;align-items:center;justify-content:center}
.drawer-close{background:rgba(0,0,0,.07);border:none;border-radius:8px;width:34px;height:34px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:oklch(44% .03 50);font-size:1rem;transition:background .15s}
.drawer-close:hover{background:rgba(0,0,0,.13)}
.drawer-form{flex:1;padding:26px 28px 32px;display:flex;flex-direction:column;gap:20px}
.drawer-field{display:flex;flex-direction:column;gap:6px}
.drawer-lbl{font-family:'Barlow',sans-serif;font-weight:600;font-size:.8rem;color:oklch(28% .04 50)}
.drawer-lbl .req{color:oklch(58% .14 42)}
.drawer-input,.drawer-select,.drawer-textarea{width:100%;padding:9px 12px;background:rgba(255,255,255,.85);border:1.5px solid rgba(0,0,0,.12);border-radius:8px;font-size:.875rem;font-family:'Barlow',sans-serif;color:oklch(18% .03 50);outline:none;transition:border-color .15s}
.drawer-input:focus,.drawer-select:focus,.drawer-textarea:focus{border-color:oklch(58% .14 42)}
.drawer-textarea{resize:vertical;min-height:78px}
.drawer-submit{margin-top:8px;background:linear-gradient(135deg,oklch(58% .14 42),oklch(44% .11 36));color:#fff;border:none;border-radius:10px;padding:13px 20px;cursor:pointer;font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:1.06rem;letter-spacing:.08em;text-transform:uppercase;box-shadow:0 4px 18px rgba(0,0,0,.18);display:flex;align-items:center;justify-content:center;gap:8px;width:100%;transition:opacity .2s}
.drawer-submit:hover{opacity:.9}
</style>

<?php if (isset($_GET['added'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-4">
    Exercise added to your library.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($_GET['edited'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-4">
    Exercise updated successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Heading row -->
<div class="page-heading-row slide-down" style="animation-delay:0ms">
    <div>
        <div class="lib-label">Library</div>
        <h1 class="page-h1">My Exercises</h1>
        <p class="page-h1-sub">
            <?= count($exercises) ?> exercise<?= count($exercises) !== 1 ? 's' : '' ?> in your library
        </p>
    </div>
    <button class="btn-new-ex" onclick="openDrawer()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Exercise
    </button>
</div>

<!-- Glass table -->
<div class="ex-table pop" style="animation-delay:120ms">
    <div class="ex-thead">
        <span>Title</span>
        <span>Category</span>
        <span>Duration</span>
        <span style="text-align:center">Actions</span>
    </div>

    <?php if ($exercises): ?>
        <?php foreach ($exercises as $i => $ex): ?>
        <div class="ex-row rise" style="animation-delay:<?= 200 + $i * 60 ?>ms">
            <div>
                <div class="ex-title"><?= htmlspecialchars($ex['title']) ?></div>
                <?php if ($ex['video_url']): ?>
                    <a href="<?= htmlspecialchars($ex['video_url']) ?>" target="_blank" class="ex-video">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                        Video
                    </a>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($ex['category_name']): ?>
                    <span class="cat-badge" style="background:<?= catColor($ex['category_name']) ?>">
                        <?= htmlspecialchars($ex['category_name']) ?>
                    </span>
                <?php else: ?>
                    <span style="color:oklch(55% .03 50)">—</span>
                <?php endif; ?>
            </div>
            <div class="ex-dur">
                <?= $ex['duration_minutes'] ? $ex['duration_minutes'] . ' min' : '—' ?>
            </div>
            <div style="display:flex;justify-content:center;gap:6px">
                <button type="button" class="del-btn" title="Edit"
                        onclick="openEditDrawer(<?= $ex['id'] ?>)"
                        style="background:rgba(139,90,43,.1);color:oklch(52% .14 42)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <form method="POST" onsubmit="return confirm('Delete this exercise?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $ex['id'] ?>">
                    <button type="submit" class="del-btn" title="Delete">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="ex-tfoot">
            <?= count($exercises) ?> exercise<?= count($exercises) !== 1 ? 's' : '' ?>
        </div>

    <?php else: ?>
        <div class="ex-empty">
            <div class="ex-empty-h">No exercises yet</div>
            <div class="ex-empty-s">Click "+ New Exercise" to start building your library</div>
            <button class="btn-new-ex" style="margin:0 auto" onclick="openDrawer()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Exercise
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- Drawer backdrop -->
<div class="drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()"></div>

<!-- Drawer panel -->
<div class="drawer" id="drawer">
    <div class="drawer-hdr">
        <div class="drawer-title">
            <span class="drawer-title-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </span>
            New Exercise
        </div>
        <button class="drawer-close" onclick="closeDrawer()">✕</button>
    </div>

    <form method="POST" class="drawer-form">
        <input type="hidden" name="action" value="add">

        <div class="drawer-field">
            <label class="drawer-lbl">Title <span class="req">*</span></label>
            <input type="text" name="title" class="drawer-input" placeholder="e.g. Romanian Deadlift" required autofocus>
            <?php if (isset($_GET['error']) && $_GET['error'] === 'title'): ?>
                <span style="font-size:.75rem;color:oklch(50% .15 20)">Title is required</span>
            <?php endif; ?>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Category</label>
            <select name="category_id" class="drawer-select">
                <option value="">— none —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Description</label>
            <textarea name="description" class="drawer-textarea" placeholder="Optional notes…"></textarea>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Duration (minutes)</label>
            <input type="number" name="duration_minutes" class="drawer-input" min="1" placeholder="e.g. 30">
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Video URL</label>
            <input type="url" name="video_url" class="drawer-input" placeholder="https://…">
        </div>

        <button type="submit" class="drawer-submit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Exercise
        </button>
    </form>
</div>

<!-- Edit drawer backdrop -->
<div class="drawer-backdrop" id="editDrawerBackdrop" onclick="closeEditDrawer()"></div>

<!-- Edit drawer -->
<div class="drawer" id="editDrawer">
    <div class="drawer-hdr">
        <div class="drawer-title">
            <span class="drawer-title-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </span>
            Edit Exercise
        </div>
        <button class="drawer-close" onclick="closeEditDrawer()">✕</button>
    </div>

    <form method="POST" class="drawer-form">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit-id">

        <div class="drawer-field">
            <label class="drawer-lbl">Title <span class="req">*</span></label>
            <input type="text" name="title" id="edit-title" class="drawer-input" required>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Category</label>
            <select name="category_id" id="edit-category" class="drawer-select">
                <option value="">— none —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Description</label>
            <textarea name="description" id="edit-desc" class="drawer-textarea"></textarea>
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Duration (minutes)</label>
            <input type="number" name="duration_minutes" id="edit-duration" class="drawer-input" min="1">
        </div>

        <div class="drawer-field">
            <label class="drawer-lbl">Video URL</label>
            <input type="url" name="video_url" id="edit-video" class="drawer-input" placeholder="https://…">
        </div>

        <button type="submit" class="drawer-submit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Changes
        </button>
    </form>
</div>

<script>
// Exercise data embedded for the edit drawer
const exData = <?= json_encode(array_map(fn($e) => [
    'id'               => (int)$e['id'],
    'title'            => $e['title'],
    'category_id'      => $e['category_id'] ? (int)$e['category_id'] : '',
    'description'      => $e['description'] ?? '',
    'duration_minutes' => $e['duration_minutes'] ?? '',
    'video_url'        => $e['video_url'] ?? '',
], $exercises), JSON_HEX_TAG) ?>;

function openEditDrawer(id) {
    const ex = exData.find(e => e.id === id);
    if (!ex) return;
    document.getElementById('edit-id').value       = ex.id;
    document.getElementById('edit-title').value    = ex.title;
    document.getElementById('edit-category').value = ex.category_id;
    document.getElementById('edit-desc').value     = ex.description;
    document.getElementById('edit-duration').value = ex.duration_minutes;
    document.getElementById('edit-video').value    = ex.video_url;
    document.getElementById('editDrawer').classList.add('open');
    document.getElementById('editDrawerBackdrop').classList.add('open');
    document.getElementById('edit-title').focus();
}
function closeEditDrawer() {
    document.getElementById('editDrawer').classList.remove('open');
    document.getElementById('editDrawerBackdrop').classList.remove('open');
}

function openDrawer() {
    document.getElementById('drawer').classList.add('open');
    document.getElementById('drawerBackdrop').classList.add('open');
    document.querySelector('.drawer-input[name="title"]').focus();
}
function closeDrawer() {
    document.getElementById('drawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('open');
}
<?php if ($openDrawer): ?>openDrawer();<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
