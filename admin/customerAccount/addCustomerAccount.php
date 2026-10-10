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
            $_SESSION['success'] = "Customer \"{$firstName} {$lastName}\" created successfully.";
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Database error creating customer: ' . $e->getMessage();
        }
    }
}
$activeModule = 'customer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Customer - PedalWorks Dynamics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light">

    <!-- Top Admin Navigation Bar -->
    <?php include('../../includes/admin_nav.php'); ?>

    <div class="container py-4">

        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Customer Accounts</a></li>
                <li class="breadcrumb-item active" aria-current="page">New Customer</li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-info bg-opacity-10 text-info p-2 rounded">
                                <i class="fa-solid fa-user-plus fs-5"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 fw-bold">Create Customer Account</h5>
                                <small class="text-muted">Register customer profile information and customer login access.</small>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4">

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                                <strong class="d-block mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Please correct the following errors:</strong>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?php echo htmlspecialchars($err); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form action="addCustomerAccount.php" method="post">

                            <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2">
                                <i class="fa-solid fa-id-card me-1 text-info"></i> Personal Information
                            </h6>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="firstName" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($_POST['firstName'] ?? ''); ?>" placeholder="e.g. Maria" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastName" class="form-label fw-semibold">Last Name</label>
                                    <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($_POST['lastName'] ?? ''); ?>" placeholder="e.g. Santos">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="maria@example.com" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="birthDate" class="form-label fw-semibold">Birth Date</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-calendar"></i></span>
                                        <input type="date" class="form-control" id="birthDate" name="birthDate" value="<?php echo htmlspecialchars($_POST['birthDate'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="address" class="form-label fw-semibold">Shipping / Billing Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="address" name="address" rows="2" placeholder="Full street address, barangay, city, postal code" required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            </div>

                            <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2">
                                <i class="fa-solid fa-key me-1 text-info"></i> Account Credentials &amp; Access
                            </h6>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="username" class="form-label fw-semibold">Login Username <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="maria_santos" required minlength="3">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="password" class="form-label fw-semibold">Initial Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Min 6 characters" required minlength="6">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="status" class="form-label fw-semibold">Account Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="Active" <?php echo (($_POST['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active (Can sign in &amp; checkout)</option>
                                    <option value="Inactive" <?php echo (($_POST['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="Disabled" <?php echo (($_POST['status'] ?? '') === 'Disabled') ? 'selected' : ''; ?>>Disabled (Access revoked)</option>
                                </select>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fa-solid fa-xmark me-1"></i> Cancel
                                </a>
                                <button type="submit" name="create_customer" class="btn btn-primary px-4">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Customer
                                </button>
                            </div>

                        </form>

                    </div>
                </div>

            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
