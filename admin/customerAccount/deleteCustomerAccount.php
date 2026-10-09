<?php
session_start();
include('../../includes/config.php');

$deleteID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleteID = isset($_POST['id']) ? (int)$_POST['id'] : 0;
}

if ($deleteID <= 0) {
    $_SESSION['error'] = 'Invalid customer ID for deletion.';
    header('Location: index.php');
    exit;
}

if (!$conn) {
    $_SESSION['error'] = 'Database is offline.';
    header('Location: index.php');
    exit;
}

// Find customerAccountID for this customer
$accStmt = mysqli_prepare($conn, "SELECT customerAccountID FROM customerAccount WHERE customerID = ? LIMIT 1");
mysqli_stmt_bind_param($accStmt, 'i', $deleteID);
mysqli_stmt_execute($accStmt);
$accRes = mysqli_stmt_get_result($accStmt);
$accRow = mysqli_fetch_assoc($accRes);
mysqli_stmt_close($accStmt);

$customerAccountID = $accRow ? (int)$accRow['customerAccountID'] : 0;

// Check if customer has dependent orders or service requests
$hasOrders = false;
$hasServices = false;

if ($customerAccountID > 0) {
    $ordCheck = mysqli_query($conn, "SELECT COUNT(*) as c FROM productSale WHERE customerAccountID = $customerAccountID");
    if ($ordCheck && ($r = mysqli_fetch_assoc($ordCheck)) && $r['c'] > 0) {
        $hasOrders = true;
    }

    $srvCheck = mysqli_query($conn, "SELECT COUNT(*) as c FROM customerService WHERE customerAccountID = $customerAccountID");
    if ($srvCheck && ($r = mysqli_fetch_assoc($srvCheck)) && $r['c'] > 0) {
        $hasServices = true;
    }
}

if ($hasOrders || $hasServices) {
    $_SESSION['error'] = "Cannot delete this customer because they have existing order history or service requests. To restrict their access, please edit their account status to 'Disabled' instead.";
} else {
    mysqli_begin_transaction($conn);
    try {
        if ($customerAccountID > 0) {
            $delCart = mysqli_prepare($conn, "DELETE FROM customerCart WHERE customerAccountID = ?");
            mysqli_stmt_bind_param($delCart, 'i', $customerAccountID);
            mysqli_stmt_execute($delCart);
            mysqli_stmt_close($delCart);

            $delAcc = mysqli_prepare($conn, "DELETE FROM customerAccount WHERE customerAccountID = ?");
            mysqli_stmt_bind_param($delAcc, 'i', $customerAccountID);
            mysqli_stmt_execute($delAcc);
            mysqli_stmt_close($delAcc);
        }

        $delCust = mysqli_prepare($conn, "DELETE FROM customer WHERE customerID = ?");
        mysqli_stmt_bind_param($delCust, 'i', $deleteID);
        mysqli_stmt_execute($delCust);
        mysqli_stmt_close($delCust);

        mysqli_commit($conn);
        $_SESSION['success'] = "Customer #{$deleteID} and account deleted successfully.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Failed to delete customer: " . $e->getMessage();
    }
}

header('Location: index.php');
exit;
