<?php
session_start();
include('../../includes/config.php');

$deleteID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleteID = isset($_POST['id']) ? (int)$_POST['id'] : 0;
}

$action = $_GET['action'] ?? 'archive';

if ($deleteID <= 0) {
    $_SESSION['error'] = 'Invalid service ID.';
    header('Location: index.php');
    exit;
}

if (!$conn) {
    $_SESSION['error'] = 'Database offline.';
    header('Location: index.php');
    exit;
}

// Find service
$stmt = mysqli_prepare($conn, "SELECT serviceID, sku, name, endDate FROM service WHERE serviceID = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $deleteID);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$service = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$service) {
    $_SESSION['error'] = 'Service not found.';
    header('Location: index.php');
    exit;
}

// Check for service requests in customerService
$hasRequests = false;
$csCheck = mysqli_query($conn, "SELECT COUNT(*) as c FROM customerService WHERE serviceID = $deleteID");
if ($csCheck && ($r = mysqli_fetch_assoc($csCheck)) && (int)$r['c'] > 0) {
    $hasRequests = true;
}

if ($action === 'permanent') {
    if ($hasRequests) {
        $_SESSION['error'] = 'Cannot permanently delete "' . htmlspecialchars($service['name']) . '" because it is linked to recorded customer service bookings. It remains safely archived.';
    } else {
        $delStmt = mysqli_prepare($conn, "DELETE FROM service WHERE serviceID = ?");
        mysqli_stmt_bind_param($delStmt, 'i', $deleteID);
        if (mysqli_stmt_execute($delStmt)) {
            $_SESSION['success'] = 'Service "' . htmlspecialchars($service['name']) . '" (SKU: ' . htmlspecialchars($service['sku']) . ') has been permanently deleted.';
        } else {
            $_SESSION['error'] = 'Failed to delete service: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($delStmt);
    }
} else {
    // Default: Deactivate / Archive (SCD Type 2)
    if ($service['endDate'] !== null) {
        $_SESSION['error'] = 'Service "' . htmlspecialchars($service['name']) . '" is already archived.';
    } else {
        $arcStmt = mysqli_prepare($conn, "UPDATE service SET endDate = NOW() WHERE serviceID = ? AND endDate IS NULL");
        mysqli_stmt_bind_param($arcStmt, 'i', $deleteID);
        if (mysqli_stmt_execute($arcStmt)) {
            $_SESSION['success'] = 'Service "' . htmlspecialchars($service['name']) . '" has been deactivated and archived.';
        } else {
            $_SESSION['error'] = 'Failed to deactivate service: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($arcStmt);
    }
}

header('Location: index.php');
exit;
