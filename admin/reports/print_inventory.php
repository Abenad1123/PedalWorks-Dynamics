<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');

// Access Control: Inventory Manager, Accounting Administrator, or Super Administrator (FR20)
requireAdminRole(['Inventory Manager', 'Accounting Administrator', 'Super Administrator']);

$products = [];
$totalValuation = 0.0;
$totalUnits = 0;
$lowStockCount = 0;
$outOfStockCount = 0;

if ($conn) {
    $sql = "SELECT p.productID, p.sku, p.name, p.price, p.stock, p.lowStockThreshold, pc.name as categoryName
            FROM product p
            LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID
            WHERE p.endDate IS NULL
            ORDER BY pc.name ASC, p.name ASC";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $stock = (int)$row['stock'];
            $price = (float)$row['price'];
            $rowVal = $stock * $price;
            $row['rowValuation'] = $rowVal;

            $totalUnits += $stock;
            $totalValuation += $rowVal;

            if ($stock <= 0) {
                $outOfStockCount++;
            } elseif ($stock <= (int)$row['lowStockThreshold']) {
                $lowStockCount++;
            }

            $products[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Inventory Manifest - PedalWorks Dynamics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-size: 12px;
        }
        .print-container {
            max-width: 900px;
            margin: 30px auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .report-header {
            border-bottom: 2px solid #212529;
            padding-bottom: 18px;
            margin-bottom: 20px;
        }
        .report-title {
            font-size: 20px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table-inventory th, .table-inventory td {
            vertical-align: middle;
            padding: 6px 10px;
        }
        .signature-line {
            width: 220px;
            border-top: 1px solid #212529;
            margin-top: 45px;
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
                margin: 1.2cm;
            }
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- Non-Print Floating Toolbar -->
    <div class="no-print bg-dark text-white py-2 sticky-top shadow-sm">
        <div class="container d-flex justify-content-between align-items-center">
            <span class="small">
                <i class="fa-solid fa-boxes-stacked text-warning me-1"></i>
                PedalWorks Dynamics &bull; Printable Inventory Manifest Preview (FR20)
            </span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary btn-sm px-3" onclick="window.print()">
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
                <div class="text-muted small">Warehouse Inventory &amp; Stock Valuation Audit</div>
                <div class="text-muted small">Km. 14 East Service Road, Western Bicutan, Taguig City, Philippines</div>
            </div>
            <div class="text-end">
                <div class="report-title text-dark">Inventory Manifest</div>
                <span class="badge bg-dark text-white text-uppercase px-2 py-1">FR20 Inventory View</span>
            </div>
        </div>

        <!-- Metadata & Summary Grid -->
        <div class="row g-2 mb-3 p-3 bg-light rounded border">
            <div class="col-sm-3">
                <span class="text-muted small d-block">Audit Date:</span>
                <strong class="text-dark"><?php echo date('F d, Y - h:i A'); ?></strong>
            </div>
            <div class="col-sm-3">
                <span class="text-muted small d-block">Active Catalog SKUs:</span>
                <strong class="text-dark"><?php echo count($products); ?> distinct products</strong>
            </div>
            <div class="col-sm-3">
                <span class="text-muted small d-block">Total Units on Hand:</span>
                <strong class="text-dark"><?php echo number_format($totalUnits); ?> units</strong>
            </div>
            <div class="col-sm-3 text-sm-end">
                <span class="text-muted small d-block">Total Inventory Asset Value:</span>
                <strong class="text-success fs-6">&#8369;<?php echo number_format($totalValuation, 2); ?></strong>
            </div>
        </div>

        <!-- Stock Health Alert Bar -->
        <div class="d-flex justify-content-between align-items-center mb-3 small text-muted">
            <span>
                Stock Status: 
                <strong class="text-success"><?php echo (count($products) - $lowStockCount - $outOfStockCount); ?> Healthy</strong> &bull; 
                <strong class="text-warning"><?php echo $lowStockCount; ?> Low Stock</strong> &bull; 
                <strong class="text-danger"><?php echo $outOfStockCount; ?> Out of Stock</strong>
            </span>
            <span>Audited By: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></span>
        </div>

        <!-- Inventory Table -->
        <table class="table table-bordered table-inventory mb-4">
            <thead class="table-light">
                <tr class="small text-uppercase">
                    <th style="width: 40px;">#</th>
                    <th style="width: 110px;">SKU</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th class="text-center" style="width: 70px;">Stock</th>
                    <th class="text-center" style="width: 100px;">Status</th>
                    <th class="text-end" style="width: 105px;">Unit Price</th>
                    <th class="text-end" style="width: 115px;">Asset Value</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No active products found in inventory.</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $idx = 1;
                    foreach ($products as $p): 
                        $stk = (int)$p['stock'];
                        $isOut = ($stk <= 0);
                        $isLow = ($stk <= (int)$p['lowStockThreshold'] && $stk > 0);
                    ?>
                    <tr>
                        <td class="text-muted"><?php echo $idx++; ?></td>
                        <td><code><?php echo htmlspecialchars($p['sku']); ?></code></td>
                        <td class="fw-semibold text-dark"><?php echo htmlspecialchars($p['name']); ?></td>
                        <td><?php echo htmlspecialchars($p['categoryName'] ?? 'Gear'); ?></td>
                        <td class="text-center fw-bold <?php echo $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-dark'); ?>">
                            <?php echo $stk; ?>
                        </td>
                        <td class="text-center small">
                            <?php if ($isOut): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($isLow): ?>
                                <span class="badge bg-warning text-dark">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">&#8369;<?php echo number_format($p['price'], 2); ?></td>
                        <td class="text-end fw-semibold text-dark">&#8369;<?php echo number_format($p['rowValuation'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="4">Total Inventory Assets</td>
                    <td class="text-center"><?php echo number_format($totalUnits); ?></td>
                    <td></td>
                    <td class="text-end">Total Valuation:</td>
                    <td class="text-end text-success">&#8369;<?php echo number_format($totalValuation, 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Signatures Section -->
        <div class="row pt-3 mt-4">
            <div class="col-6">
                <div class="signature-line">
                    <div><?php echo htmlspecialchars($_SESSION['username'] ?? 'Staff Auditor'); ?></div>
                    <span class="text-muted small fw-normal">Inventory Custodian / Inspector</span>
                </div>
            </div>
            <div class="col-6 d-flex justify-content-end">
                <div class="signature-line">
                    <div>Warehouse Operations Supervisor</div>
                    <span class="text-muted small fw-normal">Audited &amp; Certified By</span>
                </div>
            </div>
        </div>

        <div class="text-center text-muted small pt-3 mt-4 border-top">
            PedalWorks Dynamics Master Catalog Inventory Report &bull; Generated from Official Database
        </div>
    </div>

</body>
</html>
