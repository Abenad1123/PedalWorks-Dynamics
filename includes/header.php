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
    <title>PedalWorks Dynamics</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/project/PedalWorks-Dynamics/includes/style/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-pw-primary sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/project/PedalWorks-Dynamics/index.php">
            <i class="fa-solid fa-bicycle me-2"></i>PedalWorks Dynamics
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link" href="/project/PedalWorks-Dynamics/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/project/PedalWorks-Dynamics/index.php#products">Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/project/PedalWorks-Dynamics/index.php#services">Services</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/project/PedalWorks-Dynamics/view_cart.php">
                            <i class="fa-solid fa-cart-shopping"></i> Cart
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($_SESSION['email'] ?? 'Account'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/project/PedalWorks-Dynamics/user/profile.php">My Profile</a></li>
                            <li><a class="dropdown-item" href="/project/PedalWorks-Dynamics/user/orders.php">My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/project/PedalWorks-Dynamics/user/logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/project/PedalWorks-Dynamics/user/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-outline-light btn-sm ms-lg-2" href="/project/PedalWorks-Dynamics/user/register.php">Sign Up</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
