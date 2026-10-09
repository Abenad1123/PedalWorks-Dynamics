<?php
session_start();
include('../../includes/config.php');

$errors = [];
$validRoles = [
    'Super Administrator',
    'Inventory Manager',
    'Service & Repair Manager',
    'Accounting Administrator'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = trim($_POST['role'] ?? '');

    if ($username === '' || strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters long.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    if (!in_array($role, $validRoles, true)) {
        $errors[] = 'Please select a valid administrator role.';
    }

    if (empty($errors) && $conn) {
        $chkStmt = mysqli_prepare($conn, "SELECT adminAccountID FROM adminAccount WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($chkStmt, 's', $username);
        mysqli_stmt_execute($chkStmt);
        mysqli_stmt_store_result($chkStmt);
        if (mysqli_stmt_num_rows($chkStmt) > 0) {
            $errors[] = 'The username "' . htmlspecialchars($username) . '" is already taken.';
        }
        mysqli_stmt_close($chkStmt);
    }

    if (empty($errors) && $conn) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insStmt = mysqli_prepare($conn, "INSERT INTO adminAccount (username, password, role) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($insStmt, 'sss', $username, $hashedPassword, $role);
        if (mysqli_stmt_execute($insStmt)) {
            $_SESSION['success'] = 'Administrator account "' . htmlspecialchars($username) . '" created successfully.';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Failed to create administrator: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($insStmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Administrator - PedalWorks Dynamics</title>
</head>
<body>
    <h1>Add New Administrator Account</h1>

    <div>
        <a href="index.php"><button type="button">Back to Admin Accounts</button></a>
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

    <form action="addAdminAccount.php" method="post">
        <div>
            <label for="username">Username (required, min 3 characters):</label><br>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
        </div>
        <br>
        <div>
            <label for="password">Password (required, min 6 characters):</label><br>
            <input type="password" id="password" name="password" required>
        </div>
        <br>
        <div>
            <label for="role">Role (required):</label><br>
            <select id="role" name="role" required>
                <option value="">-- Select Role --</option>
                <?php foreach ($validRoles as $r): ?>
                    <option value="<?php echo htmlspecialchars($r); ?>" <?php echo (($_POST['role'] ?? '') === $r) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <br>
        <div>
            <button type="submit" name="create_admin">Create Administrator</button>
            <a href="index.php"><button type="button">Cancel</button></a>
        </div>
    </form>
</body>
</html>
