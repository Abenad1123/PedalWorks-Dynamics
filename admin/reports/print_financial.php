<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');

// Access Control: Accounting Administrator or Super Administrator (FR20)
requireAdminRole(['Accounting Administrator', 'Super Administrator']);

// Date Range Filtering
$preset    = isset($_GET['preset']) ? trim($_GET['preset']) : 'month';
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
        $periodLabel = 'All Time Financial History';
        break;
    case 'custom':
        if ($startDate === '') $startDate = date('Y-m-01 00:00:00');
        else $startDate = date('Y-m-d 00:00:00', strtotime($startDate));

        if ($endDate === '') $endDate = date('Y-m-d 23:59:59');
        else $endDate = date('Y-m-d 23:59:59', strtotime($endDate));

        $periodLabel = date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate));
        break;
    case 'month':
    default:
        $preset    = 'month';
        $startDate = date('Y-m-01 00:00:00');
        $endDate   = date('Y-m-t 23:59:59');
        $periodLabel = date('F Y') . ' (Monthly)';
        break;
}

// 1. Product Sales Income
$productSales = 0.0;
$orderCount = 0;
$unitsSold = 0;
if ($conn) {
    $pSql = "SELECT COALESCE(SUM(olp.quantity * p.price), 0.00) as salesTotal,
                    COUNT(DISTINCT ps.productSaleID) as ordersTotal,
                    COALESCE(SUM(olp.quantity), 0) as itemsTotal
             FROM productSale ps
             JOIN orderLineProduct olp ON ps.productSaleID = olp.productSaleID
             JOIN product p ON olp.productID = p.productID
             WHERE ps.status = 'Completed' AND ps.orderDate BETWEEN ? AND ?";
    $pStmt = mysqli_prepare($conn, $pSql);
    if ($pStmt) {
        mysqli_stmt_bind_param($pStmt, 'ss', $startDate, $endDate);
        mysqli_stmt_execute($pStmt);
        $pRes = mysqli_stmt_get_result($pStmt);
        if ($pRow = mysqli_fetch_assoc($pRes)) {
            $productSales = (float)$pRow['salesTotal'];
            $orderCount   = (int)$pRow['ordersTotal'];
            $unitsSold    = (int)$pRow['itemsTotal'];
        }
        mysqli_stmt_close($pStmt);
    }
}

// 2. Service Labor Revenue
$serviceLabor = 0.0;
$serviceCount = 0;
if ($conn) {
    $sSql = "SELECT COALESCE(SUM(s.price), 0.00) as laborTotal,
                    COUNT(cs.customerServiceID) as countTotal
             FROM customerService cs
             JOIN service s ON cs.serviceID = s.serviceID
             WHERE cs.status = 'Completed' 
               AND COALESCE(cs.scheduledDate, cs.requestDate) BETWEEN ? AND ?";
    $sStmt = mysqli_prepare($conn, $sSql);
    if ($sStmt) {
        mysqli_stmt_bind_param($sStmt, 'ss', $startDate, $endDate);
        mysqli_stmt_execute($sStmt);
        $sRes = mysqli_stmt_get_result($sStmt);
        if ($sRow = mysqli_fetch_assoc($sRes)) {
            $serviceLabor = (float)$sRow['laborTotal'];
            $serviceCount = (int)$sRow['countTotal'];
        }
        mysqli_stmt_close($sStmt);
    }
}

$grossIncome = $productSales + $serviceLabor;

// 3. Overhead Expenses
$expensesByCategory = [
    'Rent'            => 0.0,
    'Electricity'     => 0.0,
    'Water'           => 0.0,
    'Employee Salary' => 0.0,
    'Other'           => 0.0
];
$totalExpense = 0.0;

