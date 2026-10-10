<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');
requireAdminRole(['Inventory Manager']);

$errors = [];

// Fetch product categories
$categories = [];
if ($conn) {
    $catRes = mysqli_query($conn, "SELECT productCategoryID, name FROM productCategory ORDER BY name ASC");
    if ($catRes) {
        while ($row = mysqli_fetch_assoc($catRes)) {
            $categories[] = $row;
        }
    }
}

// Fetch existing image files in assets/images/ for convenience
$existingImages = [];
$assetsDir = __DIR__ . '/../../assets/images';
if (is_dir($assetsDir)) {
    $files = scandir($assetsDir);
    foreach ($files as $f) {
        if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp'])) {
            $existingImages[] = 'assets/images/' . $f;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $sku = trim($_POST['sku'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $categoryID = isset($_POST['productCategoryID']) ? (int)$_POST['productCategoryID'] : 0;
    $price = isset($_POST['price']) ? (float)$_POST['price'] : -1;
    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : -1;
    $lowStockThreshold = isset($_POST['lowStockThreshold']) ? (int)$_POST['lowStockThreshold'] : 5;
    $description = trim($_POST['description'] ?? '');
    $imagePath = trim($_POST['image_select'] ?? '');

    // Validation
    if ($sku === '') {
        $errors[] = 'SKU is required.';
    } elseif (!preg_match('/^[A-Za-z0-9\-_]+$/', $sku)) {
        $errors[] = 'SKU must only contain letters, numbers, hyphens, and underscores.';
    }

    if ($name === '' || strlen($name) < 2) {
        $errors[] = 'Product Name must be at least 2 characters long.';
    }

    if ($categoryID <= 0) {
        $errors[] = 'Please select a valid product category.';
    }

    if ($price < 0) {
        $errors[] = 'Price must be a valid positive amount.';
    }

    if ($stock < 0) {
        $errors[] = 'Stock quantity cannot be negative.';
    }

    if ($lowStockThreshold < 0) {
        $errors[] = 'Low Stock Threshold cannot be negative.';
    }

    // Check SKU uniqueness among active products
    if (empty($errors) && $conn) {
        $skuStmt = mysqli_prepare($conn, "SELECT productID FROM product WHERE sku = ? AND endDate IS NULL LIMIT 1");
        mysqli_stmt_bind_param($skuStmt, 's', $sku);
        mysqli_stmt_execute($skuStmt);
        mysqli_stmt_store_result($skuStmt);
        if (mysqli_stmt_num_rows($skuStmt) > 0) {
            $errors[] = 'An active product with SKU "' . htmlspecialchars($sku) . '" already exists. Each active item must have a unique SKU.';
        }
        mysqli_stmt_close($skuStmt);
    }

    // Handle Image Upload if provided
    if (empty($errors) && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['image_file']['tmp_name'];
        $fileName = $_FILES['image_file']['name'];
        $fileSize = $_FILES['image_file']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedExts)) {
            $errors[] = 'Invalid image file type. Allowed formats: JPG, JPEG, PNG, WEBP.';
        } elseif ($fileSize > 5 * 1024 * 1024) {
            $errors[] = 'Image file size exceeds the 5MB maximum limit.';
        } else {
            $uploadTargetDir = __DIR__ . '/../../assets/images/uploads/';
            if (!is_dir($uploadTargetDir)) {
                mkdir($uploadTargetDir, 0777, true);
            }
            $cleanBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
            $newFileName = 'prod_' . time() . '_' . $cleanBase . '.' . $ext;
            $destination = $uploadTargetDir . $newFileName;

            if (move_uploaded_file($fileTmp, $destination)) {
                $imagePath = 'assets/images/uploads/' . $newFileName;
            } else {
                $errors[] = 'Failed to upload image to server directory.';
            }
        }
    }

    // Insert Product into Database
    if (empty($errors) && $conn) {
        $insStmt = mysqli_prepare($conn, "INSERT INTO product (sku, name, description, productCategoryID, image, price, stock, lowStockThreshold, startDate, endDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)");
        mysqli_stmt_bind_param($insStmt, 'sssissdi', $sku, $name, $description, $categoryID, $imagePath, $price, $stock, $lowStockThreshold);

        if (mysqli_stmt_execute($insStmt)) {
            $_SESSION['success'] = 'Product "' . htmlspecialchars($name) . '" (SKU: ' . htmlspecialchars($sku) . ') has been added successfully.';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Database error: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($insStmt);
    }
}
$activeModule = 'product';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product - PedalWorks Dynamics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light">

    <!-- Top Admin Navigation Bar -->
    <?php include('../../includes/admin_nav.php'); ?>

    <div class="container py-4" style="max-width: 900px;">

        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Product Management</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add Product</li>
            </ol>
        </nav>

        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">
                    <i class="fa-solid fa-box-open text-primary me-2"></i>Add New Product
                </h1>
                <p class="text-muted small mb-0">Create a new bicycle, part, accessory, or gear item for the online catalog.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Products
            </a>
        </div>

        <!-- Validation Errors -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <h6 class="alert-heading fw-bold mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>Please resolve the following issues:</h6>
                <ul class="mb-0 small ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <span class="fw-semibold text-dark">
                    <i class="fa-solid fa-circle-info text-primary me-2"></i>Product Specifications
                </span>
            </div>
            <div class="card-body p-4">
                <form action="addProduct.php" method="post" enctype="multipart/form-data">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label for="sku" class="form-label small fw-semibold text-muted">Product SKU <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-barcode text-muted"></i></span>
                                <input type="text" id="sku" name="sku" class="form-control font-monospace" value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>" placeholder="e.g. PROD-BIKE-021" required>
                            </div>
                            <div class="form-text small">Unique item code (letters, numbers, hyphens).</div>
                        </div>
                        <div class="col-md-7">
                            <label for="name" class="form-label small fw-semibold text-muted">Product Title / Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" placeholder="e.g. TrailMaster Hardtail 29er" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="productCategoryID" class="form-label small fw-semibold text-muted">Category <span class="text-danger">*</span></label>
                            <select id="productCategoryID" name="productCategoryID" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo (int)$c['productCategoryID']; ?>" <?php echo ((isset($_POST['productCategoryID']) && (int)$_POST['productCategoryID'] === (int)$c['productCategoryID']) ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="price" class="form-label small fw-semibold text-muted">Retail Price (&#8369;) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white fw-bold">&#8369;</span>
                                <input type="number" id="price" name="price" class="form-control font-monospace" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['price'] ?? '0.00'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label for="stock" class="form-label small fw-semibold text-muted">Initial Stock <span class="text-danger">*</span></label>
                            <input type="number" id="stock" name="stock" class="form-control font-monospace" min="0" value="<?php echo htmlspecialchars($_POST['stock'] ?? '10'); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label for="lowStockThreshold" class="form-label small fw-semibold text-muted">Alert At</label>
                            <input type="number" id="lowStockThreshold" name="lowStockThreshold" class="form-control font-monospace" min="0" value="<?php echo htmlspecialchars($_POST['lowStockThreshold'] ?? '5'); ?>" required>
                            <div class="form-text small">&le; Low stock</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label small fw-semibold text-muted">Product Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4" placeholder="Detailed product specifications, frame material, components, rider fit, etc..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <!-- Image Media Card -->
                    <div class="card bg-light border mb-4">
                        <div class="card-body">
                            <h6 class="fw-semibold text-dark mb-3">
                                <i class="fa-solid fa-image text-primary me-2"></i>Product Image Media
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="image_file" class="form-label small fw-semibold text-muted">Upload New Image File</label>
                                    <input type="file" id="image_file" name="image_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp">
                                    <div class="form-text small">Accepted: JPG, PNG, WEBP (Max 5MB).</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="image_select" class="form-label small fw-semibold text-muted">Or Select from Existing Assets</label>
                                    <select id="image_select" name="image_select" class="form-select form-select-sm">
                                        <option value="">-- No Image / Select from Assets --</option>
                                        <?php foreach ($existingImages as $img): ?>
                                            <option value="<?php echo htmlspecialchars($img); ?>" <?php echo ((isset($_POST['image_select']) && $_POST['image_select'] === $img) ? 'selected' : ''); ?>>
                                                <?php echo htmlspecialchars($img); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text small">Uses preloaded photography assets.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fa-solid fa-xmark me-1"></i>Cancel
                        </a>
                        <button type="submit" name="add_product" class="btn btn-primary px-4">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save and Add Product
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
