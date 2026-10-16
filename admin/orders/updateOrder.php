<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../PHPMailer/src/Exception.php';
require '../../PHPMailer/src/PHPMailer.php';
require '../../PHPMailer/src/SMTP.php';
require '../../includes/fpdf/fpdf.php';

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

// Check previous status for inventory synchronization
$prevStatus = '';
$chkStmt = mysqli_prepare($conn, "SELECT status FROM productSale WHERE productSaleID = ? LIMIT 1");
if ($chkStmt) {
    mysqli_stmt_bind_param($chkStmt, 'i', $orderId);
    mysqli_stmt_execute($chkStmt);
    $chkRes = mysqli_stmt_get_result($chkStmt);
    if ($chkRow = mysqli_fetch_assoc($chkRes)) {
        $prevStatus = $chkRow['status'];
    }
    mysqli_stmt_close($chkStmt);
}

// 1. Update status using Prepared Statements
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

// 2. Fetch order & customer info using Prepared Statements
$stmt = mysqli_prepare($conn, "SELECT 
        ps.productSaleID,
        ps.orderDate,
        ps.status,
        ps.paymentMethod,
        ps.pickupMethod,
        ps.deliveryAddress,
        CONCAT(c.firstName, ' ', COALESCE(c.lastName, '')) AS customerName,
        c.email AS customerEmail
    FROM productSale ps
    JOIN customerAccount ca ON ps.customerAccountID = ca.customerAccountID
    JOIN customer c ON ca.customerID = c.customerID
    WHERE ps.productSaleID = ?
    LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $orderId);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// 3. Fetch line items using Prepared Statements
