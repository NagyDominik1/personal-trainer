<?php
/**
 * AJAX endpoint for the user My Plan feature.
 *
 * GET  ?action=exercises              — returns all exercises as JSON
 * POST {"action":"add_exercise", "day_id":X, "exercise_id":Y}
 * POST {"action":"remove_exercise", "assignment_id":X}
 */

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Workout.php';
require_once __DIR__ . '/../classes/Coaching.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// Must be logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db       = Database::getInstance();
$userId   = (int) $_SESSION['user_id'];
$coaching = new Coaching();

/**
 * The logged-in user may edit a plan if they own it, or if they are the
 * active coach of its owner.
 */
function canEditPlanOwner(Coaching $coaching, int $userId, int $ownerId): bool
{
    return $ownerId === $userId || $coaching->isActiveCoach($userId, $ownerId);
}

/* ── GET: return exercise library ─────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'exercises') {
        $stmt = $db->query('
            SELECT e.id, e.title, e.duration_minutes, c.name AS category_name
            FROM exercises e
            LEFT JOIN categories c ON e.category_id = c.id
            ORDER BY c.name, e.title
        ');
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}

/* ── POST actions ─────────────────────────────────────── */
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';
$workout = new Workout();

if ($action === 'add_exercise') {
    $dayId      = (int)($body['day_id']      ?? 0);
    $exerciseId = (int)($body['exercise_id'] ?? 0);

    // Verify the day belongs to a plan the user owns or coaches
    $stmt = $db->prepare('
        SELECT w.user_id FROM workout_days wd
        JOIN workouts w ON wd.workout_id = w.id
        WHERE wd.id = :day_id
    ');
    $stmt->execute([':day_id' => $dayId]);
    $owner = $stmt->fetchColumn();
    if ($owner === false || !canEditPlanOwner($coaching, $userId, (int)$owner)) {
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $ok = $workout->addExerciseToDay($dayId, $exerciseId);
    if (!$ok) {
        echo json_encode(['error' => 'Could not add exercise']);
        exit;
    }

    // Return the new exercise data so the UI can append it without reload
    $stmt = $db->prepare('
        SELECT wde.id AS assignment_id, e.title, e.duration_minutes
        FROM workout_day_exercises wde
        JOIN exercises e ON wde.exercise_id = e.id
        WHERE wde.workout_day_id = :day_id
        ORDER BY wde.sort_order DESC
        LIMIT 1
    ');
    $stmt->execute([':day_id' => $dayId]);
    echo json_encode(['ok' => true, 'exercise' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'remove_exercise') {
    $assignmentId = (int)($body['assignment_id'] ?? 0);

    // Verify the user owns or coaches the plan this exercise belongs to
    $stmt = $db->prepare('
        SELECT w.user_id FROM workout_day_exercises wde
        JOIN workout_days wd ON wde.workout_day_id = wd.id
        JOIN workouts w ON wd.workout_id = w.id
        WHERE wde.id = :aid
    ');
    $stmt->execute([':aid' => $assignmentId]);
    $owner = $stmt->fetchColumn();
    if ($owner === false || !canEditPlanOwner($coaching, $userId, (int)$owner)) {
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $workout->removeExerciseFromDay($assignmentId);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
