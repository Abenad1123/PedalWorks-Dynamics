<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');
requireAdminRole(['Accounting Administrator']);

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

// Fetch list of customer accounts
$customerList = [];
$activeCount = 0;
$inactiveCount = 0;
$disabledCount = 0;

if ($conn) {
    $listRes = mysqli_query($conn, "SELECT c.customerID, c.firstName, c.lastName, c.birthDate, c.email, c.address,
                                            ca.customerAccountID, ca.username, ca.status 
                                     FROM customer c 
                                     LEFT JOIN customerAccount ca ON c.customerID = ca.customerID 
                                     ORDER BY c.customerID DESC");
    if ($listRes) {
        while ($row = mysqli_fetch_assoc($listRes)) {
            $customerList[] = $row;
            $st = $row['status'] ?? 'Active';
            if ($st === 'Active') $activeCount++;
            elseif ($st === 'Inactive') $inactiveCount++;
            elseif ($st === 'Disabled') $disabledCount++;
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
    <title>Customer Account Management - PedalWorks Dynamics</title>
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
                <li class="breadcrumb-item active" aria-current="page">Customer Accounts</li>
            </ol>
        </nav>

        <!-- Header Ribbon -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom gap-3">
            <div>
                <h1 class="h3 mb-1 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-users text-info"></i>
                    Customer Account Management
                </h1>
                <p class="text-muted mb-0">Maintain customer identity records, monitor account security, and review registration details.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="../dashboard.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                    <i class="fa-solid fa-arrow-left"></i> Dashboard
                </a>
                <a href="addCustomerAccount.php" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                    <i class="fa-solid fa-user-plus"></i> Add New Customer
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

        <!-- Metric Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 border-start border-info border-4">
                    <div class="card-body py-3">
                        <span class="text-muted small text-uppercase fw-semibold">Total Customers</span>
                        <h4 class="fw-bold mb-0 mt-1"><?php echo count($customerList); ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 border-start border-success border-4">
                    <div class="card-body py-3">
                        <span class="text-muted small text-uppercase fw-semibold">Active Status</span>
                        <h4 class="fw-bold mb-0 mt-1 text-success"><?php echo $activeCount; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 border-start border-warning border-4">
                    <div class="card-body py-3">
                        <span class="text-muted small text-uppercase fw-semibold">Inactive Status</span>
                        <h4 class="fw-bold mb-0 mt-1 text-warning"><?php echo $inactiveCount; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm border-0 border-start border-danger border-4">
                    <div class="card-body py-3">
                        <span class="text-muted small text-uppercase fw-semibold">Disabled Status</span>
                        <h4 class="fw-bold mb-0 mt-1 text-danger"><?php echo $disabledCount; ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Directory Card -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold d-flex align-items-center gap-2">
                    <i class="fa-solid fa-address-book text-secondary"></i>
                    Customer Profiles Directory
                </h5>
                <span class="badge bg-secondary"><?php echo count($customerList); ?> profiles</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($customerList)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fa-solid fa-user-slash fa-2x mb-2"></i>
                        <p class="mb-0">No customer accounts registered yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light text-uppercase small">
                                <tr>
                                    <th class="ps-3" style="width: 70px;">ID</th>
                                    <th>Customer Name</th>
                                    <th>Username</th>
                                    <th>Email Address</th>
                                    <th>Birth Date</th>
                                    <th>Address</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3" style="width: 140px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customerList as $cust): ?>
                                    <?php
                                    $st = $cust['status'] ?? 'Active';
                                    $stBadge = 'bg-success';
                                    if ($st === 'Disabled') $stBadge = 'bg-danger';
                                    elseif ($st === 'Inactive') $stBadge = 'bg-warning text-dark';
                                    ?>
                                    <tr>
                                        <td class="ps-3 fw-bold text-muted">#<?php echo (int)$cust['customerID']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                    <i class="fa-solid fa-user"></i>
                                                </div>
                                                <span class="fw-bold text-dark">
                                                    <?php echo htmlspecialchars(trim(($cust['firstName'] ?? '') . ' ' . ($cust['lastName'] ?? ''))); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($cust['username'] ?? 'No Account'); ?></code>
                                        </td>
                                        <td>
                                            <a href="mailto:<?php echo htmlspecialchars($cust['email']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($cust['email']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="small text-muted"><?php echo htmlspecialchars($cust['birthDate'] ?? '—'); ?></span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($cust['address'] ?? '', 0, 45, '...')); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $stBadge; ?> px-2 py-1">
                                                <?php echo htmlspecialchars($st); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="btn-group btn-group-sm">
                                                <a href="editCustomerAccount.php?id=<?php echo (int)$cust['customerID']; ?>" class="btn btn-outline-primary" title="Edit Customer">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <a href="deleteCustomerAccount.php?id=<?php echo (int)$cust['customerID']; ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete or disable customer &quot;<?php echo htmlspecialchars($cust['firstName'] . ' ' . $cust['lastName']); ?>&quot;?');" title="Delete Customer">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
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
