<?php
/**
 * Update Order Status Controller
 * ITIM211 Term Test Lab Requirements:
 * - Update order status using prepared statements (10 pts + 5 pts)
 * - Send email notification via Mailtrap (5 pts)
 * - Email contains products/services, subtotal, and grand total (5 pts)
 * - Attach PDF transaction receipt (5 pts)
 * Reference: itim211-2b-shop/admin/updateOrder.php
 */
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');
include('../../includes/order_mailer.php');

// Access Control
requireAdminRole(['Inventory Manager', 'Accounting Administrator', 'Super Administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$orderId = isset($_POST['orderId']) ? (int)$_POST['orderId'] : (isset($_SESSION['orderId']) ? (int)$_SESSION['orderId'] : 0);
$status  = isset($_POST['status']) ? trim($_POST['status']) : '';

$validStatuses = ['Pending', 'Received', 'In Progress', 'Ready for Pickup', 'Completed', 'Cancelled'];

if ($orderId <= 0) {
    $_SESSION['actionError'] = 'Invalid order ID specified.';
    header('Location: index.php');
    exit;
}

if (!in_array($status, $validStatuses, true)) {
    $_SESSION['actionError'] = 'Invalid order status selected.';
    header("Location: orderDetails.php?id={$orderId}");
    exit;
}

// 1. Update status using Prepared Statements (Term Test Lab rubric: 10 pts prepared statements, 5 pts update status)
$sql = "UPDATE productSale SET status = ? WHERE productSaleID = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    $_SESSION['actionError'] = "Database error: " . mysqli_error($conn);
    header("Location: orderDetails.php?id={$orderId}");
    exit;
}

mysqli_stmt_bind_param($stmt, 'si', $status, $orderId);
$executed = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$executed) {
    $_SESSION['actionError'] = "Failed to update order status: " . mysqli_error($conn);
    header("Location: orderDetails.php?id={$orderId}");
    exit;
}

// 2. Send transaction update notification with PDF receipt via Mailtrap (Term Test Lab: 15 pts)
$emailResult = sendOrderTransactionEmail($conn, $orderId, $status);

$orderRef = '#PW-ORD-' . str_pad($orderId, 5, '0', STR_PAD_LEFT);

if ($emailResult['success']) {
    $_SESSION['actionSuccess'] = "Order {$orderRef} status updated to '{$status}'! Notification email with PDF receipt was successfully delivered to {$emailResult['email']} via Mailtrap.";
} else {
    // If SMTP failed, still report status updated, with mail warning
    $_SESSION['actionSuccess'] = "Order {$orderRef} status updated to '{$status}', but Mailtrap email notification could not be sent: " . htmlspecialchars($emailResult['message']);
}

header("Location: orderDetails.php?id={$orderId}");
exit;
