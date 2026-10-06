<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/config.php';

$errors = [];
$successMessage = '';

if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errors[] = $_SESSION['error'];
    unset($_SESSION['error']);
}

$action = $_GET['action'] ?? 'list';
$editID = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// -------------------------------------------------------------------------
// 1. HANDLE DELETE
// -------------------------------------------------------------------------
if ($action === 'delete' && $editID > 0) {
    if (!$conn) {
        $_SESSION['error'] = 'Database is offline.';
        header('Location: customerAccount.php');
        exit;
    }

    // Find customerAccountID for this customer
    $accStmt = mysqli_prepare($conn, "SELECT customerAccountID FROM customerAccount WHERE customerID = ? LIMIT 1");
    mysqli_stmt_bind_param($accStmt, 'i', $editID);
    mysqli_stmt_execute($accStmt);
    $accRes = mysqli_stmt_get_result($accStmt);
    $accRow = mysqli_fetch_assoc($accRes);
    mysqli_stmt_close($accStmt);

    $customerAccountID = $accRow ? (int)$accRow['customerAccountID'] : 0;

    // Check if customer has dependent orders or service requests
    $hasOrders = false;
    $hasServices = false;

    if ($customerAccountID > 0) {
        $ordCheck = mysqli_query($conn, "SELECT COUNT(*) as c FROM productSale WHERE customerAccountID = $customerAccountID");
        if ($ordCheck && ($r = mysqli_fetch_assoc($ordCheck)) && $r['c'] > 0) {
            $hasOrders = true;
        }

        $srvCheck = mysqli_query($conn, "SELECT COUNT(*) as c FROM customerService WHERE customerAccountID = $customerAccountID");
        if ($srvCheck && ($r = mysqli_fetch_assoc($srvCheck)) && $r['c'] > 0) {
            $hasServices = true;
        }
    }

    if ($hasOrders || $hasServices) {
        $_SESSION['error'] = "Cannot delete this customer because they have existing order history or service requests. To restrict their access, please edit their account status to 'Disabled' instead.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            if ($customerAccountID > 0) {
                // Remove any temporary cart items first
                $delCart = mysqli_prepare($conn, "DELETE FROM customerCart WHERE customerAccountID = ?");
                mysqli_stmt_bind_param($delCart, 'i', $customerAccountID);
                mysqli_stmt_execute($delCart);
                mysqli_stmt_close($delCart);

                // Remove customerAccount
                $delAcc = mysqli_prepare($conn, "DELETE FROM customerAccount WHERE customerAccountID = ?");
                mysqli_stmt_bind_param($delAcc, 'i', $customerAccountID);
                mysqli_stmt_execute($delAcc);
                mysqli_stmt_close($delAcc);
            }

            // Remove customer
            $delCust = mysqli_prepare($conn, "DELETE FROM customer WHERE customerID = ?");
            mysqli_stmt_bind_param($delCust, 'i', $editID);
            mysqli_stmt_execute($delCust);
            mysqli_stmt_close($delCust);

            mysqli_commit($conn);
            $_SESSION['success'] = "Customer #{$editID} and account deleted successfully.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Failed to delete customer: " . $e->getMessage();
        }
    }

    header('Location: customerAccount.php');
    exit;
}

