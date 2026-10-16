<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/config.php';

// Auth Guard: Customer Login Required (FR9 Requirement)
if (empty($_SESSION['customerAccountID'])) {
    $_SESSION['error'] = 'Please sign in to view your order history.';
    header('Location: ../login.php');
    exit;
}

$customerAccountID = (int)$_SESSION['customerAccountID'];
$orders = [];

if ($conn) {
    // Attempt querying MySQL VIEW vw_order_summary first (Unit 1 Rubric)
    $viewSql = "SELECT productSaleID, orderDate, orderStatus, paymentMethod, pickupMethod, deliveryAddress, totalDistinctItems, totalItemCount, grandTotal 
                FROM vw_order_summary 
                WHERE customerAccountID = ? 
                ORDER BY orderDate DESC, productSaleID DESC";
    $stmt = @mysqli_prepare($conn, $viewSql);

    // Fallback directly to tables if view is not yet defined
    if (!$stmt) {
        $tableSql = "SELECT ps.productSaleID, ps.orderDate, ps.status AS orderStatus, ps.paymentMethod, ps.pickupMethod, ps.deliveryAddress,
                            COUNT(olp.orderLineProductID) AS totalDistinctItems,
                            COALESCE(SUM(olp.quantity), 0) AS totalItemCount,
                            COALESCE(SUM(olp.quantity * p.price), 0.00) AS grandTotal
                     FROM productSale ps
                     LEFT JOIN orderLineProduct olp ON ps.productSaleID = olp.productSaleID
                     LEFT JOIN product p ON olp.productID = p.productID
                     WHERE ps.customerAccountID = ?
                     GROUP BY ps.productSaleID, ps.orderDate, ps.status, ps.paymentMethod, ps.pickupMethod, ps.deliveryAddress
                     ORDER BY ps.orderDate DESC, ps.productSaleID DESC";
        $stmt = mysqli_prepare($conn, $tableSql);
    }

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $customerAccountID);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $orders[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch line items for each order using vw_order_transaction_details (or fallback)
$orderItems = [];
if (!empty($orders) && $conn) {
    foreach ($orders as $ord) {
        $saleId = (int)$ord['productSaleID'];
        $orderItems[$saleId] = [];

        $itemSql = "SELECT productID, sku, productName, productImage, unitPrice, quantity, lineSubtotal, categoryName 
                    FROM vw_order_transaction_details 
                    WHERE productSaleID = ?";
        $iStmt = @mysqli_prepare($conn, $itemSql);

        if (!$iStmt) {
            $itemSql = "SELECT p.productID, p.sku, p.name AS productName, p.image AS productImage, p.price AS unitPrice, 
                               olp.quantity, (olp.quantity * p.price) AS lineSubtotal, pc.name AS categoryName
                        FROM orderLineProduct olp
                        JOIN product p ON olp.productID = p.productID
                        LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID
                        WHERE olp.productSaleID = ?";
            $iStmt = mysqli_prepare($conn, $itemSql);
        }

        if ($iStmt) {
            mysqli_stmt_bind_param($iStmt, 'i', $saleId);
            mysqli_stmt_execute($iStmt);
            $iRes = mysqli_stmt_get_result($iStmt);
            while ($iRow = mysqli_fetch_assoc($iRes)) {
                $orderItems[$saleId][] = $iRow;
            }
            mysqli_stmt_close($iStmt);
        }
    }

    // Fetch attached workshop labor requests (FR15)
    $attachedServices = [];
    $saleIds = array_map(function($o) { return (int)$o['productSaleID']; }, $orders);
    if (!empty($saleIds)) {
        $inClause = implode(',', $saleIds);
        $sRes = mysqli_query($conn, "SELECT cs.customerServiceID, cs.productSaleID, cs.status as serviceStatus, cs.scheduledDate, s.name as serviceName 
                                     FROM customerService cs 
                                     JOIN service s ON cs.serviceID = s.serviceID 
                                     WHERE cs.productSaleID IN ({$inClause})");
        if ($sRes) {
            while ($sRow = mysqli_fetch_assoc($sRes)) {
                $attachedServices[(int)$sRow['productSaleID']] = $sRow;
            }
        }
    }
}

$highlightOrderID = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$justPlaced = isset($_GET['placed']) && $_GET['placed'] === '1';

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small text-white-50">
                    <li class="breadcrumb-item"><a href="../index.php" class="text-white-50 text-decoration-none">Home</a></li>
                    <li class="breadcrumb-item"><a href="profile.php" class="text-white-50 text-decoration-none">Account</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Order History</li>
                </ol>
            </nav>
            <h1 class="h2 fw-bold text-white mb-0 d-flex align-items-center gap-2">
                <span>Order History &amp; Status</span>
                <?php if (!empty($orders)): ?>
                    <span class="badge rounded-pill bg-white bg-opacity-10 text-white fs-6 fw-normal">
                        <?php echo count($orders); ?> <?php echo (count($orders) === 1) ? 'order' : 'orders'; ?>
                    </span>
                <?php endif; ?>
            </h1>
        </div>

        <a href="../products.php" class="pw-btn-glass text-nowrap">
            <i class="fa-solid fa-bicycle me-1"></i> Shop More Gear
        </a>
    </div>

    <!-- Celebration Banner for newly placed order -->
    <?php if ($justPlaced && $highlightOrderID > 0): ?>
        <div class="alert alert-success pw-glass-alert d-flex align-items-center gap-3 p-4 mb-5 border-success border-opacity-25" role="alert">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white p-3 flex-shrink-0">
                <i class="fa-solid fa-circle-check fa-2x"></i>
            </div>
            <div>
                <h4 class="fw-bold text-white mb-1">Order #PW-ORD-<?php echo $highlightOrderID; ?> Confirmed!</h4>
                <p class="text-white-50 mb-0 small" style="line-height: 1.6;">
                    Thank you for riding with PedalWorks Dynamics. Your transaction has been recorded, inventory stock was reserved, and our team is preparing your items.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <!-- Apple HIG Empty State -->
        <div class="pw-order-card text-center py-5 px-4 my-4">
            <div class="mb-3">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle" 
                     style="width: 80px; height: 80px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1);">
                    <i class="fa-solid fa-clock-rotate-left fa-2x text-white-50"></i>
                </div>
            </div>
            <h3 class="fw-bold text-white mb-2">No Past Orders Found</h3>
            <p class="text-white-50 mx-auto mb-4" style="max-width: 440px; font-size: 0.95rem;">
                You haven't placed any bike or gear orders yet. When you complete a checkout, your receipt, delivery details, and live order status will be tracked here.
            </p>
            <a href="../products.php" class="pw-btn-trail px-4 py-2">
                <i class="fa-solid fa-bag-shopping me-2"></i> Start Shopping
            </a>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-4">
            <?php foreach ($orders as $order): 
                $saleId = (int)$order['productSaleID'];
                $status = $order['orderStatus'] ?? 'Received';
                $isHighlighted = ($highlightOrderID === $saleId);

                // Badge styling
                $badgeClass = 'pw-badge-status-received';
                $badgeIcon  = 'fa-circle-dot';
                if ($status === 'Pending') {
                    $badgeClass = 'pw-badge-status-pending';
                    $badgeIcon  = 'fa-clock';
                } elseif ($status === 'In Progress') {
                    $badgeClass = 'pw-badge-status-inprogress';
                    $badgeIcon  = 'fa-wrench';
                } elseif ($status === 'Ready for Pickup') {
                    $badgeClass = 'pw-badge-status-ready';
                    $badgeIcon  = 'fa-box';
                } elseif ($status === 'Completed') {
                    $badgeClass = 'pw-badge-status-completed';
                    $badgeIcon  = 'fa-circle-check';
                } elseif ($status === 'Cancelled') {
                    $badgeClass = 'pw-badge-status-cancelled';
                    $badgeIcon  = 'fa-ban';
                }

                $items = $orderItems[$saleId] ?? [];
            ?>
                <div class="pw-order-card <?php echo $isHighlighted ? 'border-primary' : ''; ?>" style="<?php echo $isHighlighted ? 'box-shadow: 0 0 0 2px rgba(209, 102, 41, 0.5);' : ''; ?>">
                    <!-- Order Card Header -->
                    <div class="p-4 border-bottom border-white border-opacity-10">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="h5 fw-bold text-white mb-0">
                                        #PW-ORD-<?php echo $saleId; ?>
                                    </span>
                                    <span class="badge rounded-pill <?php echo $badgeClass; ?> px-3 py-1 small fw-semibold">
                                        <i class="fa-solid <?php echo $badgeIcon; ?> me-1"></i><?php echo htmlspecialchars($status); ?>
                                    </span>
                                    <?php if (isset($attachedServices[$saleId])): 
                                        $attSvc = $attachedServices[$saleId];
                                    ?>
                                        <a href="services.php#req-<?php echo $attSvc['customerServiceID']; ?>" class="badge rounded-pill bg-warning text-dark text-decoration-none small px-2 py-1" title="View attached workshop service request">
                                            <i class="fa-solid fa-wrench me-1"></i> Attached Labor: <?php echo htmlspecialchars($attSvc['serviceName']); ?> (<?php echo htmlspecialchars($attSvc['serviceStatus']); ?>) &rarr;
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <span class="text-white-50 small">
                                    Placed on <?php echo date('M d, Y \a\t h:i A', strtotime($order['orderDate'])); ?>
                                </span>
                            </div>

                            <div class="text-sm-end d-flex flex-column align-items-sm-end gap-1">
                                <span class="text-white-50 small d-block">Grand Total</span>
                                <span class="h4 fw-bold text-white mb-0" style="color: var(--pw-trail-amber) !important;">
                                    &#8369;<?php echo number_format($order['grandTotal'], 2); ?>
                                </span>
                                <a href="../admin/orders/receipt.php?id=<?php echo $saleId; ?>" target="_blank" class="btn btn-outline-light btn-sm mt-1 px-2 py-1" style="font-size: 0.75rem; border-color: rgba(255,255,255,0.25);">
                                    <i class="fa-regular fa-file-pdf me-1 text-danger"></i> PDF Receipt
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Order Fulfillment & Payment Metadata -->
                    <div class="pw-order-meta-bar">
                        <div class="row g-3 small">
                            <div class="col-md-4">
                                <span class="text-white-50 d-block mb-1">Fulfillment Method</span>
                                <span class="text-white fw-semibold">
                                    <?php if ($order['pickupMethod'] === 'In-Store Pickup'): ?>
                                        <i class="fa-solid fa-store text-success me-1"></i> In-Store Pickup
                                    <?php else: ?>
                                        <i class="fa-solid fa-truck-fast text-primary me-1"></i> Doorstep Delivery
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="col-md-4">
                                <span class="text-white-50 d-block mb-1">Payment Method</span>
                                <span class="text-white fw-semibold">
                                    <i class="fa-solid fa-money-bill-wave text-warning me-1"></i> <?php echo htmlspecialchars($order['paymentMethod']); ?>
                                </span>
                            </div>

                            <div class="col-md-4">
                                <span class="text-white-50 d-block mb-1">Address / Pickup Point</span>
                                <span class="text-white" style="line-height: 1.4;">
                                    <?php echo nl2br(htmlspecialchars($order['deliveryAddress'] ?: 'Taguig Basecamp')); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Itemized Order Lines -->
                    <div class="p-4">
                        <h6 class="text-uppercase small text-white-50 fw-semibold mb-3" style="letter-spacing: 0.05em;">Purchased Items</h6>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($items as $item): ?>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="pw-cart-thumb" style="width: 56px; height: 56px;">
                                        <?php if (!empty($item['productImage']) && file_exists(__DIR__ . '/../' . $item['productImage'])): ?>
                                            <img src="/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($item['productImage']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['productName']); ?>">
                                        <?php else: ?>
                                            <i class="fa-solid fa-bicycle text-muted"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge rounded-pill bg-white bg-opacity-10 text-white-50 small" style="font-size: 0.7rem;">
                                                <?php echo htmlspecialchars($item['categoryName'] ?? 'Gear'); ?>
                                            </span>
                                            <span class="pw-sku-mono small opacity-75"><?php echo htmlspecialchars($item['sku']); ?></span>
                                        </div>
                                        <div class="fw-semibold text-white text-truncate" title="<?php echo htmlspecialchars($item['productName']); ?>">
                                            <?php echo htmlspecialchars($item['productName']); ?>
                                        </div>
                                        <div class="text-white-50 small">
                                            <?php echo (int)$item['quantity']; ?> &times; &#8369;<?php echo number_format($item['unitPrice'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="text-white fw-bold text-end" style="min-width: 100px;">
                                        &#8369;<?php echo number_format($item['lineSubtotal'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
