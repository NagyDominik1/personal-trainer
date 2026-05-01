<?php

/**
 * Application entry point.
 * Redirects to the dashboard if the user is logged in, otherwise to the login page.
 */

require_once __DIR__ . '/config/db_config.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/pages/login.php');
}
exit;
