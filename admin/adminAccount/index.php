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

// Fetch list of admin accounts
$adminList = [];
if ($conn) {
    $listQuery = "SELECT a.adminAccountID, a.username, a.role,
                         (SELECT COUNT(*) FROM customerService cs WHERE cs.adminAccountID = a.adminAccountID) AS serviceCount,
                         (SELECT COUNT(*) FROM expense ex WHERE ex.recordedBy = a.adminAccountID) AS expenseCount
                  FROM adminAccount a
                  ORDER BY a.adminAccountID ASC";
    $listRes = mysqli_query($conn, $listQuery);
    if ($listRes) {
        while ($row = mysqli_fetch_assoc($listRes)) {
            $adminList[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Account Management - PedalWorks Dynamics</title>
</head>
<body>
    <h1>Admin Account Management</h1>

    <div>
        <a href="../dashboard.php"><button type="button">Back to Admin Dashboard</button></a>
        <a href="addAdminAccount.php"><button type="button">Add New Admin</button></a>
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

    <h2>Administrator Accounts Directory (Total: <?php echo count($adminList); ?>)</h2>

    <?php if (empty($adminList)): ?>
        <p>No administrator accounts found. Please use <a href="../../seed_admin.php">seed_admin.php</a> or click "Add New Admin" to create one.</p>
    <?php else: ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Admin ID</th>
                    <th>Username</th>
                    <th>Assigned Role</th>
                    <th>Linked Records</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adminList as $admin): ?>
                    <?php
                    $isCurrentLoggedIn = (isset($_SESSION['adminAccountID']) && (int)$_SESSION['adminAccountID'] === (int)$admin['adminAccountID']);
                    ?>
                    <tr>
                        <td><?php echo (int)$admin['adminAccountID']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($admin['username']); ?></strong>
                            <?php if ($isCurrentLoggedIn): ?>
                                <em>(You)</em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($admin['role']); ?></td>
                        <td>
                            Services: <?php echo (int)$admin['serviceCount']; ?> |
                            Expenses: <?php echo (int)$admin['expenseCount']; ?>
                        </td>
                        <td>
                            <a href="editAdminAccount.php?id=<?php echo (int)$admin['adminAccountID']; ?>">
                                <button type="button">Edit</button>
                            </a>
                            <?php if ($isCurrentLoggedIn): ?>
                                <button type="button" disabled title="Cannot delete your own active session">Delete</button>
                            <?php else: ?>
                                <a href="deleteAdminAccount.php?id=<?php echo (int)$admin['adminAccountID']; ?>" onclick="return confirm('Are you sure you want to delete administrator &quot;<?php echo htmlspecialchars($admin['username']); ?>&quot;?');">
                                    <button type="button">Delete</button>
                                </a>
                            <?php endif; ?>
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
