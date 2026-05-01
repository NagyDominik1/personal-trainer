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

$pageTitle = 'Saved Workouts';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header slide-down" style="animation-delay:0ms">
    <h2>Saved Workouts</h2>
    <p>Your personal workout collection.</p>
</div>

<?php if (!$saved): ?>
    <p class="text-muted">You have no saved workouts yet.
        <a href="<?= BASE_URL ?>/pages/user/workouts.php">Browse workouts</a>
    </p>
<?php else: ?>
<div class="row g-3">
<?php foreach ($saved as $i => $w): ?>
    <div class="col-sm-6 col-xl-4 pop" style="animation-delay:<?= 120 + $i * 80 ?>ms">
        <div class="card h-100 p-3">
            <h6 class="fw-semibold mb-1"><?= htmlspecialchars($w['title']) ?></h6>
            <p class="text-muted small mb-1"><?= htmlspecialchars($w['category_name'] ?? 'No category') ?></p>
            <p class="text-muted small mb-3">
                By <?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?>
            </p>
            <div class="mt-auto d-flex gap-2">
                <a href="<?= BASE_URL ?>/pages/user/workouts.php?id=<?= $w['id'] ?>"
                   class="btn btn-sm btn-outline-secondary">View</a>
                <form method="POST">
                    <input type="hidden" name="action" value="unsave">
                    <input type="hidden" name="workout_id" value="<?= $w['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Remove from saved?')">Remove</button>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
