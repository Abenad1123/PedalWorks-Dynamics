<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['adminAccountID']) || ($_SESSION['account_type'] ?? '') !== 'admin') {
    $_SESSION['error'] = 'Access denied. Please log in with administrative credentials.';
    header('Location: /project/PedalWorks-Dynamics/login.php');
    exit;
}

function hasAdminRole($allowedRoles) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    $currentRole = $_SESSION['role'];

    if ($currentRole === 'Super Administrator') {
        return true;
    }

    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    return in_array($currentRole, $allowedRoles, true);
}

function requireAdminRole($allowedRoles) {
    if (!hasAdminRole($allowedRoles)) {
        $roleName = $_SESSION['role'] ?? 'Your role';
        $_SESSION['error'] = "Access denied: [{$roleName}] does not have permission to access that module.";
        header('Location: /project/PedalWorks-Dynamics/admin/dashboard.php');
        exit;
    }
}
