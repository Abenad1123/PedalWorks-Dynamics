<?php
session_start();
include('../includes/config.php');
include('../includes/admin_auth.php');

$totalProducts = 0;
$totalServices = 0;
$totalCustomers = 0;
$totalAdmins = 0;
$lowStockCount = 0;

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

if ($conn) {
    // Current products
    $pRes = mysqli_query($conn, "SELECT COUNT(*) as c FROM product WHERE endDate IS NULL");
    if ($pRes && ($r = mysqli_fetch_assoc($pRes))) {
        $totalProducts = (int)$r['c'];
    }

    // Current services
    $sRes = mysqli_query($conn, "SELECT COUNT(*) as c FROM service WHERE endDate IS NULL");
    if ($sRes && ($r = mysqli_fetch_assoc($sRes))) {
        $totalServices = (int)$r['c'];
    }

    // Total customers
    $cRes = mysqli_query($conn, "SELECT COUNT(*) as c FROM customer");
    if ($cRes && ($r = mysqli_fetch_assoc($cRes))) {
        $totalCustomers = (int)$r['c'];
    }

    // Total staff administrators
    $aRes = mysqli_query($conn, "SELECT COUNT(*) as c FROM adminAccount");
    if ($aRes && ($r = mysqli_fetch_assoc($aRes))) {
        $totalAdmins = (int)$r['c'];
    }

    // Low stock items
    $lsRes = mysqli_query($conn, "SELECT COUNT(*) as c FROM product WHERE endDate IS NULL AND stock <= lowStockThreshold");
    if ($lsRes && ($r = mysqli_fetch_assoc($lsRes))) {
        $lowStockCount = (int)$r['c'];
    }
}

