<?php
session_start();
include('../../includes/config.php');

$errors = [];
$editID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editID = isset($_POST['customerID']) ? (int)$_POST['customerID'] : 0;
}

if ($editID <= 0) {
    $_SESSION['error'] = 'Invalid customer ID.';
    header('Location: index.php');
    exit;
}

// Handle Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
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
    if ($password !== '' && strlen($password) < 6) $errors[] = 'New password must be at least 6 characters.';
    if (!in_array($status, ['Active', 'Inactive', 'Disabled'])) $status = 'Active';

    if (empty($errors) && $conn) {
        $chkE = mysqli_prepare($conn, "SELECT customerID FROM customer WHERE email = ? AND customerID != ? LIMIT 1");
        mysqli_stmt_bind_param($chkE, 'si', $email, $editID);
        mysqli_stmt_execute($chkE);
        mysqli_stmt_store_result($chkE);
        if (mysqli_stmt_num_rows($chkE) > 0) $errors[] = 'Email is already in use by another customer.';
        mysqli_stmt_close($chkE);

        $chkU = mysqli_prepare($conn, "SELECT customerAccountID FROM customerAccount WHERE username = ? AND customerID != ? LIMIT 1");
        mysqli_stmt_bind_param($chkU, 'si', $username, $editID);
        mysqli_stmt_execute($chkU);
        mysqli_stmt_store_result($chkU);
        if (mysqli_stmt_num_rows($chkU) > 0) $errors[] = 'Username is already taken by another user.';
        mysqli_stmt_close($chkU);
    }

    if (empty($errors) && $conn) {
        mysqli_begin_transaction($conn);
        try {
            $dbLastName = ($lastName !== '') ? $lastName : null;
            $dbBirthDate = ($birthDate !== '') ? $birthDate : null;

            $upCust = mysqli_prepare($conn, "UPDATE customer SET firstName = ?, lastName = ?, birthDate = ?, email = ?, address = ? WHERE customerID = ?");
            mysqli_stmt_bind_param($upCust, 'sssssi', $firstName, $dbLastName, $dbBirthDate, $email, $address, $editID);
            mysqli_stmt_execute($upCust);
            mysqli_stmt_close($upCust);

            $accExistsStmt = mysqli_prepare($conn, "SELECT customerAccountID FROM customerAccount WHERE customerID = ? LIMIT 1");
            mysqli_stmt_bind_param($accExistsStmt, 'i', $editID);
            mysqli_stmt_execute($accExistsStmt);
            $accExistsRes = mysqli_stmt_get_result($accExistsStmt);
            $hasAccount = mysqli_fetch_assoc($accExistsRes);
            mysqli_stmt_close($accExistsStmt);

            if ($hasAccount) {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $upAcc = mysqli_prepare($conn, "UPDATE customerAccount SET username = ?, password = ?, status = ? WHERE customerID = ?");
                    mysqli_stmt_bind_param($upAcc, 'sssi', $username, $hash, $status, $editID);
                } else {
                    $upAcc = mysqli_prepare($conn, "UPDATE customerAccount SET username = ?, status = ? WHERE customerID = ?");
                    mysqli_stmt_bind_param($upAcc, 'ssi', $username, $status, $editID);
                }
                mysqli_stmt_execute($upAcc);
                mysqli_stmt_close($upAcc);
            } else {
                $hash = password_hash(($password !== '' ? $password : 'password123'), PASSWORD_DEFAULT);
                $inAcc = mysqli_prepare($conn, "INSERT INTO customerAccount (customerID, username, password, status) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($inAcc, 'isss', $editID, $username, $hash, $status);
                mysqli_stmt_execute($inAcc);
                mysqli_stmt_close($inAcc);
            }

            mysqli_commit($conn);
            $_SESSION['success'] = "Customer #{$editID} ({$username}) updated successfully.";
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "Failed to update customer: " . $e->getMessage();
        }
    }
}

// Fetch existing customer data
$editData = null;
if ($conn) {
    $fetchStmt = mysqli_prepare($conn, "SELECT c.*, ca.customerAccountID, ca.username, ca.status 
                                         FROM customer c 
                                         LEFT JOIN customerAccount ca ON c.customerID = ca.customerID 
                                         WHERE c.customerID = ? LIMIT 1");
    mysqli_stmt_bind_param($fetchStmt, 'i', $editID);
    mysqli_stmt_execute($fetchStmt);
    $fetchRes = mysqli_stmt_get_result($fetchStmt);
    $editData = mysqli_fetch_assoc($fetchRes);
    mysqli_stmt_close($fetchStmt);
}

if (!$editData) {
    $_SESSION['error'] = 'Customer #' . $editID . ' not found.';
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Customer Account - PedalWorks Dynamics</title>
</head>
<body>
    <h1>Edit Customer Account</h1>

    <div>
        <a href="index.php"><button type="button">Back to Customer Accounts</button></a>
        <a href="../dashboard.php"><button type="button">Back to Admin Dashboard</button></a>
    </div>
    <br>

    <?php if (!empty($errors)): ?>
        <div style="color: red;">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h2>Editing Customer #<?php echo $editData['customerID']; ?> (<?php echo htmlspecialchars($editData['username'] ?? 'No Username'); ?>)</h2>

    <form action="editCustomerAccount.php?id=<?php echo $editData['customerID']; ?>" method="post">
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
            <a href="index.php"><button type="button">Cancel</button></a>
        </div>
    </form>
</body>
</html>