$items = [];
$stmt = mysqli_prepare($conn, "SELECT 
        olp.productID,
        p.name AS productName,
        olp.quantity,
        p.price,
        (olp.quantity * p.price) AS subtotal
    FROM orderLineProduct olp
    JOIN product p ON olp.productID = p.productID
    WHERE olp.productSaleID = ?");
mysqli_stmt_bind_param($stmt, 'i', $orderId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $items[] = $row;
}
mysqli_stmt_close($stmt);

// 4. Inventory synchronization: restore stock if cancelled, deduct if uncancelled
if ($prevStatus !== 'Cancelled' && $status === 'Cancelled') {
    $stockStmt = mysqli_prepare($conn, "UPDATE product SET stock = stock + ? WHERE productID = ?");
    if ($stockStmt) {
        foreach ($items as $item) {
            if (!empty($item['productID']) && !empty($item['quantity'])) {
                mysqli_stmt_bind_param($stockStmt, 'ii', $item['quantity'], $item['productID']);
                mysqli_stmt_execute($stockStmt);
            }
        }
        mysqli_stmt_close($stockStmt);
    }
} elseif ($prevStatus === 'Cancelled' && $status !== 'Cancelled') {
    $stockStmt = mysqli_prepare($conn, "UPDATE product SET stock = GREATEST(0, stock - ?) WHERE productID = ?");
    if ($stockStmt) {
        foreach ($items as $item) {
            if (!empty($item['productID']) && !empty($item['quantity'])) {
                mysqli_stmt_bind_param($stockStmt, 'ii', $item['quantity'], $item['productID']);
                mysqli_stmt_execute($stockStmt);
            }
        }
        mysqli_stmt_close($stockStmt);
    }
}

// Fetch linked workshop services if any
$services = [];
$stmt = mysqli_prepare($conn, "SELECT 
        s.name AS serviceName,
        s.price
    FROM customerService cs
    JOIN service s ON cs.serviceID = s.serviceID
    WHERE cs.productSaleID = ?");
mysqli_stmt_bind_param($stmt, 'i', $orderId);
mysqli_stmt_execute($stmt);
$sRes = mysqli_stmt_get_result($stmt);
while ($sRow = mysqli_fetch_assoc($sRes)) {
    $services[] = $sRow;
}
mysqli_stmt_close($stmt);

// Calculate totals
$grandTotal = 0;
foreach ($items as $it) {
    $grandTotal += (float)$it['subtotal'];
}
foreach ($services as $srv) {
    $grandTotal += (float)$srv['price'];
}

// 4. Generate PDF receipt using FPDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'PedalWorks Dynamics - Transaction Receipt', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'Order #PW-ORD-' . $orderId . ' | Status: ' . $status, 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'Customer: ' . ($order['customerName'] ?? 'Valued Customer') . ' (' . ($order['customerEmail'] ?? 'N/A') . ')', 0, 1);
$pdf->Cell(0, 6, 'Payment: ' . ($order['paymentMethod'] ?? 'N/A') . ' | Fulfillment: ' . ($order['pickupMethod'] ?? 'N/A'), 0, 1);
$pdf->Ln(4);

// Header Table
$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 8, 'Item Description', 1, 0, 'L', true);
$pdf->Cell(25, 8, 'Qty', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Unit Price', 1, 0, 'R', true);
$pdf->Cell(35, 8, 'Subtotal', 1, 1, 'R', true);

// Rows
$pdf->SetFont('Arial', '', 10);
foreach ($items as $it) {
    $pdf->Cell(95, 7, ' ' . substr($it['productName'], 0, 45), 1, 0, 'L');
    $pdf->Cell(25, 7, (string)$it['quantity'], 1, 0, 'C');
    $pdf->Cell(35, 7, 'PHP ' . number_format($it['price'], 2), 1, 0, 'R');
    $pdf->Cell(35, 7, 'PHP ' . number_format($it['subtotal'], 2), 1, 1, 'R');
}
foreach ($services as $srv) {
    $pdf->Cell(95, 7, ' ' . substr($srv['serviceName'], 0, 45) . ' (Service)', 1, 0, 'L');
    $pdf->Cell(25, 7, '1', 1, 0, 'C');
    $pdf->Cell(35, 7, 'PHP ' . number_format($srv['price'], 2), 1, 0, 'R');
    $pdf->Cell(35, 7, 'PHP ' . number_format($srv['price'], 2), 1, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(155, 8, 'Grand Total: ', 1, 0, 'R');
$pdf->Cell(35, 8, 'PHP ' . number_format($grandTotal, 2), 1, 1, 'R');

$pdfContent = $pdf->Output('S');

// 5. Send notification email with attached receipt via SMTP
$mail = new PHPMailer();
try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'sandbox.smtp.mailtrap.io';
    $mail->SMTPAuth   = true;
    $mail->Port       = 2525;
    $mail->Username   = '312649aee7d340';
    $mail->Password   = '7bec3dc61ba28b';

    // Recipients
    $mail->setFrom('orders@pedalworks.com', 'PedalWorks Dynamics');
    $customerEmail = !empty($order['customerEmail']) ? $order['customerEmail'] : 'customer@example.com';
    $mail->addAddress($customerEmail, $order['customerName'] ?? 'Customer');

    // Build line items list HTML
    $itemsHtml = '';
    foreach ($items as $it) {
        $itemsHtml .= "<tr>
            <td style='padding: 8px; border: 1px solid #ddd;'>{$it['productName']}</td>
            <td style='padding: 8px; border: 1px solid #ddd; text-align: center;'>{$it['quantity']}</td>
            <td style='padding: 8px; border: 1px solid #ddd; text-align: right;'>PHP " . number_format($it['price'], 2) . "</td>
            <td style='padding: 8px; border: 1px solid #ddd; text-align: right;'>PHP " . number_format($it['subtotal'], 2) . "</td>
        </tr>";
    }
    foreach ($services as $srv) {
        $itemsHtml .= "<tr>
            <td style='padding: 8px; border: 1px solid #ddd;'>{$srv['serviceName']} (Workshop Service)</td>
            <td style='padding: 8px; border: 1px solid #ddd; text-align: center;'>1</td>
            <td style='padding: 8px; border: 1px solid #ddd; text-align: right;'>PHP " . number_format($srv['price'], 2) . "</td>
            <td style='padding: 8px; border: 1px solid #ddd; text-align: right;'>PHP " . number_format($srv['price'], 2) . "</td>
        </tr>";
    }

    // Content
    $mail->isHTML(true);
    $mail->Subject = "Order #PW-ORD-{$orderId} Status Updated to {$status}";
    $mail->Body    = "<h3>Hello " . htmlspecialchars($order['customerName'] ?? 'Customer') . ",</h3>
        <p>Your order status has been updated to: <strong>{$status}</strong></p>
        <h4>Transaction Details:</h4>
        <table style='width: 100%; border-collapse: collapse; font-family: Arial, sans-serif;'>
            <thead>
                <tr style='background: #f2f2f2;'>
                    <th style='padding: 8px; border: 1px solid #ddd; text-align: left;'>Item Description</th>
                    <th style='padding: 8px; border: 1px solid #ddd;'>Quantity</th>
                    <th style='padding: 8px; border: 1px solid #ddd; text-align: right;'>Unit Price</th>
                    <th style='padding: 8px; border: 1px solid #ddd; text-align: right;'>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                {$itemsHtml}
                <tr>
                    <td colspan='3' style='padding: 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;'>Grand Total:</td>
                    <td style='padding: 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;'>PHP " . number_format($grandTotal, 2) . "</td>
                </tr>
            </tbody>
        </table>
        <p>Your official PDF receipt is attached to this email.</p>
        <p>Thank you for choosing PedalWorks Dynamics!</p>";

    $mail->AltBody = "Hello " . ($order['customerName'] ?? 'Customer') . ",\nYour order #PW-ORD-{$orderId} status has been updated to {$status}.\nGrand Total: PHP " . number_format($grandTotal, 2) . "\nReceipt PDF is attached.";

    // Attach PDF receipt
    $mail->addStringAttachment($pdfContent, "receipt_order_{$orderId}.pdf");

    $mail->send();
    $_SESSION['actionSuccess'] = "Order #PW-ORD-{$orderId} status has been updated to '{$status}'. Confirmation receipt emailed to {$customerEmail}.";
} catch (Exception $e) {
    $_SESSION['actionSuccess'] = "Order #PW-ORD-{$orderId} status has been updated to '{$status}'. (Notification email could not be delivered to {$customerEmail}).";
}

header("Location: orderDetails.php?id={$orderId}");
exit;
