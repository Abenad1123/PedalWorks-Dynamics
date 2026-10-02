<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';

$errors = [];
$firstName = '';
$lastName = '';
$birthDate = '';
$email = '';
$address = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup_customer'])) {
    $firstName       = trim($_POST['firstName'] ?? '');
    $lastName        = trim($_POST['lastName'] ?? '');
    $birthDate       = trim($_POST['birthDate'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $username        = trim($_POST['username'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Validation
    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if ($address === '') {
        $errors[] = 'Address is required.';
    }
    if ($username === '') {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$conn) {
        $errors[] = 'Database is offline. Please check that MySQL is running in XAMPP.';
    }

    if (empty($errors)) {
        // Check if email already exists
        $emailStmt = mysqli_prepare($conn, "SELECT customerID FROM customer WHERE email = ? LIMIT 1");
        if ($emailStmt) {
            mysqli_stmt_bind_param($emailStmt, 's', $email);
            mysqli_stmt_execute($emailStmt);
            mysqli_stmt_store_result($emailStmt);
            if (mysqli_stmt_num_rows($emailStmt) > 0) {
                $errors[] = 'Email is already registered. Please log in or use another email.';
            }
            mysqli_stmt_close($emailStmt);
        }

        // Check if username already exists
        $userStmt = mysqli_prepare($conn, "SELECT customerAccountID FROM customerAccount WHERE username = ? LIMIT 1");
        if ($userStmt) {
            mysqli_stmt_bind_param($userStmt, 's', $username);
            mysqli_stmt_execute($userStmt);
            mysqli_stmt_store_result($userStmt);
            if (mysqli_stmt_num_rows($userStmt) > 0) {
                $errors[] = 'Username is already taken. Please choose another username.';
            }
            mysqli_stmt_close($userStmt);
        }
    }

    // Insert customer & customerAccount atomically
    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $dbLastName = ($lastName !== '') ? $lastName : null;
            $dbBirthDate = ($birthDate !== '') ? $birthDate : null;

            // 1. Insert customer
            $custSql = "INSERT INTO customer (firstName, lastName, birthDate, email, address) VALUES (?, ?, ?, ?, ?)";
            $stmtCust = mysqli_prepare($conn, $custSql);
            mysqli_stmt_bind_param($stmtCust, "sssss", $firstName, $dbLastName, $dbBirthDate, $email, $address);
            if (!mysqli_stmt_execute($stmtCust)) {
                throw new Exception(mysqli_stmt_error($stmtCust));
            }
            $customerID = mysqli_insert_id($conn);
            mysqli_stmt_close($stmtCust);

            // 2. Insert customerAccount
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $status = 'Active';
            $accSql = "INSERT INTO customerAccount (customerID, username, password, status) VALUES (?, ?, ?, ?)";
            $stmtAcc = mysqli_prepare($conn, $accSql);
            mysqli_stmt_bind_param($stmtAcc, "isss", $customerID, $username, $hashedPassword, $status);
            if (!mysqli_stmt_execute($stmtAcc)) {
                throw new Exception(mysqli_stmt_error($stmtAcc));
            }
            $customerAccountID = mysqli_insert_id($conn);
            mysqli_stmt_close($stmtAcc);

            mysqli_commit($conn);

            // Set login session variables
            $_SESSION['customerAccountID'] = $customerAccountID;
            $_SESSION['customerID'] = $customerID;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = 'customer';
            $_SESSION['success'] = 'Account created successfully! Welcome to PedalWorks Dynamics.';

            header('Location: /project/PedalWorks-Dynamics/index.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Sign Up - PedalWorks Dynamics</title>
</head>
<body>
    <h1>Customer Sign Up</h1>

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
            <label for="firstName">First Name (required):</label>
            <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($firstName); ?>" required>
        </div>
        <br>
        <div>
            <label for="lastName">Last Name (optional):</label>
            <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($lastName); ?>">
        </div>
        <br>
        <div>
            <label for="birthDate">Birth Date (optional):</label>
            <input type="date" id="birthDate" name="birthDate" value="<?php echo htmlspecialchars($birthDate); ?>">
        </div>
        <br>
        <div>
            <label for="email">Email (required):</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
        </div>
        <br>
        <div>
            <label for="address">Address (required):</label>
            <textarea id="address" name="address" required><?php echo htmlspecialchars($address); ?></textarea>
        </div>
        <br>
        <div>
            <label for="username">Username (required):</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
        </div>
        <br>
        <div>
            <label for="password">Password (min 6 characters):</label>
            <input type="password" id="password" name="password" required>
        </div>
        <br>
        <div>
            <label for="confirmPassword">Confirm Password:</label>
            <input type="password" id="confirmPassword" name="confirmPassword" required>
        </div>
        <br>
        <div>
            <button type="submit" name="signup_customer">Submit Sign Up</button>
        </div>
    </form>
    <br>
    <p>Already have an account? <a href="/project/PedalWorks-Dynamics/login.php"><button type="button">Go to Login</button></a></p>
    <p><a href="/project/PedalWorks-Dynamics/index.php"><button type="button">Back to Homepage</button></a></p>
</body>
</html>
