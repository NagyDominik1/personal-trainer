<?php

/**
 * API endpoint: GET /api/dashboard_stats.php
 * Returns JSON statistics for the dashboard, tailored to the logged-in user's role.
 * Requires an active session — returns 401 if not authenticated.
 */

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// Only authenticated users may request stats
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db     = Database::getInstance();
$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['user_role'] ?? 'user';
$stats  = [];

if ($role === 'admin') {
    // Count trainers awaiting approval
    $stmt = $db->prepare('
        SELECT COUNT(*) FROM users
        WHERE role = "trainer" AND is_active = 1 AND is_approved = 0 AND is_banned = 0
    ');
    $stmt->execute();
    $stats['pending_trainers'] = (int) $stmt->fetchColumn();

    // Count currently banned accounts
    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE is_banned = 1');
    $stmt->execute();
    $stats['banned_users'] = (int) $stmt->fetchColumn();

    // Total registered users (excluding admins)
    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE role != "admin"');
    $stmt->execute();
    $stats['total_users'] = (int) $stmt->fetchColumn();

} elseif ($role === 'trainer') {
    // Number of exercises the trainer has created
    $stmt = $db->prepare('SELECT COUNT(*) FROM exercises WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    $stats['my_exercises'] = (int) $stmt->fetchColumn();

    // Number of workout programs the trainer has created
    $stmt = $db->prepare('SELECT COUNT(*) FROM workouts WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    $stats['my_workouts'] = (int) $stmt->fetchColumn();

    // Pass approval status so the JS can show/hide the approval notice
    $stats['is_approved'] = (bool) ($_SESSION['user_is_approved'] ?? false);

} else {
    // Number of workouts the user has saved
    $stmt = $db->prepare('SELECT COUNT(*) FROM user_workouts WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    $stats['saved_workouts'] = (int) $stmt->fetchColumn();

    // Total available workout programs across all trainers
    $stmt = $db->prepare('SELECT COUNT(*) FROM workouts');
    $stmt->execute();
    $stats['total_workouts'] = (int) $stmt->fetchColumn();
}

echo json_encode($stats);
