<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');

requireAdminRole(['Accounting Administrator', 'Super Administrator']);

$activeModule = 'reports';

$preset = isset($_GET['preset']) ? trim($_GET['preset']) : 'month';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate   = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$today = date('Y-m-d');
switch ($preset) {
    case 'today':
        $startDate = $today . ' 00:00:00';
        $endDate   = $today . ' 23:59:59';
        $periodLabel = 'Today (' . date('M d, Y') . ')';
        break;
    case 'week':
        $startDate = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $endDate   = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        $periodLabel = 'This Week (' . date('M d', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)) . ')';
        break;
    case 'year':
        $startDate = date('Y-01-01 00:00:00');
        $endDate   = date('Y-12-31 23:59:59');
        $periodLabel = 'Full Year ' . date('Y');
        break;
    case 'all':
        $startDate = '2020-01-01 00:00:00';
        $endDate   = date('Y-m-d 23:59:59');
        $periodLabel = 'All Time History';
        break;
    case 'custom':
        if ($startDate === '') $startDate = date('Y-m-01 00:00:00');
        else $startDate = date('Y-m-d 00:00:00', strtotime($startDate));

        if ($endDate === '') $endDate = date('Y-m-d 23:59:59');
        else $endDate = date('Y-m-d 23:59:59', strtotime($endDate));

        $periodLabel = 'Custom Range (' . date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate)) . ')';
        break;
    case 'month':
    default:
        $preset    = 'month';
        $startDate = date('Y-m-01 00:00:00');
        $endDate   = date('Y-m-t 23:59:59');
        $periodLabel = date('F Y') . ' (Current Month)';
        break;
}

$incomeMetrics = [
    'product_sales'    => 0.0,
    'completed_orders' => 0,
    'units_sold'       => 0,
    'service_labor'    => 0.0,
    'completed_services' => 0,
    'gross_income'     => 0.0
];

$completedOrdersList = [];
$completedServicesList = [];

if ($conn) {

    $prodSql = "SELECT ps.productSaleID, ps.orderDate, ps.pickupMethod, ps.paymentMethod,
                       CONCAT(c.firstName, IF(c.lastName IS NOT NULL AND c.lastName <> '', CONCAT(' ', c.lastName), '')) as customerName,
                       COALESCE(SUM(olp.quantity), 0) as totalItems,
                       COALESCE(SUM(olp.quantity * p.price), 0.00) as orderTotal
                FROM productSale ps
                JOIN customerAccount ca ON ps.customerAccountID = ca.customerAccountID
                JOIN customer c ON ca.customerID = c.customerID
                LEFT JOIN orderLineProduct olp ON ps.productSaleID = olp.productSaleID
                LEFT JOIN product p ON olp.productID = p.productID
                WHERE ps.status = 'Completed' AND ps.orderDate BETWEEN ? AND ?
                GROUP BY ps.productSaleID, ps.orderDate, ps.pickupMethod, ps.paymentMethod, customerName
                ORDER BY ps.orderDate DESC";
    $pStmt = mysqli_prepare($conn, $prodSql);
    if ($pStmt) {
        mysqli_stmt_bind_param($pStmt, 'ss', $startDate, $endDate);
        mysqli_stmt_execute($pStmt);
        $pRes = mysqli_stmt_get_result($pStmt);
        while ($pRow = mysqli_fetch_assoc($pRes)) {
            $incomeMetrics['product_sales'] += (float)$pRow['orderTotal'];
            $incomeMetrics['completed_orders']++;
            $incomeMetrics['units_sold'] += (int)$pRow['totalItems'];
            $completedOrdersList[] = $pRow;
        }
        mysqli_stmt_close($pStmt);
    }

    $servSql = "SELECT cs.customerServiceID, cs.requestDate, cs.scheduledDate, s.name as serviceName, s.price as laborPrice,
                       sc.name as categoryName,
                       CONCAT(c.firstName, IF(c.lastName IS NOT NULL AND c.lastName <> '', CONCAT(' ', c.lastName), '')) as customerName,
                       adm.username as technicianUsername
                FROM customerService cs
                JOIN service s ON cs.serviceID = s.serviceID
                LEFT JOIN serviceCategory sc ON s.serviceCategoryID = sc.serviceCategoryID
                JOIN customerAccount ca ON cs.customerAccountID = ca.customerAccountID
                JOIN customer c ON ca.customerID = c.customerID
                LEFT JOIN adminAccount adm ON cs.adminAccountID = adm.adminAccountID
                WHERE cs.status = 'Completed' 
                  AND COALESCE(cs.scheduledDate, cs.requestDate) BETWEEN ? AND ?
                ORDER BY cs.requestDate DESC";
    $sStmt = mysqli_prepare($conn, $servSql);
    if ($sStmt) {
        mysqli_stmt_bind_param($sStmt, 'ss', $startDate, $endDate);
        mysqli_stmt_execute($sStmt);
        $sRes = mysqli_stmt_get_result($sStmt);
        while ($sRow = mysqli_fetch_assoc($sRes)) {
            $incomeMetrics['service_labor'] += (float)$sRow['laborPrice'];
            $incomeMetrics['completed_services']++;
            $completedServicesList[] = $sRow;
        }
        mysqli_stmt_close($sStmt);
    }

    $incomeMetrics['gross_income'] = $incomeMetrics['product_sales'] + $incomeMetrics['service_labor'];
}

