<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Workout.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['ok' => false]); exit; }

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$workoutId = (int)($body['workout_id'] ?? 0);
$notes     = substr(trim($body['notes'] ?? ''), 0, 2000);
$userId    = (int)$_SESSION['user_id'];

if (!$workoutId) { echo json_encode(['ok' => false]); exit; }

$workout = new Workout();
$ok = $workout->saveNote($userId, $workoutId, $notes);
echo json_encode(['ok' => $ok]);
