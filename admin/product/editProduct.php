<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');
requireAdminRole(['Inventory Manager']);

$errors = [];
$fieldErrors = [];

$productID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productID = isset($_POST['productID']) ? (int)$_POST['productID'] : 0;
}

// Fetch all products for selector if no ID provided
$allProducts = [];
if ($conn) {
    $apRes = mysqli_query($conn, "SELECT productID, sku, name, price, endDate FROM product ORDER BY (endDate IS NULL) DESC, name ASC");
    if ($apRes) {
        while ($r = mysqli_fetch_assoc($apRes)) {
            $allProducts[] = $r;
        }
    }
}

// If no product ID selected yet, display selection view
if ($productID <= 0) {
    $activeModule = 'product';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Select Product to Edit - PedalWorks Dynamics</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    </head>
    <body class="bg-light">
        <?php include('../../includes/admin_nav.php'); ?>

        <div class="container py-4" style="max-width: 650px;">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Product Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Select Product</li>
                </ol>
            </nav>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Select Product to Edit</h5>
                </div>
                <div class="card-body p-4">
                    <form method="get" action="editProduct.php">
                        <div class="mb-3">
                            <label for="id" class="form-label small fw-semibold text-muted">Choose Item from Directory:</label>
                            <select name="id" id="id" class="form-select" required>
                                <option value="">-- Choose Product --</option>
                                <?php foreach ($allProducts as $p): ?>
                                    <option value="<?php echo (int)$p['productID']; ?>">
                                        <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['sku']); ?>) &bull; &#8369;<?php echo number_format($p['price'], 2); ?>
                                        <?php echo ($p['endDate'] !== null) ? ' [Archived]' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="index.php" class="btn btn-secondary btn-sm">Cancel</a>
                            <button type="submit" class="btn btn-primary btn-sm px-3">Proceed to Edit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// Load current product details
