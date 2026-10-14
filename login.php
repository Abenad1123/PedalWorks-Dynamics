<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('./includes/config.php');

$errors = [];
$fieldErrors = [];
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? $_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');

    // MP5: Rigorous server-side PHP validation without HTML5 validation
    if ($identifier === '') {
        $fieldErrors['identifier'] = 'Email address or username is required.';
        $errors[] = 'Email address or username is required.';
    } elseif (strpos($identifier, '@') !== false && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['identifier'] = 'Please provide a valid email format (e.g. name@example.com).';
        $errors[] = 'Please provide a valid email format.';
    }

    if ($password === '') {
        $fieldErrors['password'] = 'Password is required.';
        $errors[] = 'Password is required.';
    }

    if (empty($fieldErrors)) {
        if (!$conn) {
            $errors[] = 'Database is offline. Please ensure MySQL is running in XAMPP.';
        } else {
            // 1. Query Admin account (allows username or future email)
            $adminSql = "SELECT adminAccountID, username, password, role FROM adminAccount WHERE username = ? LIMIT 1";
            $adminStmt = mysqli_prepare($conn, $adminSql);
            mysqli_stmt_bind_param($adminStmt, 's', $identifier);
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

            // 2. Query Customer account by Email OR Username (MP3 Requirement: use email for user login)
            $custSql = "SELECT ca.customerAccountID, ca.customerID, ca.username, ca.password, ca.status, 
                               c.firstName, c.lastName, c.email, c.photo 
                        FROM customerAccount ca 
                        JOIN customer c ON ca.customerID = c.customerID 
                        WHERE c.email = ? OR ca.username = ? LIMIT 1";
            $custStmt = mysqli_prepare($conn, $custSql);
            mysqli_stmt_bind_param($custStmt, 'ss', $identifier, $identifier);
            mysqli_stmt_execute($custStmt);
            $custRes = mysqli_stmt_get_result($custStmt);
            $cust = mysqli_fetch_assoc($custRes);
            mysqli_stmt_close($custStmt);

            if ($cust && password_verify($password, $cust['password'])) {
                if ($cust['status'] === 'Disabled') {
                    $errors[] = 'Your account has been deactivated. Please contact administration.';
                } else {
                    $_SESSION['customerAccountID'] = (int)$cust['customerAccountID'];
                    $_SESSION['customerID']        = (int)$cust['customerID'];
                    $_SESSION['user_id']           = (int)$cust['customerID'];
                    $_SESSION['username']          = $cust['username'];
                    $_SESSION['email']             = $cust['email'];
                    $_SESSION['firstName']         = $cust['firstName'];
                    $_SESSION['photo']             = $cust['photo'] ?? null;
                    $_SESSION['role']              = 'customer';
                    $_SESSION['account_type']      = 'customer';
                    $_SESSION['success']           = 'Welcome back, ' . ($cust['firstName'] ?: $cust['username']) . '!';

                    header('Location: index.php');
                    exit;
                }
            }

            if (empty($errors)) {
                $errors[] = 'Wrong email, username, or password.';
            }
        }
    }
}

include('./includes/header.php');
?>

<div class="container">
    <div class="pw-auth-wrapper">
        <div class="pw-auth-card" style="max-width: 460px;">
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

            <!-- MP5: Form with novalidate to disable browser HTML5 popups and enforce pure PHP server validation -->
            <form action="login.php" method="POST" autocomplete="on" novalidate>
                <div class="mb-3">
                    <label for="identifier" class="form-label text-white-50 small fw-semibold">
                        Email Address or Username
                    </label>
                    <input type="text" 
                           class="form-control pw-form-control <?php echo isset($fieldErrors['identifier']) ? 'is-invalid border-danger' : ''; ?>" 
                           id="identifier" 
                           name="identifier" 
                           value="<?php echo htmlspecialchars($identifier); ?>" 
                           placeholder="name@example.com or username" 
                           autofocus>
                    <?php if (isset($fieldErrors['identifier'])): ?>
                        <div class="text-danger small mt-1 ps-1 fw-medium">
                            <i class="fa-solid fa-circle-exclamation me-1"></i><?php echo htmlspecialchars($fieldErrors['identifier']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label text-white-50 small fw-semibold">
                        Password
                    </label>
                    <div class="input-group">
                        <input type="password" 
                               class="form-control pw-form-control border-end-0 <?php echo isset($fieldErrors['password']) ? 'is-invalid border-danger' : ''; ?>" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password">
                        <button type="button" class="btn pw-password-toggle" id="togglePasswordBtn" title="Toggle password visibility" tabindex="-1">
                            <i class="fa-regular fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                    <?php if (isset($fieldErrors['password'])): ?>
                        <div class="text-danger small mt-1 ps-1 fw-medium">
                            <i class="fa-solid fa-circle-exclamation me-1"></i><?php echo htmlspecialchars($fieldErrors['password']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <button type="submit" name="submit" class="pw-btn-trail py-2 fw-semibold">
                        Sign In
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
