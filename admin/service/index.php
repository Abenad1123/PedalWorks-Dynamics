<?php
session_start();
include('../../includes/config.php');

$successMessage = '';
$errorMessage = '';

if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';
$showArchived = isset($_GET['show_archived']) && $_GET['show_archived'] === '1';

// Fetch service categories for dropdown
$categories = [];
if ($conn) {
    $catRes = mysqli_query($conn, "SELECT * FROM serviceCategory ORDER BY name ASC");
    if ($catRes) {
        while ($row = mysqli_fetch_assoc($catRes)) {
            $categories[] = $row;
        }
    }
}

// Query services
$serviceList = [];
$totalActiveServices = 0;
$totalBookings = 0;

if ($conn) {
    $mRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM service WHERE endDate IS NULL");
    if ($mRes && ($r = mysqli_fetch_assoc($mRes))) {
        $totalActiveServices = (int)$r['c'];
    }

    $bRes = mysqli_query($conn, "SELECT COUNT(*) AS totalRequests FROM customerService");
    if ($bRes && ($br = mysqli_fetch_assoc($bRes))) {
        $totalBookings = (int)$br['totalRequests'];
    }

    $where = [];
    $params = [];
    $types = "";

    if (!$showArchived) {
        $where[] = "s.endDate IS NULL";
    }

    if ($search !== '') {
        $where[] = "(s.name LIKE ? OR s.sku LIKE ? OR s.description LIKE ?)";
        $searchParam = "%" . $search . "%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }

    if ($categoryFilter !== '') {
        $where[] = "sc.name = ?";
        $params[] = $categoryFilter;
        $types .= "s";
    }

    $sql = "SELECT s.*, sc.name AS categoryName,
                   (SELECT COUNT(*) FROM customerService cs WHERE cs.serviceID = s.serviceID) AS usageCount
            FROM service s 
            LEFT JOIN serviceCategory sc ON s.serviceCategoryID = sc.serviceCategoryID ";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY s.serviceID DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $serviceList[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
}
$activeModule = 'service';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Management - PedalWorks Dynamics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light">

    <!-- Top Admin Navigation Bar -->
    <?php include('../../includes/admin_nav.php'); ?>

    <div class="container-fluid px-4 py-4">

        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Service Management</li>
            </ol>
        </nav>

        <!-- Header Section -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1">
                    <i class="fa-solid fa-wrench text-primary me-2"></i>Workshop Service Packages
                </h1>
                <p class="text-muted small mb-0">Manage repair tiers, tune-up services, labor rates, and historical price versions.</p>
            </div>
            <a href="addService.php" class="btn btn-primary btn-sm d-flex align-items-center shadow-sm">
                <i class="fa-solid fa-plus-circle me-2"></i>Add New Service
            </a>
        </div>

        <!-- System Alerts -->
        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Stat KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-4">
                <div class="card border-0 shadow-sm h-100 border-start border-primary border-4">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase">Active Service Packages</div>
                        <div class="fs-4 fw-bold text-dark mt-1"><?php echo $totalActiveServices; ?></div>
                        <div class="small text-muted"><i class="fa-solid fa-tag me-1 text-primary"></i>Live bookable offerings</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="card border-0 shadow-sm h-100 border-start border-success border-4">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase">Total Service Requests</div>
                        <div class="fs-4 fw-bold text-success mt-1"><?php echo $totalBookings; ?></div>
                        <div class="small text-muted"><i class="fa-solid fa-calendar-check me-1 text-success"></i>All customer work orders</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="card border-0 shadow-sm h-100 border-start border-info border-4">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase">Categories</div>
                        <div class="fs-4 fw-bold text-info mt-1"><?php echo count($categories); ?></div>
                        <div class="small text-muted"><i class="fa-solid fa-layer-group me-1 text-info"></i>Specialized service classifications</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="get" action="index.php" class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold text-muted mb-1" for="search">Keyword Search</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="search" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search SKU, service title, or scope...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1" for="category">Category</label>
                        <select id="category" name="category" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['name']); ?>" <?php echo ($categoryFilter === $cat['name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex flex-column justify-content-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="show_archived" name="show_archived" value="1" <?php echo $showArchived ? 'checked' : ''; ?>>
                            <label class="form-check-label small" for="show_archived">Show Archived</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                <i class="fa-solid fa-filter me-1"></i>Apply Filters
                            </button>
                            <?php if ($search !== '' || $categoryFilter !== '' || $showArchived): ?>
                                <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Service Directory Table Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-dark">
                    <i class="fa-solid fa-screwdriver-wrench text-primary me-2"></i>Service Packages Directory
                    <span class="badge bg-secondary ms-2"><?php echo count($serviceList); ?> item(s)</span>
                </span>
                <span class="text-muted small">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i>SCD Type 2 Rate History
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th scope="col" style="width: 50px;">ID</th>
                            <th scope="col" style="width: 70px;">Image</th>
                            <th scope="col">SKU / Code</th>
                            <th scope="col">Service Name</th>
                            <th scope="col">Category</th>
                            <th scope="col">Scope &amp; Inclusions</th>
                            <th scope="col" class="text-end">Labor Fee</th>
                            <th scope="col" class="text-center">Bookings</th>
                            <th scope="col" class="text-center">Status</th>
                            <th scope="col" class="text-end" style="min-width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($serviceList)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-wrench fa-3x text-secondary mb-3 d-block opacity-50"></i>
                                    <p class="mb-2 fw-semibold">No services found matching the criteria.</p>
                                    <a href="addService.php" class="btn btn-sm btn-primary">
                                        <i class="fa-solid fa-plus me-1"></i>Add New Service
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($serviceList as $s): ?>
                                <?php
                                $isArchived = ($s['endDate'] !== null);
                                ?>
                                <tr class="<?php echo $isArchived ? 'table-light text-muted opacity-75' : ''; ?>">
                                    <td class="text-muted fw-bold">#<?php echo (int)$s['serviceID']; ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($s['image']) && file_exists(__DIR__ . '/../../' . $s['image'])): ?>
                                            <img src="../../<?php echo htmlspecialchars($s['image']); ?>" alt="<?php echo htmlspecialchars($s['name']); ?>" class="rounded border shadow-sm" width="55" height="42" style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-secondary bg-opacity-10 text-secondary rounded d-flex align-items-center justify-content-center mx-auto" style="width: 55px; height: 42px;">
                                                <i class="fa-solid fa-wrench"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark-subtle text-dark font-monospace"><?php echo htmlspecialchars($s['sku']); ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($s['name']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            <?php echo htmlspecialchars($s['categoryName'] ?? 'General'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-muted text-truncate" style="max-width: 280px;" title="<?php echo htmlspecialchars($s['description'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($s['description'] ?? 'No description provided.'); ?>
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold text-dark font-monospace">
                                        &#8369;<?php echo number_format($s['price'], 2); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">
                                            <i class="fa-solid fa-receipt me-1 text-muted"></i><?php echo (int)$s['usageCount']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($isArchived): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border">Archived</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if (!$isArchived): ?>
                                            <a href="editService.php?id=<?php echo (int)$s['serviceID']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit Service">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="deleteService.php?id=<?php echo (int)$s['serviceID']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to deactivate/archive &quot;<?php echo htmlspecialchars($s['name']); ?>&quot;?');" title="Deactivate Service">
                                                <i class="fa-solid fa-box-archive"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="editService.php?id=<?php echo (int)$s['serviceID']; ?>" class="btn btn-sm btn-outline-secondary me-1" title="View Version">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <a href="deleteService.php?id=<?php echo (int)$s['serviceID']; ?>&action=permanent" class="btn btn-sm btn-outline-danger" onclick="return confirm('Permanently delete this archived service package?');" title="Delete Permanently">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white py-2 text-muted small text-end">
                PedalWorks Dynamics &bull; Workshop Service Module
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
