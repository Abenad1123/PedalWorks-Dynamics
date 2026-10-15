<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PedalWorks Dynamics | Adventure Bikes & Workshop</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom Glassmorphism & Nature Theme Stylesheet -->
    <link rel="stylesheet" href="/project/PedalWorks-Dynamics/includes/style/style.css">
</head>
<body class="pw-nature-body">

<nav class="navbar navbar-expand-lg pw-glass-nav sticky-top">
    <div class="container">
        <a class="navbar-brand pw-brand d-flex align-items-center gap-2" href="/project/PedalWorks-Dynamics/index.php">
            <span class="pw-brand-icon">
                <i class="fa-solid fa-person-biking"></i>
            </span>
            <span class="pw-brand-text">
                <span class="pw-brand-title">PedalWorks</span>
                <span class="pw-brand-sub">Dynamics</span>
            </span>
        </a>

        <button class="navbar-toggler pw-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars-staggered"></i>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav mx-auto align-items-lg-center pw-nav-list my-2 my-lg-0">
                <li class="nav-item">
                    <a class="nav-link pw-nav-link" href="/project/PedalWorks-Dynamics/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link pw-nav-link" href="/project/PedalWorks-Dynamics/index.php#about">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link pw-nav-link" href="/project/PedalWorks-Dynamics/products.php">Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link pw-nav-link" href="/project/PedalWorks-Dynamics/services.php">Services</a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2 pw-nav-actions">
                <?php 
                $headerCartCount = 0;
                if (!empty($_SESSION['customerAccountID']) && isset($conn) && $conn) {
                    $hcStmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(quantity), 0) FROM customerCart WHERE customerAccountID = ?");
                    if ($hcStmt) {
                        mysqli_stmt_bind_param($hcStmt, "i", $_SESSION['customerAccountID']);
                        mysqli_stmt_execute($hcStmt);
                        mysqli_stmt_bind_result($hcStmt, $headerCartCount);
                        mysqli_stmt_fetch($hcStmt);
                        mysqli_stmt_close($hcStmt);
                    }
                }
                ?>
                <?php if (!empty($_SESSION['customerAccountID']) || ($_SESSION['account_type'] ?? '') === 'customer'): ?>
                    <a class="btn pw-btn-glass-icon position-relative" href="/project/PedalWorks-Dynamics/cart.php" title="Shopping Cart">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <?php if ($headerCartCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill pw-badge-cart">
                                <?php echo (int)$headerCartCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <?php if (isset($_SESSION['customerAccountID']) || isset($_SESSION['adminAccountID']) || isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <button class="btn pw-btn-glass dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if (!empty($_SESSION['photo']) && file_exists(__DIR__ . '/../assets/images/uploads/users/' . $_SESSION['photo'])): ?>
                                <img src="/project/PedalWorks-Dynamics/assets/images/uploads/users/<?php echo htmlspecialchars($_SESSION['photo']); ?>" 
                                     alt="Profile" class="rounded-circle" style="width: 24px; height: 24px; object-fit: cover;">
                            <?php else: ?>
                                <i class="fa-solid fa-circle-user"></i>
                            <?php endif; ?>
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['username'] ?? $_SESSION['email'] ?? 'Account'); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end pw-glass-dropdown">
                            <?php if (!empty($_SESSION['customerAccountID']) || ($_SESSION['account_type'] ?? '') === 'customer'): ?>
                                <li><a class="dropdown-item pw-dropdown-item" href="/project/PedalWorks-Dynamics/user/profile.php"><i class="fa-solid fa-id-card me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item pw-dropdown-item" href="/project/PedalWorks-Dynamics/cart.php"><i class="fa-solid fa-cart-shopping me-2"></i>Shopping Cart</a></li>
                                <li><a class="dropdown-item pw-dropdown-item" href="/project/PedalWorks-Dynamics/user/orders.php"><i class="fa-solid fa-clock-rotate-left me-2"></i>Order History</a></li>
                                <li><hr class="dropdown-divider pw-dropdown-divider"></li>
                            <?php endif; ?>
                            <?php if (!empty($_SESSION['adminAccountID']) || ($_SESSION['account_type'] ?? '') === 'admin'): ?>
                                <li><a class="dropdown-item pw-dropdown-item text-warning" href="/project/PedalWorks-Dynamics/admin/dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Admin Dashboard</a></li>
                                <li><hr class="dropdown-divider pw-dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item pw-dropdown-item text-danger" href="/project/PedalWorks-Dynamics/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a class="btn pw-btn-glass-sm" href="/project/PedalWorks-Dynamics/login.php">
                        <i class="fa-solid fa-arrow-right-to-bracket me-1"></i>Login
                    </a>
                    <a class="btn pw-btn-trail-sm" href="/project/PedalWorks-Dynamics/signup.php">
                        <i class="fa-solid fa-user-plus me-1"></i> Sign Up
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
