<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('./includes/config.php');

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $errors[] = 'Please enter both username and password.';
    } elseif (!$conn) {
        $errors[] = 'Database is offline. Please ensure MySQL is running in XAMPP.';
    } else {
        // Query Admin account
        $adminSql = "SELECT adminAccountID, username, password, role FROM adminAccount WHERE username = ? LIMIT 1";
        $adminStmt = mysqli_prepare($conn, $adminSql);
        mysqli_stmt_bind_param($adminStmt, 's', $username);
        mysqli_stmt_execute($adminStmt);
        $adminRes = mysqli_stmt_get_result($adminStmt);
        $admin = mysqli_fetch_assoc($adminRes);
        mysqli_stmt_close($adminStmt);

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['adminAccountID'] = (int)$admin['adminAccountID'];
            $_SESSION['user_id']        = (int)$admin['adminAccountID'];
            $_SESSION['username']       = $admin['username'];
            $_SESSION['role']           = $admin['role'];
            $_SESSION['account_type']   = 'admin';
            $_SESSION['success']        = 'Welcome, ' . $admin['username'] . '!';

            header('Location: admin/dashboard.php');
            exit;
        }

        // Query Customer account
        $custSql = "SELECT ca.customerAccountID, ca.customerID, ca.username, ca.password, ca.status, 
                           c.firstName, c.lastName, c.email 
                    FROM customerAccount ca 
                    JOIN customer c ON ca.customerID = c.customerID 
                    WHERE ca.username = ? LIMIT 1";
        $custStmt = mysqli_prepare($conn, $custSql);
        mysqli_stmt_bind_param($custStmt, 's', $username);
        mysqli_stmt_execute($custStmt);
        $custRes = mysqli_stmt_get_result($custStmt);
        $cust = mysqli_fetch_assoc($custRes);
        mysqli_stmt_close($custStmt);

        if ($cust && password_verify($password, $cust['password'])) {
            if ($cust['status'] === 'Disabled') {
                $errors[] = 'Your account has been disabled. Please contact shop administration.';
            } else {
                $_SESSION['customerAccountID'] = (int)$cust['customerAccountID'];
                $_SESSION['customerID']        = (int)$cust['customerID'];
                $_SESSION['user_id']           = (int)$cust['customerID'];
                $_SESSION['username']          = $cust['username'];
                $_SESSION['email']             = $cust['email'];
                $_SESSION['firstName']         = $cust['firstName'];
                $_SESSION['role']              = 'customer';
                $_SESSION['account_type']      = 'customer';
                $_SESSION['success']           = 'Welcome back, ' . ($cust['firstName'] ?: $cust['username']) . '!';

                header('Location: index.php');
                exit;
            }
        }

        if (empty($errors)) {
            $errors[] = 'Wrong username or password.';
        }
    }
}

include('./includes/header.php');
?>

<div class="container">
    <div class="pw-auth-wrapper">
        <div class="pw-auth-card" style="max-width: 480px;">
            <div class="pw-auth-header">
                <div class="pw-auth-icon">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                </div>
                <h2 class="pw-auth-title">Welcome Back</h2>
                <p class="pw-auth-subtitle">Sign in to your customer account or staff dashboard</p>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success pw-glass-alert alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="fa-solid fa-circle-check text-success fs-5"></i>
                    <div class="small"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger pw-glass-alert alert-dismissible fade show mb-4" role="alert">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fa-solid fa-circle-exclamation text-warning fs-5"></i>
                        <strong class="text-white small">Authentication Notice</strong>
                    </div>
                    <ul class="mb-0 ps-3 small text-white-50">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close btn-close-white ms-auto position-absolute top-0 end-0 mt-3 me-3" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="on">
                <div class="mb-3">
                    <label for="username" class="form-label text-white-50 small fw-semibold">
                        <i class="fa-solid fa-user me-1"></i> Username
                    </label>
                    <div class="input-group pw-input-group">
                        <span class="input-group-text pw-input-group-text">
                            <i class="fa-solid fa-user-circle"></i>
                        </span>
                        <input type="text" class="form-control pw-form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($username); ?>" 
                               placeholder="Enter your username" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label text-white-50 small fw-semibold">
                        <i class="fa-solid fa-lock me-1"></i> Password
                    </label>
                    <div class="input-group pw-input-group">
                        <span class="input-group-text pw-input-group-text">
                            <i class="fa-solid fa-key"></i>
                        </span>
                        <input type="password" class="form-control pw-form-control border-end-0" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <button type="button" class="btn pw-password-toggle" id="togglePasswordBtn" title="Toggle password visibility">
                            <i class="fa-solid fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <button type="submit" name="submit" class="pw-btn-trail py-3">
                        <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Sign In
                    </button>
                </div>
            </form>

            <div class="text-center pt-3 border-top border-secondary border-opacity-25">
                <p class="text-white-50 small mb-2">
                    Don't have an account? 
                    <a href="signup.php" class="text-white fw-bold text-decoration-none">
                        Create an Account <i class="fa-solid fa-arrow-right fa-xs ms-1"></i>
                    </a>
                </p>
                <p class="text-white-50 small mb-0">
                    <a href="index.php" class="text-muted text-decoration-none small">
                        <i class="fa-solid fa-house me-1"></i> Return to Homepage
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePasswordIcon');

    if (toggleBtn && passwordInput && toggleIcon) {
        toggleBtn.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });
    }
});
</script>

<?php include('./includes/footer.php'); ?>
