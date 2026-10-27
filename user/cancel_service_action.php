<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('../includes/config.php');

if (empty($_SESSION['customerAccountID'])) {
    $_SESSION['error'] = 'Please sign in to manage your workshop requests.';
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: services.php');
    exit;
}

$customerAccountID = (int)$_SESSION['customerAccountID'];
$customerServiceID = isset($_POST['customerServiceID']) ? (int)$_POST['customerServiceID'] : 0;

if ($customerServiceID <= 0 || !$conn) {
    $_SESSION['error'] = 'Invalid service request selected.';
    header('Location: services.php');
    exit;
}

$checkStmt = mysqli_prepare($conn, "SELECT customerServiceID, status FROM customerService WHERE customerServiceID = ? AND customerAccountID = ? LIMIT 1");
if ($checkStmt) {
    mysqli_stmt_bind_param($checkStmt, 'ii', $customerServiceID, $customerAccountID);
    mysqli_stmt_execute($checkStmt);
    $res = mysqli_stmt_get_result($checkStmt);
    $req = mysqli_fetch_assoc($res);
    mysqli_stmt_close($checkStmt);

    if (!$req) {
        $_SESSION['error'] = 'Service request not found.';
        header('Location: services.php');
        exit;
    }

    if ($req['status'] !== 'Pending') {
        $_SESSION['warning'] = 'Only pending requests can be cancelled. Please contact the workshop directly at (02) 8823-2457.';
        header('Location: services.php');
        exit;
    }

    $cancelStmt = mysqli_prepare($conn, "UPDATE customerService SET status = 'Cancelled' WHERE customerServiceID = ? AND customerAccountID = ?");
    if ($cancelStmt) {
        mysqli_stmt_bind_param($cancelStmt, 'ii', $customerServiceID, $customerAccountID);
        if (mysqli_stmt_execute($cancelStmt)) {
            $_SESSION['success'] = 'Service request #PW-SRV-' . $customerServiceID . ' has been cancelled.';
        } else {
            $_SESSION['error'] = 'Failed to cancel service request: ' . mysqli_stmt_error($cancelStmt);
        }
        mysqli_stmt_close($cancelStmt);
    }
}

header('Location: services.php');
exit;
