<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('includes/config.php');

if (empty($_SESSION['customerAccountID'])) {
    $_SESSION['error'] = 'Please sign in to proceed with checkout.';
    header('Location: login.php');
    exit;
}

$customerAccountID = (int)$_SESSION['customerAccountID'];
$customerID        = (int)($_SESSION['customerID'] ?? 0);

$cartItems = [];
$subtotal = 0.0;
$totalQuantity = 0;

if ($conn) {
    $sql = "SELECT c.customerCartID, c.quantity, 
                   p.productID, p.sku, p.name, p.image, p.price, p.stock,
                   pc.name AS categoryName
            FROM customerCart c
            JOIN product p ON c.productID = p.productID
            LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID
            WHERE c.customerAccountID = ? AND p.endDate IS NULL
            ORDER BY c.customerCartID ASC";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $customerAccountID);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $lineSubtotal = (float)$row['price'] * (int)$row['quantity'];
            $row['lineSubtotal'] = $lineSubtotal;
            $subtotal += $lineSubtotal;
            $totalQuantity += (int)$row['quantity'];
            $cartItems[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

if (empty($cartItems)) {
    $_SESSION['warning'] = 'Your shopping cart is empty. Please add items before checking out.';
    header('Location: cart.php');
    exit;
}

$customerData = [
    'firstName' => $_SESSION['firstName'] ?? '',
    'lastName'  => '',
    'email'     => $_SESSION['email'] ?? '',
    'address'   => ''
];

if ($conn && $customerID > 0) {
    $cStmt = mysqli_prepare($conn, "SELECT firstName, lastName, email, address FROM customer WHERE customerID = ? LIMIT 1");
    if ($cStmt) {
        mysqli_stmt_bind_param($cStmt, 'i', $customerID);
        mysqli_stmt_execute($cStmt);
        $cRes = mysqli_stmt_get_result($cStmt);
        if ($cRow = mysqli_fetch_assoc($cRes)) {
            $customerData = $cRow;
        }
        mysqli_stmt_close($cStmt);
    }
}

$availableServices = [];
if ($conn) {
    $svcRes = @mysqli_query($conn, "SELECT s.serviceID, s.sku, s.name, s.price, sc.name AS categoryName 
                                     FROM service s 
                                     LEFT JOIN serviceCategory sc ON s.serviceCategoryID = sc.serviceCategoryID 
                                     WHERE s.endDate IS NULL 
                                     ORDER BY sc.name ASC, s.name ASC");
    if ($svcRes) {
        while ($svcRow = mysqli_fetch_assoc($svcRes)) {
            $availableServices[] = $svcRow;
        }
    }
}

$errors = [];
$pickupMethod    = $_POST['pickupMethod'] ?? 'Delivery';
$deliveryAddress = $_POST['deliveryAddress'] ?? ($customerData['address'] ?? '');
$paymentMethod   = $_POST['paymentMethod'] ?? 'Cash on Delivery';

if ($pickupMethod === 'Delivery') {
    $paymentMethod = 'Cash on Delivery';
} elseif ($pickupMethod === 'In-Store Pickup') {
    $paymentMethod = 'Cash on Pickup';
}

$attachLabor         = !empty($_POST['attachLabor']) && $_POST['attachLabor'] === '1';
$laborServiceID      = isset($_POST['laborServiceID']) ? (int)$_POST['laborServiceID'] : 0;
$laborScheduledRaw   = isset($_POST['laborScheduledDate']) ? trim($_POST['laborScheduledDate']) : '';
$laborNotes          = isset($_POST['laborNotes']) ? trim($_POST['laborNotes']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!in_array($pickupMethod, ['Delivery', 'In-Store Pickup'], true)) {
        $errors['pickupMethod'] = 'Please select a valid fulfillment method.';
    }

    if ($pickupMethod === 'Delivery') {
        $cleanAddress = trim($deliveryAddress);
        if ($cleanAddress === '') {
            $errors['deliveryAddress'] = 'Delivery address is required for doorstep delivery.';
        } elseif (mb_strlen($cleanAddress) < 8) {
            $errors['deliveryAddress'] = 'Please provide a complete street address (minimum 8 characters).';
        }

        $paymentMethod = 'Cash on Delivery';
    } else {

        $deliveryAddress = 'In-Store Pickup: PedalWorks Basecamp, Km. 14 East Service Road, Western Bicutan, Taguig City';

        $paymentMethod = 'Cash on Pickup';
    }

    $laborScheduledFormatted = null;
    if ($attachLabor) {
        if ($laborServiceID <= 0) {
            $errors['laborServiceID'] = 'Please select a workshop service to attach.';
        }
        if ($laborScheduledRaw !== '') {
            $ts = strtotime($laborScheduledRaw);
            if (!$ts || $ts < strtotime(date('Y-m-d 08:00:00'))) {
                $errors['laborScheduledDate'] = 'Preferred service schedule date must not be in the past.';
            } else {
                $laborScheduledFormatted = date('Y-m-d H:i:s', $ts);
            }
        }
    }

    if (empty($errors) && $conn) {

        mysqli_begin_transaction($conn);

        try {

            $checkStmt = mysqli_prepare($conn, "SELECT c.quantity, p.productID, p.name, p.stock, p.price 
                                                FROM customerCart c 
                                                JOIN product p ON c.productID = p.productID 
                                                WHERE c.customerAccountID = ? AND p.endDate IS NULL 
                                                FOR UPDATE");
            mysqli_stmt_bind_param($checkStmt, 'i', $customerAccountID);
            mysqli_stmt_execute($checkStmt);
            $checkRes = mysqli_stmt_get_result($checkStmt);

            $lockedItems = [];
            while ($item = mysqli_fetch_assoc($checkRes)) {
                if ((int)$item['stock'] < (int)$item['quantity']) {
                    throw new Exception('Insufficient inventory for "' . $item['name'] . '". Only ' . (int)$item['stock'] . ' unit(s) remaining in stock.');
                }
                $lockedItems[] = $item;
            }
            mysqli_stmt_close($checkStmt);

            if (empty($lockedItems)) {
                throw new Exception('Your shopping cart contains items that are no longer available.');
            }

            $saleStmt = mysqli_prepare($conn, "INSERT INTO productSale (customerAccountID, paymentMethod, pickupMethod, deliveryAddress, status, orderDate) 
                                               VALUES (?, ?, ?, ?, 'Pending', NOW())");
            mysqli_stmt_bind_param($saleStmt, 'isss', $customerAccountID, $paymentMethod, $pickupMethod, $deliveryAddress);
            if (!mysqli_stmt_execute($saleStmt)) {
                throw new Exception('Could not create order transaction: ' . mysqli_stmt_error($saleStmt));
            }
            $productSaleID = mysqli_insert_id($conn);
            mysqli_stmt_close($saleStmt);

            $lineStmt  = mysqli_prepare($conn, "INSERT INTO orderLineProduct (productSaleID, productID, quantity) VALUES (?, ?, ?)");
            $stockStmt = mysqli_prepare($conn, "UPDATE product SET stock = stock - ? WHERE productID = ?");

            foreach ($lockedItems as $item) {
                $pid = (int)$item['productID'];
                $qty = (int)$item['quantity'];

                mysqli_stmt_bind_param($lineStmt, 'iii', $productSaleID, $pid, $qty);
                if (!mysqli_stmt_execute($lineStmt)) {
                    throw new Exception('Failed to record order item: ' . mysqli_stmt_error($lineStmt));
                }

                mysqli_stmt_bind_param($stockStmt, 'ii', $qty, $pid);
                if (!mysqli_stmt_execute($stockStmt)) {
                    throw new Exception('Failed to update stock inventory: ' . mysqli_stmt_error($stockStmt));
                }
            }
            mysqli_stmt_close($lineStmt);
            mysqli_stmt_close($stockStmt);

            if ($attachLabor && $laborServiceID > 0) {
                $laborStmt = mysqli_prepare($conn, "INSERT INTO customerService (serviceID, customerAccountID, productSaleID, requestDate, scheduledDate, notes, status) 
                                                    VALUES (?, ?, ?, NOW(), ?, ?, 'Pending')");
                if ($laborStmt) {
                    mysqli_stmt_bind_param($laborStmt, 'iiiss', $laborServiceID, $customerAccountID, $productSaleID, $laborScheduledFormatted, $laborNotes);
                    if (!mysqli_stmt_execute($laborStmt)) {
                        throw new Exception('Failed to attach labor request to order: ' . mysqli_stmt_error($laborStmt));
                    }
                    mysqli_stmt_close($laborStmt);
                }
            }

            $clearStmt = mysqli_prepare($conn, "DELETE FROM customerCart WHERE customerAccountID = ?");
            mysqli_stmt_bind_param($clearStmt, 'i', $customerAccountID);
            mysqli_stmt_execute($clearStmt);
            mysqli_stmt_close($clearStmt);

            mysqli_commit($conn);

            try {
                if (file_exists('PHPMailer/src/PHPMailer.php')) {
                    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                        require 'PHPMailer/src/Exception.php';
                        require 'PHPMailer/src/PHPMailer.php';
                        require 'PHPMailer/src/SMTP.php';
                    }

                    $mail = new PHPMailer\PHPMailer\PHPMailer(false);
                    $mail->isSMTP();
                    $mail->Host       = 'sandbox.smtp.mailtrap.io';
                    $mail->SMTPAuth   = true;
                    $mail->Port       = 2525;
                    $mail->Username   = '312649aee7d340';
                    $mail->Password   = '7bec3dc61ba28b';
                    $mail->setFrom('orders@pedalworks.com', 'PedalWorks Dynamics');

                    $cEmail = !empty($customerData['email']) ? $customerData['email'] : 'customer@example.com';
                    $cName = trim(($customerData['firstName'] ?? '') . ' ' . ($customerData['lastName'] ?? ''));
                    if ($cName === '') $cName = 'Valued Rider';
                    $mail->addAddress($cEmail, $cName);

                    $mail->isHTML(true);
                    $mail->Subject = "Order Confirmation #PW-ORD-{$productSaleID} - PedalWorks Dynamics";

                    $itemsHtml = '';
                    foreach ($lockedItems as $li) {
                        $liPrice = number_format((float)$li['price'], 2);
                        $lineTotal = (float)$li['price'] * (int)$li['quantity'];
                        $liSub = number_format($lineTotal, 2);
                        $itemsHtml .= "<tr>
                            <td style='padding:8px; border:1px solid #ddd;'>{$li['name']}</td>
                            <td style='padding:8px; border:1px solid #ddd; text-align:center;'>{$li['quantity']}</td>
                            <td style='padding:8px; border:1px solid #ddd; text-align:right;'>PHP {$liPrice}</td>
                            <td style='padding:8px; border:1px solid #ddd; text-align:right;'>PHP {$liSub}</td>
                        </tr>";
                    }
                    $subtotalFmt = number_format($subtotal, 2);

                    $mail->Body = "<h2>Order Confirmation - PedalWorks Dynamics</h2>
                        <p>Hello <strong>{$cName}</strong>,</p>
                        <p>Thank you for ordering with PedalWorks Dynamics. Your order <strong>#PW-ORD-{$productSaleID}</strong> has been successfully placed and is now queued for preparation.</p>
                        <table style='width:100%; border-collapse:collapse; margin-bottom:15px; font-family:sans-serif;'>
                            <tr><td style='padding:4px 0;'><strong>Fulfillment Method:</strong></td><td>{$pickupMethod}</td></tr>
                            <tr><td style='padding:4px 0;'><strong>Payment Method:</strong></td><td>{$paymentMethod}</td></tr>
                            <tr><td style='padding:4px 0;'><strong>Delivery / Pickup Address:</strong></td><td>" . htmlspecialchars($deliveryAddress) . "</td></tr>
                        </table>
                        <h3>Order Summary</h3>
                        <table style='width:100%; border-collapse:collapse; font-family:sans-serif;'>
                            <thead>
                                <tr style='background:#f2f2f2;'>
                                    <th style='padding:8px; border:1px solid #ddd; text-align:left;'>Item Description</th>
                                    <th style='padding:8px; border:1px solid #ddd;'>Qty</th>
                                    <th style='padding:8px; border:1px solid #ddd; text-align:right;'>Price</th>
                                    <th style='padding:8px; border:1px solid #ddd; text-align:right;'>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$itemsHtml}
                                <tr>
                                    <td colspan='3' style='padding:8px; border:1px solid #ddd; text-align:right; font-weight:bold;'>Total Payable:</td>
                                    <td style='padding:8px; border:1px solid #ddd; text-align:right; font-weight:bold;'>PHP {$subtotalFmt}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p style='margin-top:20px; color:#666;'>You can track your order status and view your printable invoice anytime under your customer portal.</p>";

                    $mail->send();
                }
            } catch (\Throwable $mailEx) {

            }

            $successMsg = 'Your order #PW-ORD-' . $productSaleID . ' has been placed successfully!';
            if ($attachLabor) {
                $successMsg .= ' An attached workshop service request was also scheduled with our mechanics.';
            }
            $_SESSION['success'] = $successMsg;
            header('Location: user/orders.php?order_id=' . $productSaleID . '&placed=1');
            exit;

        } catch (Exception $ex) {
            mysqli_rollback($conn);
            $errors['general'] = $ex->getMessage();
        }
    }
}

include('includes/header.php');
?>

<div class="container py-5">

    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small text-white-50">
                <li class="breadcrumb-item"><a href="index.php" class="text-white-50 text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="cart.php" class="text-white-50 text-decoration-none">Cart</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Checkout</li>
            </ol>
        </nav>
        <h1 class="h2 fw-bold text-white mb-3">Checkout &amp; Finalize Order</h1>

        <div class="pw-checkout-stepper mb-4">
            <a href="cart.php" class="pw-step-item completed text-decoration-none">
                <span class="pw-step-num"><i class="fa-solid fa-check"></i></span>
                <span>1. Review Cart</span>
            </a>
            <div class="pw-step-divider active"></div>
            <div class="pw-step-item active">
                <span class="pw-step-num">2</span>
                <span>2. Fulfillment &amp; Payment</span>
            </div>
            <div class="pw-step-divider"></div>
            <div class="pw-step-item">
                <span class="pw-step-num">3</span>
                <span>3. Confirmation</span>
            </div>
        </div>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger pw-glass-alert alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation text-danger fs-5"></i>
            <div><?php echo htmlspecialchars($errors['general']); ?></div>
            <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form action="checkout.php" method="POST" novalidate>
        <div class="row g-4">

            <div class="col-lg-7">

                <div class="pw-checkout-card p-4 mb-4">
                    <h5 class="fw-bold text-white mb-3 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-truck-fast text-primary"></i>
                        <span>Fulfillment Method</span>
                    </h5>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="pw-segmented-option">
                                <input type="radio" name="pickupMethod" value="Delivery" 
                                       <?php echo ($pickupMethod === 'Delivery') ? 'checked' : ''; ?>
                                       onchange="toggleFulfillment('Delivery');">
                                <div class="pw-segmented-box h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fw-semibold text-white">Doorstep Delivery</span>
                                        <i class="fa-solid fa-house-chimney text-warning"></i>
                                    </div>
                                    <p class="text-white-50 small mb-0">Delivered directly to your provided address</p>
                                </div>
                            </label>
                        </div>

                        <div class="col-sm-6">
                            <label class="pw-segmented-option">
                                <input type="radio" name="pickupMethod" value="In-Store Pickup" 
                                       <?php echo ($pickupMethod === 'In-Store Pickup') ? 'checked' : ''; ?>
                                       onchange="toggleFulfillment('In-Store Pickup');">
                                <div class="pw-segmented-box h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fw-semibold text-white">In-Store Pickup</span>
                                        <i class="fa-solid fa-store text-success"></i>
                                    </div>
                                    <p class="text-white-50 small mb-0">Pick up at Taguig Basecamp (Free)</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    <?php if (isset($errors['pickupMethod'])): ?>
                        <div class="text-danger small mb-3"><?php echo htmlspecialchars($errors['pickupMethod']); ?></div>
                    <?php endif; ?>

                    <div id="deliveryAddressGroup" style="<?php echo ($pickupMethod === 'In-Store Pickup') ? 'display: none;' : ''; ?>">
                        <label for="deliveryAddress" class="form-label text-white small fw-semibold">
                            Delivery Street Address <span class="text-danger">*</span>
                        </label>
                        <textarea name="deliveryAddress" id="deliveryAddress" rows="3" 
                                  class="form-control pw-form-control <?php echo isset($errors['deliveryAddress']) ? 'is-invalid' : ''; ?>"
                                  placeholder="House/Unit #, Street Name, Barangay, City, Postal Code"><?php echo htmlspecialchars($deliveryAddress); ?></textarea>
                        <?php if (isset($errors['deliveryAddress'])): ?>
                            <div class="text-danger small mt-1"><?php echo htmlspecialchars($errors['deliveryAddress']); ?></div>
                        <?php else: ?>
                            <div class="text-white-50 small mt-1">Pre-filled from your profile. You may modify this for this order.</div>
                        <?php endif; ?>
                    </div>

                    <div id="pickupInfoGroup" class="p-3 rounded" style="background: rgba(255,255,255,0.04); border: 1px dashed rgba(255,255,255,0.18); <?php echo ($pickupMethod !== 'In-Store Pickup') ? 'display: none;' : ''; ?>">
                        <div class="d-flex align-items-start gap-3">
                            <div class="pw-workshop-spec-icon text-success" style="font-size: 1.4rem;">
                                <i class="fa-solid fa-store"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between">
                                    <strong class="text-white d-block">PedalWorks Taguig Central Basecamp</strong>
                                    <span class="badge rounded-pill bg-success bg-opacity-25 text-success border border-success border-opacity-25 small">Free Pickup</span>
                                </div>
                                <span class="text-white-50 small d-block mb-2">Km. 14 East Service Road, Western Bicutan, Taguig City 1630</span>
                                <div class="small text-white-50 mb-1">
                                    <i class="fa-solid fa-clock text-info me-1"></i> Operating Hours: Mon – Sat: 8:00 AM – 6:30 PM | Sun: 9:00 AM – 3:00 PM
                                </div>
                                <div class="small text-warning">
                                    <i class="fa-solid fa-id-card me-1"></i> Pickup Protocol: Present your Order ID at the main counter upon arrival.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pw-checkout-card p-4 mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="fw-bold text-white mb-0 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-screwdriver-wrench text-warning"></i>
                            <span>Workshop Labor Request</span>
                        </h5>
                        <span class="badge rounded-pill bg-warning text-dark small fw-bold">Optional Service</span>
                    </div>
                    <p class="text-white-50 small mb-3">
                        Would you like our certified mechanics to professionally assemble or install the components in this order?
                    </p>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="attachLaborSwitch" name="attachLabor" value="1" <?php echo $attachLabor ? 'checked' : ''; ?> onchange="document.getElementById('laborDetailsGroup').style.display = this.checked ? 'block' : 'none';">
                        <label class="form-check-label text-white fw-semibold" for="attachLaborSwitch">
                            Attach a labor or parts installation request to this order
                        </label>
                    </div>

                    <div id="laborDetailsGroup" class="p-3 rounded mt-2" style="background: rgba(255,255,255,0.03); border: 1px solid var(--pw-glass-border); <?php echo $attachLabor ? '' : 'display: none;'; ?>">
                        <div class="mb-3">
                            <label class="form-label text-white small fw-bold">Select Workshop Service</label>
                            <select name="laborServiceID" class="form-select pw-form-select <?php echo isset($errors['laborServiceID']) ? 'is-invalid' : ''; ?>">
                                <option value="">-- Choose Workshop Service --</option>
                                <?php foreach ($availableServices as $svc): ?>
                                    <option value="<?php echo (int)$svc['serviceID']; ?>" <?php echo ($laborServiceID === (int)$svc['serviceID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($svc['name']); ?> (&#8369;<?php echo number_format($svc['price'], 2); ?> &bull; <?php echo htmlspecialchars($svc['categoryName'] ?? 'Workshop'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['laborServiceID'])): ?>
                                <div class="text-danger small mt-1"><?php echo htmlspecialchars($errors['laborServiceID']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-white small fw-bold">Preferred Service Date &amp; Time</label>
                            <input type="datetime-local" name="laborScheduledDate" class="form-control pw-form-control <?php echo isset($errors['laborScheduledDate']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($laborScheduledRaw); ?>" min="<?php echo date('Y-m-d\TH:i'); ?>">
                            <?php if (isset($errors['laborScheduledDate'])): ?>
                                <div class="text-danger small mt-1"><?php echo htmlspecialchars($errors['laborScheduledDate']); ?></div>
                            <?php else: ?>
                                <div class="text-white-50 small mt-1">Target date to bring your bike or complete parts installation.</div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="form-label text-white small fw-bold">Technician Notes / Bike Model</label>
                            <textarea name="laborNotes" class="form-control pw-form-control" rows="2" placeholder="e.g. Please install the ordered brake set and pedals on my 29er hardtail."><?php echo htmlspecialchars($laborNotes); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="pw-checkout-card p-4">
                    <h5 class="fw-bold text-white mb-3 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-credit-card text-success"></i>
                        <span>Payment Method</span>
                    </h5>

                    <div class="row g-3" id="paymentOptionsRow">
                        <div class="col-12" id="codOptionCol" style="<?php echo ($pickupMethod === 'In-Store Pickup') ? 'display: none;' : ''; ?>">
                            <label class="pw-segmented-option">
                                <input type="radio" name="paymentMethod" id="paymentMethodCOD" value="Cash on Delivery" 
                                       <?php echo ($pickupMethod !== 'In-Store Pickup') ? 'checked' : ''; ?>
                                       <?php echo ($pickupMethod === 'In-Store Pickup') ? 'disabled' : ''; ?>>
                                <div class="pw-segmented-box h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fw-semibold text-white">Cash on Delivery (COD)</span>
                                        <i class="fa-solid fa-money-bill-wave text-success"></i>
                                    </div>
                                    <p class="text-white-50 small mb-0">Pay upon parcel arrival at your doorstep</p>
                                </div>
                            </label>
                        </div>

                        <div class="col-12" id="copOptionCol" style="<?php echo ($pickupMethod === 'Delivery') ? 'display: none;' : ''; ?>">
                            <label class="pw-segmented-option">
                                <input type="radio" name="paymentMethod" id="paymentMethodCOP" value="Cash on Pickup" 
                                       <?php echo ($pickupMethod === 'In-Store Pickup') ? 'checked' : ''; ?>
                                       <?php echo ($pickupMethod === 'Delivery') ? 'disabled' : ''; ?>>
                                <div class="pw-segmented-box h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fw-semibold text-white">Cash on Pickup (COP)</span>
                                        <i class="fa-solid fa-hand-holding-dollar text-warning"></i>
                                    </div>
                                    <p class="text-white-50 small mb-0">Pay over-the-counter when collecting at Taguig Basecamp</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div id="deliveryPaymentNotice" class="text-white-50 small mt-3" style="<?php echo ($pickupMethod === 'Delivery') ? 'display: block;' : 'display: none;'; ?>">
                        <i class="fa-solid fa-circle-info text-info me-1"></i>
                        <span>Doorstep delivery orders strictly use Cash on Delivery (COD).</span>
                    </div>
                    <div id="pickupPaymentNotice" class="text-white-50 small mt-3" style="<?php echo ($pickupMethod === 'In-Store Pickup') ? 'display: block;' : 'display: none;'; ?>">
                        <i class="fa-solid fa-circle-info text-warning me-1"></i>
                        <span>Cash on Pickup is the only available payment method for in-store pickup orders.</span>
                    </div>
                    <?php if (isset($errors['paymentMethod'])): ?>
                        <div class="text-danger small mt-2"><?php echo htmlspecialchars($errors['paymentMethod']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="pw-checkout-card p-4 sticky-top" style="top: 90px;">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-white border-opacity-10">
                        <h5 class="fw-bold text-white mb-0">Order Review</h5>
                        <span class="badge rounded-pill bg-white bg-opacity-10 text-white small">
                            <?php echo $totalQuantity; ?> <?php echo ($totalQuantity === 1) ? 'item' : 'items'; ?>
                        </span>
                    </div>

                    <div class="mb-3" style="max-height: 280px; overflow-y: auto;">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="d-flex align-items-center gap-3 py-2 border-bottom border-white border-opacity-5">
                                <div class="pw-cart-thumb" style="width: 48px; height: 48px;">
                                    <?php if (!empty($item['image']) && file_exists(__DIR__ . '/' . $item['image'])): ?>
                                        <img src="/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($item['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php else: ?>
                                        <i class="fa-solid fa-bicycle text-muted"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-white small text-truncate" title="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </div>
                                    <div class="text-white-50 small">
                                        <?php echo (int)$item['quantity']; ?> &times; &#8369;<?php echo number_format($item['price'], 2); ?>
                                    </div>
                                </div>
                                <div class="text-white fw-semibold small text-end">
                                    &#8369;<?php echo number_format($item['lineSubtotal'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-between text-white-50 small mb-2">
                        <span>Subtotal</span>
                        <span class="text-white fw-semibold">&#8369;<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between text-white-50 small mb-2">
                        <span>Fulfillment Fee</span>
                        <span class="text-success fw-semibold">FREE</span>
                    </div>

                    <hr class="border-white border-opacity-10 my-3">

                    <div class="d-flex justify-content-between align-items-baseline mb-4">
                        <span class="text-white fw-bold">Grand Total</span>
                        <span class="h3 fw-bold text-white mb-0" style="color: var(--pw-trail-amber) !important;">
                            &#8369;<?php echo number_format($subtotal, 2); ?>
                        </span>
                    </div>

                    <button type="submit" class="pw-btn-trail w-100 py-3 justify-content-center text-center">
                        <i class="fa-solid fa-lock me-2"></i> Confirm &amp; Place Order
                    </button>

                    <a href="cart.php" class="pw-btn-glass w-100 mt-2 py-2 text-center justify-content-center">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Cart Items
                    </a>

                    <div class="text-center text-white-50 small mt-3">
                        <i class="fa-solid fa-circle-check text-success me-1"></i>
                        <span>Stock inventory automatically verified upon placement</span>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleFulfillment(method) {
    const addressGroup  = document.getElementById('deliveryAddressGroup');
    const pickupGroup   = document.getElementById('pickupInfoGroup');
    const codCol        = document.getElementById('codOptionCol');
    const copCol        = document.getElementById('copOptionCol');
    const codRadio      = document.getElementById('paymentMethodCOD');
    const copRadio      = document.getElementById('paymentMethodCOP');
    const pickupNotice  = document.getElementById('pickupPaymentNotice');
    const deliveryNotice = document.getElementById('deliveryPaymentNotice');

    if (method === 'In-Store Pickup') {
        addressGroup.style.display = 'none';
        pickupGroup.style.display = 'block';

        // In-Store Pickup allows ONLY Cash on Pickup
        if (codCol) codCol.style.display = 'none';
        if (copCol) copCol.style.display = 'block';
        if (codRadio) {
            codRadio.disabled = true;
            codRadio.checked = false;
        }
        if (copRadio) {
            copRadio.disabled = false;
            copRadio.checked = true;
        }
        if (pickupNotice) pickupNotice.style.display = 'block';
        if (deliveryNotice) deliveryNotice.style.display = 'none';
    } else {
        addressGroup.style.display = 'block';
        pickupGroup.style.display = 'none';

        // Doorstep Delivery allows ONLY Cash on Delivery (COD)
        if (codCol) codCol.style.display = 'block';
        if (copCol) copCol.style.display = 'none';
        if (codRadio) {
            codRadio.disabled = false;
            codRadio.checked = true;
        }
        if (copRadio) {
            copRadio.disabled = true;
            copRadio.checked = false;
        }
        if (deliveryNotice) deliveryNotice.style.display = 'block';
        if (pickupNotice) pickupNotice.style.display = 'none';
    }
}

// Client-side real-time inline validation for delivery address
document.addEventListener('DOMContentLoaded', function() {
    const addrField = document.getElementById('deliveryAddress');
    if (addrField) {
        addrField.addEventListener('input', function() {
            const val = this.value.trim();
            let feedback = document.getElementById('addrFeedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.id = 'addrFeedback';
                this.parentNode.appendChild(feedback);
            }
            if (val.length === 0) {
                feedback.className = 'pw-field-feedback invalid';
                feedback.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Street address cannot be empty for courier delivery.';
            } else if (val.length < 8) {
                feedback.className = 'pw-field-feedback invalid';
                feedback.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Please enter at least 8 characters for courier dispatch.';
            } else {
                feedback.className = 'pw-field-feedback valid';
                feedback.innerHTML = '<i class="fa-solid fa-circle-check"></i> Address format looks complete.';
            }
        });
    }
});
</script>

<?php include('includes/footer.php'); ?>