$expenseMetrics = [
    'total_expense' => 0.0,
    'by_category'   => [
        'Rent'            => 0.0,
        'Electricity'     => 0.0,
        'Water'           => 0.0,
        'Employee Salary' => 0.0,
        'Other'           => 0.0
    ]
];
$expenseList = [];

if ($conn) {
    $expSql = "SELECT e.*, adm.username as recordedByUsername
               FROM expense e
               JOIN adminAccount adm ON e.recordedBy = adm.adminAccountID
               WHERE e.expenseDate BETWEEN ? AND ?
               ORDER BY e.expenseDate DESC";
    $eStmt = mysqli_prepare($conn, $expSql);
    if ($eStmt) {
        mysqli_stmt_bind_param($eStmt, 'ss', $startDate, $endDate);
        mysqli_stmt_execute($eStmt);
        $eRes = mysqli_stmt_get_result($eStmt);
        while ($eRow = mysqli_fetch_assoc($eRes)) {
            $cat = $eRow['category'];
            $amt = (float)$eRow['amount'];
            $expenseMetrics['total_expense'] += $amt;
            if (isset($expenseMetrics['by_category'][$cat])) {
                $expenseMetrics['by_category'][$cat] += $amt;
            } else {
                $expenseMetrics['by_category']['Other'] += $amt;
            }
            $expenseList[] = $eRow;
        }
        mysqli_stmt_close($eStmt);
    }
}

$netIncome = $incomeMetrics['gross_income'] - $expenseMetrics['total_expense'];
$profitMargin = ($incomeMetrics['gross_income'] > 0) ? ($netIncome / $incomeMetrics['gross_income']) * 100 : 0.0;

