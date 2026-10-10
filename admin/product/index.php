<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');
requireAdminRole(['Inventory Manager']);

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
$stockFilter = isset($_GET['stock_status']) ? trim($_GET['stock_status']) : '';
$showArchived = isset($_GET['show_archived']) && $_GET['show_archived'] === '1';

// Fetch categories for filter dropdown
$categories = [];
if ($conn) {
    $catRes = mysqli_query($conn, "SELECT * FROM productCategory ORDER BY name ASC");
    if ($catRes) {
        while ($row = mysqli_fetch_assoc($catRes)) {
            $categories[] = $row;
        }
    }
}

// Build query
$productList = [];
$totalActive = 0;
$totalLowStock = 0;
$totalOutOfStock = 0;

if ($conn) {
    // Metric counts
    $mRes = mysqli_query($conn, "SELECT 
        COUNT(*) AS activeCount,
        SUM(CASE WHEN stock <= lowStockThreshold AND stock > 0 THEN 1 ELSE 0 END) AS lowCount,
        SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) AS outCount
        FROM product WHERE endDate IS NULL");
    if ($mRes && ($mRow = mysqli_fetch_assoc($mRes))) {
        $totalActive = (int)$mRow['activeCount'];
        $totalLowStock = (int)$mRow['lowCount'];
        $totalOutOfStock = (int)$mRow['outCount'];
    }

    $where = [];
    $params = [];
    $types = "";

    if (!$showArchived) {
        $where[] = "p.endDate IS NULL";
    }

    if ($search !== '') {
        $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
        $searchParam = "%" . $search . "%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }

    if ($categoryFilter !== '') {
        $where[] = "pc.name = ?";
        $params[] = $categoryFilter;
        $types .= "s";
    }

    if ($stockFilter === 'low') {
        $where[] = "p.stock <= p.lowStockThreshold AND p.stock > 0";
    } elseif ($stockFilter === 'out') {
        $where[] = "p.stock <= 0";
    } elseif ($stockFilter === 'instock') {
        $where[] = "p.stock > p.lowStockThreshold";
    }

    $sql = "SELECT p.*, pc.name AS categoryName 
            FROM product p 
            LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID ";
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY p.productID DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $productList[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
}
$activeModule = 'product';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - PedalWorks Dynamics</title>
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
                <li class="breadcrumb-item active" aria-current="page">Product Management</li>
            </ol>
        </nav>

        <!-- Header Section -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1">
                    <i class="fa-solid fa-bicycle text-primary me-2"></i>Product Catalog &amp; Inventory
                </h1>
                <p class="text-muted small mb-0">Manage bikes, components, safety gear, stock counts, and SCD Type 2 pricing records.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="stockUpdate.php" class="btn btn-outline-warning text-dark btn-sm d-flex align-items-center shadow-sm">
                    <i class="fa-solid fa-boxes-stacked me-2"></i>Stock Adjustment
                </a>
                <a href="addProduct.php" class="btn btn-primary btn-sm d-flex align-items-center shadow-sm">
                    <i class="fa-solid fa-plus-circle me-2"></i>Add New Product
                </a>
            </div>
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
            <div class="col-sm-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 border-start border-primary border-4">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase">Active Catalog</div>
                        <div class="fs-4 fw-bold text-dark mt-1"><?php echo $totalActive; ?></div>
                        <div class="small text-muted"><i class="fa-solid fa-tag me-1 text-primary"></i>Live saleable items</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 border-start border-warning border-4">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase">Low Stock Alert</div>
                        <div class="fs-4 fw-bold text-warning mt-1"><?php echo $totalLowStock; ?></div>
                        <div class="small text-muted"><i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>&le; Alert threshold</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 border-start border-danger border-4">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase">Out of Stock</div>
                        <div class="fs-4 fw-bold text-danger mt-1"><?php echo $totalOutOfStock; ?></div>
                        <div class="small text-muted"><i class="fa-solid fa-circle-xmark me-1 text-danger"></i>0 items available</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 border-start border-info border-4">
                    <div class="card-body">
                        <div class="text-muted small fw-semibold text-uppercase">Categories</div>
                        <div class="fs-4 fw-bold text-info mt-1"><?php echo count($categories); ?></div>
                        <div class="small text-muted"><i class="fa-solid fa-layer-group me-1 text-info"></i>Product classifications</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="get" action="index.php" class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1" for="search">Keyword Search</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="search" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search SKU, name, or description...">
                        </div>
                    </div>
                    <div class="col-md-3">
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
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted mb-1" for="stock_status">Stock Status</label>
                        <select id="stock_status" name="stock_status" class="form-select form-select-sm">
                            <option value="">All Stock</option>
                            <option value="instock" <?php echo ($stockFilter === 'instock') ? 'selected' : ''; ?>>Adequate Stock</option>
                            <option value="low" <?php echo ($stockFilter === 'low') ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out" <?php echo ($stockFilter === 'out') ? 'selected' : ''; ?>>Out of Stock</option>
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
                            <?php if ($search !== '' || $categoryFilter !== '' || $stockFilter !== '' || $showArchived): ?>
                                <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Product Table Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-dark">
                    <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Product Directory
                    <span class="badge bg-secondary ms-2"><?php echo count($productList); ?> item(s)</span>
                </span>
                <span class="text-muted small">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i>SCD Type 2 Price Tracked
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th scope="col" style="width: 50px;">ID</th>
                            <th scope="col" style="width: 70px;">Image</th>
                            <th scope="col">SKU / Code</th>
                            <th scope="col">Product Name &amp; Description</th>
                            <th scope="col">Category</th>
                            <th scope="col" class="text-end">Retail Price</th>
                            <th scope="col" class="text-center">Stock Level</th>
                            <th scope="col" class="text-center">Alert Limit</th>
                            <th scope="col" class="text-center">Status</th>
                            <th scope="col" class="text-end" style="min-width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($productList)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-box-open fa-3x text-secondary mb-3 d-block opacity-50"></i>
                                    <p class="mb-2 fw-semibold">No products found matching the criteria.</p>
                                    <a href="addProduct.php" class="btn btn-sm btn-primary">
                                        <i class="fa-solid fa-plus me-1"></i>Add New Product
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productList as $p): ?>
                                <?php
                                $isArchived = ($p['endDate'] !== null);
                                $stockVal = (int)$p['stock'];
                                $threshVal = (int)$p['lowStockThreshold'];
                                $isLow = (!$isArchived && $stockVal <= $threshVal && $stockVal > 0);
                                $isOut = (!$isArchived && $stockVal <= 0);
                                ?>
                                <tr class="<?php echo $isArchived ? 'table-light text-muted opacity-75' : ''; ?>">
                                    <td class="text-muted fw-bold">#<?php echo (int)$p['productID']; ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($p['image']) && file_exists(__DIR__ . '/../../' . $p['image'])): ?>
                                            <img src="../../<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="rounded border shadow-sm" width="55" height="42" style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-secondary bg-opacity-10 text-secondary rounded d-flex align-items-center justify-content-center mx-auto" style="width: 55px; height: 42px;">
                                                <i class="fa-solid fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark-subtle text-dark font-monospace"><?php echo htmlspecialchars($p['sku']); ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <?php if (!empty($p['description'])): ?>
                                            <div class="text-muted text-truncate" style="max-width: 320px;" title="<?php echo htmlspecialchars($p['description']); ?>">
                                                <?php echo htmlspecialchars($p['description']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                            <?php echo htmlspecialchars($p['categoryName'] ?? 'Uncategorized'); ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold text-dark font-monospace">
                                        &#8369;<?php echo number_format($p['price'], 2); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($isArchived): ?>
                                            <span class="badge bg-secondary"><?php echo $stockVal; ?> (Archived)</span>
                                        <?php elseif ($isOut): ?>
                                            <span class="badge bg-danger"><i class="fa-solid fa-circle-xmark me-1"></i>0 (Out)</span>
                                        <?php elseif ($isLow): ?>
                                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i><?php echo $stockVal; ?> (Low)</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><?php echo $stockVal; ?> units</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-muted">
                                        &le; <?php echo $threshVal; ?>
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
                                            <a href="stockUpdate.php?id=<?php echo (int)$p['productID']; ?>" class="btn btn-sm btn-outline-warning text-dark me-1" title="Adjust Stock">
                                                <i class="fa-solid fa-boxes-stacked"></i>
                                            </a>
                                            <a href="editProduct.php?id=<?php echo (int)$p['productID']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit Product">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="deleteProduct.php?id=<?php echo (int)$p['productID']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to deactivate/archive &quot;<?php echo htmlspecialchars($p['name']); ?>&quot;?');" title="Deactivate Product">
                                                <i class="fa-solid fa-box-archive"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="editProduct.php?id=<?php echo (int)$p['productID']; ?>" class="btn btn-sm btn-outline-secondary me-1" title="View Version">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <a href="deleteProduct.php?id=<?php echo (int)$p['productID']; ?>&action=permanent" class="btn btn-sm btn-outline-danger" onclick="return confirm('Permanently delete this archived product version?');" title="Delete Permanently">
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
                PedalWorks Dynamics &bull; Product Inventory Module
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
