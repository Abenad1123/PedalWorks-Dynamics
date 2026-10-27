<?php
session_start();
include('../../includes/config.php');
require('../../includes/fpdf/fpdf.php');

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    die("Invalid order ID.");
}

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

if (!$order) {
    die("Order not found.");
}

$items = [];
$stmt = mysqli_prepare($conn, "SELECT 
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
while ($r = mysqli_fetch_assoc($res)) {
    $items[] = $r;
}
mysqli_stmt_close($stmt);

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
while ($s = mysqli_fetch_assoc($sRes)) {
    $services[] = $s;
}
mysqli_stmt_close($stmt);

$grandTotal = 0;
foreach ($items as $it) {
    $grandTotal += (float)$it['subtotal'];
}
foreach ($services as $srv) {
    $grandTotal += (float)$srv['price'];
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'PedalWorks Dynamics - Transaction Receipt', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'Order #PW-ORD-' . $orderId . ' | Status: ' . $order['status'], 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'Customer: ' . ($order['customerName'] ?? 'Valued Customer') . ' (' . ($order['customerEmail'] ?? 'N/A') . ')', 0, 1);
$pdf->Cell(0, 6, 'Payment: ' . ($order['paymentMethod'] ?? 'N/A') . ' | Fulfillment: ' . ($order['pickupMethod'] ?? 'N/A'), 0, 1);
$pdf->Ln(4);

$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 8, 'Item Description', 1, 0, 'L', true);
$pdf->Cell(25, 8, 'Qty', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Unit Price', 1, 0, 'R', true);
$pdf->Cell(35, 8, 'Subtotal', 1, 1, 'R', true);

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

$filename = 'receipt_order_' . $orderId . '.pdf';
$pdf->Output('I', $filename);
exit;
