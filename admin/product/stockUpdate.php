<?php
session_start();
include('../../includes/config.php');

$errors = [];
$successMessage = '';

if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}

$selectedID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedID = isset($_POST['productID']) ? (int)$_POST['productID'] : 0;
}

// Handle Stock Adjustment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_stock'])) {
    $actionType = $_POST['action_type'] ?? 'add';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

    if ($selectedID <= 0) {
        $errors[] = 'Please select a valid product.';
    }
    if ($quantity < 0) {
        $errors[] = 'Quantity must be zero or a positive number.';
    }

    if (empty($errors) && $conn) {
        // Fetch current active product
        $stmt = mysqli_prepare($conn, "SELECT productID, name, sku, stock, lowStockThreshold FROM product WHERE productID = ? AND endDate IS NULL LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $selectedID);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $p = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$p) {
            $errors[] = 'Active product not found or has been archived.';
        } else {
            $currentStock = (int)$p['stock'];
            $newStock = $currentStock;

            if ($actionType === 'add') {
                $newStock = $currentStock + $quantity;
            } elseif ($actionType === 'deduct') {
                if ($quantity > $currentStock) {
                    $errors[] = "Cannot deduct {$quantity} units. Current stock is only {$currentStock} units.";
                } else {
                    $newStock = $currentStock - $quantity;
                }
            } elseif ($actionType === 'set') {
                $newStock = $quantity;
            } else {
                $errors[] = 'Invalid action type selected.';
            }

            if (empty($errors)) {
                $upStmt = mysqli_prepare($conn, "UPDATE product SET stock = ? WHERE productID = ? AND endDate IS NULL");
                mysqli_stmt_bind_param($upStmt, 'ii', $newStock, $selectedID);
                if (mysqli_stmt_execute($upStmt)) {
                    $_SESSION['success'] = "Inventory stock for \"{$p['name']}\" ({$p['sku']}) successfully updated from {$currentStock} to {$newStock} units.";
                    header("Location: stockUpdate.php?id=" . $selectedID);
                    exit;
                } else {
                    $errors[] = 'Database error: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($upStmt);
            }
        }
    }
}

// Fetch all active products
$activeProducts = [];
$lowStockProducts = [];

if ($conn) {
    $pQuery = "SELECT p.*, pc.name AS categoryName 
               FROM product p 
               LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID 
               WHERE p.endDate IS NULL 
               ORDER BY (p.stock <= p.lowStockThreshold) DESC, p.stock ASC, p.name ASC";
    $pRes = mysqli_query($conn, $pQuery);
    if ($pRes) {
        while ($row = mysqli_fetch_assoc($pRes)) {
            $activeProducts[] = $row;
            if ((int)$row['stock'] <= (int)$row['lowStockThreshold']) {
                $lowStockProducts[] = $row;
            }
        }
    }
}

