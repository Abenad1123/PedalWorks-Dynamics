<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');
requireAdminRole(['Super Administrator']);

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
$activeModule = 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Administrator - PedalWorks Dynamics</title>
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
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Staff Accounts</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add New Admin</li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-primary bg-opacity-10 text-primary p-2 rounded">
                                <i class="fa-solid fa-user-plus fs-5"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 fw-bold">Create Administrator Account</h5>
                                <small class="text-muted">Register a new staff member with authorized role permissions.</small>
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

                        <form action="addAdminAccount.php" method="post">
                            <div class="mb-3">
                                <label for="username" class="form-label fw-semibold">
                                    Username <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="e.g. john_doe" required minlength="3">
                                </div>
                                <div class="form-text">Unique handle for logging into the admin portal (min 3 characters).</div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">
                                    Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Create a secure password" required minlength="6">
                                </div>
                                <div class="form-text">Must be at least 6 characters long.</div>
                            </div>

                            <div class="mb-4">
                                <label for="role" class="form-label fw-semibold">
                                    Assigned System Role <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-shield-halved"></i></span>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">-- Choose Access Role --</option>
                                        <?php foreach ($validRoles as $r): ?>
                                            <option value="<?php echo htmlspecialchars($r); ?>" <?php echo (($_POST['role'] ?? '') === $r) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($r); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-text">Controls administrative access levels across shop management modules.</div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fa-solid fa-xmark me-1"></i> Cancel
                                </a>
                                <button type="submit" name="create_admin" class="btn btn-primary px-4">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Administrator
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
