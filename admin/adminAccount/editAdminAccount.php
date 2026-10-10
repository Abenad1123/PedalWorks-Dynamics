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
            if (isset($_SESSION['adminAccountID']) && (int)$_SESSION['adminAccountID'] === $editID) {
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
            }
            $_SESSION['success'] = 'Administrator "' . htmlspecialchars($username) . '" updated successfully.';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Failed to update administrator: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($upStmt);
    }
}

// Fetch existing details
$adminData = null;
if ($conn) {
    $selStmt = mysqli_prepare($conn, "SELECT adminAccountID, username, role FROM adminAccount WHERE adminAccountID = ? LIMIT 1");
    mysqli_stmt_bind_param($selStmt, 'i', $editID);
    mysqli_stmt_execute($selStmt);
    $res = mysqli_stmt_get_result($selStmt);
    $adminData = mysqli_fetch_assoc($res);
    mysqli_stmt_close($selStmt);
}

if (!$adminData) {
    $_SESSION['error'] = 'Administrator account not found.';
    header('Location: index.php');
    exit;
}
$activeModule = 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Administrator - PedalWorks Dynamics</title>
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
                <li class="breadcrumb-item active" aria-current="page">Edit <?php echo htmlspecialchars($adminData['username']); ?></li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-primary bg-opacity-10 text-primary p-2 rounded">
                                <i class="fa-solid fa-user-pen fs-5"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 fw-bold">Edit Administrator Account</h5>
                                <small class="text-muted">Modify staff credentials or access tier (ID: #<?php echo (int)$adminData['adminAccountID']; ?>)</small>
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

                        <form action="editAdminAccount.php" method="post">
                            <input type="hidden" name="adminAccountID" value="<?php echo (int)$adminData['adminAccountID']; ?>">

                            <div class="mb-3">
                                <label for="username" class="form-label fw-semibold">
                                    Username <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? $adminData['username']); ?>" required minlength="3">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">
                                    Change Password <small class="text-muted fw-normal">(Optional)</small>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Leave empty to keep existing password" minlength="6">
                                </div>
                                <div class="form-text">Only enter a new password if you wish to reset or update this account's password.</div>
                            </div>

                            <div class="mb-4">
                                <label for="role" class="form-label fw-semibold">
                                    Assigned Role <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-shield-halved"></i></span>
                                    <select class="form-select" id="role" name="role" required>
                                        <?php 
                                        $currentRole = $_POST['role'] ?? $adminData['role'];
                                        foreach ($validRoles as $r): 
                                        ?>
                                            <option value="<?php echo htmlspecialchars($r); ?>" <?php echo ($currentRole === $r) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($r); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Accounts
                                </a>
                                <button type="submit" name="update_admin" class="btn btn-primary px-4">
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