// Find currently selected product details
$currentSelected = null;
if ($selectedID > 0) {
    foreach ($activeProducts as $ap) {
        if ((int)$ap['productID'] === $selectedID) {
            $currentSelected = $ap;
            break;
        }
    }
}
$activeModule = 'product';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management &amp; Adjustment - PedalWorks Dynamics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light">

    <!-- Top Admin Navigation Bar -->
    <?php include('../../includes/admin_nav.php'); ?>

    <div class="container-fluid px-4 py-4">

        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Product Management</a></li>
                <li class="breadcrumb-item active" aria-current="page">Stock Update</li>
            </ol>
        </nav>

        <!-- Header Section -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1">
                    <i class="fa-solid fa-boxes-stacked text-warning me-2"></i>Inventory Stock Management
                </h1>
                <p class="text-muted small mb-0">Record deliveries, stock adjustments, manual counts, and inspect low-stock alert thresholds.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="addProduct.php" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus-circle me-1"></i>Add Product
                </a>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to Catalog
                </a>
            </div>
        </div>

        <!-- System Alerts -->
        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <h6 class="alert-heading fw-bold mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>Adjustment Error:</h6>
                <ul class="mb-0 small ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <!-- Left Column: Adjustment Form Card -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <span class="fw-semibold text-dark">
                            <i class="fa-solid fa-sliders text-warning me-2"></i>Adjust Product Inventory
                        </span>
                    </div>
                    <div class="card-body p-4">
                        <form method="post" action="stockUpdate.php">
                            
                            <div class="mb-3">
                                <label for="productID" class="form-label small fw-semibold text-muted">Select Item <span class="text-danger">*</span></label>
                                <select id="productID" name="productID" class="form-select" onchange="window.location.href='stockUpdate.php?id=' + this.value;" required>
                                    <option value="">-- Choose Product to Adjust --</option>
                                    <?php foreach ($activeProducts as $p): ?>
                                        <option value="<?php echo (int)$p['productID']; ?>" <?php echo ($selectedID === (int)$p['productID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['sku']); ?>) &mdash; Current: <?php echo (int)$p['stock']; ?>
                                            <?php if ((int)$p['stock'] <= (int)$p['lowStockThreshold']): ?>
                                                &bull; [LOW STOCK]
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($currentSelected): ?>
                                <div class="p-3 bg-light rounded border mb-3">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($currentSelected['name']); ?></div>
                                    <div class="small text-muted mb-2">
                                        <span class="badge bg-secondary font-monospace me-1"><?php echo htmlspecialchars($currentSelected['sku']); ?></span>
                                        <span><?php echo htmlspecialchars($currentSelected['categoryName'] ?? ''); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="small text-muted d-block">Current Stock:</span>
                                            <span class="fs-5 fw-bold <?php echo ((int)$currentSelected['stock'] <= (int)$currentSelected['lowStockThreshold']) ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo (int)$currentSelected['stock']; ?> units
                                            </span>
                                        </div>
                                        <div class="text-end">
                                            <span class="small text-muted d-block">Low Stock Limit:</span>
                                            <span class="badge bg-secondary">&le; <?php echo (int)$currentSelected['lowStockThreshold']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted mb-2">Adjustment Operation <span class="text-danger">*</span></label>
                                <div class="d-flex flex-column gap-2">
                                    <div class="form-check p-3 border rounded bg-white">
                                        <input class="form-check-input" type="radio" name="action_type" id="op_add" value="add" checked>
                                        <label class="form-check-label w-100" for="op_add">
                                            <strong class="text-success"><i class="fa-solid fa-plus-circle me-1"></i>Restock / Add (+)</strong>
                                            <div class="small text-muted">Increase available quantity with new shipment/delivery.</div>
                                        </label>
                                    </div>
                                    <div class="form-check p-3 border rounded bg-white">
                                        <input class="form-check-input" type="radio" name="action_type" id="op_deduct" value="deduct">
                                        <label class="form-check-label w-100" for="op_deduct">
                                            <strong class="text-danger"><i class="fa-solid fa-minus-circle me-1"></i>Dispatch / Deduct (-)</strong>
                                            <div class="small text-muted">Decrease quantity for damage, shrinkage, or offline sale.</div>
                                        </label>
                                    </div>
                                    <div class="form-check p-3 border rounded bg-white">
                                        <input class="form-check-input" type="radio" name="action_type" id="op_set" value="set">
                                        <label class="form-check-label w-100" for="op_set">
                                            <strong class="text-primary"><i class="fa-solid fa-pen-clip me-1"></i>Physical Audit (=)</strong>
                                            <div class="small text-muted">Directly override stock level to exact counted total.</div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="quantity" class="form-label small fw-semibold text-muted">Quantity (Units) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-hashtag text-muted"></i></span>
                                    <input type="number" id="quantity" name="quantity" class="form-control font-monospace" min="0" value="10" required>
                                </div>
                            </div>

                            <button type="submit" name="apply_stock" class="btn btn-warning text-dark w-100 fw-semibold py-2">
                                <i class="fa-solid fa-check me-1"></i>Apply Stock Adjustment
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Low Stock Alerts & Summary Table -->
            <div class="col-lg-7">
                <?php if (!empty($lowStockProducts)): ?>
                    <div class="alert alert-warning border-warning shadow-sm mb-4 d-flex align-items-center">
                        <i class="fa-solid fa-triangle-exclamation fa-2x text-warning me-3"></i>
                        <div>
                            <strong>Attention Required:</strong> <strong><?php echo count($lowStockProducts); ?></strong> item(s) are at or below their alert threshold. Immediate replenishment recommended.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <span class="fw-semibold text-dark">
                            <i class="fa-solid fa-warehouse text-primary me-2"></i>Active Inventory Overview
                        </span>
                        <span class="badge bg-secondary"><?php echo count($activeProducts); ?> items</span>
                    </div>
                    <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light small text-uppercase text-muted sticky-top">
                                <tr>
                                    <th scope="col">SKU</th>
                                    <th scope="col">Product</th>
                                    <th scope="col" class="text-end">Current Stock</th>
                                    <th scope="col" class="text-center">Limit</th>
                                    <th scope="col" class="text-center">Status</th>
                                    <th scope="col" class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php foreach ($activeProducts as $p): ?>
                                    <?php
                                    $st = (int)$p['stock'];
                                    $th = (int)$p['lowStockThreshold'];
                                    $isOut = ($st <= 0);
                                    $isLow = ($st <= $th && $st > 0);
                                    $isSelectedRow = ($selectedID === (int)$p['productID']);
                                    ?>
                                    <tr class="<?php echo $isSelectedRow ? 'table-warning' : ''; ?>">
                                        <td>
                                            <span class="badge bg-dark-subtle text-dark font-monospace"><?php echo htmlspecialchars($p['sku']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['name']); ?></div>
                                            <span class="text-muted small"><?php echo htmlspecialchars($p['categoryName'] ?? ''); ?></span>
                                        </td>
                                        <td class="text-end fw-bold font-monospace">
                                            <?php if ($isOut): ?>
                                                <span class="text-danger">0 units</span>
                                            <?php elseif ($isLow): ?>
                                                <span class="text-warning"><?php echo $st; ?> units</span>
                                            <?php else: ?>
                                                <span class="text-success"><?php echo $st; ?> units</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center text-muted">
                                            &le; <?php echo $th; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($isOut): ?>
                                                <span class="badge bg-danger">Out</span>
                                            <?php elseif ($isLow): ?>
                                                <span class="badge bg-warning text-dark">Low</span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">Normal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="stockUpdate.php?id=<?php echo (int)$p['productID']; ?>" class="btn btn-sm btn-outline-warning text-dark" title="Select to Adjust">
                                                <i class="fa-solid fa-sliders"></i>
                                            </a>
                                            <a href="editProduct.php?id=<?php echo (int)$p['productID']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Product">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
