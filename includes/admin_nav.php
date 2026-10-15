<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure auth helper functions exist if not already included
if (!function_exists('hasAdminRole')) {
    include_once(__DIR__ . '/admin_auth.php');
}

$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$activeModule = $activeModule ?? '';

if (!$activeModule) {
    if (strpos($currentUri, '/admin/service_requests/') !== false) {
        $activeModule = 'service_requests';
    } elseif (strpos($currentUri, '/admin/expenses/') !== false) {
        $activeModule = 'expenses';
    } elseif (strpos($currentUri, '/admin/reports/') !== false) {
        $activeModule = 'reports';
    } elseif (strpos($currentUri, '/admin/product/') !== false || strpos($currentUri, 'product.php') !== false) {
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

// Role checks for each navigation tab & menu item
$canAccessProducts  = hasAdminRole(['Inventory Manager']);
$canAccessServices  = hasAdminRole(['Service & Repair Manager']);
$canAccessExpenses  = hasAdminRole(['Accounting Administrator']);
$canAccessReports   = hasAdminRole(['Accounting Administrator', 'Inventory Manager']);
$canAccessCustomers = hasAdminRole(['Accounting Administrator']);
$canAccessAdmins    = hasAdminRole(['Super Administrator']);

$hasCatalogGroup    = ($canAccessProducts || $canAccessServices);
$hasOperationsGroup = ($canAccessServices || $canAccessExpenses);
$hasUserGroup       = ($canAccessCustomers || $canAccessAdmins);

$isCatalogActive    = in_array($activeModule, ['product', 'service'], true);
$isOperationsActive = in_array($activeModule, ['service_requests', 'expenses'], true);
$isReportsActive    = ($activeModule === 'reports');
$isUserActive       = in_array($activeModule, ['customer', 'admin'], true);

$adminUsername = $_SESSION['username'] ?? 'Admin';
$adminRole     = $_SESSION['role'] ?? 'Staff Member';
$initial       = strtoupper(substr($adminUsername, 0, 1));
?>
<style>
/* Apple-inspired Admin Top Navigation Bar Styling */
.pw-admin-navbar {
    background: rgba(15, 23, 42, 0.94) !important;
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    transition: all 0.25s ease;
}

.pw-admin-navbar .navbar-brand {
    font-weight: 700;
    font-size: 0.98rem;
    letter-spacing: -0.015em;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.35rem 0.5rem;
    border-radius: 8px;
    transition: opacity 0.2s ease;
}

.pw-admin-navbar .navbar-brand:hover {
    opacity: 0.9;
}

.pw-admin-navbar .navbar-brand-badge {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 0.2rem 0.45rem;
    border-radius: 5px;
    background: rgba(255, 255, 255, 0.12);
    color: #cbd5e1;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.pw-admin-navbar .nav-link {
    color: #94a3b8 !important;
    font-size: 0.86rem;
    font-weight: 500;
    padding: 0.42rem 0.85rem !important;
    border-radius: 7px;
    transition: all 0.18s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.pw-admin-navbar .nav-link:hover,
.pw-admin-navbar .nav-link:focus {
    color: #f8fafc !important;
    background: rgba(255, 255, 255, 0.06);
}

.pw-admin-navbar .nav-link.active {
    color: #ffffff !important;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.12);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
}

.pw-admin-navbar .nav-link.active .fa-chevron-down {
    color: #f59e0b;
}

/* Modern Glass Dropdown Menus */
.pw-admin-navbar .dropdown-menu {
    background: rgba(18, 26, 43, 0.98);
    backdrop-filter: blur(24px) saturate(190%);
    -webkit-backdrop-filter: blur(24px) saturate(190%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    box-shadow: 0 16px 40px -10px rgba(0, 0, 0, 0.65), 0 0 0 1px rgba(255, 255, 255, 0.05);
    padding: 0.45rem;
    min-width: 230px;
    margin-top: 0.45rem;
    animation: pwDropdownFadeIn 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes pwDropdownFadeIn {
    from {
        opacity: 0;
        transform: translateY(-6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.pw-admin-navbar .dropdown-header {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
    color: #64748b;
    padding: 0.5rem 0.75rem 0.25rem;
}

.pw-admin-navbar .dropdown-item {
    font-size: 0.84rem;
    font-weight: 500;
    color: #cbd5e1;
    padding: 0.55rem 0.8rem;
    border-radius: 8px;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pw-admin-navbar .dropdown-item i {
    width: 18px;
    text-align: center;
    font-size: 0.9rem;
    color: #94a3b8;
    transition: color 0.15s ease;
}

.pw-admin-navbar .dropdown-item:hover,
.pw-admin-navbar .dropdown-item:focus {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

.pw-admin-navbar .dropdown-item:hover i {
    color: #38bdf8;
}

.pw-admin-navbar .dropdown-item.active {
    background: rgba(56, 189, 248, 0.15);
    color: #38bdf8;
    font-weight: 600;
}

.pw-admin-navbar .dropdown-item.active i {
    color: #38bdf8;
}

.pw-admin-navbar .dropdown-divider {
    border-top-color: rgba(255, 255, 255, 0.08);
    margin: 0.35rem 0;
}

/* Profile Chip Button */
.pw-admin-profile-btn {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #f1f5f9;
    padding: 0.3rem 0.65rem 0.3rem 0.35rem;
    border-radius: 30px;
    font-size: 0.82rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    transition: all 0.2s ease;
}

.pw-admin-profile-btn:hover,
.pw-admin-profile-btn:focus {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.2);
    color: #ffffff;
}

.pw-admin-avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0284c7, #2563eb);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
}

.pw-admin-storefront-btn {
    border-color: rgba(255, 255, 255, 0.15);
    color: #cbd5e1;
    font-size: 0.8rem;
    padding: 0.35rem 0.75rem;
    border-radius: 7px;
    transition: all 0.18s ease;
}

.pw-admin-storefront-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.3);
}
</style>

<nav class="navbar navbar-expand-lg navbar-dark pw-admin-navbar sticky-top shadow-sm">
    <div class="container-fluid px-3 px-xl-4">
        <!-- Brand / Logo -->
        <a class="navbar-brand" href="/project/PedalWorks-Dynamics/admin/dashboard.php">
            <i class="fa-solid fa-person-biking text-warning fs-5"></i>
            <span>PedalWorks</span>
            <span class="navbar-brand-badge">Admin</span>
        </a>

        <!-- Mobile Hamburger Toggler -->
        <button class="navbar-toggler border-0 shadow-none px-2 py-1" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars text-light fs-5"></i>
        </button>

        <!-- Navbar Menu Items -->
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-2 gap-lg-1">
                <!-- 1. Dashboard -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($activeModule === 'dashboard') ? 'active' : ''; ?>" href="/project/PedalWorks-Dynamics/admin/dashboard.php">
                        <i class="fa-solid fa-gauge"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <!-- 2. Catalog Dropdown (Products, Services) -->
                <?php if ($hasCatalogGroup): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo $isCatalogActive ? 'active' : ''; ?>" href="#" id="catalogDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-boxes-stacked"></i>
                            <span>Catalog</span>
                        </a>
                        <ul class="dropdown-menu shadow-lg" aria-labelledby="catalogDropdown">
                            <li class="dropdown-header">Store Inventory &amp; Offerings</li>
                            <?php if ($canAccessProducts): ?>
                                <li>
                                    <a class="dropdown-item <?php echo ($activeModule === 'product') ? 'active' : ''; ?>" href="/project/PedalWorks-Dynamics/admin/product/index.php">
                                        <i class="fa-solid fa-bicycle"></i>
                                        <div>
                                            <div class="fw-semibold">Products &amp; Inventory</div>
                                            <small class="text-muted d-block" style="font-size: 0.72rem;">Manage bikes, components &amp; stock</small>
                                        </div>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if ($canAccessServices): ?>
                                <li>
                                    <a class="dropdown-item <?php echo ($activeModule === 'service') ? 'active' : ''; ?>" href="/project/PedalWorks-Dynamics/admin/service/index.php">
                                        <i class="fa-solid fa-screwdriver-wrench"></i>
                                        <div>
                                            <div class="fw-semibold">Workshop Services</div>
                                            <small class="text-muted d-block" style="font-size: 0.72rem;">Tune-ups, overhauls &amp; labor pricing</small>
                                        </div>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- 3. Operations Dropdown (Service Tracking, Expenses) -->
                <?php if ($hasOperationsGroup): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo $isOperationsActive ? 'active' : ''; ?>" href="#" id="operationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-layer-group"></i>
                            <span>Operations</span>
                        </a>
                        <ul class="dropdown-menu shadow-lg" aria-labelledby="operationsDropdown">
                            <li class="dropdown-header">Workshop &amp; Store Management</li>
                            <?php if ($canAccessServices): ?>
                                <li>
                                    <a class="dropdown-item <?php echo ($activeModule === 'service_requests') ? 'active' : ''; ?>" href="/project/PedalWorks-Dynamics/admin/service_requests/index.php">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                        <div>
                                            <div class="fw-semibold">Service Tracking (FR16)</div>
                                            <small class="text-muted d-block" style="font-size: 0.72rem;">Monitor customer repair lifecycle</small>
                                        </div>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if ($canAccessExpenses): ?>
                                <li>
                                    <a class="dropdown-item <?php echo ($activeModule === 'expenses') ? 'active' : ''; ?>" href="/project/PedalWorks-Dynamics/admin/expenses/index.php">
                                        <i class="fa-solid fa-receipt"></i>
                                        <div>
                                            <div class="fw-semibold">Overhead Expenses (FR17)</div>
                                            <small class="text-muted d-block" style="font-size: 0.72rem;">Record rent, utilities, salaries</small>
                                        </div>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- 4. Reports (Standalone link or quick dropdown) -->
                <?php if ($canAccessReports): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo $isReportsActive ? 'active' : ''; ?>" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-chart-line"></i>
                            <span>Reports</span>
                        </a>
                        <ul class="dropdown-menu shadow-lg" aria-labelledby="reportsDropdown">
                            <li class="dropdown-header">Financial &amp; Inventory Analytics</li>
                            <li>
                                <a class="dropdown-item <?php echo ($activeModule === 'reports') ? 'active' : ''; ?>" href="/project/PedalWorks-Dynamics/admin/reports/financial.php">
                                    <i class="fa-solid fa-chart-pie"></i>
                                    <div>
                                        <div class="fw-semibold">Income &amp; Expenses (FR19)</div>
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">Revenue analytics &amp; profit margins</small>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li class="dropdown-header">Printable Statements (FR20)</li>
                            <li>
                                <a class="dropdown-item" href="/project/PedalWorks-Dynamics/admin/reports/print_financial.php" target="_blank">
                                    <i class="fa-solid fa-print"></i>
                                    <div>
                                        <div class="fw-semibold">Print Financial Statement</div>
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">Official P&amp;L print view</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/project/PedalWorks-Dynamics/admin/reports/print_inventory.php" target="_blank">
                                    <i class="fa-solid fa-file-invoice"></i>
                                    <div>
                                        <div class="fw-semibold">Print Inventory Audit</div>
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">Valuation &amp; stock audit report</small>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- 5. Users Dropdown (Customers, Staff) -->
                <?php if ($hasUserGroup): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo $isUserActive ? 'active' : ''; ?>" href="#" id="usersDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-users-gear"></i>
                            <span>Users</span>
                        </a>
                        <ul class="dropdown-menu shadow-lg" aria-labelledby="usersDropdown">
                            <li class="dropdown-header">Account Management</li>
                            <?php if ($canAccessCustomers): ?>
                                <li>
                                    <a class="dropdown-item <?php echo ($activeModule === 'customer') ? 'active' : ''; ?>" href="/project/PedalWorks-Dynamics/admin/customerAccount/index.php">
                                        <i class="fa-solid fa-users"></i>
                                        <div>
                                            <div class="fw-semibold">Customer Accounts</div>
                                            <small class="text-muted d-block" style="font-size: 0.72rem;">Profiles &amp; client directory</small>
                                        </div>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if ($canAccessAdmins): ?>
                                <li>
                                    <a class="dropdown-item <?php echo ($activeModule === 'admin') ? 'active' : ''; ?>" href="/project/PedalWorks-Dynamics/admin/adminAccount/index.php">
                                        <i class="fa-solid fa-user-shield"></i>
                                        <div>
                                            <div class="fw-semibold">Staff &amp; Role Access (MP2)</div>
                                            <small class="text-muted d-block" style="font-size: 0.72rem;">Staff RBAC &amp; credentials</small>
                                        </div>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Right-Hand Actions: Storefront Button & Apple-styled Profile Dropdown -->
            <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
                <a href="/project/PedalWorks-Dynamics/index.php" target="_blank" class="btn btn-outline-light btn-sm pw-admin-storefront-btn">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Storefront
                </a>

                <?php if (isset($_SESSION['username'])): ?>
                    <div class="dropdown">
                        <button class="btn pw-admin-profile-btn dropdown-toggle" type="button" id="adminUserMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="pw-admin-avatar"><?php echo htmlspecialchars($initial); ?></span>
                            <span class="d-none d-sm-inline text-truncate" style="max-width: 110px;"><?php echo htmlspecialchars($adminUsername); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="adminUserMenu" style="min-width: 240px;">
                            <li class="px-3 py-2 border-bottom border-secondary-subtle">
                                <div class="fw-bold text-white"><?php echo htmlspecialchars($adminUsername); ?></div>
                                <span class="badge bg-primary text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                                    <?php echo htmlspecialchars($adminRole); ?>
                                </span>
                            </li>
                            <li>
                                <a class="dropdown-item mt-1" href="/project/PedalWorks-Dynamics/admin/dashboard.php">
                                    <i class="fa-solid fa-gauge"></i> Overview Dashboard
                                </a>
                            </li>
                            <?php if ($canAccessAdmins): ?>
                                <li>
                                    <a class="dropdown-item" href="/project/PedalWorks-Dynamics/admin/adminAccount/index.php">
                                        <i class="fa-solid fa-user-gear"></i> Manage Staff
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="/project/PedalWorks-Dynamics/logout.php">
                                    <i class="fa-solid fa-right-from-bracket text-danger"></i> Sign Out
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="/project/PedalWorks-Dynamics/login.php" class="btn btn-outline-info btn-sm">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

