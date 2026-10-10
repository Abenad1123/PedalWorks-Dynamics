<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');
requireAdminRole(['Accounting Administrator']);

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
        if (mysqli_stmt_num_rows($chkE) > 0) $errors[] = 'Email is already used by another customer.';
        mysqli_stmt_close($chkE);

        $chkU = mysqli_prepare($conn, "SELECT customerAccountID FROM customerAccount WHERE username = ? AND customerID != ? LIMIT 1");
        mysqli_stmt_bind_param($chkU, 'si', $username, $editID);
        mysqli_stmt_execute($chkU);
        mysqli_stmt_store_result($chkU);
        if (mysqli_stmt_num_rows($chkU) > 0) $errors[] = 'Username is already taken by another account.';
        mysqli_stmt_close($chkU);
    }

    if (empty($errors) && $conn) {
        mysqli_begin_transaction($conn);
        try {
            $dbLastName = ($lastName !== '') ? $lastName : null;
            $dbBirthDate = ($birthDate !== '') ? $birthDate : null;

            $cUp = mysqli_prepare($conn, "UPDATE customer SET firstName = ?, lastName = ?, birthDate = ?, email = ?, address = ? WHERE customerID = ?");
            mysqli_stmt_bind_param($cUp, 'sssssi', $firstName, $dbLastName, $dbBirthDate, $email, $address, $editID);
            mysqli_stmt_execute($cUp);
            mysqli_stmt_close($cUp);

            $accChk = mysqli_prepare($conn, "SELECT customerAccountID FROM customerAccount WHERE customerID = ? LIMIT 1");
            mysqli_stmt_bind_param($accChk, 'i', $editID);
            mysqli_stmt_execute($accChk);
            $accRes = mysqli_stmt_get_result($accChk);
            $existingAcc = mysqli_fetch_assoc($accRes);
            mysqli_stmt_close($accChk);

            if ($existingAcc) {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $aUp = mysqli_prepare($conn, "UPDATE customerAccount SET username = ?, password = ?, status = ? WHERE customerID = ?");
                    mysqli_stmt_bind_param($aUp, 'sssi', $username, $hash, $status, $editID);
                } else {
                    $aUp = mysqli_prepare($conn, "UPDATE customerAccount SET username = ?, status = ? WHERE customerID = ?");
                    mysqli_stmt_bind_param($aUp, 'ssi', $username, $status, $editID);
                }
                mysqli_stmt_execute($aUp);
                mysqli_stmt_close($aUp);
            } else {
                $hash = password_hash(($password !== '' ? $password : 'password123'), PASSWORD_DEFAULT);
                $inAcc = mysqli_prepare($conn, "INSERT INTO customerAccount (customerID, username, password, status) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($inAcc, 'isss', $editID, $username, $hash, $status);
                mysqli_stmt_execute($inAcc);
                mysqli_stmt_close($inAcc);
            }

            mysqli_commit($conn);
            $_SESSION['success'] = "Customer \"{$firstName} {$lastName}\" updated successfully.";
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Failed to update customer: ' . $e->getMessage();
        }
    }
}

// Load current record
$customer = null;
if ($conn) {
    $selStmt = mysqli_prepare($conn, "SELECT c.*, ca.customerAccountID, ca.username, ca.status 
                                      FROM customer c 
                                      LEFT JOIN customerAccount ca ON c.customerID = ca.customerID 
                                      WHERE c.customerID = ? LIMIT 1");
    mysqli_stmt_bind_param($selStmt, 'i', $editID);
    mysqli_stmt_execute($selStmt);
    $res = mysqli_stmt_get_result($selStmt);
    $customer = mysqli_fetch_assoc($res);
    mysqli_stmt_close($selStmt);
}

if (!$customer) {
    $_SESSION['error'] = 'Customer profile not found.';
    header('Location: index.php');
    exit;
}
$activeModule = 'customer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer - PedalWorks Dynamics</title>
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
                <li class="breadcrumb-item active" aria-current="page">Edit #<?php echo (int)$customer['customerID']; ?></li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-info bg-opacity-10 text-info p-2 rounded">
                                <i class="fa-solid fa-user-pen fs-5"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 fw-bold">Edit Customer Profile</h5>
                                <small class="text-muted">Modify contact information and account access status (ID: #<?php echo (int)$customer['customerID']; ?>)</small>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4">

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                                <strong class="d-block mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Please fix the errors:</strong>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?php echo htmlspecialchars($err); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form action="editCustomerAccount.php" method="post">
                            <input type="hidden" name="customerID" value="<?php echo (int)$customer['customerID']; ?>">

                            <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2">
                                <i class="fa-solid fa-id-card me-1 text-info"></i> Personal Information
                            </h6>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="firstName" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($_POST['firstName'] ?? $customer['firstName']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastName" class="form-label fw-semibold">Last Name</label>
                                    <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($_POST['lastName'] ?? $customer['lastName'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $customer['email']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="birthDate" class="form-label fw-semibold">Birth Date</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-calendar"></i></span>
                                        <input type="date" class="form-control" id="birthDate" name="birthDate" value="<?php echo htmlspecialchars($_POST['birthDate'] ?? $customer['birthDate'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="address" class="form-label fw-semibold">Shipping / Billing Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="address" name="address" rows="2" required><?php echo htmlspecialchars($_POST['address'] ?? $customer['address']); ?></textarea>
                            </div>

                            <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2">
                                <i class="fa-solid fa-key me-1 text-info"></i> Account Credentials &amp; Access
                            </h6>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="username" class="form-label fw-semibold">Login Username <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? $customer['username'] ?? ''); ?>" required minlength="3">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="password" class="form-label fw-semibold">Change Password <small class="text-muted fw-normal">(Optional)</small></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Leave empty to keep current password" minlength="6">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="status" class="form-label fw-semibold">Account Status</label>
                                <?php $currentStatus = $_POST['status'] ?? $customer['status'] ?? 'Active'; ?>
                                <select class="form-select" id="status" name="status">
                                    <option value="Active" <?php echo ($currentStatus === 'Active') ? 'selected' : ''; ?>>Active (Can sign in &amp; checkout)</option>
                                    <option value="Inactive" <?php echo ($currentStatus === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="Disabled" <?php echo ($currentStatus === 'Disabled') ? 'selected' : ''; ?>>Disabled (Access revoked)</option>
                                </select>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Customers
                                </a>
                                <button type="submit" name="update_customer" class="btn btn-primary px-4">
                                    <i class="fa-solid fa-check me-1"></i> Save Changes
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