if ($conn) {
    $eSql = "SELECT category, COALESCE(SUM(amount), 0.00) as catTotal
             FROM expense
             WHERE expenseDate BETWEEN ? AND ?
             GROUP BY category";
    $eStmt = mysqli_prepare($conn, $eSql);
    if ($eStmt) {
        mysqli_stmt_bind_param($eStmt, 'ss', $startDate, $endDate);
        mysqli_stmt_execute($eStmt);
        $eRes = mysqli_stmt_get_result($eStmt);
        while ($eRow = mysqli_fetch_assoc($eRes)) {
            $cat = $eRow['category'];
            $amt = (float)$eRow['catTotal'];
            $totalExpense += $amt;
            if (isset($expensesByCategory[$cat])) {
                $expensesByCategory[$cat] = $amt;
            } else {
                $expensesByCategory['Other'] += $amt;
            }
        }
        mysqli_stmt_close($eStmt);
    }
}

$netProfit = $grossIncome - $totalExpense;
$profitMargin = ($grossIncome > 0) ? ($netProfit / $grossIncome) * 100 : 0.0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Summary Statement - PedalWorks Dynamics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-size: 13px;
        }
        .print-container {
            max-width: 820px;
            margin: 30px auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .report-header {
            border-bottom: 2px solid #212529;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .report-title {
            font-size: 22px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-meta-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 12px 18px;
            margin-bottom: 25px;
        }
        .signature-line {
            width: 220px;
            border-top: 1px solid #212529;
            margin-top: 50px;
            padding-top: 5px;
            text-align: center;
            font-weight: 600;
        }
        @media print {
            body {
                background: #ffffff !important;
                margin: 0;
                padding: 0;
            }
            .print-container {
                box-shadow: none !important;
                border: none !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            @page {
                margin: 1.5cm;
            }
        }
    </style>
</head>
<body>

    <!-- Non-Print Floating Toolbar -->
    <div class="no-print bg-dark text-white py-2 sticky-top shadow-sm">
        <div class="container d-flex justify-content-between align-items-center">
            <span class="small">
                <i class="fa-solid fa-file-invoice-dollar text-success me-1"></i>
                PedalWorks Dynamics &bull; Printable Statement Preview (FR20)
            </span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-success btn-sm px-3" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Print / Save as PDF
                </button>
                <button type="button" class="btn btn-outline-light btn-sm px-3" onclick="window.close()">
                    Close Window
                </button>
            </div>
        </div>
    </div>

    <div class="print-container">
        <!-- Letterhead Header -->
        <div class="report-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-0 text-dark">PedalWorks Dynamics</h2>
                <div class="text-muted small">High-Performance Bicycles &amp; Precision Maintenance Lab</div>
                <div class="text-muted small">Km. 14 East Service Road, Western Bicutan, Taguig City, Philippines</div>
                <div class="text-muted small">Tel: (02) 8823-2457 &bull; Email: dispatch@pedalworks.local</div>
            </div>
            <div class="text-end">
                <div class="report-title text-dark">Financial Statement</div>
                <span class="badge bg-dark text-white text-uppercase px-2 py-1">FR19 &amp; FR20 Report</span>
            </div>
        </div>

        <!-- Metadata Box -->
        <div class="report-meta-box">
            <div class="row g-2">
                <div class="col-6">
                    <span class="text-muted small d-block">Reporting Period:</span>
                    <strong class="text-dark"><?php echo htmlspecialchars($periodLabel); ?></strong>
                </div>
                <div class="col-6 text-end">
                    <span class="text-muted small d-block">Generated On:</span>
                    <strong class="text-dark"><?php echo date('F d, Y - h:i A'); ?></strong>
                </div>
                <div class="col-6">
                    <span class="text-muted small d-block">Accounting Method:</span>
                    <strong class="text-dark">Accrual Basis (Completed Transactions Only)</strong>
                </div>
                <div class="col-6 text-end">
                    <span class="text-muted small d-block">Prepared By:</span>
                    <strong class="text-dark"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrator'); ?> (<?php echo htmlspecialchars($_SESSION['role'] ?? 'Staff'); ?>)</strong>
                </div>
            </div>
        </div>

        <!-- 1. Executive Summary Table -->
        <h6 class="fw-bold text-uppercase border-bottom pb-2 mb-3">1. Executive Financial Summary</h6>
        <table class="table table-bordered mb-4">
            <thead class="table-light">
                <tr>
                    <th>Performance Metric</th>
                    <th class="text-end" style="width: 200px;">Amount (PHP)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Gross Sales Revenue (Product Sales + Workshop Labor)</td>
                    <td class="text-end fw-bold text-success">&#8369;<?php echo number_format($grossIncome, 2); ?></td>
                </tr>
                <tr>
                    <td>Less: Total Operating Overhead Expenses</td>
                    <td class="text-end fw-bold text-danger">(&#8369;<?php echo number_format($totalExpense, 2); ?>)</td>
                </tr>
                <tr class="table-light fw-bold" style="font-size: 15px;">
                    <td>Net Operating Income / (Loss)</td>
                    <td class="text-end <?php echo ($netProfit >= 0) ? 'text-success' : 'text-danger'; ?>">
                        &#8369;<?php echo number_format($netProfit, 2); ?>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted small">Operating Profit Margin</td>
                    <td class="text-end fw-semibold"><?php echo number_format($profitMargin, 2); ?>%</td>
                </tr>
            </tbody>
        </table>

        <!-- 2. Revenue Breakdown (FR18) -->
        <h6 class="fw-bold text-uppercase border-bottom pb-2 mb-3">2. Recognized Revenue Breakdown (FR18)</h6>
        <table class="table table-bordered mb-4">
            <thead class="table-light small">
                <tr>
                    <th>Revenue Stream</th>
                    <th>Volume Details</th>
                    <th class="text-end" style="width: 200px;">Amount (PHP)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Retail Product Sales</td>
                    <td><?php echo $orderCount; ?> completed orders &bull; <?php echo $unitsSold; ?> item units</td>
                    <td class="text-end fw-semibold">&#8369;<?php echo number_format($productSales, 2); ?></td>
                </tr>
                <tr>
                    <td>Workshop Repair &amp; Maintenance Labor</td>
                    <td><?php echo $serviceCount; ?> completed repair jobs</td>
                    <td class="text-end fw-semibold">&#8369;<?php echo number_format($serviceLabor, 2); ?></td>
                </tr>
                <tr class="table-light fw-bold">
                    <td colspan="2">Total Gross Revenue</td>
                    <td class="text-end text-success">&#8369;<?php echo number_format($grossIncome, 2); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- 3. Overhead Expenses Breakdown (FR17) -->
        <h6 class="fw-bold text-uppercase border-bottom pb-2 mb-3">3. Operating Overhead Expenses (FR17)</h6>
        <table class="table table-bordered mb-4">
            <thead class="table-light small">
                <tr>
                    <th>Expense Category</th>
                    <th class="text-end" style="width: 200px;">Amount (PHP)</th>
                    <th class="text-end" style="width: 120px;">% of Expenses</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expensesByCategory as $catName => $catAmount): 
                    $pct = ($totalExpense > 0) ? ($catAmount / $totalExpense) * 100 : 0.0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($catName); ?></td>
                    <td class="text-end fw-semibold text-danger">&#8369;<?php echo number_format($catAmount, 2); ?></td>
                    <td class="text-end text-muted small"><?php echo number_format($pct, 1); ?>%</td>
                </tr>
                <?php endforeach; ?>
                <tr class="table-light fw-bold">
                    <td>Total Operating Expenses</td>
                    <td class="text-end text-danger">&#8369;<?php echo number_format($totalExpense, 2); ?></td>
                    <td class="text-end">100.0%</td>
                </tr>
            </tbody>
        </table>

        <!-- Signatures Section -->
        <div class="row pt-4 mt-5">
            <div class="col-6">
                <div class="signature-line">
                    <div><?php echo htmlspecialchars($_SESSION['username'] ?? 'Staff Auditor'); ?></div>
                    <span class="text-muted small fw-normal">Prepared By / Accounting Staff</span>
                </div>
            </div>
            <div class="col-6 d-flex justify-content-end">
                <div class="signature-line">
                    <div>Shop Operations Manager</div>
                    <span class="text-muted small fw-normal">Approved &amp; Certified By</span>
                </div>
            </div>
        </div>

        <div class="text-center text-muted small pt-4 mt-4 border-top">
            This document is computer-generated from the PedalWorks Dynamics database. Valid without stamp unless specified.
        </div>
    </div>

</body>
</html>
