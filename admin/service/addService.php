<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');
requireAdminRole(['Service & Repair Manager']);

$errors = [];
$fieldErrors = [];

// Fetch service categories
$categories = [];
if ($conn) {
    $catRes = mysqli_query($conn, "SELECT serviceCategoryID, name FROM serviceCategory ORDER BY name ASC");
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $rawSku         = $_POST['sku'] ?? '';
    $rawName        = $_POST['name'] ?? '';
    $rawCategoryID  = $_POST['serviceCategoryID'] ?? '';
    $rawPrice       = $_POST['price'] ?? '';
    $rawDescription = $_POST['description'] ?? '';
    $rawImageSelect = $_POST['image_select'] ?? '';

    $sku         = trim($rawSku);
    $name        = trim($rawName);
    $categoryID  = ($rawCategoryID !== '') ? (int)$rawCategoryID : 0;
    $price       = -1;
    $description = trim($rawDescription);
    $imagePath   = trim($rawImageSelect);

    // 1. SKU validation
    if ($sku === '') {
        $fieldErrors['sku'] = 'Service SKU is required.';
        $errors[] = 'Service SKU is required.';
    } elseif (!preg_match('/^[A-Za-z0-9\-_]+$/', $sku)) {
        $fieldErrors['sku'] = 'SKU must only contain letters, numbers, hyphens, and underscores.';
        $errors[] = 'SKU must only contain letters, numbers, hyphens, and underscores.';
    } elseif (strlen($sku) < 3 || strlen($sku) > 30) {
        $fieldErrors['sku'] = 'SKU length must be between 3 and 30 characters.';
        $errors[] = 'SKU length must be between 3 and 30 characters.';
    }

    // 2. Name validation
    if ($name === '') {
        $fieldErrors['name'] = 'Service Package Title is required.';
        $errors[] = 'Service Package Title is required.';
    } elseif (strlen($name) < 2) {
        $fieldErrors['name'] = 'Service Name must be at least 2 characters long.';
        $errors[] = 'Service Name must be at least 2 characters long.';
    } elseif (strlen($name) > 100) {
        $fieldErrors['name'] = 'Service Name cannot exceed 100 characters.';
        $errors[] = 'Service Name cannot exceed 100 characters.';
    }

    // 3. Category validation
    if ($rawCategoryID === '' || $categoryID <= 0) {
        $fieldErrors['serviceCategoryID'] = 'Please select a valid service category.';
        $errors[] = 'Please select a valid service category.';
    } elseif ($conn) {
        $chkCat = mysqli_prepare($conn, "SELECT serviceCategoryID FROM serviceCategory WHERE serviceCategoryID = ? LIMIT 1");
        mysqli_stmt_bind_param($chkCat, 'i', $categoryID);
        mysqli_stmt_execute($chkCat);
        mysqli_stmt_store_result($chkCat);
        if (mysqli_stmt_num_rows($chkCat) === 0) {
            $fieldErrors['serviceCategoryID'] = 'The selected service category does not exist.';
            $errors[] = 'The selected service category does not exist.';
        }
        mysqli_stmt_close($chkCat);
    }

    // 4. Labor Fee / Price validation
    if (trim($rawPrice) === '') {
        $fieldErrors['price'] = 'Standard Labor Fee is required.';
        $errors[] = 'Standard Labor Fee is required.';
    } elseif (!is_numeric($rawPrice)) {
        $fieldErrors['price'] = 'Labor Fee must be a valid numeric amount (e.g. 500.00).';
        $errors[] = 'Labor Fee must be a valid numeric amount.';
    } elseif ((float)$rawPrice < 0) {
        $fieldErrors['price'] = 'Labor Fee / Price cannot be negative.';
        $errors[] = 'Labor Fee / Price cannot be negative.';
    } else {
        $price = round((float)$rawPrice, 2);
    }

    // 5. Check SKU uniqueness among active services
    if (!isset($fieldErrors['sku']) && $conn) {
        $chkStmt = mysqli_prepare($conn, "SELECT serviceID FROM service WHERE sku = ? AND endDate IS NULL LIMIT 1");
        mysqli_stmt_bind_param($chkStmt, 's', $sku);
        mysqli_stmt_execute($chkStmt);
        mysqli_stmt_store_result($chkStmt);
        if (mysqli_stmt_num_rows($chkStmt) > 0) {
            $fieldErrors['sku'] = 'An active service with SKU "' . htmlspecialchars($sku) . '" already exists.';
            $errors[] = 'An active service with SKU "' . htmlspecialchars($sku) . '" already exists.';
        }
        mysqli_stmt_close($chkStmt);
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
            $newFileName = 'serv_' . time() . '_' . $cleanBase . '.' . $ext;
            $destination = $uploadTargetDir . $newFileName;

            if (move_uploaded_file($fileTmp, $destination)) {
                $imagePath = 'assets/images/uploads/' . $newFileName;
            } else {
                $errors[] = 'Failed to upload image to server directory.';
            }
        }
    }

    // Insert Service
    if (empty($errors) && $conn) {
        $insStmt = mysqli_prepare($conn, "INSERT INTO service (sku, name, image, description, price, serviceCategoryID, startDate, endDate) VALUES (?, ?, ?, ?, ?, ?, NOW(), NULL)");
        mysqli_stmt_bind_param($insStmt, 'ssssdi', $sku, $name, $imagePath, $description, $price, $categoryID);

        if (mysqli_stmt_execute($insStmt)) {
            $newServiceID = mysqli_insert_id($conn);

            // Record primary image in serviceImage table
            if (!empty($imagePath)) {
                $siStmt = mysqli_prepare($conn, "INSERT INTO serviceImage (serviceID, imagePath, isPrimary) VALUES (?, ?, 1)");
                if ($siStmt) {
                    mysqli_stmt_bind_param($siStmt, 'is', $newServiceID, $imagePath);
                    mysqli_stmt_execute($siStmt);
                    mysqli_stmt_close($siStmt);
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
                            $gFileName = 'serv_gal_' . time() . '_' . $idx . '_' . $cleanBase . '.' . $gExt;
                            $gDest = $uploadTargetDir . $gFileName;

                            if (move_uploaded_file($gTmp, $gDest)) {
                                $galleryPath = 'assets/images/uploads/' . $gFileName;
                                $gStmt = mysqli_prepare($conn, "INSERT INTO serviceImage (serviceID, imagePath, isPrimary) VALUES (?, ?, 0)");
                                if ($gStmt) {
                                    mysqli_stmt_bind_param($gStmt, 'is', $newServiceID, $galleryPath);
                                    mysqli_stmt_execute($gStmt);
                                    mysqli_stmt_close($gStmt);
                                }
                            }
                        }
                    }
                }
            }

            $_SESSION['success'] = 'Service "' . htmlspecialchars($name) . '" (SKU: ' . htmlspecialchars($sku) . ') has been added successfully with photos.';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Database error: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($insStmt);
    }
}
$activeModule = 'service';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Service Package - PedalWorks Dynamics</title>
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
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Service Management</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add Service</li>
            </ol>
        </nav>

        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">
                    <i class="fa-solid fa-wrench text-primary me-2"></i>Add Service Package
                </h1>
                <p class="text-muted small mb-0">Create a new workshop repair package, tune-up service, or maintenance tier.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Services
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
                    <i class="fa-solid fa-circle-info text-primary me-2"></i>Service Details &amp; Labor Rate
                </span>
            </div>
            <div class="card-body p-4">
                <!-- Service Form -->
                <form action="addService.php" method="post" enctype="multipart/form-data" novalidate>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label for="sku" class="form-label small fw-semibold text-muted">Service SKU <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-barcode text-muted"></i></span>
                                <input type="text" id="sku" name="sku" 
                                       class="form-control font-monospace <?php echo isset($fieldErrors['sku']) ? 'is-invalid border-danger' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>" 
                                       placeholder="e.g. SERV-TUNE-005">
                            </div>
                            <?php if (isset($fieldErrors['sku'])): ?>
                                <div class="text-danger small mt-1 ps-1 fw-medium"><?php echo htmlspecialchars($fieldErrors['sku']); ?></div>
                            <?php else: ?>
                                <div class="form-text small">Unique service package code.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-7">
                            <label for="name" class="form-label small fw-semibold text-muted">Service Package Title <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" 
                                   class="form-control <?php echo isset($fieldErrors['name']) ? 'is-invalid border-danger' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                   placeholder="e.g. Complete Overhaul &amp; Brake Bleed">
                            <?php if (isset($fieldErrors['name'])): ?>
                                <div class="text-danger small mt-1 ps-1 fw-medium"><?php echo htmlspecialchars($fieldErrors['name']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="serviceCategoryID" class="form-label small fw-semibold text-muted">Category <span class="text-danger">*</span></label>
                            <select id="serviceCategoryID" name="serviceCategoryID" 
                                    class="form-select <?php echo isset($fieldErrors['serviceCategoryID']) ? 'is-invalid border-danger' : ''; ?>">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $sc): ?>
                                    <option value="<?php echo (int)$sc['serviceCategoryID']; ?>" <?php echo ((isset($_POST['serviceCategoryID']) && (int)$_POST['serviceCategoryID'] === (int)$sc['serviceCategoryID']) ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($sc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($fieldErrors['serviceCategoryID'])): ?>
                                <div class="text-danger small mt-1 ps-1 fw-medium"><?php echo htmlspecialchars($fieldErrors['serviceCategoryID']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="price" class="form-label small fw-semibold text-muted">Standard Labor Fee (&#8369;) <span class="text-danger">*</span></label>
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
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label small fw-semibold text-muted">Scope of Work &amp; Inclusions</label>
                        <textarea id="description" name="description" class="form-control" rows="4" placeholder="Detail the inspection points, cleaning procedure, adjustments, lubrication, and parts included in this service package..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <!-- Image Media Box -->
                    <div class="card bg-light border mb-4">
                        <div class="card-body">
                            <h6 class="fw-semibold text-dark mb-3">
                                <i class="fa-solid fa-image text-primary me-2"></i>Service Media Image
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="image_file" class="form-label small fw-semibold text-muted">Upload Service Image</label>
                                    <input type="file" id="image_file" name="image_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp">
                                    <div class="form-text small">Accepted: JPG, PNG, WEBP (Max 5MB).</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="image_select" class="form-label small fw-semibold text-muted">Or Select Existing Asset Path</label>
                                    <select id="image_select" name="image_select" class="form-select form-select-sm">
                                        <option value="">-- No Image / Select from Assets --</option>
                                        <?php foreach ($existingImages as $img): ?>
                                            <option value="<?php echo htmlspecialchars($img); ?>" <?php echo ((isset($_POST['image_select']) && $_POST['image_select'] === $img) ? 'selected' : ''); ?>>
                                                <?php echo htmlspecialchars($img); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text small">Uses preloaded workshop images.</div>
                                </div>
                            </div>

                            <hr class="my-3 text-secondary opacity-25">

                            <!-- Gallery Photos Upload -->
                            <div>
                                <label for="gallery_images" class="form-label small fw-semibold text-muted d-flex align-items-center gap-1">
                                    <i class="fa-solid fa-images text-info"></i>
                                    <span>Additional Service Gallery Photos</span>
                                </label>
                                <input type="file" id="gallery_images" name="gallery_images[]" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp" multiple>
                                <div class="form-text small">
                                    Select photos to add to the service gallery showcase. Allowed: JPG, PNG, WEBP (Max 5MB each).
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fa-solid fa-xmark me-1"></i>Cancel
                        </a>
                        <button type="submit" name="add_service" class="btn btn-primary px-4">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save and Add Service
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
