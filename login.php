<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Please enter both username and password.';
    } elseif (!$conn) {
        $errors[] = 'Database is offline. Please ensure MySQL is running in XAMPP.';
    } else {
        $isAdminAction = isset($_POST['login_admin']);
        $isCustomerAction = isset($_POST['login_customer']);

        // 1. If explicit admin login requested, or checking admin first
        if ($isAdminAction || (!$isCustomerAction)) {
            $stmt = mysqli_prepare($conn, "SELECT adminAccountID, username, password, role FROM adminAccount WHERE username = ? LIMIT 1");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $username);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $admin = mysqli_fetch_assoc($res);
                mysqli_stmt_close($stmt);

                if ($admin && password_verify($password, $admin['password'])) {
                    $_SESSION['adminAccountID'] = (int)$admin['adminAccountID'];
                    $_SESSION['username']       = $admin['username'];
                    $_SESSION['role']           = $admin['role'];
                    $_SESSION['account_type']   = 'admin';
                    $_SESSION['success']        = 'Logged in successfully as Admin (' . $admin['username'] . ').';

                    header('Location: /project/PedalWorks-Dynamics/admin/dashboard.php');
                    exit;
                } elseif ($isAdminAction) {
                    $errors[] = 'Invalid admin username or password.';
                }
            }
        }

        // 2. If explicit customer login requested, or admin check didn't match and not specifically admin action
        if (empty($errors) && ($isCustomerAction || (!$isAdminAction))) {
            $custSql = "SELECT ca.customerAccountID, ca.customerID, ca.username, ca.password, ca.status, 
                               c.firstName, c.lastName, c.email 
                        FROM customerAccount ca 
                        JOIN customer c ON ca.customerID = c.customerID 
                        WHERE ca.username = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $custSql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $username);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $cust = mysqli_fetch_assoc($res);
                mysqli_stmt_close($stmt);

                if ($cust) {
                    if ($cust['status'] === 'Disabled') {
                        $errors[] = 'Your customer account has been disabled. Please contact support.';
                    } elseif ($cust['status'] === 'Inactive') {
                        $errors[] = 'Your customer account is currently inactive.';
                    } elseif (password_verify($password, $cust['password'])) {
                        $_SESSION['customerAccountID'] = (int)$cust['customerAccountID'];
                        $_SESSION['customerID']        = (int)$cust['customerID'];
                        $_SESSION['user_id']           = (int)$cust['customerID'];
                        $_SESSION['username']          = $cust['username'];
                        $_SESSION['email']             = $cust['email'];
                        $_SESSION['firstName']         = $cust['firstName'];
                        $_SESSION['role']              = 'customer';
                        $_SESSION['account_type']      = 'customer';
                        $_SESSION['success']           = 'Welcome back, ' . ($cust['firstName'] ?: $cust['username']) . '!';

                        header('Location: /project/PedalWorks-Dynamics/index.php');
                        exit;
                    } else {
                        $errors[] = 'Invalid customer username or password.';
                    }
                } else {
                    $errors[] = 'Invalid username or password.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - PedalWorks Dynamics</title>
</head>
<body>
    <h1>Login</h1>

    <?php if (isset($_SESSION['success'])): ?>
        <div style="color: green;">
            <p><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div style="color: red;">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="" method="post">
        <div>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
        </div>
        <br>
        <div>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <br>
        <div>
            <button type="submit" name="login_customer">Submit as Customer</button>
            <button type="submit" name="login_admin">Submit as Admin</button>
        </div>
    </form>
    <br>
    <p>New customer? <a href="/project/PedalWorks-Dynamics/signup.php"><button type="button">Go to Sign Up</button></a></p>
    <p><a href="/project/PedalWorks-Dynamics/index.php"><button type="button">Back to Homepage</button></a></p>
</body>
</html>
