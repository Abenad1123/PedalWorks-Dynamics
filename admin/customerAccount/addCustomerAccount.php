<?php
session_start();
include('../../includes/config.php');

$errors = [];

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
        $chkE = mysqli_prepare($conn, "SELECT customerID FROM customer WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($chkE, 's', $email);
        mysqli_stmt_execute($chkE);
        mysqli_stmt_store_result($chkE);
        if (mysqli_stmt_num_rows($chkE) > 0) $errors[] = 'Email is already in use by another customer.';
        mysqli_stmt_close($chkE);

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
            $_SESSION['success'] = "Customer account for '{$username}' created successfully.";
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "Failed to create customer: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Customer Account - PedalWorks Dynamics</title>
</head>
<body>
    <h1>Add New Customer Account</h1>

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

    <form action="addCustomerAccount.php" method="post">
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
            <a href="index.php"><button type="button">Cancel</button></a>
        </div>
    </form>
</body>
</html>
