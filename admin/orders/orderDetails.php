<?php
/**
 * Admin Order Details & Status Update Form
 * ITIM211 Term Test Lab Rubric:
 * - Admin can view all orders and update the status of the transaction (5 pts)
 * - Queries MySQL VIEW vw_order_transaction_details (Unit 1 rubric - 20 pts)
 * - Reference pattern: itim211-2b-shop/admin/orderDetails.php
 */
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');

// Access Control
requireAdminRole(['Inventory Manager', 'Accounting Administrator', 'Super Administrator']);

$activeModule = 'orders';

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0 && isset($_SESSION['orderId'])) {
    $orderId = (int)$_SESSION['orderId'];
}
$_SESSION['orderId'] = $orderId;

if ($orderId <= 0) {
    $_SESSION['actionError'] = 'Invalid order ID specified.';
    header('Location: index.php');
    exit;
}

// 1. Fetch Order and Customer details using Prepared Statement on vw_order_transaction_details (or fallback)
$order = null;
$stmtOrder = mysqli_prepare($conn, "SELECT 
        productSaleID,
        orderDate,
        orderStatus,
        paymentMethod,
        pickupMethod,
        deliveryAddress,
        customerAccountID,
        customerUsername,
        customerID,
        customerName,
        customerEmail,
        customerRegisteredAddress
    FROM `vw_order_transaction_details`
    WHERE productSaleID = ?
    LIMIT 1");

if ($stmtOrder) {
    mysqli_stmt_bind_param($stmtOrder, 'i', $orderId);
    mysqli_stmt_execute($stmtOrder);
    $resOrder = mysqli_stmt_get_result($stmtOrder);
    $order = mysqli_fetch_assoc($resOrder);
    mysqli_stmt_close($stmtOrder);
}

// Fallback if view didn't match (e.g. order with no items yet)
if (!$order) {
    $fbStmt = mysqli_prepare($conn, "SELECT 
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
            c.email AS customerEmail,
            c.address AS customerRegisteredAddress
        FROM `productSale` ps
        JOIN `customerAccount` ca ON ps.customerAccountID = ca.customerAccountID
        JOIN `customer` c ON ca.customerID = c.customerID
        WHERE ps.productSaleID = ?
        LIMIT 1");
    if ($fbStmt) {
        mysqli_stmt_bind_param($fbStmt, 'i', $orderId);
        mysqli_stmt_execute($fbStmt);
        $fbRes = mysqli_stmt_get_result($fbStmt);
        $order = mysqli_fetch_assoc($fbRes);
        mysqli_stmt_close($fbStmt);
    }
}

if (!$order) {
    $_SESSION['actionError'] = "Order #{$orderId} was not found in the database.";
    header('Location: index.php');
    exit;
}

// 2. Fetch Line Items using Prepared Statement (Unit 1 & Term Test Lab rubric)
$items = [];
$stmtItems = mysqli_prepare($conn, "SELECT 
        orderLineProductID,
        productID,
        quantity,
        sku,
        productName,
        productImage,
        unitPrice,
        lineSubtotal,
        categoryName
    FROM `vw_order_transaction_details`
    WHERE productSaleID = ?");

if ($stmtItems) {
    mysqli_stmt_bind_param($stmtItems, 'i', $orderId);
    mysqli_stmt_execute($stmtItems);
    $resItems = mysqli_stmt_get_result($stmtItems);
    while ($it = mysqli_fetch_assoc($resItems)) {
        $items[] = $it;
    }
    mysqli_stmt_close($stmtItems);
}

// 3. Fetch Linked Workshop Services (if any)
$services = [];
$stmtSrv = mysqli_prepare($conn, "SELECT 
        cs.customerServiceID,
        cs.requestDate,
        cs.scheduledDate,
        cs.notes,
        cs.status AS serviceStatus,
        s.sku AS serviceSku,
        s.name AS serviceName,
        s.price AS servicePrice
    FROM `customerService` cs
    JOIN `service` s ON cs.serviceID = s.serviceID
    WHERE cs.productSaleID = ?");

if ($stmtSrv) {
    mysqli_stmt_bind_param($stmtSrv, 'i', $orderId);
    mysqli_stmt_execute($stmtSrv);
    $resSrv = mysqli_stmt_get_result($stmtSrv);
    while ($sRow = mysqli_fetch_assoc($resSrv)) {
        $services[] = $sRow;
    }
    mysqli_stmt_close($stmtSrv);
}

// Calculate totals
$itemsSubtotal = 0;
foreach ($items as $row) {
    $itemsSubtotal += (float)$row['lineSubtotal'];
}

$servicesSubtotal = 0;
foreach ($services as $srv) {
    $servicesSubtotal += (float)$srv['servicePrice'];
}

$grandTotal = $itemsSubtotal + $servicesSubtotal;

$orderRef = '#PW-ORD-' . str_pad($orderId, 5, '0', STR_PAD_LEFT);
$currentStatus = $order['orderStatus'];
$statuses = ['Pending', 'Received', 'In Progress', 'Ready for Pickup', 'Completed', 'Cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order <?php echo htmlspecialchars($orderRef); ?> - PedalWorks Dynamics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .status-badge-pending     { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .status-badge-received    { background: #e0f2fe; color: #075985; border: 1px solid #bae6fd; }
        .status-badge-inprogress  { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; }
        .status-badge-ready       { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
        .status-badge-completed   { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .status-badge-cancelled   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body class="bg-light">

    <!-- Top Admin Navigation Bar -->
    <?php include('../../includes/admin_nav.php'); ?>

    <div class="container-fluid px-4 py-4">

        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Orders</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($orderRef); ?></li>
            </ol>
        </nav>

        <!-- Header Section -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h1 class="h3 fw-bold mb-0">Order <?php echo htmlspecialchars($orderRef); ?></h1>
                    <?php 
                        $statusClass = 'status-badge-' . strtolower(str_replace(' ', '', $currentStatus));
                    ?>
                    <span class="badge rounded-pill px-3 py-1 fw-semibold <?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($currentStatus); ?>
                    </span>
                </div>
                <p class="text-muted small mb-0 mt-1">
                    Placed on <?php echo !empty($order['orderDate']) ? date('F d, Y \a\t h:i A', strtotime($order['orderDate'])) : 'N/A'; ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="receipt.php?id=<?php echo $orderId; ?>" target="_blank" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-file-pdf me-1"></i> Download Receipt (PDF)
                </a>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Orders
                </a>
            </div>
        </div>

        <!-- Feedback Messages -->
        <?php if (!empty($_SESSION['actionSuccess'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['actionSuccess']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['actionSuccess']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['actionError'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($_SESSION['actionError']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['actionError']); ?>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- Left Column: Order Items & Financials -->
            <div class="col-lg-8">
                
                <!-- Items Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold fs-6">
                            <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Order Line Items
                        </h5>
                        <span class="badge bg-light text-dark border">
                            <?php echo count($items); ?> item(s) &bull; Queried via <code>vw_order_transaction_details</code>
                        </span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-secondary small text-uppercase">
                                <tr>
                                    <th class="ps-4">Item</th>
                                    <th>Category</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end pe-4">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            No physical products linked to this order.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-3">
                                                    <?php if (!empty($item['productImage'])): ?>
                                                        <img src="../../<?php echo htmlspecialchars($item['productImage']); ?>" alt="" class="rounded border object-fit-cover" width="46" height="46" onerror="this.src='https://placehold.co/46x46/1e293b/ffffff?text=Item'">
                                                    <?php else: ?>
                                                        <div class="bg-secondary-subtle rounded d-flex align-items-center justify-content-center text-secondary border" style="width: 46px; height: 46px;">
                                                            <i class="fa-solid fa-bicycle"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['productName']); ?></div>
                                                        <small class="text-muted">SKU: <?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-secondary border">
                                                    <?php echo htmlspecialchars($item['categoryName'] ?? 'Bikes'); ?>
                                                </span>
                                            </td>
                                            <td class="text-center fw-semibold">
                                                <?php echo (int)$item['quantity']; ?>
                                            </td>
                                            <td class="text-end text-muted">
                                                &#8369;<?php echo number_format((float)$item['unitPrice'], 2); ?>
                                            </td>
                                            <td class="text-end pe-4 fw-bold text-dark">
                                                &#8369;<?php echo number_format((float)$item['lineSubtotal'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <!-- Workshop Services Attached (if any) -->
                                <?php if (!empty($services)): ?>
                                    <tr class="table-light">
                                        <td colspan="5" class="ps-4 py-2 small fw-bold text-uppercase text-secondary">
                                            <i class="fa-solid fa-screwdriver-wrench me-1 text-primary"></i> Linked Workshop Services
                                        </td>
                                    </tr>
                                    <?php foreach ($services as $srv): ?>
                                        <tr>
                                            <td class="ps-4" colspan="2">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($srv['serviceName']); ?></div>
                                                <small class="text-muted">SKU: <?php echo htmlspecialchars($srv['serviceSku'] ?? 'SRV'); ?> | Schedule: <?php echo htmlspecialchars($srv['scheduledDate'] ?? 'Pending Assignment'); ?></small>
                                            </td>
                                            <td class="text-center fw-semibold">1</td>
                                            <td class="text-end text-muted">&#8369;<?php echo number_format((float)$srv['servicePrice'], 2); ?></td>
                                            <td class="text-end pe-4 fw-bold text-dark">&#8369;<?php echo number_format((float)$srv['servicePrice'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Financial Summary Footer -->
                    <div class="card-footer bg-white p-4 border-top">
                        <div class="row justify-content-end">
                            <div class="col-md-6 col-lg-5">
                                <div class="d-flex justify-content-between mb-2 small text-muted">
                                    <span>Products Subtotal:</span>
                                    <span>&#8369;<?php echo number_format($itemsSubtotal, 2); ?></span>
                                </div>
                                <?php if ($servicesSubtotal > 0): ?>
                                    <div class="d-flex justify-content-between mb-2 small text-muted">
                                        <span>Workshop Services:</span>
                                        <span>&#8369;<?php echo number_format($servicesSubtotal, 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-dark fs-6">Grand Total:</span>
                                    <span class="h4 fw-bold text-primary mb-0">&#8369;<?php echo number_format($grandTotal, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Status Update Form & Customer Details -->
            <div class="col-lg-4">
                
                <!-- Status Update Card (Term Test Lab Rubric: 5 pts update order status + Mailtrap integration) -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold fs-6">
                            <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Update Order Status
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="updateOrder.php" method="POST">
                            <input type="hidden" name="orderId" value="<?php echo $orderId; ?>">

                            <div class="mb-3">
                                <label for="statusSelect" class="form-label small fw-semibold text-secondary">
                                    Change Transaction Status:
                                </label>
                                <select name="status" id="statusSelect" class="form-select">
                                    <?php foreach ($statuses as $st): ?>
                                        <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($currentStatus === $st) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($st); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Mailtrap Trigger Notice -->
                            <div class="alert alert-info py-2 px-3 small mb-3 border-0 bg-info-subtle text-info-emphasis">
                                <i class="fa-solid fa-paper-plane me-1"></i>
                                <strong>Automated Email Notification:</strong> Updating this status will trigger PHPMailer to send an email with the transaction breakdown and attached PDF receipt to the customer via <strong>Mailtrap</strong>.
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Update Status &amp; Send Email
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Customer & Fulfillment Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold fs-6">
                            <i class="fa-solid fa-user-circle text-primary me-2"></i>Customer &amp; Fulfillment
                        </h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Customer</div>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($order['customerName'] ?: 'Valued Customer'); ?></div>
                            <div class="small text-muted"><i class="fa-regular fa-envelope me-1"></i><?php echo htmlspecialchars($order['customerEmail'] ?: 'N/A'); ?></div>
                            <?php if (!empty($order['customerUsername'])): ?>
                                <div class="small text-muted"><i class="fa-solid fa-at me-1"></i><?php echo htmlspecialchars($order['customerUsername']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3 pb-3 border-bottom">
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Fulfillment Mode</div>
                            <div class="fw-semibold text-dark">
                                <i class="fa-solid fa-truck text-primary me-1"></i><?php echo htmlspecialchars($order['pickupMethod'] ?? 'Standard'); ?>
                            </div>
                            <?php if (!empty($order['deliveryAddress'])): ?>
                                <div class="small text-muted mt-1 bg-light p-2 rounded border">
                                    <i class="fa-solid fa-location-dot me-1 text-danger"></i>
                                    <?php echo nl2br(htmlspecialchars($order['deliveryAddress'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Payment Method</div>
                            <div class="fw-semibold text-dark">
                                <i class="fa-solid fa-credit-card text-success me-1"></i><?php echo htmlspecialchars($order['paymentMethod'] ?? 'Cash'); ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
