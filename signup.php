<?php
session_start();
include('./includes/config.php');

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
        // Check email uniqueness
        $emailCheck = mysqli_prepare($conn, "SELECT customerID FROM customer WHERE email = ? LIMIT 1");
        if ($emailCheck) {
            mysqli_stmt_bind_param($emailCheck, 's', $email);
            mysqli_stmt_execute($emailCheck);
            mysqli_stmt_store_result($emailCheck);
            if (mysqli_stmt_num_rows($emailCheck) > 0) {
                $errors[] = 'This email address is already registered.';
            }
            mysqli_stmt_close($emailCheck);
        }

        // Check username uniqueness
        $userCheck = mysqli_prepare($conn, "SELECT customerAccountID FROM customerAccount WHERE username = ? LIMIT 1");
        if ($userCheck) {
            mysqli_stmt_bind_param($userCheck, 's', $username);
            mysqli_stmt_execute($userCheck);
            mysqli_stmt_store_result($userCheck);
            if (mysqli_stmt_num_rows($userCheck) > 0) {
                $errors[] = 'This username is already taken. Please choose another.';
            }
            mysqli_stmt_close($userCheck);
        }
    }

    // Insert customer & account inside a transaction
    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $dbLastName = ($lastName !== '') ? $lastName : null;
            $dbBirthDate = ($birthDate !== '') ? $birthDate : null;

            $insertCustomer = mysqli_prepare($conn, "INSERT INTO customer (firstName, lastName, birthDate, email, address) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($insertCustomer, 'sssss', $firstName, $dbLastName, $dbBirthDate, $email, $address);
            mysqli_stmt_execute($insertCustomer);
            $newCustomerID = mysqli_insert_id($conn);
            mysqli_stmt_close($insertCustomer);

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $status = 'Active';

            $insertAccount = mysqli_prepare($conn, "INSERT INTO customerAccount (customerID, username, password, status) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($insertAccount, 'isss', $newCustomerID, $username, $hashedPassword, $status);
            mysqli_stmt_execute($insertAccount);
            mysqli_stmt_close($insertAccount);

            mysqli_commit($conn);

            $_SESSION['success'] = "Account successfully created for {$username}! You can now log in.";
            header('Location: login.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Failed to create account: ' . $e->getMessage();
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
    <p>Already have an account? <a href="login.php"><button type="button">Go to Login</button></a></p>
    <p><a href="index.php"><button type="button">Back to Homepage</button></a></p>
</body>
</html>
