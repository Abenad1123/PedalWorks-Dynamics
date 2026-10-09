<?php
/**
 * PedalWorks Dynamics — Initial Database Seeder
 * Run this file in your browser: http://localhost/project/PedalWorks-Dynamics/seed_admin.php
 * or from CLI: php seed_admin.php
 *
 * This script safely sets up:
 * 1. Default Admin Accounts for all 4 system roles
 * 2. Default Product and Service Categories
 */

include('./includes/config.php');

if (!$conn) {
    die("<h3>Database connection failed.</h3><p>Please make sure MySQL is running in XAMPP and database 'pedalworks_db' exists.</p>");
}

$messages = [];

// -------------------------------------------------------------------------
// 1. Seed Admin Accounts
// -------------------------------------------------------------------------
$adminAccounts = [
    [
        'username' => 'admin',
        'password' => 'admin123',
        'role'     => 'Super Administrator'
    ],
    [
        'username' => 'inventory_mgr',
        'password' => 'admin123',
        'role'     => 'Inventory Manager'
    ],
    [
        'username' => 'service_mgr',
        'password' => 'admin123',
        'role'     => 'Service & Repair Manager'
    ],
    [
        'username' => 'accounting_mgr',
        'password' => 'admin123',
        'role'     => 'Accounting Administrator'
    ]
];

foreach ($adminAccounts as $acc) {
    $stmt = mysqli_prepare($conn, "SELECT adminAccountID FROM adminAccount WHERE username = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $acc['username']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) === 0) {
        $hashed = password_hash($acc['password'], PASSWORD_DEFAULT);
        $insert = mysqli_prepare($conn, "INSERT INTO adminAccount (username, password, role) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($insert, 'sss', $acc['username'], $hashed, $acc['role']);
        mysqli_stmt_execute($insert);
        mysqli_stmt_close($insert);
        $messages[] = "Created admin: <strong>{$acc['username']}</strong> (Password: <code>{$acc['password']}</code>, Role: {$acc['role']})";
    } else {
        $messages[] = "Admin <strong>{$acc['username']}</strong> already exists in database.";
    }
    mysqli_stmt_close($stmt);
}

// -------------------------------------------------------------------------
// 2. Seed Default Product Categories
// -------------------------------------------------------------------------
$productCategories = [
    'Full Build Bikes',
    'Bicycle Parts',
    'Attachments',
    'Safety Gear'
];

foreach ($productCategories as $catName) {
    $stmt = mysqli_prepare($conn, "SELECT productCategoryID FROM productCategory WHERE name = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $catName);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) === 0) {
        $insert = mysqli_prepare($conn, "INSERT INTO productCategory (name) VALUES (?)");
        mysqli_stmt_bind_param($insert, 's', $catName);
        mysqli_stmt_execute($insert);
        mysqli_stmt_close($insert);
        $messages[] = "Created product category: <strong>{$catName}</strong>";
    }
    mysqli_stmt_close($stmt);
}

// -------------------------------------------------------------------------
// 3. Seed Default Service Categories
// -------------------------------------------------------------------------
$serviceCategories = [
    'Tune-Up & Overhaul',
    'Brake Services',
    'Drivetrain & Transmission',
    'Wheel & Tire Services',
    'Custom Component Mounts'
];

foreach ($serviceCategories as $scName) {
    $stmt = mysqli_prepare($conn, "SELECT serviceCategoryID FROM serviceCategory WHERE name = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $scName);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) === 0) {
        $insert = mysqli_prepare($conn, "INSERT INTO serviceCategory (name) VALUES (?)");
        mysqli_stmt_bind_param($insert, 's', $scName);
        mysqli_stmt_execute($insert);
        mysqli_stmt_close($insert);
        $messages[] = "Created service category: <strong>{$scName}</strong>";
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Seeder - PedalWorks Dynamics</title>
</head>
<body>
    <h1>PedalWorks Dynamics: Database Seeder</h1>
    <p>This script ensures your team members have all initial admin accounts and categories set up.</p>

    <h2>Results:</h2>
    <ul>
        <?php foreach ($messages as $msg): ?>
            <li><?php echo $msg; ?></li>
        <?php endforeach; ?>
    </ul>

    <h2>Default Admin Logins:</h2>
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>Username</th>
                <th>Password</th>
                <th>Role</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>admin</strong></td>
                <td>admin123</td>
                <td>Super Administrator</td>
                <td>Full access to all systems and modules</td>
            </tr>
            <tr>
                <td><strong>inventory_mgr</strong></td>
                <td>admin123</td>
                <td>Inventory Manager</td>
                <td>Manages products, categories, and stock updates</td>
            </tr>
            <tr>
                <td><strong>service_mgr</strong></td>
                <td>admin123</td>
                <td>Service &amp; Repair Manager</td>
                <td>Manages repair services and labor requests</td>
            </tr>
            <tr>
                <td><strong>accounting_mgr</strong></td>
                <td>admin123</td>
                <td>Accounting Administrator</td>
                <td>Manages shop expenses and financial reports</td>
            </tr>
        </tbody>
    </table>

    <br>
    <div>
        <a href="/project/PedalWorks-Dynamics/login.php"><button type="button">Go to Login Page</button></a>
        <a href="/project/PedalWorks-Dynamics/index.php"><button type="button">Go to Homepage</button></a>
    </div>
</body>
</html>