// -------------------------------------------------------------------------
// 2. HANDLE CREATE (POST)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_customer'])) {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName  = trim($_POST['lastName'] ?? '');
    $birthDate = trim($_POST['birthDate'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $status    = $_POST['status'] ?? 'Active';

    if ($firstName === '') $errors[] = 'First name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($address === '') $errors[] = 'Address is required.';
    if ($username === '' || strlen($username) < 3) $errors[] = 'Username must be at least 3 characters.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if (!in_array($status, ['Active', 'Inactive', 'Disabled'])) $status = 'Active';

    if (empty($errors) && $conn) {
        // Check email uniqueness
        $chkE = mysqli_prepare($conn, "SELECT customerID FROM customer WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($chkE, 's', $email);
        mysqli_stmt_execute($chkE);
        mysqli_stmt_store_result($chkE);
        if (mysqli_stmt_num_rows($chkE) > 0) $errors[] = 'Email is already in use by another customer.';
        mysqli_stmt_close($chkE);

        // Check username uniqueness
        $chkU = mysqli_prepare($conn, "SELECT customerAccountID FROM customerAccount WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($chkU, 's', $username);
        mysqli_stmt_execute($chkU);
        mysqli_stmt_store_result($chkU);
        if (mysqli_stmt_num_rows($chkU) > 0) $errors[] = 'Username is already taken.';
        mysqli_stmt_close($chkU);
    }

    if (empty($errors) && $conn) {
        mysqli_begin_transaction($conn);
        try {
            $dbLastName = ($lastName !== '') ? $lastName : null;
            $dbBirthDate = ($birthDate !== '') ? $birthDate : null;

            $custSql = "INSERT INTO customer (firstName, lastName, birthDate, email, address) VALUES (?, ?, ?, ?, ?)";
            $cStmt = mysqli_prepare($conn, $custSql);
            mysqli_stmt_bind_param($cStmt, 'sssss', $firstName, $dbLastName, $dbBirthDate, $email, $address);
            mysqli_stmt_execute($cStmt);
            $newCustomerID = mysqli_insert_id($conn);
            mysqli_stmt_close($cStmt);

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $accSql = "INSERT INTO customerAccount (customerID, username, password, status) VALUES (?, ?, ?, ?)";
            $aStmt = mysqli_prepare($conn, $accSql);
            mysqli_stmt_bind_param($aStmt, 'isss', $newCustomerID, $username, $hash, $status);
            mysqli_stmt_execute($aStmt);
            mysqli_stmt_close($aStmt);

            mysqli_commit($conn);
            $_SESSION['success'] = "Customer '{$username}' created successfully.";
            header('Location: customerAccount.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "Error creating customer: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------------------
// 3. HANDLE UPDATE (POST)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    $custID    = (int)($_POST['customerID'] ?? 0);
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName  = trim($_POST['lastName'] ?? '');
    $birthDate = trim($_POST['birthDate'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $newPass   = $_POST['password'] ?? '';
    $status    = $_POST['status'] ?? 'Active';

    if ($custID <= 0) $errors[] = 'Invalid customer ID.';
    if ($firstName === '') $errors[] = 'First name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($address === '') $errors[] = 'Address is required.';
    if ($username === '' || strlen($username) < 3) $errors[] = 'Username must be at least 3 characters.';
    if (!in_array($status, ['Active', 'Inactive', 'Disabled'])) $status = 'Active';

    if (empty($errors) && $conn) {
        // Check email uniqueness excluding this customer
        $chkE = mysqli_prepare($conn, "SELECT customerID FROM customer WHERE email = ? AND customerID != ? LIMIT 1");
        mysqli_stmt_bind_param($chkE, 'si', $email, $custID);
        mysqli_stmt_execute($chkE);
        mysqli_stmt_store_result($chkE);
        if (mysqli_stmt_num_rows($chkE) > 0) $errors[] = 'Email is already used by another customer.';
        mysqli_stmt_close($chkE);

        // Check username uniqueness excluding this customer's account
        $chkU = mysqli_prepare($conn, "SELECT customerAccountID FROM customerAccount WHERE username = ? AND customerID != ? LIMIT 1");
        mysqli_stmt_bind_param($chkU, 'si', $username, $custID);
        mysqli_stmt_execute($chkU);
        mysqli_stmt_store_result($chkU);
        if (mysqli_stmt_num_rows($chkU) > 0) $errors[] = 'Username is already taken by another account.';
        mysqli_stmt_close($chkU);
    }

    if (empty($errors) && $conn) {
        mysqli_begin_transaction($conn);
        try {
            $dbLastName = ($lastName !== '') ? $lastName : null;
            $dbBirthDate = ($birthDate !== '') ? $birthDate : null;

            // 1. Update customer profile
            $updC = mysqli_prepare($conn, "UPDATE customer SET firstName = ?, lastName = ?, birthDate = ?, email = ?, address = ? WHERE customerID = ?");
            mysqli_stmt_bind_param($updC, 'sssssi', $firstName, $dbLastName, $dbBirthDate, $email, $address, $custID);
            mysqli_stmt_execute($updC);
            mysqli_stmt_close($updC);

            // 2. Update customerAccount (with or without password change)
            if ($newPass !== '') {
                if (strlen($newPass) < 6) {
                    throw new Exception('New password must be at least 6 characters.');
                }
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $updA = mysqli_prepare($conn, "UPDATE customerAccount SET username = ?, password = ?, status = ? WHERE customerID = ?");
                mysqli_stmt_bind_param($updA, 'sssi', $username, $newHash, $status, $custID);
            } else {
                $updA = mysqli_prepare($conn, "UPDATE customerAccount SET username = ?, status = ? WHERE customerID = ?");
                mysqli_stmt_bind_param($updA, 'ssi', $username, $status, $custID);
            }
            mysqli_stmt_execute($updA);
            mysqli_stmt_close($updA);

            mysqli_commit($conn);
            $_SESSION['success'] = "Customer '{$username}' updated successfully.";
            header('Location: customerAccount.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "Error updating customer: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------------------
// 4. FETCH DATA FOR EDIT MODE
// -------------------------------------------------------------------------
$editData = null;
if ($action === 'edit' && $editID > 0 && $conn) {
    $q = mysqli_prepare($conn, "SELECT c.*, ca.customerAccountID, ca.username, ca.status 
                                FROM customer c 
                                LEFT JOIN customerAccount ca ON c.customerID = ca.customerID 
                                WHERE c.customerID = ? LIMIT 1");
    mysqli_stmt_bind_param($q, 'i', $editID);
    mysqli_stmt_execute($q);
    $res = mysqli_stmt_get_result($q);
    $editData = mysqli_fetch_assoc($res);
    mysqli_stmt_close($q);

    if (!$editData) {
        $errors[] = "Customer #{$editID} not found.";
        $action = 'list';
    }
}

// -------------------------------------------------------------------------
// 5. FETCH ALL CUSTOMERS FOR LIST
// -------------------------------------------------------------------------
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
        <a href="/project/PedalWorks-Dynamics/admin/dashboard.php"><button type="button">Back to Admin Dashboard</button></a>
        <?php if ($action !== 'add'): ?>
            <a href="?action=add"><button type="button">Add New Customer</button></a>
        <?php endif; ?>
        <?php if ($action !== 'list'): ?>
            <a href="customerAccount.php"><button type="button">View All Customers</button></a>
        <?php endif; ?>
    </div>
    <br>

    <?php if ($successMessage !== ''): ?>
        <div style="color: green;">
            <p><strong>Success:</strong> <?php echo htmlspecialchars($successMessage); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div style="color: red;">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- ===================================================================
         VIEW 1: ADD NEW CUSTOMER
         =================================================================== -->
    <?php if ($action === 'add'): ?>
        <h2>Add New Customer</h2>
        <form action="customerAccount.php?action=add" method="post">
            <div>
                <label for="firstName">First Name (required):</label><br>
                <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($_POST['firstName'] ?? ''); ?>" required>
            </div>
            <br>
            <div>
                <label for="lastName">Last Name (optional):</label><br>
                <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($_POST['lastName'] ?? ''); ?>">
            </div>
            <br>
            <div>
                <label for="birthDate">Birth Date (optional):</label><br>
                <input type="date" id="birthDate" name="birthDate" value="<?php echo htmlspecialchars($_POST['birthDate'] ?? ''); ?>">
            </div>
            <br>
            <div>
                <label for="email">Email Address (required):</label><br>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            <br>
            <div>
                <label for="address">Address (required):</label><br>
                <textarea id="address" name="address" rows="3" cols="40" required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
            </div>
            <br>
            <div>
                <label for="username">Username (required):</label><br>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>
            <br>
            <div>
                <label for="password">Password (min 6 characters):</label><br>
                <input type="password" id="password" name="password" required>
            </div>
            <br>
            <div>
                <label for="status">Account Status:</label><br>
                <select id="status" name="status">
                    <option value="Active" <?php echo (($_POST['status'] ?? '') === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo (($_POST['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Disabled" <?php echo (($_POST['status'] ?? '') === 'Disabled') ? 'selected' : ''; ?>>Disabled</option>
                </select>
            </div>
            <br>
            <div>
                <button type="submit" name="create_customer">Create Customer Account</button>
                <a href="customerAccount.php"><button type="button">Cancel</button></a>
            </div>
        </form>

    <!-- ===================================================================
         VIEW 2: EDIT CUSTOMER
         =================================================================== -->
    <?php elseif ($action === 'edit' && $editData): ?>
        <h2>Edit Customer #<?php echo $editData['customerID']; ?> (<?php echo htmlspecialchars($editData['username'] ?? ''); ?>)</h2>
        <form action="customerAccount.php?action=edit&id=<?php echo $editData['customerID']; ?>" method="post">
            <input type="hidden" name="customerID" value="<?php echo $editData['customerID']; ?>">
            <div>
                <label for="firstName">First Name (required):</label><br>
                <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($_POST['firstName'] ?? $editData['firstName']); ?>" required>
            </div>
            <br>
            <div>
                <label for="lastName">Last Name (optional):</label><br>
                <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($_POST['lastName'] ?? $editData['lastName'] ?? ''); ?>">
            </div>
            <br>
            <div>
                <label for="birthDate">Birth Date (optional):</label><br>
                <input type="date" id="birthDate" name="birthDate" value="<?php echo htmlspecialchars($_POST['birthDate'] ?? $editData['birthDate'] ?? ''); ?>">
            </div>
            <br>
            <div>
                <label for="email">Email Address (required):</label><br>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $editData['email']); ?>" required>
            </div>
            <br>
            <div>
                <label for="address">Address (required):</label><br>
                <textarea id="address" name="address" rows="3" cols="40" required><?php echo htmlspecialchars($_POST['address'] ?? $editData['address']); ?></textarea>
            </div>
            <br>
            <div>
                <label for="username">Username (required):</label><br>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? $editData['username'] ?? ''); ?>" required>
            </div>
            <br>
            <div>
                <label for="password">New Password (leave blank to keep current password):</label><br>
                <input type="password" id="password" name="password" placeholder="Leave empty to keep unchanged">
            </div>
            <br>
            <div>
                <label for="status">Account Status:</label><br>
                <?php $currentStatus = $_POST['status'] ?? $editData['status'] ?? 'Active'; ?>
                <select id="status" name="status">
                    <option value="Active" <?php echo ($currentStatus === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo ($currentStatus === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Disabled" <?php echo ($currentStatus === 'Disabled') ? 'selected' : ''; ?>>Disabled</option>
                </select>
            </div>
            <br>
            <div>
                <button type="submit" name="update_customer">Save Changes</button>
                <a href="customerAccount.php"><button type="button">Cancel</button></a>
            </div>
        </form>

    <!-- ===================================================================
         VIEW 3: CUSTOMER LIST (DEFAULT)
         =================================================================== -->
    <?php else: ?>
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
                                <a href="customerAccount.php?action=edit&id=<?php echo (int)$cust['customerID']; ?>">
                                    <button type="button">Edit</button>
                                </a>
                                <a href="customerAccount.php?action=delete&id=<?php echo (int)$cust['customerID']; ?>" onclick="return confirm('Are you sure you want to delete customer #<?php echo (int)$cust['customerID']; ?> (<?php echo htmlspecialchars($cust['username'] ?? ''); ?>)?');">
                                    <button type="button">Delete</button>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <br><br>
    <div>
        <a href="/project/PedalWorks-Dynamics/admin/dashboard.php"><button type="button">Back to Admin Dashboard</button></a>
        <a href="/project/PedalWorks-Dynamics/index.php"><button type="button">Go to Homepage</button></a>
    </div>
</body>
</html>
