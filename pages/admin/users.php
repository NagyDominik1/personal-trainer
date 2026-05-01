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

    if ($action === 'ban') {
        $db->prepare('UPDATE users SET is_banned = 1 WHERE id = :id')
           ->execute([':id' => $id]);
        $msg = 'User banned.';
    } elseif ($action === 'unban') {
        $db->prepare('UPDATE users SET is_banned = 0 WHERE id = :id')
           ->execute([':id' => $id]);
        $msg = 'User unbanned.';
    }
}

$users = $db->query('
    SELECT * FROM users
    WHERE role != "admin"
    ORDER BY role, first_name
')->fetchAll();

$pageTitle = 'Manage Users';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header slide-down" style="animation-delay:0ms">
    <h2>Manage Users</h2>
    <p>Ban or unban member accounts.</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card pop" style="animation-delay:120ms">
    <table class="table table-hover mb-0">
        <thead class="table-light">
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($u['role']) ?></span></td>
                <td>
                    <?php if ($u['is_banned']): ?>
                        <span class="badge bg-danger">Banned</span>
                    <?php elseif (!$u['is_active']): ?>
                        <span class="badge bg-warning text-dark">Inactive</span>
                    <?php else: ?>
                        <span class="badge bg-success">Active</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <?php if ($u['is_banned']): ?>
                            <input type="hidden" name="action" value="unban">
                            <button class="btn btn-sm btn-outline-success">Unban</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="ban">
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Ban this user?')">Ban</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No users found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