$monthlyTrend = [];
if ($conn) {

    $cursor = new DateTime($startDate);
    $endCursor = new DateTime($endDate);
    $endCursor->modify('last day of this month');

    $iter = 0;
    while ($cursor <= $endCursor && $iter < 12) {
        $mKey = $cursor->format('Y-m');
        $mLabel = $cursor->format('M Y');
        $monthlyTrend[$mKey] = [
            'label'    => $mLabel,
            'income'   => 0.0,
            'expenses' => 0.0
        ];
        $cursor->modify('+1 month');
        $iter++;
    }

    $mProdSql = "SELECT DATE_FORMAT(ps.orderDate, '%Y-%m') as mKey, COALESCE(SUM(olp.quantity * p.price), 0.0) as rev
                 FROM productSale ps
                 JOIN orderLineProduct olp ON ps.productSaleID = olp.productSaleID
                 JOIN product p ON olp.productID = p.productID
                 WHERE ps.status = 'Completed' AND ps.orderDate BETWEEN ? AND ?
                 GROUP BY mKey";
    $mStmt = mysqli_prepare($conn, $mProdSql);
    if ($mStmt) {
        mysqli_stmt_bind_param($mStmt, 'ss', $startDate, $endDate);
        mysqli_stmt_execute($mStmt);
        $mRes = mysqli_stmt_get_result($mStmt);
        while ($mr = mysqli_fetch_assoc($mRes)) {
            if (isset($monthlyTrend[$mr['mKey']])) {
                $monthlyTrend[$mr['mKey']]['income'] += (float)$mr['rev'];
            }
        }
        mysqli_stmt_close($mStmt);
    }

    $mServSql = "SELECT DATE_FORMAT(COALESCE(cs.scheduledDate, cs.requestDate), '%Y-%m') as mKey, COALESCE(SUM(s.price), 0.0) as rev
                 FROM customerService cs
                 JOIN service s ON cs.serviceID = s.serviceID
                 WHERE cs.status = 'Completed' AND COALESCE(cs.scheduledDate, cs.requestDate) BETWEEN ? AND ?
                 GROUP BY mKey";
    $mSStmt = mysqli_prepare($conn, $mServSql);
    if ($mSStmt) {
        mysqli_stmt_bind_param($mSStmt, 'ss', $startDate, $endDate);
        mysqli_stmt_execute($mSStmt);
        $mSRes = mysqli_stmt_get_result($mSStmt);
        while ($msr = mysqli_fetch_assoc($mSRes)) {
            if (isset($monthlyTrend[$msr['mKey']])) {
                $monthlyTrend[$msr['mKey']]['income'] += (float)$msr['rev'];
            }
        }
        mysqli_stmt_close($mSStmt);
    }

    $mExpSql = "SELECT DATE_FORMAT(expenseDate, '%Y-%m') as mKey, COALESCE(SUM(amount), 0.0) as exp
                FROM expense
                WHERE expenseDate BETWEEN ? AND ?
                GROUP BY mKey";
    $mEStmt = mysqli_prepare($conn, $mExpSql);
    if ($mEStmt) {
        mysqli_stmt_bind_param($mEStmt, 'ss', $startDate, $endDate);
        mysqli_stmt_execute($mEStmt);
        $mERes = mysqli_stmt_get_result($mEStmt);
        while ($mer = mysqli_fetch_assoc($mERes)) {
            if (isset($monthlyTrend[$mer['mKey']])) {
                $monthlyTrend[$mer['mKey']]['expenses'] += (float)$mer['exp'];
            }
        }
        mysqli_stmt_close($mEStmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Report - PedalWorks Dynamics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .table-middle td { vertical-align: middle; }
        .kpi-card { transition: transform 0.15s ease-out; }
        .kpi-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-light">

    <?php include('../../includes/admin_nav.php'); ?>

    <div class="container-fluid px-4 py-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Income &amp; Expense Reporting</li>
                    </ol>
                </nav>
                <h1 class="h3 mb-0 fw-bold">
                    <i class="fa-solid fa-chart-line text-success me-2"></i>Financial Performance &amp; Income Tracking
                </h1>
                <p class="text-muted small mb-0">Automated revenue logging and summary report of total income versus expenses.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">

                <a href="print_financial.php?preset=<?php echo urlencode($preset); ?>&start_date=<?php echo urlencode(substr($startDate, 0, 10)); ?>&end_date=<?php echo urlencode(substr($endDate, 0, 10)); ?>" 
                   target="_blank" class="btn btn-outline-dark btn-sm px-3 shadow-sm">
                    <i class="fa-solid fa-print me-1 text-primary"></i> Print Financial Summary
                </a>
                <a href="print_inventory.php" target="_blank" class="btn btn-outline-dark btn-sm px-3 shadow-sm">
                    <i class="fa-solid fa-boxes-stacked me-1 text-info"></i> Print Inventory List
                </a>
                <a href="../expenses/index.php" class="btn btn-danger btn-sm px-3">
                    <i class="fa-solid fa-receipt me-1"></i> Record Expense
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="financial.php" class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <label class="form-label small text-muted text-uppercase fw-bold mb-1">Preset Reporting Periods</label>
                        <div class="btn-group w-100" role="group">
                            <a href="financial.php?preset=today" class="btn btn-sm <?php echo ($preset === 'today') ? 'btn-primary' : 'btn-outline-secondary'; ?>">Today</a>
                            <a href="financial.php?preset=week" class="btn btn-sm <?php echo ($preset === 'week') ? 'btn-primary' : 'btn-outline-secondary'; ?>">This Week</a>
                            <a href="financial.php?preset=month" class="btn btn-sm <?php echo ($preset === 'month') ? 'btn-primary' : 'btn-outline-secondary'; ?>">This Month</a>
                            <a href="financial.php?preset=year" class="btn btn-sm <?php echo ($preset === 'year') ? 'btn-primary' : 'btn-outline-secondary'; ?>">This Year</a>
                            <a href="financial.php?preset=all" class="btn btn-sm <?php echo ($preset === 'all') ? 'btn-primary' : 'btn-outline-secondary'; ?>">All Time</a>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small text-muted text-uppercase fw-bold mb-1">Custom Date Range</label>
                        <div class="input-group input-group-sm">
                            <input type="hidden" name="preset" value="custom">
                            <span class="input-group-text">From</span>
                            <input type="date" name="start_date" class="form-control" value="<?php echo substr($startDate, 0, 10); ?>" required>
                            <span class="input-group-text">To</span>
                            <input type="date" name="end_date" class="form-control" value="<?php echo substr($endDate, 0, 10); ?>" required>
                            <button type="submit" class="btn btn-primary px-3">
                                <i class="fa-solid fa-filter me-1"></i> Generate
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between p-3 mb-4 rounded bg-white shadow-sm border-start border-primary border-4">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-regular fa-calendar-check text-primary fs-5"></i>
                <span class="text-muted small">Reporting Window:</span>
                <strong class="text-dark"><?php echo htmlspecialchars($periodLabel); ?></strong>
            </div>
            <span class="badge bg-light text-dark border small">
                Standard Accrual: Completed Transactions Only
            </span>
        </div>

        <div class="row g-3 mb-4">

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm kpi-card h-100 border-start border-info border-4">
                    <div class="card-body">
                        <span class="text-muted small text-uppercase fw-semibold d-block">Product Sales Income</span>
                        <h3 class="fw-bold mb-1 text-info">&#8369;<?php echo number_format($incomeMetrics['product_sales'], 2); ?></h3>
                        <span class="small text-muted">
                            <i class="fa-solid fa-box me-1"></i><?php echo $incomeMetrics['completed_orders']; ?> completed orders (<?php echo $incomeMetrics['units_sold']; ?> items)
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm kpi-card h-100 border-start border-warning border-4">
                    <div class="card-body">
                        <span class="text-muted small text-uppercase fw-semibold d-block">Service Labor Income</span>
                        <h3 class="fw-bold mb-1 text-warning">&#8369;<?php echo number_format($incomeMetrics['service_labor'], 2); ?></h3>
                        <span class="small text-muted">
                            <i class="fa-solid fa-wrench me-1"></i><?php echo $incomeMetrics['completed_services']; ?> completed repairs
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm kpi-card h-100 border-start border-danger border-4">
                    <div class="card-body">
                        <span class="text-muted small text-uppercase fw-semibold d-block">Total Overhead Expenses</span>
                        <h3 class="fw-bold mb-1 text-danger">&#8369;<?php echo number_format($expenseMetrics['total_expense'], 2); ?></h3>
                        <span class="small text-muted">
                            <i class="fa-solid fa-receipt me-1"></i><?php echo count($expenseList); ?> recorded bill entries
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm kpi-card h-100 border-start <?php echo ($netIncome >= 0) ? 'border-success' : 'border-danger'; ?> border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small text-uppercase fw-semibold">Net Operating Income</span>
                            <span class="badge <?php echo ($netIncome >= 0) ? 'bg-success' : 'bg-danger'; ?> small">
                                <?php echo ($profitMargin >= 0 ? '+' : '') . number_format($profitMargin, 1); ?>% Margin
                            </span>
                        </div>
                        <h3 class="fw-bold mb-1 <?php echo ($netIncome >= 0) ? 'text-success' : 'text-danger'; ?>">
                            &#8369;<?php echo number_format($netIncome, 2); ?>
                        </h3>
                        <span class="small text-muted">
                            Gross: &#8369;<?php echo number_format($incomeMetrics['gross_income'], 2); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="fa-solid fa-chart-column text-primary"></i>
                            <span>Revenue vs. Operating Expense Trend</span>
                        </h5>
                        <span class="badge bg-light text-dark border small">Monthly Tracking</span>
                    </div>
                    <div class="card-body p-3">
                        <div style="position: relative; height: 280px; width: 100%;">
                            <canvas id="financialTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="fa-solid fa-chart-pie text-success"></i>
                            <span>Expense Composition</span>
                        </h5>
                        <span class="badge bg-danger bg-opacity-10 text-danger small fw-bold">Overhead</span>
                    </div>
                    <div class="card-body p-3 d-flex flex-column justify-content-center align-items-center">
                        <div style="position: relative; height: 240px; width: 100%;">
                            <canvas id="expenseDoughnutChart"></canvas>
                        </div>
                        <div class="small text-muted text-center mt-2">
                            Overhead breakdown by utility, rent, and labor salaries
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-arrow-down-long text-success me-2"></i>Income Streams Breakdown
                        </h5>
                        <span class="badge bg-success bg-opacity-10 text-success fw-bold">
                            Total: &#8369;<?php echo number_format($incomeMetrics['gross_income'], 2); ?>
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover table-middle mb-0">
                            <thead class="table-light small text-uppercase text-muted">
                                <tr>
                                    <th>Revenue Category</th>
                                    <th>Volume</th>
                                    <th class="text-end">Total Amount</th>
                                    <th class="text-end">% of Gross</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <i class="fa-solid fa-boxes-stacked text-info me-2"></i>
                                        <strong>Retail Product Sales</strong>
                                        <div class="small text-muted">Bicycles, components, gear, attachments</div>
                                    </td>
                                    <td><?php echo $incomeMetrics['completed_orders']; ?> orders</td>
                                    <td class="text-end fw-bold text-dark">&#8369;<?php echo number_format($incomeMetrics['product_sales'], 2); ?></td>
                                    <td class="text-end text-muted small">
                                        <?php echo ($incomeMetrics['gross_income'] > 0) ? number_format(($incomeMetrics['product_sales'] / $incomeMetrics['gross_income']) * 100, 1) . '%' : '0.0%'; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <i class="fa-solid fa-wrench text-warning me-2"></i>
                                        <strong>Workshop Labor &amp; Repairs</strong>
                                        <div class="small text-muted">Tune-ups, brake bleeds, wheel truing, assembly</div>
                                    </td>
                                    <td><?php echo $incomeMetrics['completed_services']; ?> services</td>
                                    <td class="text-end fw-bold text-dark">&#8369;<?php echo number_format($incomeMetrics['service_labor'], 2); ?></td>
                                    <td class="text-end text-muted small">
                                        <?php echo ($incomeMetrics['gross_income'] > 0) ? number_format(($incomeMetrics['service_labor'] / $incomeMetrics['gross_income']) * 100, 1) . '%' : '0.0%'; ?>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td>Total Recognized Gross Revenue</td>
                                    <td><?php echo ($incomeMetrics['completed_orders'] + $incomeMetrics['completed_services']); ?> total</td>
                                    <td class="text-end text-success">&#8369;<?php echo number_format($incomeMetrics['gross_income'], 2); ?></td>
                                    <td class="text-end">100%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-arrow-up-long text-danger me-2"></i>Overhead Expenses Breakdown
                        </h5>
                        <span class="badge bg-danger bg-opacity-10 text-danger fw-bold">
                            Total: &#8369;<?php echo number_format($expenseMetrics['total_expense'], 2); ?>
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover table-middle mb-0">
                            <thead class="table-light small text-uppercase text-muted">
                                <tr>
                                    <th>Expense Category</th>
                                    <th class="text-end">Total Amount</th>
                                    <th class="text-end">% of Total Expense</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expenseMetrics['by_category'] as $catName => $catAmount): 
                                    $pct = ($expenseMetrics['total_expense'] > 0) ? ($catAmount / $expenseMetrics['total_expense']) * 100 : 0.0;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($catName); ?></strong>
                                    </td>
                                    <td class="text-end fw-bold text-danger">&#8369;<?php echo number_format($catAmount, 2); ?></td>
                                    <td class="text-end text-muted small"><?php echo number_format($pct, 1); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td>Total Operating Expenses</td>
                                    <td class="text-end text-danger">&#8369;<?php echo number_format($expenseMetrics['total_expense'], 2); ?></td>
                                    <td class="text-end">100%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="fa-solid fa-clock-rotate-left text-muted me-2"></i>Completed Income Transactions in Period
                </h5>
                <span class="badge bg-light text-dark border">
                    <?php echo count($completedOrdersList); ?> Orders &bull; <?php echo count($completedServicesList); ?> Services
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-middle mb-0">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th>Type</th>
                            <th>Ref #</th>
                            <th>Date Completed</th>
                            <th>Customer</th>
                            <th>Details</th>
                            <th class="text-end">Amount Recognized</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($completedOrdersList) && empty($completedServicesList)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    No completed transactions recorded in this period.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($completedOrdersList as $ord): ?>
                            <tr>
                                <td><span class="badge bg-info text-dark"><i class="fa-solid fa-box me-1"></i>Product Order</span></td>
                                <td class="fw-bold">#PW-ORD-<?php echo $ord['productSaleID']; ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($ord['orderDate'])); ?></td>
                                <td><?php echo htmlspecialchars($ord['customerName']); ?></td>
                                <td class="small text-muted"><?php echo $ord['totalItems']; ?> items &bull; <?php echo htmlspecialchars($ord['pickupMethod']); ?></td>
                                <td class="text-end fw-bold text-success">&#8369;<?php echo number_format($ord['orderTotal'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>

                            <?php foreach ($completedServicesList as $srv): ?>
                            <tr>
                                <td><span class="badge bg-warning text-dark"><i class="fa-solid fa-wrench me-1"></i>Repair Labor</span></td>
                                <td class="fw-bold">#PW-SRV-<?php echo $srv['customerServiceID']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($srv['requestDate'])); ?></td>
                                <td><?php echo htmlspecialchars($srv['customerName']); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($srv['serviceName']); ?></td>
                                <td class="text-end fw-bold text-success">&#8369;<?php echo number_format($srv['laborPrice'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    </div>

    <script src="../../includes/js/chart.min.js"></script>
    <script>
    if (typeof Chart === 'undefined') {
        document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js"><\/script>');
    }
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js failed to load.');
            return;
        }

        // 1. Monthly Financial Trend Chart (Bar + Line)
        const trendLabels = <?php echo json_encode(array_values(array_map(fn($m) => $m['label'], $monthlyTrend))); ?>;
        const trendIncome = <?php echo json_encode(array_values(array_map(fn($m) => (float)$m['income'], $monthlyTrend))); ?>;
        const trendExpense = <?php echo json_encode(array_values(array_map(fn($m) => (float)$m['expenses'], $monthlyTrend))); ?>;

        const ctxTrend = document.getElementById('financialTrendChart')?.getContext('2d');
        if (ctxTrend) {
            new Chart(ctxTrend, {
                type: 'bar',
                data: {
                    labels: trendLabels.length ? trendLabels : ['Current Period'],
                    datasets: [
                        {
                            label: 'Gross Income (₱)',
                            data: trendIncome.length ? trendIncome : [<?php echo (float)$incomeMetrics['gross_income']; ?>],
                            backgroundColor: 'rgba(16, 185, 129, 0.75)',
                            borderColor: '#10b981',
                            borderWidth: 1.5,
                            borderRadius: 6
                        },
                        {
                            label: 'Overhead Expenses (₱)',
                            data: trendExpense.length ? trendExpense : [<?php echo (float)$expenseMetrics['total_expense']; ?>],
                            backgroundColor: 'rgba(239, 68, 68, 0.75)',
                            borderColor: '#ef4444',
                            borderWidth: 1.5,
                            borderRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: { size: 12, family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto' },
                                boxWidth: 14
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ₱' + Number(context.parsed.y).toLocaleString(undefined, { minimumFractionDigits: 2 });
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + Number(value).toLocaleString();
                                },
                                font: { size: 11 }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11 } }
                        }
                    }
                }
            });
        }

        // 2. Overhead Expense Distribution Doughnut Chart
        const expLabels = <?php echo json_encode(array_keys($expenseMetrics['by_category'])); ?>;
        const expValues = <?php echo json_encode(array_values($expenseMetrics['by_category'])); ?>;
        const totalExp = <?php echo (float)$expenseMetrics['total_expense']; ?>;

        const ctxExp = document.getElementById('expenseDoughnutChart')?.getContext('2d');
        if (ctxExp) {
            new Chart(ctxExp, {
                type: 'doughnut',
                data: {
                    labels: expLabels,
                    datasets: [{
                        data: totalExp > 0 ? expValues : [1],
                        backgroundColor: totalExp > 0 ? [
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(107, 114, 128, 0.8)'
                        ] : ['rgba(203, 213, 225, 0.5)'],
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (totalExp === 0) return 'No recorded expenses in period';
                                    const val = Number(context.parsed);
                                    const pct = totalExp > 0 ? ((val / totalExp) * 100).toFixed(1) : 0;
                                    return context.label + ': ₱' + val.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' (' + pct + '%)';
                                }
                            }
                        }
                    },
                    cutout: '62%'
                }
            });
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
