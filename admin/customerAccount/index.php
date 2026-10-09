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

// Fetch list of customer accounts
$customerList = [];
if ($conn) {
    $listRes = mysqli_query($conn, "SELECT c.customerID, c.firstName, c.lastName, c.birthDate, c.email, c.address,
                                            ca.customerAccountID, ca.username, ca.status 
                                     FROM customer c 
                                     LEFT JOIN customerAccount ca ON c.customerID = ca.customerID 
                                     ORDER BY c.customerID DESC");
    if ($listRes) {
        while ($row = mysqli_fetch_assoc($listRes)) {
            $customerList[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Account Management - PedalWorks Dynamics</title>
</head>
<body>
    <h1>Customer Account Management</h1>

    <div>
        <a href="../dashboard.php"><button type="button">Back to Admin Dashboard</button></a>
        <a href="addCustomerAccount.php"><button type="button">Add New Customer</button></a>
    </div>
    <br>

    <?php if ($successMessage !== ''): ?>
        <div style="color: green;">
            <p><strong>Success:</strong> <?php echo htmlspecialchars($successMessage); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div style="color: red;">
            <p><strong>Error:</strong> <?php echo htmlspecialchars($errorMessage); ?></p>
        </div>
    <?php endif; ?>

    <h2>Customer Accounts Directory (Total: <?php echo count($customerList); ?>)</h2>

    <?php if (empty($customerList)): ?>
        <p>No customer accounts found in the database. Click "Add New Customer" above to create one.</p>
    <?php else: ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Birth Date</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customerList as $cust): ?>
                    <tr>
                        <td><?php echo (int)$cust['customerID']; ?></td>
                        <td><strong><?php echo htmlspecialchars($cust['username'] ?? 'No Account'); ?></strong></td>
                        <td><?php echo htmlspecialchars(trim(($cust['firstName'] ?? '') . ' ' . ($cust['lastName'] ?? ''))); ?></td>
                        <td><?php echo htmlspecialchars($cust['email']); ?></td>
                        <td><?php echo htmlspecialchars($cust['birthDate'] ?? '—'); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($cust['address'])); ?></td>
                        <td>
                            <?php
                            $st = $cust['status'] ?? 'Unknown';
                            if ($st === 'Active') {
                                echo '<span style="color: green; font-weight: bold;">Active</span>';
                            } elseif ($st === 'Disabled') {
                                echo '<span style="color: red; font-weight: bold;">Disabled</span>';
                            } else {
                                echo '<span style="color: orange; font-weight: bold;">Inactive</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="editCustomerAccount.php?id=<?php echo (int)$cust['customerID']; ?>">
                                <button type="button">Edit</button>
                            </a>
                            <a href="deleteCustomerAccount.php?id=<?php echo (int)$cust['customerID']; ?>" onclick="return confirm('Are you sure you want to delete customer #<?php echo (int)$cust['customerID']; ?> (<?php echo htmlspecialchars($cust['username'] ?? ''); ?>)?');">
                                <button type="button">Delete</button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <br><br>
    <div>
        <a href="../dashboard.php"><button type="button">Back to Admin Dashboard</button></a>
        <a href="../../index.php"><button type="button">Go to Homepage</button></a>
    </div>
</body>
</html>
