<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');
requireAdminRole(['Super Administrator']);

$successMessage = '';
$errorMessage = '';

if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Fetch list of admin accounts
$adminList = [];
$superCount = 0;
$invCount = 0;
$servCount = 0;
$accCount = 0;

if ($conn) {
    $listQuery = "SELECT a.adminAccountID, a.username, a.role,
                         (SELECT COUNT(*) FROM customerService cs WHERE cs.adminAccountID = a.adminAccountID) AS serviceCount,
                         (SELECT COUNT(*) FROM expense ex WHERE ex.recordedBy = a.adminAccountID) AS expenseCount
                  FROM adminAccount a
                  ORDER BY a.adminAccountID ASC";
    $listRes = mysqli_query($conn, $listQuery);
    if ($listRes) {
        while ($row = mysqli_fetch_assoc($listRes)) {
            $adminList[] = $row;
            if ($row['role'] === 'Super Administrator') $superCount++;
            elseif ($row['role'] === 'Inventory Manager') $invCount++;
            elseif ($row['role'] === 'Service & Repair Manager') $servCount++;
            elseif ($row['role'] === 'Accounting Administrator') $accCount++;
        }
    }
}
$activeModule = 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Account Management - PedalWorks Dynamics</title>
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
                <li class="breadcrumb-item active" aria-current="page">Staff Accounts</li>
            </ol>
        </nav>

        <!-- Header Ribbon -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom gap-3">
            <div>
                <h1 class="h3 mb-1 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-user-shield text-warning"></i>
                    Admin Account Management
                </h1>
                <p class="text-muted mb-0">Control administrative accounts, assign operational roles, and safeguard system permissions.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="../dashboard.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                    <i class="fa-solid fa-arrow-left"></i> Dashboard
                </a>
                <a href="addAdminAccount.php" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                    <i class="fa-solid fa-user-plus"></i> Add New Admin
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
                <i class="fa-solid fa-circle-check fs-5"></i>
                <div><?php echo htmlspecialchars($successMessage); ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
                <i class="fa-solid fa-triangle-exclamation fs-5"></i>
                <div><?php echo htmlspecialchars($errorMessage); ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 border-start border-primary border-4">
                    <div class="card-body py-3">
                        <span class="text-muted small text-uppercase fw-semibold">Total Staff</span>
                        <h4 class="fw-bold mb-0 mt-1"><?php echo count($adminList); ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 border-start border-danger border-4">
                    <div class="card-body py-3">
                        <span class="text-muted small text-uppercase fw-semibold">Super Admins</span>
                        <h4 class="fw-bold mb-0 mt-1 text-danger"><?php echo $superCount; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 border-start border-info border-4">
                    <div class="card-body py-3">
                        <span class="text-muted small text-uppercase fw-semibold">Inventory Mgrs</span>
                        <h4 class="fw-bold mb-0 mt-1 text-info"><?php echo $invCount; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 border-start border-success border-4">
                    <div class="card-body py-3">
                        <span class="text-muted small text-uppercase fw-semibold">Service Mgrs</span>
                        <h4 class="fw-bold mb-0 mt-1 text-success"><?php echo $servCount; ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admins Table Card -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold d-flex align-items-center gap-2">
                    <i class="fa-solid fa-users-gear text-secondary"></i>
                    Administrator Directory
                </h5>
                <span class="badge bg-secondary"><?php echo count($adminList); ?> staff registered</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($adminList)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fa-solid fa-user-xmark fa-2x mb-2"></i>
                        <p class="mb-0">No administrator accounts found in the database.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light text-uppercase small">
                                <tr>
                                    <th class="ps-3" style="width: 80px;">ID</th>
                                    <th>Username</th>
                                    <th>Assigned Role</th>
                                    <th>Linked Activity</th>
                                    <th class="text-end pe-3" style="width: 160px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminList as $admin): ?>
                                    <?php
                                    $isCurrentLoggedIn = (isset($_SESSION['adminAccountID']) && (int)$_SESSION['adminAccountID'] === (int)$admin['adminAccountID']);
                                    $roleBadge = 'bg-secondary';
                                    if ($admin['role'] === 'Super Administrator') $roleBadge = 'bg-danger';
                                    elseif ($admin['role'] === 'Inventory Manager') $roleBadge = 'bg-info text-dark';
                                    elseif ($admin['role'] === 'Service & Repair Manager') $roleBadge = 'bg-success';
                                    elseif ($admin['role'] === 'Accounting Administrator') $roleBadge = 'bg-warning text-dark';
                                    ?>
                                    <tr>
                                        <td class="ps-3 fw-bold text-muted"><?php echo (int)$admin['adminAccountID']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                    <i class="fa-solid fa-user"></i>
                                                </div>
                                                <div>
                                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($admin['username']); ?></span>
                                                    <?php if ($isCurrentLoggedIn): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-1">You</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $roleBadge; ?> px-2 py-1">
                                                <?php echo htmlspecialchars($admin['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-muted small">
                                                <i class="fa-solid fa-wrench text-secondary me-1"></i><?php echo (int)$admin['serviceCount']; ?> Services
                                                &bull;
                                                <i class="fa-solid fa-receipt text-secondary ms-1 me-1"></i><?php echo (int)$admin['expenseCount']; ?> Expenses
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="btn-group btn-group-sm">
                                                <a href="editAdminAccount.php?id=<?php echo (int)$admin['adminAccountID']; ?>" class="btn btn-outline-primary" title="Edit Admin">
                                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                                </a>
                                                <?php if ($isCurrentLoggedIn): ?>
                                                    <button type="button" class="btn btn-outline-secondary" disabled title="Cannot delete active session">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <a href="deleteAdminAccount.php?id=<?php echo (int)$admin['adminAccountID']; ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete administrator &quot;<?php echo htmlspecialchars($admin['username']); ?>&quot;?');" title="Delete Admin">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