// Module access checks
$canManageProducts  = hasAdminRole(['Inventory Manager']);
$canManageServices  = hasAdminRole(['Service & Repair Manager']);
$canManageCustomers = hasAdminRole(['Accounting Administrator']);
$canManageAdmins    = hasAdminRole(['Super Administrator']);
$currentRole        = $_SESSION['role'] ?? 'Staff Member';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PedalWorks Dynamics</title>
    <!-- Bootstrap 5 CDN matching itim211 source example -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light">

    <!-- Top Admin Navigation Bar -->
    <?php include('../includes/admin_nav.php'); ?>

    <!-- Main Content Area -->
    <div class="container py-4">

        <!-- Welcome Banner -->
        <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-2">
            <div>
                <h1 class="h3 mb-1 fw-bold">
                    <i class="fa-solid fa-gauge-high text-primary me-2"></i>Management Dashboard
                </h1>
                <p class="text-muted mb-0 small">
                    Signed in as <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong> &bull; Role: 
                    <span class="badge bg-primary"><?php echo htmlspecialchars($currentRole); ?></span>
                </p>
            </div>
            <div>
                <a href="../index.php" class="btn btn-outline-secondary btn-sm" target="_blank">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Storefront
                </a>
            </div>
        </div>

        <!-- Success & Error System Alerts -->
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

        <?php if ($lowStockCount > 0 && $canManageProducts): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-4 shadow-sm" role="alert">
                <i class="fa-solid fa-triangle-exclamation fs-5"></i>
                <div>
                    <strong>Inventory Alert:</strong> There are <strong><?php echo $lowStockCount; ?></strong> product(s) at or below their low stock threshold.
                    <a href="product/stockUpdate.php" class="alert-link ms-2">Update Stock Now &rarr;</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Summary Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100 border-start border-primary border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Active Products</span>
                                <h3 class="fw-bold mb-0 mt-1"><?php echo $totalProducts; ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                                <i class="fa-solid fa-bicycle fa-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100 border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Workshop Services</span>
                                <h3 class="fw-bold mb-0 mt-1"><?php echo $totalServices; ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                                <i class="fa-solid fa-wrench fa-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100 border-start border-info border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Customer Accounts</span>
                                <h3 class="fw-bold mb-0 mt-1"><?php echo $totalCustomers; ?></h3>
                            </div>
                            <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                                <i class="fa-solid fa-users fa-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card shadow-sm h-100 border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">Admin Staff</span>
                                <h3 class="fw-bold mb-0 mt-1"><?php echo $totalAdmins; ?></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                                <i class="fa-solid fa-shield-halved fa-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Management Modules Grid -->
        <h2 class="h5 mb-3 fw-bold text-secondary">Operation Modules</h2>
        <div class="row g-4">

            <!-- 1. Products Module (Inventory Manager & Super Admin) -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card shadow-sm h-100 <?php echo !$canManageProducts ? 'opacity-75 border-secondary-subtle' : ''; ?>">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-boxes-stacked text-primary fs-4"></i>
                                <h5 class="card-title mb-0 fw-bold">Products</h5>
                            </div>
                            <?php if (!$canManageProducts): ?>
                                <span class="badge bg-secondary-subtle text-secondary small"><i class="fa-solid fa-lock me-1"></i>Restricted</span>
                            <?php endif; ?>
                        </div>
                        <p class="card-text text-muted small flex-grow-1">
                            Manage bike catalog, prices, categories, and inventory stock levels.
                        </p>
                        <div class="d-grid gap-2">
                            <?php if ($canManageProducts): ?>
                                <a href="product/index.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fa-solid fa-list me-1"></i>Product Overview
                                </a>
                                <a href="product/addProduct.php" class="btn btn-light btn-sm text-secondary">
                                    <i class="fa-solid fa-plus me-1"></i>Add Product
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary btn-sm" disabled>
                                    <i class="fa-solid fa-ban me-1"></i>Inventory Manager Only
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Services Module (Service & Repair Manager & Super Admin) -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card shadow-sm h-100 <?php echo !$canManageServices ? 'opacity-75 border-secondary-subtle' : ''; ?>">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-screwdriver-wrench text-success fs-4"></i>
                                <h5 class="card-title mb-0 fw-bold">Services</h5>
                            </div>
                            <?php if (!$canManageServices): ?>
                                <span class="badge bg-secondary-subtle text-secondary small"><i class="fa-solid fa-lock me-1"></i>Restricted</span>
                            <?php endif; ?>
                        </div>
                        <p class="card-text text-muted small flex-grow-1">
                            Configure repair labor, tune-up service packages, and maintenance rates.
                        </p>
                        <div class="d-grid gap-2">
                            <?php if ($canManageServices): ?>
                                <a href="service/index.php" class="btn btn-outline-success btn-sm">
                                    <i class="fa-solid fa-list me-1"></i>Service Overview
                                </a>
                                <a href="service/addService.php" class="btn btn-light btn-sm text-secondary">
                                    <i class="fa-solid fa-plus me-1"></i>Add Service
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary btn-sm" disabled>
                                    <i class="fa-solid fa-ban me-1"></i>Service Manager Only
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Customer Accounts Module (Super Admin Only) -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card shadow-sm h-100 <?php echo !$canManageCustomers ? 'opacity-75 border-secondary-subtle' : ''; ?>">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-address-book text-info fs-4"></i>
                                <h5 class="card-title mb-0 fw-bold">Customer Accounts</h5>
                            </div>
                            <?php if (!$canManageCustomers): ?>
                                <span class="badge bg-secondary-subtle text-secondary small"><i class="fa-solid fa-lock me-1"></i>Restricted</span>
                            <?php endif; ?>
                        </div>
                        <p class="card-text text-muted small flex-grow-1">
                            Browse customer profiles, modify personal info, and manage account statuses.
                        </p>
                        <div class="d-grid gap-2">
                            <?php if ($canManageCustomers): ?>
                                <a href="customerAccount/index.php" class="btn btn-outline-info btn-sm">
                                    <i class="fa-solid fa-users-gear me-1"></i>Manage Customers
                                </a>
                                <a href="customerAccount/addCustomerAccount.php" class="btn btn-light btn-sm text-secondary">
                                    <i class="fa-solid fa-user-plus me-1"></i>New Customer
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary btn-sm" disabled>
                                    <i class="fa-solid fa-ban me-1"></i>Accounting Admin Only
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Admin Accounts Module (Super Admin Only) -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card shadow-sm h-100 <?php echo !$canManageAdmins ? 'opacity-75 border-secondary-subtle' : ''; ?>">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-user-shield text-warning fs-4"></i>
                                <h5 class="card-title mb-0 fw-bold">Admin Accounts</h5>
                            </div>
                            <?php if (!$canManageAdmins): ?>
                                <span class="badge bg-secondary-subtle text-secondary small"><i class="fa-solid fa-lock me-1"></i>Restricted</span>
                            <?php endif; ?>
                        </div>
                        <p class="card-text text-muted small flex-grow-1">
                            Assign roles (Super Admin, Inventory, Service, Accounting) and staff credentials.
                        </p>
                        <div class="d-grid gap-2">
                            <?php if ($canManageAdmins): ?>
                                <a href="adminAccount/index.php" class="btn btn-outline-warning btn-sm text-dark">
                                    <i class="fa-solid fa-users-viewfinder me-1"></i>Manage Staff
                                </a>
                                <a href="adminAccount/addAdminAccount.php" class="btn btn-light btn-sm text-secondary">
                                    <i class="fa-solid fa-user-plus me-1"></i>New Staff
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary btn-sm" disabled>
                                    <i class="fa-solid fa-ban me-1"></i>Super Admin Only
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Footer Info -->
        <div class="mt-5 pt-3 border-top text-center text-muted small">
            <p class="mb-0">PedalWorks Dynamics &bull; Bicycle Shop Management System &bull; BSIT-2B-T</p>
        </div>

    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
