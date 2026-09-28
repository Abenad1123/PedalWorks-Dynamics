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
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
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

<!-- Glassmorphism Main Navigation -->
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
            <span class="pw-trail-badge ms-1 d-none d-sm-inline-block">
                <i class="fa-solid fa-mountain me-1"></i>Adventure Lab
            </span>
        </a>

        <button class="navbar-toggler pw-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars-staggered"></i>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav mx-auto align-items-lg-center pw-nav-list my-2 my-lg-0">
                <li class="nav-item">
                    <a class="nav-link pw-nav-link active" href="/project/PedalWorks-Dynamics/index.php">
                        <i class="fa-solid fa-compass me-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link pw-nav-link" href="/project/PedalWorks-Dynamics/index.php#products">
                        <i class="fa-solid fa-bicycle me-1"></i> Gear &amp; Bikes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link pw-nav-link" href="/project/PedalWorks-Dynamics/index.php#services">
                        <i class="fa-solid fa-wrench me-1"></i> Repair Lab
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link pw-nav-link" href="/project/PedalWorks-Dynamics/index.php#about">
                        <i class="fa-solid fa-tree me-1"></i> The Shop
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2 pw-nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a class="btn pw-btn-glass-icon position-relative" href="/project/PedalWorks-Dynamics/view_cart.php" title="Shopping Cart">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <?php if (isset($_SESSION['cart_products']) && count($_SESSION['cart_products']) > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill pw-badge-cart">
                                <?php echo count($_SESSION['cart_products']); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown">
                        <button class="btn pw-btn-glass dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-circle-user"></i>
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Account'); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end pw-glass-dropdown">
                            <li><a class="dropdown-item pw-dropdown-item" href="/project/PedalWorks-Dynamics/user/profile.php"><i class="fa-solid fa-id-card me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item pw-dropdown-item" href="/project/PedalWorks-Dynamics/user/orders.php"><i class="fa-solid fa-clock-rotate-left me-2"></i>My Orders</a></li>
                            <li><hr class="dropdown-divider pw-dropdown-divider"></li>
                            <li><a class="dropdown-item pw-dropdown-item text-danger" href="/project/PedalWorks-Dynamics/user/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a class="btn pw-btn-glass-sm" href="/project/PedalWorks-Dynamics/user/login.php">
                        <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Rider Login
                    </a>
                    <a class="btn pw-btn-trail-sm" href="/project/PedalWorks-Dynamics/user/register.php">
                        <i class="fa-solid fa-user-plus me-1"></i> Sign Up
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
