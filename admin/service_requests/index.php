<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');

// Access Control: Service & Repair Manager or Super Administrator (FR16)
requireAdminRole(['Service & Repair Manager', 'Super Administrator']);

$activeModule = 'service_requests';

// Handle Status & Technician Assignment Update
$actionSuccess = '';
$actionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $serviceReqId = isset($_POST['customerServiceID']) ? (int)$_POST['customerServiceID'] : 0;
    $newStatus    = isset($_POST['status']) ? trim($_POST['status']) : '';
    $assignedTech = !empty($_POST['adminAccountID']) ? (int)$_POST['adminAccountID'] : null;

    $validStatuses = ['Pending', 'Received', 'In Progress', 'Ready for Pickup', 'Completed', 'Cancelled'];

    if ($serviceReqId <= 0) {
        $actionError = 'Invalid service request selected.';
    } elseif (!in_array($newStatus, $validStatuses, true)) {
        $actionError = 'Please select a valid service lifecycle status.';
    } elseif ($conn) {
        // If technician not provided, default to current admin if status is moving beyond Pending
        if ($assignedTech === null && in_array($newStatus, ['Received', 'In Progress', 'Ready for Pickup', 'Completed'], true)) {
            $assignedTech = (int)$_SESSION['adminAccountID'];
        }

        $updateSql = "UPDATE customerService SET status = ?, adminAccountID = ? WHERE customerServiceID = ?";
        $uStmt = mysqli_prepare($conn, $updateSql);
        if ($uStmt) {
            mysqli_stmt_bind_param($uStmt, 'sii', $newStatus, $assignedTech, $serviceReqId);
            if (mysqli_stmt_execute($uStmt)) {
                $actionSuccess = "Service request #PW-SRV-{$serviceReqId} status updated to '{$newStatus}' successfully.";
            } else {
                $actionError = "Failed to update service status: " . mysqli_stmt_error($uStmt);
            }
            mysqli_stmt_close($uStmt);
        }
    }
}

// Fetch technicians for assignment dropdown
$technicians = [];
if ($conn) {
    $tRes = mysqli_query($conn, "SELECT adminAccountID, username, role FROM adminAccount WHERE role IN ('Service & Repair Manager', 'Super Administrator') ORDER BY username ASC");
    if ($tRes) {
        while ($tRow = mysqli_fetch_assoc($tRes)) {
            $technicians[] = $tRow;
        }
    }
}

// Filters
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$searchQuery  = isset($_GET['search']) ? trim($_GET['search']) : '';

// KPI Statistics
$kpi = [
    'pending'     => 0,
    'in_progress' => 0,
    'ready'       => 0,
    'completed'   => 0,
    'total'       => 0
];

