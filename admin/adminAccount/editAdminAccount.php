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

$editID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editID = isset($_POST['adminAccountID']) ? (int)$_POST['adminAccountID'] : 0;
}

if ($editID <= 0) {
    $_SESSION['error'] = 'Invalid administrator account ID.';
    header('Location: index.php');
    exit;
}

// Handle Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = trim($_POST['role'] ?? '');

    if ($username === '' || strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters long.';
    }
    if (!in_array($role, $validRoles, true)) {
        $errors[] = 'Please select a valid administrator role.';
    }
    if ($password !== '' && strlen($password) < 6) {
        $errors[] = 'New password must be at least 6 characters long.';
    }

    if (empty($errors) && $conn) {
        // Check username uniqueness excluding current admin
        $chkStmt = mysqli_prepare($conn, "SELECT adminAccountID FROM adminAccount WHERE username = ? AND adminAccountID != ? LIMIT 1");
        mysqli_stmt_bind_param($chkStmt, 'si', $username, $editID);
        mysqli_stmt_execute($chkStmt);
        mysqli_stmt_store_result($chkStmt);
        if (mysqli_stmt_num_rows($chkStmt) > 0) {
            $errors[] = 'The username "' . htmlspecialchars($username) . '" is already taken by another admin.';
        }
        mysqli_stmt_close($chkStmt);

        // Check if demoting the only Super Administrator
        $currStmt = mysqli_prepare($conn, "SELECT role FROM adminAccount WHERE adminAccountID = ? LIMIT 1");
        mysqli_stmt_bind_param($currStmt, 'i', $editID);
        mysqli_stmt_execute($currStmt);
        $currRes = mysqli_stmt_get_result($currStmt);
        $currRow = mysqli_fetch_assoc($currRes);
        mysqli_stmt_close($currStmt);

        if ($currRow && $currRow['role'] === 'Super Administrator' && $role !== 'Super Administrator') {
            $countSuperRes = mysqli_query($conn, "SELECT COUNT(*) as c FROM adminAccount WHERE role = 'Super Administrator'");
            $countSuper = ($countSuperRes && ($r = mysqli_fetch_assoc($countSuperRes))) ? (int)$r['c'] : 0;
            if ($countSuper <= 1) {
                $errors[] = 'Cannot change the role of the only Super Administrator. Assign another Super Administrator first.';
            }
        }
    }

    if (empty($errors) && $conn) {
        if ($password !== '') {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $upStmt = mysqli_prepare($conn, "UPDATE adminAccount SET username = ?, password = ?, role = ? WHERE adminAccountID = ?");
            mysqli_stmt_bind_param($upStmt, 'sssi', $username, $hashedPassword, $role, $editID);
        } else {
            $upStmt = mysqli_prepare($conn, "UPDATE adminAccount SET username = ?, role = ? WHERE adminAccountID = ?");
            mysqli_stmt_bind_param($upStmt, 'ssi', $username, $role, $editID);
        }

        if (mysqli_stmt_execute($upStmt)) {
            // Update current session if the admin edited their own account
            if (isset($_SESSION['adminAccountID']) && (int)$_SESSION['adminAccountID'] === $editID) {
                $_SESSION['username'] = $username;
                $_SESSION['role']     = $role;
            }
            $_SESSION['success'] = 'Administrator account #' . $editID . ' updated successfully.';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Failed to update administrator: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($upStmt);
    }
}

// Fetch existing data for form
$adminData = null;
if ($conn) {
    $stmt = mysqli_prepare($conn, "SELECT adminAccountID, username, role FROM adminAccount WHERE adminAccountID = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $editID);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $adminData = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

if (!$adminData) {
    $_SESSION['error'] = 'Administrator account with ID #' . $editID . ' was not found.';
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Administrator - PedalWorks Dynamics</title>
</head>
<body>
    <h1>Edit Administrator Account</h1>

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

    <h2>Editing: <?php echo htmlspecialchars($adminData['username']); ?> (ID #<?php echo (int)$adminData['adminAccountID']; ?>)</h2>

    <form action="editAdminAccount.php?id=<?php echo (int)$adminData['adminAccountID']; ?>" method="post">
        <input type="hidden" name="adminAccountID" value="<?php echo (int)$adminData['adminAccountID']; ?>">
        <div>
            <label for="username">Username (required):</label><br>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? $adminData['username']); ?>" required>
        </div>
        <br>
        <div>
            <label for="password">New Password (leave blank to keep current password):</label><br>
            <input type="password" id="password" name="password" placeholder="Leave blank to keep unchanged">
        </div>
        <br>
        <div>
            <label for="role">Role (required):</label><br>
            <?php $selectedRole = $_POST['role'] ?? $adminData['role']; ?>
            <select id="role" name="role" required>
                <?php foreach ($validRoles as $r): ?>
                    <option value="<?php echo htmlspecialchars($r); ?>" <?php echo ($selectedRole === $r) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <br>
        <div>
            <button type="submit" name="update_admin">Save Changes</button>
            <a href="index.php"><button type="button">Cancel</button></a>
        </div>
    </form>
</body>
</html>
