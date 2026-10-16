<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');
requireAdminRole(['Inventory Manager']);

$errors = [];
$fieldErrors = [];

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
    $rawSku               = $_POST['sku'] ?? '';
    $rawName              = $_POST['name'] ?? '';
    $rawCategoryID        = $_POST['productCategoryID'] ?? '';
    $rawPrice             = $_POST['price'] ?? '';
    $rawStock             = $_POST['stock'] ?? '';
    $rawLowStockThreshold = $_POST['lowStockThreshold'] ?? '';
    $rawDescription       = $_POST['description'] ?? '';
    $rawImageSelect       = $_POST['image_select'] ?? '';

    $sku               = trim($rawSku);
    $name              = trim($rawName);
    $categoryID        = ($rawCategoryID !== '') ? (int)$rawCategoryID : 0;
    $price             = -1;
    $stock             = -1;
    $lowStockThreshold = 5;
    $description       = trim($rawDescription);
    $imagePath         = trim($rawImageSelect);

    // 1. SKU validation (PHP)
    if ($sku === '') {
        $fieldErrors['sku'] = 'Product SKU is required.';
        $errors[] = 'Product SKU is required.';
    } elseif (!preg_match('/^[A-Za-z0-9\-_]+$/', $sku)) {
        $fieldErrors['sku'] = 'SKU must only contain letters, numbers, hyphens, and underscores.';
        $errors[] = 'SKU must only contain letters, numbers, hyphens, and underscores.';
    } elseif (strlen($sku) < 3 || strlen($sku) > 30) {
        $fieldErrors['sku'] = 'SKU length must be between 3 and 30 characters.';
        $errors[] = 'SKU length must be between 3 and 30 characters.';
    }

    // 2. Product Name validation (PHP)
    if ($name === '') {
        $fieldErrors['name'] = 'Product Title / Name is required.';
        $errors[] = 'Product Title / Name is required.';
    } elseif (strlen($name) < 2) {
        $fieldErrors['name'] = 'Product Name must be at least 2 characters long.';
        $errors[] = 'Product Name must be at least 2 characters long.';
    } elseif (strlen($name) > 100) {
        $fieldErrors['name'] = 'Product Name cannot exceed 100 characters.';
        $errors[] = 'Product Name cannot exceed 100 characters.';
    }

    // 3. Category validation (PHP)
    if ($rawCategoryID === '' || $categoryID <= 0) {
        $fieldErrors['productCategoryID'] = 'Please select a valid product category.';
        $errors[] = 'Please select a valid product category.';
    } elseif ($conn) {
        $catStmt = mysqli_prepare($conn, "SELECT productCategoryID FROM productCategory WHERE productCategoryID = ? LIMIT 1");
        mysqli_stmt_bind_param($catStmt, 'i', $categoryID);
        mysqli_stmt_execute($catStmt);
        mysqli_stmt_store_result($catStmt);
        if (mysqli_stmt_num_rows($catStmt) === 0) {
            $fieldErrors['productCategoryID'] = 'The selected category does not exist.';
            $errors[] = 'The selected category does not exist.';
        }
        mysqli_stmt_close($catStmt);
    }

    // 4. Retail Price validation (PHP)
    if (trim($rawPrice) === '') {
        $fieldErrors['price'] = 'Retail Price is required.';
        $errors[] = 'Retail Price is required.';
    } elseif (!is_numeric($rawPrice)) {
        $fieldErrors['price'] = 'Retail Price must be a valid numeric amount (e.g. 15999.00).';
        $errors[] = 'Retail Price must be a valid numeric amount.';
    } elseif ((float)$rawPrice < 0) {
        $fieldErrors['price'] = 'Retail Price cannot be negative.';
        $errors[] = 'Retail Price cannot be negative.';
    } else {
        $price = round((float)$rawPrice, 2);
    }

    // 5. Initial Stock validation (PHP)
    if (trim($rawStock) === '') {
        $fieldErrors['stock'] = 'Initial Stock quantity is required.';
        $errors[] = 'Initial Stock quantity is required.';
    } elseif (!ctype_digit(strval(trim($rawStock)))) {
        $fieldErrors['stock'] = 'Stock quantity must be a non-negative whole integer (e.g. 10).';
        $errors[] = 'Stock quantity must be a non-negative whole integer.';
    } else {
        $stock = (int)$rawStock;
    }

    // 6. Low Stock Alert Threshold validation (PHP)
    if (trim($rawLowStockThreshold) === '') {
        $lowStockThreshold = 5;
    } elseif (!ctype_digit(strval(trim($rawLowStockThreshold)))) {
        $fieldErrors['lowStockThreshold'] = 'Low stock threshold must be a non-negative whole integer.';
        $errors[] = 'Low stock threshold must be a non-negative whole integer.';
    } else {
        $lowStockThreshold = (int)$rawLowStockThreshold;
    }

    // 7. Check SKU uniqueness among active products (PHP)
    if (!isset($fieldErrors['sku']) && $conn) {
        $skuStmt = mysqli_prepare($conn, "SELECT productID FROM product WHERE sku = ? AND endDate IS NULL LIMIT 1");
        mysqli_stmt_bind_param($skuStmt, 's', $sku);
        mysqli_stmt_execute($skuStmt);
        mysqli_stmt_store_result($skuStmt);
        if (mysqli_stmt_num_rows($skuStmt) > 0) {
            $fieldErrors['sku'] = 'An active product with SKU "' . htmlspecialchars($sku) . '" already exists. Each active item must have a unique SKU.';
            $errors[] = 'An active product with SKU "' . htmlspecialchars($sku) . '" already exists.';
        }
        mysqli_stmt_close($skuStmt);
    }

    // 8. Image Upload validation (PHP)
    if (empty($errors) && isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error occurred (code: ' . (int)$_FILES['image_file']['error'] . ').';
        } else {
            $fileTmp = $_FILES['image_file']['tmp_name'];
            $fileName = $_FILES['image_file']['name'];
            $fileSize = $_FILES['image_file']['size'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowedExts)) {
                $fieldErrors['image_file'] = 'Invalid image file type. Allowed formats: JPG, JPEG, PNG, WEBP.';
                $errors[] = 'Invalid image file type. Allowed formats: JPG, JPEG, PNG, WEBP.';
            } elseif ($fileSize > 5 * 1024 * 1024) {
                $fieldErrors['image_file'] = 'Image file size exceeds the 5MB maximum limit.';
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
    }

    // Insert Product into Database
    if (empty($errors) && $conn) {
        $insStmt = mysqli_prepare($conn, "INSERT INTO product (sku, name, description, productCategoryID, image, price, stock, lowStockThreshold, startDate, endDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)");
        mysqli_stmt_bind_param($insStmt, 'sssissdi', $sku, $name, $description, $categoryID, $imagePath, $price, $stock, $lowStockThreshold);

        if (mysqli_stmt_execute($insStmt)) {
            $newProductID = mysqli_insert_id($conn);

            // Record primary image in productImage table
            if (!empty($imagePath)) {
                $piStmt = mysqli_prepare($conn, "INSERT INTO productImage (productID, imagePath, isPrimary) VALUES (?, ?, 1)");
                if ($piStmt) {
                    mysqli_stmt_bind_param($piStmt, 'is', $newProductID, $imagePath);
                    mysqli_stmt_execute($piStmt);
                    mysqli_stmt_close($piStmt);
                }
            }

            // Handle additional gallery photos upload
            if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
                $uploadTargetDir = __DIR__ . '/../../assets/images/uploads/';
                if (!is_dir($uploadTargetDir)) {
                    mkdir($uploadTargetDir, 0777, true);
                }
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

                foreach ($_FILES['gallery_images']['name'] as $idx => $gName) {
                    if ($_FILES['gallery_images']['error'][$idx] === UPLOAD_ERR_OK && !empty($gName)) {
                        $gTmp  = $_FILES['gallery_images']['tmp_name'][$idx];
                        $gSize = $_FILES['gallery_images']['size'][$idx];
                        $gExt  = strtolower(pathinfo($gName, PATHINFO_EXTENSION));

                        if (in_array($gExt, $allowedExts) && $gSize <= 5 * 1024 * 1024) {
                            $cleanBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($gName, PATHINFO_FILENAME));
                            $gFileName = 'prod_gal_' . time() . '_' . $idx . '_' . $cleanBase . '.' . $gExt;
                            $gDest = $uploadTargetDir . $gFileName;

                            if (move_uploaded_file($gTmp, $gDest)) {
                                $galleryPath = 'assets/images/uploads/' . $gFileName;
                                $gStmt = mysqli_prepare($conn, "INSERT INTO productImage (productID, imagePath, isPrimary) VALUES (?, ?, 0)");
                                if ($gStmt) {
                                    mysqli_stmt_bind_param($gStmt, 'is', $newProductID, $galleryPath);
                                    mysqli_stmt_execute($gStmt);
                                    mysqli_stmt_close($gStmt);
                                }
                            }
                        }
                    }
                }
            }

            $_SESSION['success'] = 'Product "' . htmlspecialchars($name) . '" (SKU: ' . htmlspecialchars($sku) . ') has been added successfully with photos.';
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
                <!-- Product Form -->
                <form action="addProduct.php" method="post" enctype="multipart/form-data" novalidate>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label for="sku" class="form-label small fw-semibold text-muted">Product SKU <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-barcode text-muted"></i></span>
                                <input type="text" id="sku" name="sku" 
                                       class="form-control font-monospace <?php echo isset($fieldErrors['sku']) ? 'is-invalid border-danger' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>" 
                                       placeholder="e.g. PROD-BIKE-021">
                            </div>
                            <?php if (isset($fieldErrors['sku'])): ?>
                                <div class="text-danger small mt-1 ps-1 fw-medium"><?php echo htmlspecialchars($fieldErrors['sku']); ?></div>
                            <?php else: ?>
                                <div class="form-text small">Unique item code (letters, numbers, hyphens).</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-7">
                            <label for="name" class="form-label small fw-semibold text-muted">Product Title / Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" 
                                   class="form-control <?php echo isset($fieldErrors['name']) ? 'is-invalid border-danger' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                   placeholder="e.g. TrailMaster Hardtail 29er">
                            <?php if (isset($fieldErrors['name'])): ?>
                                <div class="text-danger small mt-1 ps-1 fw-medium"><?php echo htmlspecialchars($fieldErrors['name']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="productCategoryID" class="form-label small fw-semibold text-muted">Category <span class="text-danger">*</span></label>
                            <select id="productCategoryID" name="productCategoryID" 
                                    class="form-select <?php echo isset($fieldErrors['productCategoryID']) ? 'is-invalid border-danger' : ''; ?>">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo (int)$c['productCategoryID']; ?>" <?php echo ((isset($_POST['productCategoryID']) && (int)$_POST['productCategoryID'] === (int)$c['productCategoryID']) ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($fieldErrors['productCategoryID'])): ?>
                                <div class="text-danger small mt-1 ps-1 fw-medium"><?php echo htmlspecialchars($fieldErrors['productCategoryID']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label for="price" class="form-label small fw-semibold text-muted">Retail Price (&#8369;) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white fw-bold">&#8369;</span>
                                <input type="text" id="price" name="price" 
                                       class="form-control font-monospace <?php echo isset($fieldErrors['price']) ? 'is-invalid border-danger' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" 
                                       placeholder="0.00">
                            </div>
                            <?php if (isset($fieldErrors['price'])): ?>
                                <div class="text-danger small mt-1 ps-1 fw-medium"><?php echo htmlspecialchars($fieldErrors['price']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2">
                            <label for="stock" class="form-label small fw-semibold text-muted">Initial Stock <span class="text-danger">*</span></label>
                            <input type="text" id="stock" name="stock" 
                                   class="form-control font-monospace <?php echo isset($fieldErrors['stock']) ? 'is-invalid border-danger' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($_POST['stock'] ?? ''); ?>" 
                                   placeholder="10">
                            <?php if (isset($fieldErrors['stock'])): ?>
                                <div class="text-danger small mt-1 ps-1 fw-medium"><?php echo htmlspecialchars($fieldErrors['stock']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2">
                            <label for="lowStockThreshold" class="form-label small fw-semibold text-muted">Alert At</label>
                            <input type="text" id="lowStockThreshold" name="lowStockThreshold" 
                                   class="form-control font-monospace <?php echo isset($fieldErrors['lowStockThreshold']) ? 'is-invalid border-danger' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($_POST['lowStockThreshold'] ?? '5'); ?>" 
                                   placeholder="5">
                            <?php if (isset($fieldErrors['lowStockThreshold'])): ?>
                                <div class="text-danger small mt-1 ps-1 fw-medium"><?php echo htmlspecialchars($fieldErrors['lowStockThreshold']); ?></div>
                            <?php else: ?>
                                <div class="form-text small">&le; Low stock</div>
                            <?php endif; ?>
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

                            <hr class="my-3 text-secondary opacity-25">

                            <!-- Gallery Photos Upload -->
                            <div>
                                <label for="gallery_images" class="form-label small fw-semibold text-muted d-flex align-items-center gap-1">
                                    <i class="fa-solid fa-images text-info"></i>
                                    <span>Additional Product Gallery Photos</span>
                                </label>
                                <input type="file" id="gallery_images" name="gallery_images[]" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp" multiple>
                                <div class="form-text small">
                                    Select photos to add to the product gallery showcase. Allowed: JPG, PNG, WEBP (Max 5MB each).
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
