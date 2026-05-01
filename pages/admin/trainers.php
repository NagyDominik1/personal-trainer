<?php
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireRole('admin');

$db  = Database::getInstance();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $db->prepare('UPDATE users SET is_approved = 1 WHERE id = :id AND role = "trainer"')
           ->execute([':id' => $id]);
        $msg = 'Trainer approved.';
    } elseif ($action === 'reject') {
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

$pageTitle = 'Trainers';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header slide-down" style="animation-delay:0ms">
    <h2>Approve Trainers</h2>
    <p>Review and manage trainer accounts.</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<h5 class="mb-3 pop" style="animation-delay:120ms">Pending Approval <span class="badge bg-warning text-dark"><?= count($pending) ?></span></h5>
<div class="card mb-4 pop" style="animation-delay:180ms">
    <table class="table table-hover mb-0">
        <thead class="table-light">
            <tr><th>Name</th><th>Email</th><th>Registered</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($pending as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></td>
                <td><?= htmlspecialchars($t['email']) ?></td>
                <td><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                <td class="text-end">
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button class="btn btn-sm btn-success">Approve</button>
                    </form>
                    <form method="POST" class="d-inline"
                          onsubmit="return confirm('Remove this trainer account?')">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button class="btn btn-sm btn-outline-danger">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$pending): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">No pending trainers.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<h5 class="mb-3 pop" style="animation-delay:300ms">Approved Trainers</h5>
<div class="card pop" style="animation-delay:360ms">
    <table class="table table-hover mb-0">
        <thead class="table-light">
            <tr><th>Name</th><th>Email</th><th>Phone</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($approved as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></td>
                <td><?= htmlspecialchars($t['email']) ?></td>
                <td><?= htmlspecialchars($t['phone'] ?? '—') ?></td>
                <td class="text-end">
                    <form method="POST" class="d-inline"
                          onsubmit="return confirm('Revoke approval?')">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <input type="hidden" name="action" value="revoke">
                        <button class="btn btn-sm btn-outline-warning">Revoke</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$approved): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">No approved trainers yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
