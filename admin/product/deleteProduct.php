<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');
requireAdminRole(['Inventory Manager']);

$deleteID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleteID = isset($_POST['id']) ? (int)$_POST['id'] : 0;
}

$action = $_GET['action'] ?? 'archive';

if ($deleteID <= 0) {
    $_SESSION['error'] = 'Invalid product ID.';
    header('Location: index.php');
    exit;
}

if (!$conn) {
    $_SESSION['error'] = 'Database offline.';
    header('Location: index.php');
    exit;
}

// Find product
$stmt = mysqli_prepare($conn, "SELECT productID, sku, name, endDate FROM product WHERE productID = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $deleteID);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$product) {
    $_SESSION['error'] = 'Product not found.';
    header('Location: index.php');
    exit;
}

// Check for sales order history
$hasOrders = false;
$orderCheck = mysqli_query($conn, "SELECT COUNT(*) as c FROM orderLineProduct WHERE productID = $deleteID");
if ($orderCheck && ($r = mysqli_fetch_assoc($orderCheck)) && (int)$r['c'] > 0) {
    $hasOrders = true;
}

if ($action === 'permanent') {
    if ($hasOrders) {
        $_SESSION['error'] = 'Cannot permanently delete "' . htmlspecialchars($product['name']) . '" because it is linked to past customer sales orders. It remains safely archived.';
    } else {
        // Remove from any active carts first
        mysqli_query($conn, "DELETE FROM customerCart WHERE productID = $deleteID");

        $delStmt = mysqli_prepare($conn, "DELETE FROM product WHERE productID = ?");
        mysqli_stmt_bind_param($delStmt, 'i', $deleteID);
        if (mysqli_stmt_execute($delStmt)) {
            $_SESSION['success'] = 'Product "' . htmlspecialchars($product['name']) . '" (SKU: ' . htmlspecialchars($product['sku']) . ') has been permanently deleted.';
        } else {
            $_SESSION['error'] = 'Failed to delete product: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($delStmt);
    }
} else {
    // Default action: Deactivate / Archive (SCD Type 2)
    if ($product['endDate'] !== null) {
        $_SESSION['error'] = 'Product "' . htmlspecialchars($product['name']) . '" is already archived.';
    } else {
        $arcStmt = mysqli_prepare($conn, "UPDATE product SET endDate = NOW() WHERE productID = ? AND endDate IS NULL");
        mysqli_stmt_bind_param($arcStmt, 'i', $deleteID);
        if (mysqli_stmt_execute($arcStmt)) {
            $_SESSION['success'] = 'Product "' . htmlspecialchars($product['name']) . '" has been deactivated and archived (sales history preserved).';
        } else {
            $_SESSION['error'] = 'Failed to deactivate product: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($arcStmt);
    }
}

header('Location: index.php');
exit;
