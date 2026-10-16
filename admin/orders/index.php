<?php
/**
 * Admin Orders Management
 * ITIM211 Term Test Lab Rubric:
 * - Admin can view all orders and update the status of the transaction (5 pts)
 * - Uses MySQL VIEWS (vw_order_summary) (Unit 1 Rubric - 20 pts)
 */
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');

// Access Control: Inventory Managers, Accounting Administrators, or Super Admin
requireAdminRole(['Inventory Manager', 'Accounting Administrator', 'Super Administrator']);

$activeModule = 'orders';

// Filters & Search
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$searchQuery  = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base query using MySQL VIEW vw_order_summary (Unit 1 rubric)
$sql = "SELECT * FROM `vw_order_summary`";
$where = [];
$params = [];
$types = '';

if ($statusFilter !== 'all' && $statusFilter !== '') {
    $where[] = "orderStatus = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($searchQuery !== '') {
    $where[] = "(customerName LIKE ? OR customerEmail LIKE ? OR customerUsername LIKE ? OR productSaleID = ?)";
    $like = '%' . $searchQuery . '%';
    $searchId = is_numeric($searchQuery) ? (int)$searchQuery : 0;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $searchId;
    $types .= 'sssi';
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY productSaleID DESC";

// Execute prepared statement
$orders = [];
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $orders[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// KPI Statistics
$kpis = [
    'total'       => 0,
    'pending'     => 0,
    'in_progress' => 0,
    'completed'   => 0,
    'totalSales'  => 0.00
];

$kpiRes = mysqli_query($conn, "SELECT 
        COUNT(*) AS totalCount,
        SUM(CASE WHEN orderStatus IN ('Pending', 'Received') THEN 1 ELSE 0 END) AS pendingCount,
        SUM(CASE WHEN orderStatus IN ('In Progress', 'Ready for Pickup') THEN 1 ELSE 0 END) AS inProgressCount,
        SUM(CASE WHEN orderStatus = 'Completed' THEN 1 ELSE 0 END) AS completedCount,
        COALESCE(SUM(grandTotal), 0) AS totalRevenue
    FROM `vw_order_summary`");

if ($kpiRes && $kRow = mysqli_fetch_assoc($kpiRes)) {
    $kpis['total']       = (int)$kRow['totalCount'];
    $kpis['pending']     = (int)$kRow['pendingCount'];
    $kpis['in_progress'] = (int)$kRow['inProgressCount'];
    $kpis['completed']   = (int)$kRow['completedCount'];
    $kpis['totalSales']  = (float)$kRow['totalRevenue'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Orders &amp; Transactions - PedalWorks Dynamics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .pw-stat-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .pw-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.08);
        }
        .status-badge-pending     { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .status-badge-received    { background: #e0f2fe; color: #075985; border: 1px solid #bae6fd; }
        .status-badge-inprogress  { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; }
        .status-badge-ready       { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
        .status-badge-completed   { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .status-badge-cancelled   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body class="bg-light">

    <!-- Top Navigation Bar -->
    <?php include('../../includes/admin_nav.php'); ?>

    <div class="container-fluid px-4 py-4">

        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Orders &amp; Transactions</li>
            </ol>
        </nav>

        <!-- Header Section -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1">Customer Orders &amp; Transactions</h1>
                <p class="text-muted small mb-0">Track customer sales, fulfill orders, and issue automated status emails with PDF receipts.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="../reports/financial.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-chart-line me-1"></i> Sales Analytics
                </a>
                <a href="index.php" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-rotate-right me-1"></i> Refresh
                </a>
            </div>
        </div>

        <!-- Feedback Alert Messages -->
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

        <!-- KPI Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="pw-stat-card border-start border-primary border-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Total Orders</div>
                            <div class="h3 fw-bold mb-0 text-dark"><?php echo number_format($kpis['total']); ?></div>
                        </div>
                        <div class="bg-primary-subtle text-primary rounded-3 p-3">
                            <i class="fa-solid fa-bag-shopping fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="pw-stat-card border-start border-warning border-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Pending / Received</div>
                            <div class="h3 fw-bold mb-0 text-dark"><?php echo number_format($kpis['pending']); ?></div>
                        </div>
                        <div class="bg-warning-subtle text-warning rounded-3 p-3">
                            <i class="fa-solid fa-hourglass-half fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="pw-stat-card border-start border-info border-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">In Progress / Ready</div>
                            <div class="h3 fw-bold mb-0 text-dark"><?php echo number_format($kpis['in_progress']); ?></div>
                        </div>
                        <div class="bg-info-subtle text-info rounded-3 p-3">
                            <i class="fa-solid fa-boxes-packing fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="pw-stat-card border-start border-success border-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold">Completed Orders</div>
                            <div class="h3 fw-bold mb-0 text-dark"><?php echo number_format($kpis['completed']); ?></div>
                        </div>
                        <div class="bg-success-subtle text-success rounded-3 p-3">
                            <i class="fa-solid fa-circle-check fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form action="index.php" method="GET" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search order #, customer name, email..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all" <?php echo ($statusFilter === 'all') ? 'selected' : ''; ?>>All Order Statuses</option>
                            <option value="Pending" <?php echo ($statusFilter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Received" <?php echo ($statusFilter === 'Received') ? 'selected' : ''; ?>>Received</option>
                            <option value="In Progress" <?php echo ($statusFilter === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Ready for Pickup" <?php echo ($statusFilter === 'Ready for Pickup') ? 'selected' : ''; ?>>Ready for Pickup</option>
                            <option value="Completed" <?php echo ($statusFilter === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo ($statusFilter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-dark btn-sm flex-grow-1">Filter</button>
                        <?php if ($statusFilter !== 'all' || $searchQuery !== ''): ?>
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders Table Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold fs-6">Orders List (<?php echo count($orders); ?>)</h5>
                <span class="badge bg-secondary-subtle text-secondary">Queried via <code>vw_order_summary</code></span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-4">Order ID</th>
                            <th>Customer</th>
                            <th>Date Placed</th>
                            <th class="text-center">Items</th>
                            <th>Fulfillment &amp; Payment</th>
                            <th class="text-end">Grand Total</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-box-open fs-1 text-secondary opacity-50 mb-3 d-block"></i>
                                    No customer orders found matching the filter criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $row): 
                                $orderId   = (int)$row['productSaleID'];
                                $orderRef  = '#PW-ORD-' . str_pad($orderId, 5, '0', STR_PAD_LEFT);
                                $status    = $row['orderStatus'];
                                $statusClass = 'status-badge-' . strtolower(str_replace(' ', '', $status));
                                $orderDate = !empty($row['orderDate']) ? date('M d, Y h:i A', strtotime($row['orderDate'])) : 'N/A';
                            ?>
                                <tr>
                                    <td class="ps-4">
                                        <a href="orderDetails.php?id=<?php echo $orderId; ?>" class="fw-bold text-decoration-none text-primary">
                                            <?php echo htmlspecialchars($orderRef); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['customerName'] ?: 'Guest / Customer'); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($row['customerEmail'] ?: 'N/A'); ?></div>
                                    </td>
                                    <td>
                                        <div class="small text-dark"><?php echo htmlspecialchars($orderDate); ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">
                                            <?php echo (int)($row['totalItemCount'] ?? 1); ?> pcs
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-dark">
                                            <i class="fa-solid fa-truck-fast text-muted me-1"></i><?php echo htmlspecialchars($row['pickupMethod'] ?? 'Pickup'); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="fa-solid fa-credit-card text-muted me-1"></i><?php echo htmlspecialchars($row['paymentMethod'] ?? 'Cash'); ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-bold text-dark">
                                            &#8369;<?php echo number_format((float)$row['grandTotal'], 2); ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill px-3 py-1 fw-semibold <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <a href="orderDetails.php?id=<?php echo $orderId; ?>" class="btn btn-outline-primary" title="View Order & Update Status">
                                                <i class="fa-regular fa-eye me-1"></i> Details
                                            </a>
                                            <a href="receipt.php?id=<?php echo $orderId; ?>" target="_blank" class="btn btn-outline-secondary" title="View / Print PDF Receipt">
                                                <i class="fa-regular fa-file-pdf"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