if ($conn) {
    $kRes = mysqli_query($conn, "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status IN ('Received', 'In Progress') THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'Ready for Pickup' THEN 1 ELSE 0 END) as ready,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
        FROM customerService");
    if ($kRes && ($kr = mysqli_fetch_assoc($kRes))) {
        $kpi['total']       = (int)($kr['total'] ?? 0);
        $kpi['pending']     = (int)($kr['pending'] ?? 0);
        $kpi['in_progress'] = (int)($kr['in_progress'] ?? 0);
        $kpi['ready']       = (int)($kr['ready'] ?? 0);
        $kpi['completed']   = (int)($kr['completed'] ?? 0);
    }
}

// Fetch Service Requests
$requests = [];
if ($conn) {
    $where = [];
    $params = [];
    $types = "";

    if ($statusFilter !== 'all') {
        if ($statusFilter === 'active') {
            $where[] = "cs.status IN ('Pending', 'Received', 'In Progress', 'Ready for Pickup')";
        } elseif (in_array($statusFilter, ['Pending', 'Received', 'In Progress', 'Ready for Pickup', 'Completed', 'Cancelled'], true)) {
            $where[] = "cs.status = ?";
            $params[] = $statusFilter;
            $types .= "s";
        }
    }

    if ($searchQuery !== '') {
        $where[] = "(s.name LIKE ? OR s.sku LIKE ? OR ca.username LIKE ? OR c.firstName LIKE ? OR c.lastName LIKE ? OR c.email LIKE ?)";
        $wildcard = "%" . $searchQuery . "%";
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $types .= "ssssss";
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "SELECT cs.customerServiceID, cs.requestDate, cs.scheduledDate, cs.notes, cs.status, cs.productSaleID,
                   s.serviceID, s.sku AS serviceSku, s.name AS serviceName, s.price AS servicePrice,
                   sc.name AS categoryName,
                   ca.customerAccountID, ca.username AS customerUsername,
                   c.customerID, CONCAT(c.firstName, IF(c.lastName IS NOT NULL AND c.lastName <> '', CONCAT(' ', c.lastName), '')) AS customerName,
                   c.email AS customerEmail, c.address AS customerAddress,
                   adm.adminAccountID, adm.username AS technicianUsername
            FROM customerService cs
            JOIN service s ON cs.serviceID = s.serviceID
            LEFT JOIN serviceCategory sc ON s.serviceCategoryID = sc.serviceCategoryID
            JOIN customerAccount ca ON cs.customerAccountID = ca.customerAccountID
            JOIN customer c ON ca.customerID = c.customerID
            LEFT JOIN adminAccount adm ON cs.adminAccountID = adm.adminAccountID
            {$whereClause}
            ORDER BY cs.requestDate DESC, cs.customerServiceID DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $requests[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

// Status Badges & Styling Helper
function getAdminStatusBadge($status) {
    switch ($status) {
        case 'Pending':
            return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Pending</span>';
        case 'Received':
            return '<span class="badge bg-info text-dark"><i class="fa-solid fa-box-archive me-1"></i>Received</span>';
        case 'In Progress':
            return '<span class="badge bg-primary text-white"><i class="fa-solid fa-screwdriver-wrench me-1"></i>In Progress</span>';
        case 'Ready for Pickup':
            return '<span class="badge bg-success text-white"><i class="fa-solid fa-bicycle me-1"></i>Ready for Pickup</span>';
        case 'Completed':
            return '<span class="badge bg-success text-white"><i class="fa-solid fa-circle-check me-1"></i>Completed</span>';
        case 'Cancelled':
            return '<span class="badge bg-danger text-white"><i class="fa-solid fa-ban me-1"></i>Cancelled</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Status Tracking (FR16) - PedalWorks Dynamics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .table-middle td { vertical-align: middle; }
    </style>
</head>
<body class="bg-light">

    <?php include('../../includes/admin_nav.php'); ?>

    <div class="container-fluid px-4 py-4">

        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-2">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="../service/index.php">Services</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Service Tracking (FR16)</li>
                    </ol>
                </nav>
                <h1 class="h3 mb-0 fw-bold">
                    <i class="fa-solid fa-screwdriver-wrench text-primary me-2"></i>Service &amp; Repair Status Tracking
                </h1>
                <p class="text-muted small mb-0">FR16: Manually update and monitor customer repair requests throughout workshop lifecycle</p>
            </div>
            <div class="d-flex gap-2">
                <a href="../service/index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-list me-1"></i> Catalog Services
                </a>
                <a href="../dashboard.php" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-gauge me-1"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Feedback Alerts -->
        <?php if ($actionSuccess !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($actionSuccess); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($actionError !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($actionError); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- KPI Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm border-start border-warning border-4 h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Pending Requests</span>
                            <h3 class="fw-bold mb-0 text-warning"><?php echo $kpi['pending']; ?></h3>
                        </div>
                        <i class="fa-solid fa-clock fa-2x text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm border-start border-primary border-4 h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">In-Bay Active</span>
                            <h3 class="fw-bold mb-0 text-primary"><?php echo $kpi['in_progress']; ?></h3>
                        </div>
                        <i class="fa-solid fa-screwdriver-wrench fa-2x text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm border-start border-info border-4 h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Ready for Pickup</span>
                            <h3 class="fw-bold mb-0 text-info"><?php echo $kpi['ready']; ?></h3>
                        </div>
                        <i class="fa-solid fa-bicycle fa-2x text-info opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm border-start border-success border-4 h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Completed Jobs</span>
                            <h3 class="fw-bold mb-0 text-success"><?php echo $kpi['completed']; ?></h3>
                        </div>
                        <i class="fa-solid fa-circle-check fa-2x text-success opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Controls -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="index.php" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search by customer, service, or SKU..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <label class="input-group-text">Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo ($statusFilter === 'all') ? 'selected' : ''; ?>>All Statuses (<?php echo $kpi['total']; ?>)</option>
                                <option value="active" <?php echo ($statusFilter === 'active') ? 'selected' : ''; ?>>All Active In-Shop</option>
                                <option value="Pending" <?php echo ($statusFilter === 'Pending') ? 'selected' : ''; ?>>Pending (<?php echo $kpi['pending']; ?>)</option>
                                <option value="Received" <?php echo ($statusFilter === 'Received') ? 'selected' : ''; ?>>Received at Bay</option>
                                <option value="In Progress" <?php echo ($statusFilter === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Ready for Pickup" <?php echo ($statusFilter === 'Ready for Pickup') ? 'selected' : ''; ?>>Ready for Pickup (<?php echo $kpi['ready']; ?>)</option>
                                <option value="Completed" <?php echo ($statusFilter === 'Completed') ? 'selected' : ''; ?>>Completed (<?php echo $kpi['completed']; ?>)</option>
                                <option value="Cancelled" <?php echo ($statusFilter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm px-3">
                            <i class="fa-solid fa-filter me-1"></i> Apply Filter
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Requests Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="fa-solid fa-table-list text-muted me-2"></i>Service Requests Directory
                </h5>
                <span class="badge bg-light text-dark border">
                    Showing <?php echo count($requests); ?> records
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-muted">
                            <th>ID &amp; Ref</th>
                            <th>Customer</th>
                            <th>Service Requested</th>
                            <th>Labor Fee</th>
                            <th>Requested / Target Date</th>
                            <th>Assigned Tech</th>
                            <th>Status (FR16)</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-inbox fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                    <h6>No Service Requests Found</h6>
                                    <p class="small mb-0">No workshop repair requests match the current filters.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $r): 
                                $modalId = "statusModal_" . $r['customerServiceID'];
                            ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-dark">#PW-SRV-<?php echo str_pad($r['customerServiceID'], 5, '0', STR_PAD_LEFT); ?></span>
                                    <?php if (!empty($r['productSaleID'])): ?>
                                        <div class="small">
                                            <span class="badge bg-light text-dark border">
                                                <i class="fa-solid fa-link text-warning me-1"></i>Order #<?php echo (int)$r['productSaleID']; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($r['customerName']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($r['customerEmail']); ?></div>
                                    <div class="small text-muted"><i class="fa-solid fa-user me-1"></i>@<?php echo htmlspecialchars($r['customerUsername']); ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-primary"><?php echo htmlspecialchars($r['serviceName']); ?></div>
                                    <div class="small text-muted">
                                        <span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($r['categoryName'] ?? 'Workshop'); ?></span>
                                        <span class="text-muted ms-1">SKU: <?php echo htmlspecialchars($r['serviceSku']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold text-success">&#8369;<?php echo number_format($r['servicePrice'], 2); ?></span>
                                </td>
                                <td>
                                    <div class="small text-muted">
                                        Req: <?php echo date('M d, Y', strtotime($r['requestDate'])); ?>
                                    </div>
                                    <?php if (!empty($r['scheduledDate'])): ?>
                                        <div class="small fw-semibold text-dark">
                                            <i class="fa-regular fa-clock text-warning me-1"></i><?php echo date('M d, Y h:i A', strtotime($r['scheduledDate'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="small text-muted">Flexible Walk-in</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($r['technicianUsername'])): ?>
                                        <span class="badge bg-light text-dark border">
                                            <i class="fa-solid fa-user-gear text-success me-1"></i><?php echo htmlspecialchars($r['technicianUsername']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo getAdminStatusBadge($r['status']); ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> Update Status
                                    </button>
                                </td>
                            </tr>

                            <!-- Status Update Modal -->
                            <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="index.php">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="customerServiceID" value="<?php echo (int)$r['customerServiceID']; ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="fa-solid fa-sliders text-primary me-2"></i>Update Request #PW-SRV-<?php echo $r['customerServiceID']; ?>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <!-- Service & Customer Summary -->
                                                <div class="p-3 mb-3 rounded bg-light border">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <strong class="text-dark"><?php echo htmlspecialchars($r['serviceName']); ?></strong>
                                                        <span class="text-success fw-bold">&#8369;<?php echo number_format($r['servicePrice'], 2); ?></span>
                                                    </div>
                                                    <div class="small text-muted mb-2">Customer: <?php echo htmlspecialchars($r['customerName']); ?> (<?php echo htmlspecialchars($r['customerEmail']); ?>)</div>
                                                    <?php if (!empty($r['notes'])): ?>
                                                        <div class="small bg-white p-2 rounded border">
                                                            <strong>Customer Notes:</strong> <?php echo nl2br(htmlspecialchars($r['notes'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Status Select (FR16) -->
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold small text-uppercase">Service Lifecycle Status <span class="text-danger">*</span></label>
                                                    <select name="status" class="form-select" required>
                                                        <option value="Pending" <?php echo ($r['status'] === 'Pending') ? 'selected' : ''; ?>>Pending (Awaiting Initial Review)</option>
                                                        <option value="Received" <?php echo ($r['status'] === 'Received') ? 'selected' : ''; ?>>Received (Bicycle/Part Received at Workshop Bay)</option>
                                                        <option value="In Progress" <?php echo ($r['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress (Labor &amp; Repairs Underway)</option>
                                                        <option value="Ready for Pickup" <?php echo ($r['status'] === 'Ready for Pickup') ? 'selected' : ''; ?>>Ready for Pickup (Repairs Finished, Awaiting Customer)</option>
                                                        <option value="Completed" <?php echo ($r['status'] === 'Completed') ? 'selected' : ''; ?>>Completed (Transaction Finalized &amp; Released)</option>
                                                        <option value="Cancelled" <?php echo ($r['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled (Service Request Voided)</option>
                                                    </select>
                                                    <div class="form-text small">Updating status allows the customer to track their bike's progress live.</div>
                                                </div>

                                                <!-- Technician Assignment -->
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold small text-uppercase">Assign Workshop Technician</label>
                                                    <select name="adminAccountID" class="form-select">
                                                        <option value="">-- Unassigned / Workshop Pool --</option>
                                                        <?php foreach ($technicians as $tech): ?>
                                                            <option value="<?php echo (int)$tech['adminAccountID']; ?>" <?php echo ((int)$r['adminAccountID'] === (int)$tech['adminAccountID']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($tech['username']); ?> (<?php echo htmlspecialchars($tech['role']); ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary btn-sm px-3">
                                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

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
