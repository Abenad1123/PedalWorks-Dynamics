<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');

// Access Control: Accounting Administrator or Super Administrator (FR17)
requireAdminRole(['Accounting Administrator', 'Super Administrator']);

$activeModule = 'expenses';

$successMessage = '';
$errorMessage = '';
$formErrors = [];

// Handle Form Submissions: ADD, EDIT, DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $category    = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amountRaw   = trim($_POST['amount'] ?? '');
        $expenseDateRaw = trim($_POST['expenseDate'] ?? '');
        $recordedBy  = (int)($_SESSION['adminAccountID'] ?? 1);
        $expenseID   = isset($_POST['expenseID']) ? (int)$_POST['expenseID'] : 0;

        $validCategories = ['Water', 'Electricity', 'Employee Salary', 'Rent', 'Other'];

        // Pure PHP Validation (MP5 Compliance)
        if (!in_array($category, $validCategories, true)) {
            $formErrors['category'] = 'Please select a valid expense category.';
        }

        if ($category === 'Other' && $description === '') {
            $formErrors['description'] = 'A description is required when category is "Other".';
        }

        if ($amountRaw === '' || !is_numeric($amountRaw) || (float)$amountRaw <= 0) {
            $formErrors['amount'] = 'Expense amount must be a positive number greater than 0.';
        } else {
            $amount = round((float)$amountRaw, 2);
        }

        $expenseDate = null;
        if ($expenseDateRaw === '') {
            $formErrors['expenseDate'] = 'Please specify the date of this expense.';
        } else {
            $ts = strtotime($expenseDateRaw);
            if (!$ts) {
                $formErrors['expenseDate'] = 'Invalid date format provided.';
            } else {
                $expenseDate = date('Y-m-d H:i:s', $ts);
            }
        }

        if (empty($formErrors) && $conn) {
            if ($action === 'add') {
                $stmt = mysqli_prepare($conn, "INSERT INTO expense (category, description, amount, expenseDate, recordedBy) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ssdss', $category, $description, $amount, $expenseDate, $recordedBy);
                    if (mysqli_stmt_execute($stmt)) {
                        $successMessage = "Expense of ₱" . number_format($amount, 2) . " for '{$category}' recorded successfully.";
                    } else {
                        $errorMessage = "Failed to record expense: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                }
            } else { // edit
                $stmt = mysqli_prepare($conn, "UPDATE expense SET category = ?, description = ?, amount = ?, expenseDate = ? WHERE expenseID = ?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ssdsi', $category, $description, $amount, $expenseDate, $expenseID);
                    if (mysqli_stmt_execute($stmt)) {
                        $successMessage = "Expense record #{$expenseID} updated successfully.";
                    } else {
                        $errorMessage = "Failed to update expense: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    } elseif ($action === 'delete') {
        $expenseID = isset($_POST['expenseID']) ? (int)$_POST['expenseID'] : 0;
        if ($expenseID > 0 && $conn) {
            $stmt = mysqli_prepare($conn, "DELETE FROM expense WHERE expenseID = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $expenseID);
                if (mysqli_stmt_execute($stmt)) {
                    $successMessage = "Expense entry #{$expenseID} was deleted.";
                } else {
                    $errorMessage = "Failed to delete expense: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Filters
$filterCategory = isset($_GET['category']) ? trim($_GET['category']) : 'all';
$filterMonth    = isset($_GET['month']) ? trim($_GET['month']) : '';

// KPI Analytics for Expenses
$kpi = [
    'total'       => 0.0,
    'this_month'  => 0.0,
    'rent'        => 0.0,
    'utilities'   => 0.0,
    'salaries'    => 0.0
];

if ($conn) {
    $nowMonth = date('Y-m');
    $kRes = mysqli_query($conn, "SELECT 
        COALESCE(SUM(amount), 0) as totalAll,
        COALESCE(SUM(CASE WHEN DATE_FORMAT(expenseDate, '%Y-%m') = '{$nowMonth}' THEN amount ELSE 0 END), 0) as thisMonth,
        COALESCE(SUM(CASE WHEN category = 'Rent' THEN amount ELSE 0 END), 0) as rentTotal,
        COALESCE(SUM(CASE WHEN category IN ('Electricity', 'Water') THEN amount ELSE 0 END), 0) as utilTotal,
        COALESCE(SUM(CASE WHEN category = 'Employee Salary' THEN amount ELSE 0 END), 0) as salTotal
        FROM expense");
    if ($kRes && ($kr = mysqli_fetch_assoc($kRes))) {
        $kpi['total']      = (float)$kr['totalAll'];
        $kpi['this_month'] = (float)$kr['thisMonth'];
        $kpi['rent']       = (float)$kr['rentTotal'];
        $kpi['utilities']  = (float)$kr['utilTotal'];
        $kpi['salaries']   = (float)$kr['salTotal'];
    }
}

// Query Expenses List
$expenses = [];
if ($conn) {
    $where = [];
    $params = [];
    $types = "";

    if ($filterCategory !== 'all' && in_array($filterCategory, ['Water', 'Electricity', 'Employee Salary', 'Rent', 'Other'], true)) {
        $where[] = "e.category = ?";
        $params[] = $filterCategory;
        $types .= "s";
    }

    if ($filterMonth !== '') {
        $where[] = "DATE_FORMAT(e.expenseDate, '%Y-%m') = ?";
        $params[] = $filterMonth;
        $types .= "s";
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "SELECT e.*, adm.username as recordedByUsername 
            FROM expense e
            JOIN adminAccount adm ON e.recordedBy = adm.adminAccountID
            {$whereClause}
            ORDER BY e.expenseDate DESC, e.expenseID DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $expenses[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

function getCategoryBadge($cat) {
    switch ($cat) {
        case 'Rent':
            return '<span class="badge bg-danger"><i class="fa-solid fa-building me-1"></i>Rent</span>';
        case 'Electricity':
            return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-bolt me-1"></i>Electricity</span>';
        case 'Water':
            return '<span class="badge bg-info text-dark"><i class="fa-solid fa-droplet me-1"></i>Water</span>';
        case 'Employee Salary':
            return '<span class="badge bg-primary"><i class="fa-solid fa-id-badge me-1"></i>Salaries</span>';
        default:
            return '<span class="badge bg-secondary"><i class="fa-solid fa-receipt me-1"></i>Other</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Recording - PedalWorks Dynamics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .table-middle td { vertical-align: middle; }
    </style>
</head>
<body class="bg-light">

    <?php include('../../includes/admin_nav.php'); ?>

    <div class="container-fluid px-4 py-4">

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-2">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Expense Recording</li>
                    </ol>
                </nav>
                <h1 class="h3 mb-0 fw-bold">
                    <i class="fa-solid fa-money-bill-transfer text-danger me-2"></i>Shop Overhead Expenses
                </h1>
                <p class="text-muted small mb-0">Record and categorize shop overhead expenses (electricity, rent, water, salaries).</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-danger btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                    <i class="fa-solid fa-plus me-1"></i> Record New Expense
                </button>
                <a href="../reports/financial.php" class="btn btn-outline-success btn-sm px-3">
                    <i class="fa-solid fa-chart-pie me-1"></i> Income &amp; Expense Reports
                </a>
            </div>
        </div>

        <!-- Feedback Alerts -->
        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm border-start border-danger border-4 h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">This Month's Overhead</span>
                            <h3 class="fw-bold mb-0 text-danger">&#8369;<?php echo number_format($kpi['this_month'], 2); ?></h3>
                            <span class="text-muted small"><?php echo date('F Y'); ?></span>
                        </div>
                        <i class="fa-solid fa-calendar-day fa-2x text-danger opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm border-start border-secondary border-4 h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Rent Paid</span>
                            <h3 class="fw-bold mb-0 text-dark">&#8369;<?php echo number_format($kpi['rent'], 2); ?></h3>
                            <span class="text-muted small">Storefront facility lease</span>
                        </div>
                        <i class="fa-solid fa-building fa-2x text-secondary opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm border-start border-warning border-4 h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Utilities (Power &amp; Water)</span>
                            <h3 class="fw-bold mb-0 text-warning">&#8369;<?php echo number_format($kpi['utilities'], 2); ?></h3>
                            <span class="text-muted small">Meralco &amp; Manila Water</span>
                        </div>
                        <i class="fa-solid fa-bolt fa-2x text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm border-start border-primary border-4 h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Technician Payroll</span>
                            <h3 class="fw-bold mb-0 text-primary">&#8369;<?php echo number_format($kpi['salaries'], 2); ?></h3>
                            <span class="text-muted small">Employee salaries</span>
                        </div>
                        <i class="fa-solid fa-id-badge fa-2x text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Controls -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="index.php" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <label class="input-group-text"><i class="fa-solid fa-tag me-1"></i> Category</label>
                            <select name="category" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo ($filterCategory === 'all') ? 'selected' : ''; ?>>All Categories</option>
                                <option value="Rent" <?php echo ($filterCategory === 'Rent') ? 'selected' : ''; ?>>Rent</option>
                                <option value="Electricity" <?php echo ($filterCategory === 'Electricity') ? 'selected' : ''; ?>>Electricity</option>
                                <option value="Water" <?php echo ($filterCategory === 'Water') ? 'selected' : ''; ?>>Water</option>
                                <option value="Employee Salary" <?php echo ($filterCategory === 'Employee Salary') ? 'selected' : ''; ?>>Employee Salary</option>
                                <option value="Other" <?php echo ($filterCategory === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <label class="input-group-text"><i class="fa-solid fa-calendar me-1"></i> Month</label>
                            <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($filterMonth); ?>" onchange="this.form.submit()">
                        </div>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm px-3">Filter</button>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Expenses Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="fa-solid fa-receipt text-muted me-2"></i>Expense Records
                </h5>
                <span class="badge bg-light text-dark border">
                    Total: <?php echo count($expenses); ?> entries (&#8369;<?php echo number_format(array_sum(array_column($expenses, 'amount')), 2); ?>)
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-muted">
                            <th>ID</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Expense Date</th>
                            <th>Recorded By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-receipt fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                    <h6>No Expense Records Found</h6>
                                    <p class="small mb-0">Click "Record New Expense" above to add your shop overhead bills.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $exp): 
                                $editModalId = "editExpenseModal_" . $exp['expenseID'];
                                $delModalId  = "delExpenseModal_" . $exp['expenseID'];
                            ?>
                            <tr>
                                <td class="fw-bold">#EXP-<?php echo $exp['expenseID']; ?></td>
                                <td><?php echo getCategoryBadge($exp['category']); ?></td>
                                <td>
                                    <?php echo !empty($exp['description']) ? htmlspecialchars($exp['description']) : '<span class="text-muted fst-italic">Standard overhead</span>'; ?>
                                </td>
                                <td>
                                    <span class="fw-bold text-danger">&#8369;<?php echo number_format($exp['amount'], 2); ?></span>
                                </td>
                                <td>
                                    <i class="fa-regular fa-calendar text-muted me-1"></i>
                                    <?php echo date('M d, Y', strtotime($exp['expenseDate'])); ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="fa-solid fa-user-tie text-info me-1"></i><?php echo htmlspecialchars($exp['recordedByUsername']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#<?php echo $editModalId; ?>">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#<?php echo $delModalId; ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- Edit Expense Modal -->
                            <div class="modal fade" id="<?php echo $editModalId; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="index.php">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="expenseID" value="<?php echo (int)$exp['expenseID']; ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold">Edit Expense #EXP-<?php echo $exp['expenseID']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold small text-uppercase">Category</label>
                                                    <select name="category" class="form-select" required>
                                                        <option value="Water" <?php echo ($exp['category'] === 'Water') ? 'selected' : ''; ?>>Water</option>
                                                        <option value="Electricity" <?php echo ($exp['category'] === 'Electricity') ? 'selected' : ''; ?>>Electricity</option>
                                                        <option value="Employee Salary" <?php echo ($exp['category'] === 'Employee Salary') ? 'selected' : ''; ?>>Employee Salary</option>
                                                        <option value="Rent" <?php echo ($exp['category'] === 'Rent') ? 'selected' : ''; ?>>Rent</option>
                                                        <option value="Other" <?php echo ($exp['category'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold small text-uppercase">Amount (&#8369;)</label>
                                                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?php echo htmlspecialchars($exp['amount']); ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold small text-uppercase">Expense Date</label>
                                                    <input type="date" name="expenseDate" class="form-control" value="<?php echo date('Y-m-d', strtotime($exp['expenseDate'])); ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold small text-uppercase">Description</label>
                                                    <input type="text" name="description" class="form-control" value="<?php echo htmlspecialchars($exp['description'] ?? ''); ?>" placeholder="Bill details, invoice #, or note...">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary btn-sm px-3">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Delete Confirmation Modal -->
                            <div class="modal fade" id="<?php echo $delModalId; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="index.php">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="expenseID" value="<?php echo (int)$exp['expenseID']; ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold text-danger">Delete Expense Record</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <p class="mb-1">Are you sure you want to delete expense <strong>#EXP-<?php echo $exp['expenseID']; ?></strong>?</p>
                                                <p class="text-muted small mb-0">Category: <?php echo htmlspecialchars($exp['category']); ?> &bull; Amount: &#8369;<?php echo number_format($exp['amount'], 2); ?></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger btn-sm px-3">Confirm Delete</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Add Expense Modal -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">
                            <i class="fa-solid fa-receipt text-danger me-2"></i>Record Shop Expense
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase">Expense Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="">-- Choose Category --</option>
                                <option value="Electricity">Electricity (e.g. Meralco bill)</option>
                                <option value="Rent">Rent (Monthly shop space lease)</option>
                                <option value="Water">Water (e.g. Manila Water)</option>
                                <option value="Employee Salary">Employee Salary (Mechanic/staff payroll)</option>
                                <option value="Other">Other (General shop supplies / maintenance)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase">Amount (&#8369;) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">&#8369;</span>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase">Date of Expense <span class="text-danger">*</span></label>
                            <input type="date" name="expenseDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase">Description / Details</label>
                            <input type="text" name="description" class="form-control" placeholder="e.g. Billing Period Oct 2026, Account #12345 (Required if 'Other')">
                            <div class="form-text small">Required if category is "Other".</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Expense Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
