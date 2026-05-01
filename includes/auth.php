<?php

// Redirect to login if the user is not authenticated
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/pages/login.php');
        exit;
    }
}

// Require a specific role — sends 403 if role does not match
function requireRole(string $role): void
{
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
}

// Check the current user's role without blocking access
function hasRole(string $role): bool
{
    return ($_SESSION['user_role'] ?? '') === $role;
}

// Return true if a valid session exists
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}
