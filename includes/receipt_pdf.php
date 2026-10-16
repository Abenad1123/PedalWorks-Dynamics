<?php
/**
 * Transaction Receipt PDF Generator
 * ITIM211 Term Test Lab Rubric: Add a PDF attachment of the transaction receipt (5 pts)
 */

require_once __DIR__ . '/fpdf/fpdf.php';

class PedalWorksReceiptPDF extends FPDF
{
    private $orderId;

    public function setOrderId($id) {
        $this->orderId = $id;
    }

    // Header
    function Header()
    {
        // Brand banner
        $this->SetFillColor(15, 23, 42); // #0f172a slate-900
        $this->Rect(0, 0, 210, 36, 'F');

        $this->SetY(8);
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(245, 158, 11); // Amber / orange accent
        $this->Cell(0, 8, 'PEDALWORKS DYNAMICS', 0, 1, 'C');

        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(203, 213, 225); // Slate-300
        $this->Cell(0, 5, 'Official Customer Transaction Receipt', 0, 1, 'C');
        $this->Cell(0, 5, 'Bikes, High-Performance Components & Workshop Services', 0, 1, 'C');

        $this->Ln(12);
    }

    // Footer
    function Footer()
    {
        $this->SetY(-22);
        $this->SetDrawColor(226, 232, 240);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(4);

        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 4, 'Thank you for choosing PedalWorks Dynamics! Keep this receipt for warranty and service records.', 0, 1, 'C');
        $this->Cell(0, 4, 'PedalWorks Dynamics Store & Workshop | orders@pedalworks.com | Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

/**
 * Generate a transaction receipt PDF as a binary string
 *
 * @param array $order Order summary data (productSaleID, orderDate, status, paymentMethod, pickupMethod, customerName, customerEmail, deliveryAddress, etc.)
 * @param array $items Array of order items (productName, sku, quantity, unitPrice, lineSubtotal, categoryName)
 * @param array $services Optional array of linked customer services
 * @return string Binary PDF content
 */
function generateOrderReceiptPDF($order, $items, $services = [])
{
    $pdf = new PedalWorksReceiptPDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->setOrderId($order['productSaleID'] ?? 0);
    $pdf->AddPage();
    $pdf->SetMargins(15, 15, 15);

    // Metadata & Customer Section
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(15, 23, 42);
    $orderRef = 'Order #PW-ORD-' . str_pad($order['productSaleID'] ?? 0, 5, '0', STR_PAD_LEFT);
    $pdf->Cell(100, 7, $orderRef, 0, 0, 'L');

    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(71, 85, 105);
    $dateStr = !empty($order['orderDate']) ? date('F d, Y h:i A', strtotime($order['orderDate'])) : date('F d, Y h:i A');
    $pdf->Cell(80, 7, 'Date: ' . $dateStr, 0, 1, 'R');

    // Thin separator line
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(4);

    // Two-column details box: Customer details | Fulfillment details
    $yBefore = $pdf->GetY();
    
    // Left column: Customer
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(90, 6, 'CUSTOMER INFORMATION', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(51, 65, 85);
    $pdf->Cell(90, 5, 'Name: ' . ($order['customerName'] ?? 'Valued Customer'), 0, 1, 'L');
    $pdf->Cell(90, 5, 'Email: ' . ($order['customerEmail'] ?? 'N/A'), 0, 1, 'L');
    if (!empty($order['customerUsername'])) {
        $pdf->Cell(90, 5, 'Account: @' . $order['customerUsername'], 0, 1, 'L');
    }

    $yLeft = $pdf->GetY();

    // Right column: Fulfillment & Payment
    $pdf->SetXY(110, $yBefore);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(85, 6, 'TRANSACTION STATUS', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(51, 65, 85);
    $pdf->SetX(110);
    $pdf->Cell(85, 5, 'Status: ' . strtoupper($order['orderStatus'] ?? $order['status'] ?? 'Received'), 0, 1, 'L');
    $pdf->SetX(110);
    $pdf->Cell(85, 5, 'Payment Method: ' . ($order['paymentMethod'] ?? 'N/A'), 0, 1, 'L');
    $pdf->SetX(110);
    $pdf->Cell(85, 5, 'Fulfillment: ' . ($order['pickupMethod'] ?? 'Standard'), 0, 1, 'L');
    if (!empty($order['deliveryAddress'])) {
        $pdf->SetX(110);
        $cleanAddr = str_replace(["\r", "\n"], ', ', $order['deliveryAddress']);
        $pdf->MultiCell(85, 4.5, 'Address: ' . substr($cleanAddr, 0, 120), 0, 'L');
    }

    $yRight = $pdf->GetY();
    $pdf->SetY(max($yLeft, $yRight) + 6);

    // Items Section Header
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 7, 'ORDER LINE ITEMS', 0, 1, 'L');

    // Table Header
    $pdf->SetFillColor(241, 245, 249); // slate-100
    $pdf->SetDrawColor(203, 213, 225);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(90, 8, 'Item Description', 1, 0, 'L', true);
    $pdf->Cell(25, 8, 'Type', 1, 0, 'C', true);
    $pdf->Cell(15, 8, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Unit Price', 1, 0, 'R', true);
    $pdf->Cell(25, 8, 'Subtotal', 1, 1, 'R', true);

    // Table Rows (Products)
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(51, 65, 85);

    $itemsSubtotal = 0;
    foreach ($items as $item) {
        $name     = $item['productName'] ?? 'Product Item';
        $qty      = (int)($item['quantity'] ?? 1);
        $price    = (float)($item['unitPrice'] ?? $item['price'] ?? 0);
        $subtotal = $qty * $price;
        $itemsSubtotal += $subtotal;

        // Truncate name safely if too long
        if (strlen($name) > 42) {
            $name = substr($name, 0, 39) . '...';
        }

        $pdf->Cell(90, 7, '  ' . $name, 1, 0, 'L');
        $pdf->Cell(25, 7, 'Product', 1, 0, 'C');
        $pdf->Cell(15, 7, (string)$qty, 1, 0, 'C');
        $pdf->Cell(25, 7, 'PHP ' . number_format($price, 2), 1, 0, 'R');
        $pdf->Cell(25, 7, 'PHP ' . number_format($subtotal, 2) . ' ', 1, 1, 'R');
    }

    // Optional Services attached to this order
    $servicesSubtotal = 0;
    if (!empty($services)) {
        foreach ($services as $srv) {
            $sName  = $srv['serviceName'] ?? 'Workshop Service';
            $sPrice = (float)($srv['servicePrice'] ?? $srv['price'] ?? 0);
            $servicesSubtotal += $sPrice;

            if (strlen($sName) > 42) {
                $sName = substr($sName, 0, 39) . '...';
            }

            $pdf->Cell(90, 7, '  ' . $sName, 1, 0, 'L');
            $pdf->Cell(25, 7, 'Service', 1, 0, 'C');
            $pdf->Cell(15, 7, '1', 1, 0, 'C');
            $pdf->Cell(25, 7, 'PHP ' . number_format($sPrice, 2), 1, 0, 'R');
            $pdf->Cell(25, 7, 'PHP ' . number_format($sPrice, 2) . ' ', 1, 1, 'R');
        }
    }

    $grandTotal = $itemsSubtotal + $servicesSubtotal;

    // Financial Summary Block
    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(71, 85, 105);

    $pdf->Cell(130, 6, '', 0, 0);
    $pdf->Cell(25, 6, 'Products Total:', 0, 0, 'R');
    $pdf->Cell(25, 6, 'PHP ' . number_format($itemsSubtotal, 2) . ' ', 0, 1, 'R');

    if ($servicesSubtotal > 0) {
        $pdf->Cell(130, 6, '', 0, 0);
        $pdf->Cell(25, 6, 'Services Total:', 0, 0, 'R');
        $pdf->Cell(25, 6, 'PHP ' . number_format($servicesSubtotal, 2) . ' ', 0, 1, 'R');
    }

    // Grand Total Highlight
    $pdf->SetFillColor(254, 243, 199); // amber-100
    $pdf->SetDrawColor(245, 158, 11);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(15, 23, 42);

    $pdf->Cell(115, 8, '', 0, 0);
    $pdf->Cell(40, 8, 'GRAND TOTAL: ', 1, 0, 'R', true);
    $pdf->Cell(25, 8, 'PHP ' . number_format($grandTotal, 2) . ' ', 1, 1, 'R', true);

    // Payment Status Note
    $pdf->Ln(6);
    $pdf->SetFont('Arial', 'I', 8.5);
    $pdf->SetTextColor(100, 116, 139);
    $paymentNote = 'Payment recorded via ' . ($order['paymentMethod'] ?? 'Standard Payment') . '. Verified electronically by PedalWorks Dynamics Administration.';
    $pdf->MultiCell(0, 4.5, $paymentNote, 0, 'L');

    return $pdf->Output('S');
}
