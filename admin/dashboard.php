<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - PedalWorks Dynamics</title>
</head>
<body>
    <h1>Admin Dashboard</h1>
    <?php if (isset($_SESSION['username'])): ?>
        <p>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> (Role: <?php echo htmlspecialchars($_SESSION['role'] ?? 'Staff'); ?>)</p>
    <?php endif; ?>
    <div>
        <a href="/project/PedalWorks-Dynamics/admin/product.php"><button type="button">Product Page</button></a>
        <a href="/project/PedalWorks-Dynamics/admin/service.php"><button type="button">Service Page</button></a>
        <a href="/project/PedalWorks-Dynamics/admin/customerAccount.php"><button type="button">Customer Account Page</button></a>
        <a href="/project/PedalWorks-Dynamics/admin/adminAccount.php"><button type="button">Admin Account Page</button></a>
    </div>
    <br>
    <div>
        <a href="/project/PedalWorks-Dynamics/logout.php"><button type="button">Logout</button></a>
        <a href="/project/PedalWorks-Dynamics/index.php"><button type="button">Go to Homepage</button></a>
    </div>
</body>
</html>
