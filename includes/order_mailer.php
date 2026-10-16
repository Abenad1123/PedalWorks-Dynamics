<?php
/**
 * Order Mailer Helper
 * ITIM211 Term Test Lab Requirements:
 * - Prepared statements (10 pts)
 * - Send email to customer of updated transaction (5 pts)
 * - Email contains list of products/services, subtotal, and grand total (5 pts)
 * - Add a PDF attachment of the transaction receipt (5 pts)
 * - Use Mailtrap service (sandbox.smtp.mailtrap.io)
 */

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/includes/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/receipt_pdf.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an updated transaction notification email to customer via Mailtrap with PDF receipt
 *
 * @param mysqli $conn Active database connection
 * @param int $orderId Product sale / order ID
 * @param string $newStatus The updated order status
 * @return array ['success' => bool, 'message' => string, 'email' => string]
 */
function sendOrderTransactionEmail($conn, $orderId, $newStatus)
{
    $orderId = (int)$orderId;
    if ($orderId <= 0 || !$conn) {
        return ['success' => false, 'message' => 'Invalid order ID or database connection.'];
    }

    // 1. Fetch Order & Customer Details using Prepared Statements (Term Test Lab rubric)
    $stmtOrder = mysqli_prepare($conn, "SELECT 
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

    if (!$stmtOrder) {
        return ['success' => false, 'message' => 'Database prepare failed: ' . mysqli_error($conn)];
    }

    mysqli_stmt_bind_param($stmtOrder, 'i', $orderId);
    mysqli_stmt_execute($stmtOrder);
    $resOrder = mysqli_stmt_get_result($stmtOrder);
    $order = mysqli_fetch_assoc($resOrder);
    mysqli_stmt_close($stmtOrder);

    if (!$order) {
        return ['success' => false, 'message' => 'Order not found for ID #' . $orderId];
    }

    // 2. Fetch Line Items using Prepared Statement (Term Test Lab rubric)
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

    if ($stmtItems) {
        mysqli_stmt_bind_param($stmtItems, 'i', $orderId);
        mysqli_stmt_execute($stmtItems);
        $resItems = mysqli_stmt_get_result($stmtItems);
        while ($row = mysqli_fetch_assoc($resItems)) {
            $items[] = $row;
        }
        mysqli_stmt_close($stmtItems);
    }

    // 3. Fetch Linked Workshop Services (if any) using Prepared Statement
    $services = [];
    $stmtServices = mysqli_prepare($conn, "SELECT 
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

    if ($stmtServices) {
        mysqli_stmt_bind_param($stmtServices, 'i', $orderId);
        mysqli_stmt_execute($stmtServices);
        $resServices = mysqli_stmt_get_result($stmtServices);
        while ($sRow = mysqli_fetch_assoc($resServices)) {
            $services[] = $sRow;
        }
        mysqli_stmt_close($stmtServices);
    }

    // Calculate totals
    $productsSubtotal = 0;
    foreach ($items as $it) {
        $productsSubtotal += ((float)$it['unitPrice'] * (int)$it['quantity']);
    }

    $servicesSubtotal = 0;
    foreach ($services as $srv) {
        $servicesSubtotal += (float)$srv['servicePrice'];
    }

    $grandTotal = $productsSubtotal + $servicesSubtotal;

    // Load Mailtrap SMTP Configuration
    $mailConfig = require __DIR__ . '/mail_config.php';

    // 4. Generate PDF Receipt via FPDF (Term Test Lab rubric: 5 pts)
    $pdfBytes = generateOrderReceiptPDF($order, $items, $services);

    // 5. Build HTML Email Body (Term Test Lab rubric: 5 pts list of products/services, subtotal & grand total)
    $customerName  = htmlspecialchars($order['customerName']);
    $orderRef      = 'PW-ORD-' . str_pad($orderId, 5, '0', STR_PAD_LEFT);
    $statusColor   = '#0284c7'; // default blue
    if ($newStatus === 'Completed') $statusColor = '#10b981'; // green
    if ($newStatus === 'Cancelled') $statusColor = '#ef4444'; // red
    if ($newStatus === 'In Progress' || $newStatus === 'Ready for Pickup') $statusColor = '#f59e0b'; // amber

    // Item table HTML rows
    $itemsHtml = '';
    foreach ($items as $item) {
        $name     = htmlspecialchars($item['productName']);
        $qty      = (int)$item['quantity'];
        $price    = number_format((float)$item['unitPrice'], 2);
        $subtotal = number_format($qty * (float)$item['unitPrice'], 2);
        $itemsHtml .= "
        <tr style='border-bottom: 1px solid #334155;'>
            <td style='padding: 10px 12px; color: #f8fafc; font-size: 14px;'>{$name}</td>
            <td style='padding: 10px 12px; color: #94a3b8; font-size: 13px; text-align: center;'>Product</td>
            <td style='padding: 10px 12px; color: #cbd5e1; font-size: 14px; text-align: center;'>{$qty}</td>
            <td style='padding: 10px 12px; color: #cbd5e1; font-size: 14px; text-align: right;'>PHP {$price}</td>
            <td style='padding: 10px 12px; color: #f8fafc; font-size: 14px; text-align: right; font-weight: 600;'>PHP {$subtotal}</td>
        </tr>";
    }

    // Service table HTML rows (if any)
    $servicesHtml = '';
    if (!empty($services)) {
        foreach ($services as $srv) {
            $sName  = htmlspecialchars($srv['serviceName']);
            $sPrice = number_format((float)$srv['servicePrice'], 2);
            $servicesHtml .= "
            <tr style='border-bottom: 1px solid #334155;'>
                <td style='padding: 10px 12px; color: #f8fafc; font-size: 14px;'>{$sName}</td>
                <td style='padding: 10px 12px; color: #38bdf8; font-size: 13px; text-align: center;'>Workshop Service</td>
                <td style='padding: 10px 12px; color: #cbd5e1; font-size: 14px; text-align: center;'>1</td>
                <td style='padding: 10px 12px; color: #cbd5e1; font-size: 14px; text-align: right;'>PHP {$sPrice}</td>
                <td style='padding: 10px 12px; color: #f8fafc; font-size: 14px; text-align: right; font-weight: 600;'>PHP {$sPrice}</td>
            </tr>";
        }
    }

    $formattedProductsSubtotal = number_format($productsSubtotal, 2);
    $formattedServicesSubtotal = number_format($servicesSubtotal, 2);
    $formattedGrandTotal       = number_format($grandTotal, 2);

    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Order Status Update - PedalWorks</title>
    </head>
    <body style='margin: 0; padding: 0; background-color: #0b1120; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #e2e8f0;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #0b1120; padding: 30px 15px;'>
            <tr>
                <td align='center'>
                    <table width='600' cellpadding='0' cellspacing='0' style='max-width: 600px; width: 100%; background: #0f172a; border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);'>
                        
                        <!-- Header -->
                        <tr>
                            <td style='background: linear-gradient(135deg, #1e293b, #0f172a); padding: 24px 30px; border-bottom: 2px solid #f59e0b;'>
                                <table width='100%' cellpadding='0' cellspacing='0'>
                                    <tr>
                                        <td>
                                            <span style='font-size: 20px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;'>
                                                PEDAL<span style='color: #f59e0b;'>WORKS</span> DYNAMICS
                                            </span>
                                            <div style='font-size: 11px; color: #94a3b8; margin-top: 3px; letter-spacing: 0.5px; text-transform: uppercase;'>
                                                Transaction Status Update
                                            </div>
                                        </td>
                                        <td align='right'>
                                            <span style='background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; border: 1px solid rgba(245, 158, 11, 0.3);'>
                                                #{$orderRef}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Body Content -->
                        <tr>
                            <td style='padding: 30px;'>
                                <h2 style='margin: 0 0 12px 0; font-size: 18px; color: #f8fafc;'>
                                    Hello, {$customerName}!
                                </h2>
                                <p style='margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #cbd5e1;'>
                                    Your order <strong>#{$orderRef}</strong> has been updated. Below is the summary of your transaction and items.
                                </p>

                                <!-- Status Badge Banner -->
                                <div style='background: rgba(15, 23, 42, 0.7); border-left: 4px solid {$statusColor}; padding: 14px 18px; border-radius: 8px; margin-bottom: 25px; border-top: 1px solid #1e293b; border-right: 1px solid #1e293b; border-bottom: 1px solid #1e293b;'>
                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 600;'>Current Order Status</div>
                                    <div style='font-size: 18px; font-weight: 700; color: {$statusColor}; margin-top: 4px;'>
                                        " . strtoupper($newStatus) . "
                                    </div>
                                    <div style='font-size: 12px; color: #94a3b8; margin-top: 4px;'>
                                        Payment Method: <strong>{$order['paymentMethod']}</strong> | Fulfillment: <strong>{$order['pickupMethod']}</strong>
                                    </div>
                                </div>

                                <!-- Line Items Table (Products & Services) -->
                                <h3 style='margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; color: #cbd5e1; font-weight: 700;'>
                                    Items in Your Transaction
                                </h3>
                                <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse: collapse; margin-bottom: 20px; background: #131d33; border-radius: 8px; overflow: hidden; border: 1px solid #1e293b;'>
                                    <thead>
                                        <tr style='background: #1e293b;'>
                                            <th align='left' style='padding: 10px 12px; font-size: 12px; color: #94a3b8; text-transform: uppercase;'>Item</th>
                                            <th align='center' style='padding: 10px 12px; font-size: 12px; color: #94a3b8; text-transform: uppercase;'>Type</th>
                                            <th align='center' style='padding: 10px 12px; font-size: 12px; color: #94a3b8; text-transform: uppercase;'>Qty</th>
                                            <th align='right' style='padding: 10px 12px; font-size: 12px; color: #94a3b8; text-transform: uppercase;'>Price</th>
                                            <th align='right' style='padding: 10px 12px; font-size: 12px; color: #94a3b8; text-transform: uppercase;'>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {$itemsHtml}
                                        {$servicesHtml}
                                    </tbody>
                                </table>

                                <!-- Totals Block -->
                                <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom: 25px;'>
                                    <tr>
                                        <td align='right' style='padding: 4px 12px; font-size: 13px; color: #94a3b8;'>Products Subtotal:</td>
                                        <td align='right' width='120' style='padding: 4px 12px; font-size: 14px; color: #cbd5e1;'>PHP {$formattedProductsSubtotal}</td>
                                    </tr>";

    if ($servicesSubtotal > 0) {
        $htmlBody .= "
                                    <tr>
                                        <td align='right' style='padding: 4px 12px; font-size: 13px; color: #94a3b8;'>Services Subtotal:</td>
                                        <td align='right' width='120' style='padding: 4px 12px; font-size: 14px; color: #cbd5e1;'>PHP {$formattedServicesSubtotal}</td>
                                    </tr>";
    }

    $htmlBody .= "
                                    <tr>
                                        <td align='right' style='padding: 10px 12px; font-size: 15px; font-weight: 700; color: #f8fafc;'>Grand Total:</td>
                                        <td align='right' width='120' style='padding: 10px 12px; font-size: 17px; font-weight: 800; color: #f59e0b;'>PHP {$formattedGrandTotal}</td>
                                    </tr>
                                </table>

                                <!-- PDF Attachment Note Banner -->
                                <div style='background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #38bdf8; display: flex; align-items: center;'>
                                    <span>&#128206; <strong>Receipt Attached:</strong> An official PDF transaction receipt (<code>PedalWorks_Receipt_{$orderRef}.pdf</code>) is attached to this email for your records.</span>
                                </div>

                                <p style='margin: 0; font-size: 13px; color: #94a3b8; line-height: 1.5;'>
                                    If you have any questions regarding your order or workshop schedule, please contact our support desk or reply directly to this notification.
                                </p>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style='background: #090e1a; padding: 18px 30px; text-align: center; border-top: 1px solid #1e293b; font-size: 12px; color: #64748b;'>
                                PedalWorks Dynamics &bull; High-Performance Cycling &amp; Precision Workshop<br>
                                Automated Transaction Service &bull; Mailtrap Testing Environment
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>";

    $plainText = "PEDALWORKS DYNAMICS - ORDER UPDATE\n\n"
        . "Order #{$orderRef}\n"
        . "Customer: {$customerName}\n"
        . "Status: {$newStatus}\n"
        . "Grand Total: PHP {$formattedGrandTotal}\n\n"
        . "An official PDF receipt has been attached to this email.\n";

    // 6. Send via PHPMailer + Mailtrap
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $mailConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Port       = $mailConfig['port'];
        $mail->Username   = $mailConfig['username'];
        $mail->Password   = $mailConfig['password'];

        $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
        
        $customerEmail = !empty($order['customerEmail']) ? $order['customerEmail'] : 'customer@example.com';
        $mail->addAddress($customerEmail, $order['customerName']);

        // Attach PDF Receipt (Term Test Lab rubric: 5 pts)
        $pdfFileName = 'PedalWorks_Receipt_' . $orderRef . '.pdf';
        $mail->addStringAttachment($pdfBytes, $pdfFileName, 'base64', 'application/pdf');

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Order Status Updated: #{$orderRef} is now {$newStatus}";
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainText;

        $mail->send();

        return [
            'success' => true,
            'message' => "Order #{$orderRef} updated to '{$newStatus}' and transaction email with receipt PDF was successfully sent to {$customerEmail} via Mailtrap.",
            'email'   => $customerEmail
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Order updated to '{$newStatus}', but email could not be sent. Mailer Error: {$mail->ErrorInfo}",
            'email'   => $order['customerEmail'] ?? ''
        ];
    }
}
