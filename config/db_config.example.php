<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Detect environment from the host name in the browser address bar.
$host    = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = $host === 'localhost'
        || $host === '127.0.0.1'
        || strpos($host, 'localhost:') === 0
        || strpos($host, '127.0.0.1:') === 0;

if ($isLocal) {
    // LOCAL (XAMPP)
    define('DB_HOST',    'localhost');
    define('DB_NAME',    'ee');
    define('DB_USER',    'root');
    define('DB_PASS',    '');
    define('DB_CHARSET', 'utf8mb4');

    define('BASE_URL', '/webprog/projekt');
} else {
    // SCHOOL SERVER (Virtualmin) — fill in your assigned credentials
    define('DB_HOST',    'localhost');
    define('DB_NAME',    'ee');
    define('DB_USER',    'ee');
    define('DB_PASS',    'YOUR_DB_PASSWORD');
    define('DB_CHARSET', 'utf8mb4');

    define('BASE_URL', '/WebProg/Projekt');
}