$product = null;
if ($conn) {
    $stmt = mysqli_prepare($conn, "SELECT p.*, pc.name AS categoryName 
                                  FROM product p 
                                  LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID 
                                  WHERE p.productID = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $productID);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

if (!$product) {
    $_SESSION['error'] = 'Product not found.';
    header('Location: index.php');
    exit;
}

// Fetch categories
$categories = [];
if ($conn) {
    $catRes = mysqli_query($conn, "SELECT productCategoryID, name FROM productCategory ORDER BY name ASC");
    if ($catRes) {
        while ($row = mysqli_fetch_assoc($catRes)) {
            $categories[] = $row;
        }
    }
}

// Fetch existing image assets
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

// Handle Set Primary Image Action
if (isset($_GET['set_primary_img']) && $conn) {
    $targetImgID = (int)$_GET['set_primary_img'];
    $findStmt = mysqli_prepare($conn, "SELECT imagePath FROM productImage WHERE productImageID = ? AND productID = ? LIMIT 1");
    if ($findStmt) {
        mysqli_stmt_bind_param($findStmt, 'ii', $targetImgID, $productID);
        mysqli_stmt_execute($findStmt);
        $findRes = mysqli_stmt_get_result($findStmt);
        if ($targetRow = mysqli_fetch_assoc($findRes)) {
            $newPrimaryPath = $targetRow['imagePath'];
            // Reset old primary
            mysqli_query($conn, "UPDATE productImage SET isPrimary = 0 WHERE productID = " . (int)$productID);
            // Set new primary
            mysqli_query($conn, "UPDATE productImage SET isPrimary = 1 WHERE productImageID = " . (int)$targetImgID);
            // Update product.image
            $uProdStmt = mysqli_prepare($conn, "UPDATE product SET image = ? WHERE productID = ?");
            mysqli_stmt_bind_param($uProdStmt, 'si', $newPrimaryPath, $productID);
            mysqli_stmt_execute($uProdStmt);
            mysqli_stmt_close($uProdStmt);

            $_SESSION['success'] = 'Primary product photo updated.';
        }
        mysqli_stmt_close($findStmt);
    }
    header('Location: editProduct.php?id=' . $productID);
    exit;
}

// Handle Delete Image Action
if (isset($_GET['delete_img']) && $conn) {
    $targetImgID = (int)$_GET['delete_img'];
    $delStmt = mysqli_prepare($conn, "DELETE FROM productImage WHERE productImageID = ? AND productID = ?");
    if ($delStmt) {
        mysqli_stmt_bind_param($delStmt, 'ii', $targetImgID, $productID);
        mysqli_stmt_execute($delStmt);
        mysqli_stmt_close($delStmt);

        // Check if any primary image remains
        $chkPri = mysqli_query($conn, "SELECT imagePath FROM productImage WHERE productID = " . (int)$productID . " ORDER BY isPrimary DESC, productImageID ASC LIMIT 1");
        if ($chkRow = mysqli_fetch_assoc($chkPri)) {
            mysqli_query($conn, "UPDATE productImage SET isPrimary = 1 WHERE productID = " . (int)$productID . " AND imagePath = '" . mysqli_real_escape_string($conn, $chkRow['imagePath']) . "'");
            $uProd = mysqli_prepare($conn, "UPDATE product SET image = ? WHERE productID = ?");
            mysqli_stmt_bind_param($uProd, 'si', $chkRow['imagePath'], $productID);
            mysqli_stmt_execute($uProd);
            mysqli_stmt_close($uProd);
        } else {
            mysqli_query($conn, "UPDATE product SET image = '' WHERE productID = " . (int)$productID);
        }

        $_SESSION['success'] = 'Photo removed from product gallery.';
    }
    header('Location: editProduct.php?id=' . $productID);
    exit;
}

// Fetch all photos for this product
$productImages = [];
if ($conn) {
    $imgStmt = mysqli_prepare($conn, "SELECT productImageID, imagePath, isPrimary FROM productImage WHERE productID = ? ORDER BY isPrimary DESC, productImageID ASC");
    if ($imgStmt) {
        mysqli_stmt_bind_param($imgStmt, 'i', $productID);
        mysqli_stmt_execute($imgStmt);
        $imgRes = mysqli_stmt_get_result($imgStmt);
        while ($iRow = mysqli_fetch_assoc($imgRes)) {
            $productImages[] = $iRow;
        }
        mysqli_stmt_close($imgStmt);
    }
}

// Handle Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $rawName              = $_POST['name'] ?? '';
    $rawCategoryID        = $_POST['productCategoryID'] ?? '';
    $rawPrice             = $_POST['price'] ?? '';
    $rawLowStockThreshold = $_POST['lowStockThreshold'] ?? '';
    $rawDescription       = $_POST['description'] ?? '';
    $rawImageSelect       = $_POST['image_select'] ?? '';

    $name              = trim($rawName);
    $categoryID        = ($rawCategoryID !== '') ? (int)$rawCategoryID : 0;
    $newPrice          = -1;
    $lowStockThreshold = 5;
    $description       = trim($rawDescription);
    $imagePath         = trim($rawImageSelect ?: $product['image']);

    // 1. Name validation
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

    // 2. Category validation
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

    // 3. Price validation
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
        $newPrice = round((float)$rawPrice, 2);
    }

    // 4. Low Stock Alert Threshold validation
    if (trim($rawLowStockThreshold) === '') {
        $fieldErrors['lowStockThreshold'] = 'Low stock threshold is required.';
        $errors[] = 'Low stock threshold is required.';
    } elseif (!ctype_digit(strval(trim($rawLowStockThreshold)))) {
        $fieldErrors['lowStockThreshold'] = 'Low stock threshold must be a non-negative whole integer.';
        $errors[] = 'Low stock threshold must be a non-negative whole integer.';
    } else {
        $lowStockThreshold = (int)$rawLowStockThreshold;
    }

    // 5. Handle Image Upload if provided
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

    if (empty($errors) && $conn) {
        $oldPrice = (float)$product['price'];
        $isPriceChanged = (abs($newPrice - $oldPrice) >= 0.01);
        $sku = $product['sku'];
        $currentStock = (int)$product['stock'];

        if ($isPriceChanged) {
            // Price History Versioning: Close previous version with endDate = NOW(), insert new version with new price
            mysqli_begin_transaction($conn);
            try {
                // 1. Close current active version
                $closeStmt = mysqli_prepare($conn, "UPDATE product SET endDate = NOW() WHERE sku = ? AND endDate IS NULL");
                mysqli_stmt_bind_param($closeStmt, 's', $sku);
                mysqli_stmt_execute($closeStmt);
                mysqli_stmt_close($closeStmt);

                // 2. Insert new version with updated price and attributes
                $insStmt = mysqli_prepare($conn, "INSERT INTO product (sku, name, description, productCategoryID, image, price, stock, lowStockThreshold, startDate, endDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)");
                mysqli_stmt_bind_param($insStmt, 'sssissdi', $sku, $name, $description, $categoryID, $imagePath, $newPrice, $currentStock, $lowStockThreshold);
                mysqli_stmt_execute($insStmt);
                $newProductID = mysqli_insert_id($conn);
                mysqli_stmt_close($insStmt);

                // Copy previous images to new product version
                mysqli_query($conn, "INSERT INTO productImage (productID, imagePath, isPrimary)
                    SELECT $newProductID, imagePath, isPrimary FROM productImage WHERE productID = " . (int)$productID);

                // Update primary if changed
                if (!empty($imagePath)) {
                    mysqli_query($conn, "UPDATE productImage SET isPrimary = 0 WHERE productID = $newProductID");
                    $chkImg = mysqli_query($conn, "SELECT productImageID FROM productImage WHERE productID = $newProductID AND imagePath = '" . mysqli_real_escape_string($conn, $imagePath) . "' LIMIT 1");
                    if ($chkRow = mysqli_fetch_assoc($chkImg)) {
                        mysqli_query($conn, "UPDATE productImage SET isPrimary = 1 WHERE productImageID = " . (int)$chkRow['productImageID']);
                    } else {
                        mysqli_query($conn, "INSERT INTO productImage (productID, imagePath, isPrimary) VALUES ($newProductID, '" . mysqli_real_escape_string($conn, $imagePath) . "', 1)");
                    }
                }

                // Upload additional gallery photos for new version
                if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
                    $uploadTargetDir = __DIR__ . '/../../assets/images/uploads/';
                    if (!is_dir($uploadTargetDir)) mkdir($uploadTargetDir, 0777, true);
                    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                    foreach ($_FILES['gallery_images']['name'] as $idx => $gName) {
                        if ($_FILES['gallery_images']['error'][$idx] === UPLOAD_ERR_OK && !empty($gName)) {
                            $gTmp  = $_FILES['gallery_images']['tmp_name'][$idx];
                            $gSize = $_FILES['gallery_images']['size'][$idx];
                            $gExt  = strtolower(pathinfo($gName, PATHINFO_EXTENSION));
                            if (in_array($gExt, $allowedExts) && $gSize <= 5 * 1024 * 1024) {
                                $cleanBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($gName, PATHINFO_FILENAME));
                                $gFileName = 'prod_gal_' . time() . '_' . $idx . '_' . $cleanBase . '.' . $gExt;
                                if (move_uploaded_file($gTmp, $uploadTargetDir . $gFileName)) {
                                    $galPath = 'assets/images/uploads/' . $gFileName;
                                    mysqli_query($conn, "INSERT INTO productImage (productID, imagePath, isPrimary) VALUES ($newProductID, '$galPath', 0)");
                                }
                            }
                        }
                    }
                }

                mysqli_commit($conn);
                $_SESSION['success'] = 'Product "' . htmlspecialchars($name) . '" updated with new price &#8369;' . number_format($newPrice, 2) . ' (New price history version created).';
                header('Location: index.php');
                exit;
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $errors[] = 'Failed to update product price history: ' . $e->getMessage();
            }
        } else {
            // Update product in place (no price change)
            $upStmt = mysqli_prepare($conn, "UPDATE product SET name = ?, description = ?, productCategoryID = ?, image = ?, lowStockThreshold = ? WHERE productID = ?");
            mysqli_stmt_bind_param($upStmt, 'ssisii', $name, $description, $categoryID, $imagePath, $lowStockThreshold, $productID);

            if (mysqli_stmt_execute($upStmt)) {
                // Sync primary image in productImage
                if (!empty($imagePath)) {
                    mysqli_query($conn, "UPDATE productImage SET isPrimary = 0 WHERE productID = " . (int)$productID);
                    $chkImg = mysqli_query($conn, "SELECT productImageID FROM productImage WHERE productID = " . (int)$productID . " AND imagePath = '" . mysqli_real_escape_string($conn, $imagePath) . "' LIMIT 1");
                    if ($chkRow = mysqli_fetch_assoc($chkImg)) {
                        mysqli_query($conn, "UPDATE productImage SET isPrimary = 1 WHERE productImageID = " . (int)$chkRow['productImageID']);
                    } else {
                        mysqli_query($conn, "INSERT INTO productImage (productID, imagePath, isPrimary) VALUES (" . (int)$productID . ", '" . mysqli_real_escape_string($conn, $imagePath) . "', 1)");
                    }
                }

                // Handle additional gallery images upload
                if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
                    $uploadTargetDir = __DIR__ . '/../../assets/images/uploads/';
                    if (!is_dir($uploadTargetDir)) mkdir($uploadTargetDir, 0777, true);
                    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                    foreach ($_FILES['gallery_images']['name'] as $idx => $gName) {
                        if ($_FILES['gallery_images']['error'][$idx] === UPLOAD_ERR_OK && !empty($gName)) {
                            $gTmp  = $_FILES['gallery_images']['tmp_name'][$idx];
                            $gSize = $_FILES['gallery_images']['size'][$idx];
                            $gExt  = strtolower(pathinfo($gName, PATHINFO_EXTENSION));
                            if (in_array($gExt, $allowedExts) && $gSize <= 5 * 1024 * 1024) {
                                $cleanBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($gName, PATHINFO_FILENAME));
                                $gFileName = 'prod_gal_' . time() . '_' . $idx . '_' . $cleanBase . '.' . $gExt;
                                if (move_uploaded_file($gTmp, $uploadTargetDir . $gFileName)) {
                                    $galPath = 'assets/images/uploads/' . $gFileName;
                                    mysqli_query($conn, "INSERT INTO productImage (productID, imagePath, isPrimary) VALUES (" . (int)$productID . ", '$galPath', 0)");
                                }
                            }
                        }
                    }
                }

                $_SESSION['success'] = 'Product "' . htmlspecialchars($name) . '" updated successfully.';
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'Failed to update product: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($upStmt);
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
    <title>Edit Product - <?php echo htmlspecialchars($product['name']); ?> - PedalWorks Dynamics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light">

    <!-- Top Admin Navigation Bar -->
    <?php include('../../includes/admin_nav.php'); ?>

    <div class="container py-4" style="max-width: 920px;">

        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Product Management</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Product</li>
            </ol>
        </nav>

        <!-- Header Section -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1">
                    <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Product: <?php echo htmlspecialchars($product['name']); ?>
                </h1>
                <p class="text-muted small mb-0">Update product specifications, active pricing, and media gallery.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="stockUpdate.php?id=<?php echo (int)$product['productID']; ?>" class="btn btn-outline-warning text-dark btn-sm">
                    <i class="fa-solid fa-boxes-stacked me-1"></i>Adjust Stock
                </a>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to Products
                </a>
            </div>
        </div>

        <?php if ($product['endDate'] !== null): ?>
            <div class="alert alert-warning border-warning shadow-sm d-flex align-items-center mb-4" role="alert">
                <i class="fa-solid fa-clock-rotate-left fa-2x me-3 text-warning"></i>
                <div>
                    <strong>Archived Historical Record:</strong> This product version concluded on <strong><?php echo date('M d, Y h:i A', strtotime($product['endDate'])); ?></strong>. Modifications here will update this historical entry or create a fresh live revision.
                </div>
            </div>
        <?php endif; ?>

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
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <span class="fw-semibold text-dark">
                    <i class="fa-solid fa-sliders text-primary me-2"></i>Product Attributes &amp; Pricing
                </span>
            </div>
            <div class="card-body p-4">
                <!-- Edit Product Form -->
                <form action="editProduct.php" method="post" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="productID" value="<?php echo (int)$product['productID']; ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Permanent SKU</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="text" class="form-control font-monospace bg-light" value="<?php echo htmlspecialchars($product['sku']); ?>" disabled readonly>
                            </div>
                            <div class="form-text small">Immutable identifier across all versions.</div>
                        </div>
                        <div class="col-md-8">
                            <label for="name" class="form-label small fw-semibold text-muted">Product Title / Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" 
                                   class="form-control <?php echo isset($fieldErrors['name']) ? 'is-invalid border-danger' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? $product['name']); ?>">
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
                                <?php 
                                $selectedCat = isset($_POST['productCategoryID']) ? (int)$_POST['productCategoryID'] : (int)$product['productCategoryID'];
                                foreach ($categories as $c): 
                                ?>
                                    <option value="<?php echo (int)$c['productCategoryID']; ?>" <?php echo ($selectedCat === (int)$c['productCategoryID']) ? 'selected' : ''; ?>>
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
                                       value="<?php echo htmlspecialchars($_POST['price'] ?? $product['price']); ?>" 
                                       placeholder="0.00">
                            </div>
                            <?php if (isset($fieldErrors['price'])): ?>
                                <div class="text-danger small mt-1 ps-1 fw-medium"><?php echo htmlspecialchars($fieldErrors['price']); ?></div>
                            <?php else: ?>
                                <div class="form-text small text-muted"><i class="fa-solid fa-clock-rotate-left me-1"></i>Price modifications update current catalog rate while preserving historical order records.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label for="lowStockThreshold" class="form-label small fw-semibold text-muted">Low Stock Alert Limit</label>
                            <input type="text" id="lowStockThreshold" name="lowStockThreshold" 
                                   class="form-control font-monospace <?php echo isset($fieldErrors['lowStockThreshold']) ? 'is-invalid border-danger' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($_POST['lowStockThreshold'] ?? $product['lowStockThreshold']); ?>">
                            <?php if (isset($fieldErrors['lowStockThreshold'])): ?>
                                <div class="text-danger small mt-1 ps-1 fw-medium"><?php echo htmlspecialchars($fieldErrors['lowStockThreshold']); ?></div>
                            <?php else: ?>
                                <div class="form-text small">Warning triggered at or below this value.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Current Inventory Info Banner -->
                    <div class="p-3 bg-light rounded border mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small fw-semibold d-block">Current Available Stock Level:</span>
                            <span class="fs-5 fw-bold <?php echo ((int)$product['stock'] <= (int)$product['lowStockThreshold']) ? 'text-warning' : 'text-success'; ?>">
                                <i class="fa-solid fa-boxes-stacked me-1"></i><?php echo (int)$product['stock']; ?> units in warehouse
                            </span>
                        </div>
                        <a href="stockUpdate.php?id=<?php echo (int)$product['productID']; ?>" class="btn btn-sm btn-outline-warning text-dark">
                            <i class="fa-solid fa-plus-minus me-1"></i>Quick Adjust Stock
                        </a>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label small fw-semibold text-muted">Product Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? $product['description'] ?? ''); ?></textarea>
                    </div>

                    <!-- Media Assets Box -->
                    <div class="card bg-light border mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-semibold text-dark mb-0">
                                    <i class="fa-solid fa-images text-primary me-2"></i>Product Gallery Photos (<?php echo count($productImages); ?>)
                                </h6>
                                <span class="badge bg-light text-secondary border">
                                    Photo Showcase
                                </span>
                            </div>

                            <!-- Current Gallery Photos Grid -->
                            <?php if (!empty($productImages)): ?>
                                <div class="row g-3 mb-4">
                                    <?php foreach ($productImages as $pi): ?>
                                        <div class="col-6 col-sm-4 col-md-3">
                                            <div class="card h-100 border text-center p-2 shadow-sm position-relative <?php echo $pi['isPrimary'] ? 'border-primary border-2 bg-white' : 'bg-white'; ?>">
                                                <div class="d-flex align-items-center justify-content-center bg-light rounded mb-2" style="height: 110px; overflow: hidden;">
                                                    <?php if (file_exists(__DIR__ . '/../../' . $pi['imagePath'])): ?>
                                                        <img src="../../<?php echo htmlspecialchars($pi['imagePath']); ?>" class="img-fluid rounded" style="max-height: 100%; object-fit: contain;">
                                                    <?php else: ?>
                                                        <div class="text-muted small"><i class="fa-solid fa-image fa-2x mb-1 d-block opacity-50"></i><?php echo htmlspecialchars(basename($pi['imagePath'])); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-auto">
                                                    <?php if ($pi['isPrimary']): ?>
                                                        <span class="badge bg-success w-100 py-1 mb-1"><i class="fa-solid fa-star me-1"></i>Primary Photo</span>
                                                    <?php else: ?>
                                                        <a href="editProduct.php?id=<?php echo $productID; ?>&set_primary_img=<?php echo $pi['productImageID']; ?>" class="btn btn-outline-primary btn-sm w-100 py-1 mb-1" style="font-size: 0.72rem;">
                                                            Set as Primary
                                                        </a>
                                                        <a href="editProduct.php?id=<?php echo $productID; ?>&delete_img=<?php echo $pi['productImageID']; ?>" class="btn btn-outline-danger btn-sm w-100 py-1" style="font-size: 0.72rem;" onclick="return confirm('Remove this photo from product gallery?');">
                                                            <i class="fa-solid fa-trash me-1"></i>Delete
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-secondary py-3 text-center small mb-3">
                                    <i class="fa-solid fa-image me-1"></i> No photos linked to this product yet. Upload photos below.
                                </div>
                            <?php endif; ?>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="image_file" class="form-label small fw-semibold text-muted">Upload / Replace Primary Image</label>
                                    <input type="file" id="image_file" name="image_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp">
                                    <div class="form-text small">Sets the main cover photo for the product card.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="image_select" class="form-label small fw-semibold text-muted">Or Select Asset Path</label>
                                    <select id="image_select" name="image_select" class="form-select form-select-sm">
                                        <option value="">-- Keep Current Image (<?php echo htmlspecialchars($product['image'] ?? 'None'); ?>) --</option>
                                        <?php foreach ($existingImages as $img): ?>
                                            <option value="<?php echo htmlspecialchars($img); ?>" <?php echo (($product['image'] === $img) ? 'selected' : ''); ?>>
                                                <?php echo htmlspecialchars($img); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text small">Uses preloaded photography assets.</div>
                                </div>
                            </div>

                            <hr class="my-3 text-secondary opacity-25">

                            <!-- Add Multiple Additional Photos to Gallery -->
                            <div>
                                <label for="gallery_images" class="form-label small fw-semibold text-muted d-flex align-items-center gap-1">
                                    <i class="fa-solid fa-cloud-arrow-up text-info"></i>
                                    <span>Upload Additional Gallery Photos (Multiple)</span>
                                </label>
                                <input type="file" id="gallery_images" name="gallery_images[]" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp" multiple>
                                <div class="form-text small">
                                    Select multiple files to append to the product gallery (Ctrl+Click or drag files). Allowed: JPG, PNG, WEBP (Max 5MB each).
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fa-solid fa-xmark me-1"></i>Cancel
                        </a>
                        <button type="submit" name="update_product" class="btn btn-primary px-4">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
