<?php
/**
 * Admin Authentication & Role-Based Access Control (RBAC) Guard
 * PedalWorks Dynamics
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verify user is authenticated as an admin
if (!isset($_SESSION['adminAccountID']) || ($_SESSION['account_type'] ?? '') !== 'admin') {
    $_SESSION['error'] = 'Access denied. Please log in with administrative credentials.';
    header('Location: /project/PedalWorks-Dynamics/login.php');
    exit;
}

/**
 * Check if the currently logged-in administrator has one of the allowed roles.
 *
 * @param array|string $allowedRoles Single role string or array of allowed roles
 * @return bool
 */
function hasAdminRole($allowedRoles) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    $currentRole = $_SESSION['role'];

    // Super Administrator has universal access across all modules
    if ($currentRole === 'Super Administrator') {
        return true;
    }

    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    return in_array($currentRole, $allowedRoles, true);
}

/**
 * Enforce role access control. If the current administrator lacks the required role,
 * sets an error alert and redirects to the admin dashboard.
 *
 * @param array|string $allowedRoles Single role string or array of allowed roles
 */
function requireAdminRole($allowedRoles) {
    if (!hasAdminRole($allowedRoles)) {
        $roleName = $_SESSION['role'] ?? 'Your role';
        $_SESSION['error'] = "Access denied: [{$roleName}] does not have permission to access that module.";
        header('Location: /project/PedalWorks-Dynamics/admin/dashboard.php');
        exit;
    }
}
