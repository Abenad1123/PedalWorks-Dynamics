<?php
/**
 * View / Download Order Receipt PDF
 * ITIM211 Term Test Lab
 */
session_start();
include('../../includes/config.php');
include('../../includes/receipt_pdf.php');

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($orderId <= 0) {
    die("Invalid order ID.");
}

// Fetch order details via prepared statement
$stmt = mysqli_prepare($conn, "SELECT 
        ps.productSaleID,
        ps.orderDate,
        ps.status AS orderStatus,
        ps.paymentMethod,
        ps.pickupMethod,
        ps.deliveryAddress,
        ca.customerAccountID,
        ca.username AS customerUsername,
        c.customerID,
        CONCAT(c.firstName, IF(c.lastName IS NOT NULL AND c.lastName <> '', CONCAT(' ', c.lastName), '')) AS customerName,
        c.email AS customerEmail
    FROM productSale ps
    JOIN customerAccount ca ON ps.customerAccountID = ca.customerAccountID
    JOIN customer c ON ca.customerID = c.customerID
    WHERE ps.productSaleID = ?
    LIMIT 1");

mysqli_stmt_bind_param($stmt, 'i', $orderId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order) {
    die("Order not found.");
}

// Fetch line items via prepared statement
$items = [];
$stmtItems = mysqli_prepare($conn, "SELECT 
        olp.orderLineProductID,
        olp.productID,
        olp.quantity,
        p.sku,
        p.name AS productName,
        p.price AS unitPrice,
        (olp.quantity * p.price) AS lineSubtotal,
        pc.name AS categoryName
    FROM orderLineProduct olp
    JOIN product p ON olp.productID = p.productID
    LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID
    WHERE olp.productSaleID = ?");

mysqli_stmt_bind_param($stmtItems, 'i', $orderId);
mysqli_stmt_execute($stmtItems);
$resItems = mysqli_stmt_get_result($stmtItems);
while ($r = mysqli_fetch_assoc($resItems)) {
    $items[] = $r;
}
mysqli_stmt_close($stmtItems);

// Fetch linked workshop services
$services = [];
$stmtSrv = mysqli_prepare($conn, "SELECT 
        cs.customerServiceID,
        cs.requestDate,
        cs.scheduledDate,
        cs.status AS serviceStatus,
        s.sku AS serviceSku,
        s.name AS serviceName,
        s.price AS servicePrice
    FROM customerService cs
    JOIN service s ON cs.serviceID = s.serviceID
    WHERE cs.productSaleID = ?");

mysqli_stmt_bind_param($stmtSrv, 'i', $orderId);
mysqli_stmt_execute($stmtSrv);
$resSrv = mysqli_stmt_get_result($stmtSrv);
while ($s = mysqli_fetch_assoc($resSrv)) {
    $services[] = $s;
}
mysqli_stmt_close($stmtSrv);

$pdfContent = generateOrderReceiptPDF($order, $items, $services);

$filename = 'PedalWorks_Receipt_PW-ORD-' . str_pad($orderId, 5, '0', STR_PAD_LEFT) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;
exit;
