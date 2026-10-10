<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$activeModule = $activeModule ?? '';

if (!$activeModule) {
    if (strpos($currentUri, '/admin/product/') !== false || strpos($currentUri, 'product.php') !== false) {
        $activeModule = 'product';
    } elseif (strpos($currentUri, '/admin/service/') !== false || strpos($currentUri, 'service.php') !== false) {
        $activeModule = 'service';
    } elseif (strpos($currentUri, '/admin/customerAccount/') !== false || strpos($currentUri, 'customerAccount.php') !== false) {
        $activeModule = 'customer';
    } elseif (strpos($currentUri, '/admin/adminAccount/') !== false || strpos($currentUri, 'adminAccount.php') !== false) {
        $activeModule = 'admin';
    } else {
        $activeModule = 'dashboard';
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/project/PedalWorks-Dynamics/admin/dashboard.php">
            <i class="fa-solid fa-person-biking text-warning fs-4"></i>
            <span>PedalWorks Dynamics <span class="badge bg-secondary fw-normal ms-1 small">Admin</span></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($activeModule === 'dashboard') ? 'active fw-bold text-white' : ''; ?>" href="/project/PedalWorks-Dynamics/admin/dashboard.php">
                        <i class="fa-solid fa-gauge me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($activeModule === 'product') ? 'active fw-bold text-white' : ''; ?>" href="/project/PedalWorks-Dynamics/admin/product/index.php">
                        <i class="fa-solid fa-boxes-stacked me-1"></i> Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($activeModule === 'service') ? 'active fw-bold text-white' : ''; ?>" href="/project/PedalWorks-Dynamics/admin/service/index.php">
                        <i class="fa-solid fa-screwdriver-wrench me-1"></i> Services
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($activeModule === 'customer') ? 'active fw-bold text-white' : ''; ?>" href="/project/PedalWorks-Dynamics/admin/customerAccount/index.php">
                        <i class="fa-solid fa-users me-1"></i> Customers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($activeModule === 'admin') ? 'active fw-bold text-white' : ''; ?>" href="/project/PedalWorks-Dynamics/admin/adminAccount/index.php">
                        <i class="fa-solid fa-user-shield me-1"></i> Staff Accounts
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <a href="/project/PedalWorks-Dynamics/index.php" target="_blank" class="btn btn-outline-light btn-sm">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Storefront
                </a>

                <?php if (isset($_SESSION['username'])): ?>
                    <span class="text-light small d-none d-md-inline">
                        <i class="fa-solid fa-circle-user me-1 text-info"></i>
                        <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                        <span class="badge bg-primary ms-1"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?></span>
                    </span>
                    <a href="/project/PedalWorks-Dynamics/logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="/project/PedalWorks-Dynamics/login.php" class="btn btn-outline-info btn-sm">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
