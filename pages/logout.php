<?php

/**
 * Logout — destroys the session and redirects to the login page.
 */

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Remove all session variables, then destroy the session
$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL . '/pages/login.php');
exit;
