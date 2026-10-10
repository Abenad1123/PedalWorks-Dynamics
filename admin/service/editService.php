<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');
requireAdminRole(['Service & Repair Manager']);

$errors = [];

$serviceID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceID = isset($_POST['serviceID']) ? (int)$_POST['serviceID'] : 0;
}

// Fetch all services for selector dropdown if no ID given
$allServices = [];
if ($conn) {
    $asRes = mysqli_query($conn, "SELECT serviceID, sku, name, price, endDate FROM service ORDER BY (endDate IS NULL) DESC, name ASC");
    if ($asRes) {
        while ($r = mysqli_fetch_assoc($asRes)) {
            $allServices[] = $r;
        }
    }
}

// If no ID selected, display selection view
if ($serviceID <= 0) {
    $activeModule = 'service';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Select Service to Edit - PedalWorks Dynamics</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    </head>
    <body class="bg-light">
        <?php include('../../includes/admin_nav.php'); ?>

        <div class="container py-4" style="max-width: 650px;">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Service Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Select Service</li>
                </ol>
            </nav>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Select Service to Edit</h5>
                </div>
                <div class="card-body p-4">
                    <form method="get" action="editService.php">
                        <div class="mb-3">
                            <label for="id" class="form-label small fw-semibold text-muted">Choose Service Package:</label>
                            <select name="id" id="id" class="form-select" required>
                                <option value="">-- Choose Service --</option>
                                <?php foreach ($allServices as $s): ?>
                                    <option value="<?php echo (int)$s['serviceID']; ?>">
                                        <?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['sku']); ?>) &bull; &#8369;<?php echo number_format($s['price'], 2); ?>
                                        <?php echo ($s['endDate'] !== null) ? ' [Archived]' : ''; ?>
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

// Load service details
$service = null;
if ($conn) {
    $stmt = mysqli_prepare($conn, "SELECT s.*, sc.name AS categoryName 
                                  FROM service s 
                                  LEFT JOIN serviceCategory sc ON s.serviceCategoryID = sc.serviceCategoryID 
                                  WHERE s.serviceID = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $serviceID);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $service = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

if (!$service) {
    $_SESSION['error'] = 'Service not found.';
    header('Location: index.php');
    exit;
}

// Fetch categories
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

// Handle Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service'])) {
    $name = trim($_POST['name'] ?? '');
    $categoryID = isset($_POST['serviceCategoryID']) ? (int)$_POST['serviceCategoryID'] : 0;
    $newPrice = isset($_POST['price']) ? (float)$_POST['price'] : -1;
    $description = trim($_POST['description'] ?? '');
    $imagePath = trim($_POST['image_select'] ?? $service['image']);

    if ($name === '' || strlen($name) < 2) {
        $errors[] = 'Service Name must be at least 2 characters long.';
    }
    if ($categoryID <= 0) {
        $errors[] = 'Please select a valid service category.';
    }
    if ($newPrice < 0) {
        $errors[] = 'Labor Fee / Price must be a valid non-negative amount.';
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

    if (empty($errors) && $conn) {
        $oldPrice = (float)$service['price'];
        $isPriceChanged = (abs($newPrice - $oldPrice) >= 0.01);
        $sku = $service['sku'];

        if ($isPriceChanged) {
            // SCD Type 2: Close previous version, insert new version with new price
            mysqli_begin_transaction($conn);
            try {
                // 1. Close current active version
                $closeStmt = mysqli_prepare($conn, "UPDATE service SET endDate = NOW() WHERE sku = ? AND endDate IS NULL");
                mysqli_stmt_bind_param($closeStmt, 's', $sku);
                mysqli_stmt_execute($closeStmt);
                mysqli_stmt_close($closeStmt);

                // 2. Insert new version
                $insStmt = mysqli_prepare($conn, "INSERT INTO service (sku, name, image, description, price, serviceCategoryID, startDate, endDate) VALUES (?, ?, ?, ?, ?, ?, NOW(), NULL)");
                mysqli_stmt_bind_param($insStmt, 'ssssdi', $sku, $name, $imagePath, $description, $newPrice, $categoryID);
                mysqli_stmt_execute($insStmt);
                mysqli_stmt_close($insStmt);

                mysqli_commit($conn);
                $_SESSION['success'] = 'Service "' . htmlspecialchars($name) . '" updated with new labor fee &#8369;' . number_format($newPrice, 2) . ' (New rate history version created).';
                header('Location: index.php');
                exit;
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $errors[] = 'Failed to update service rate history: ' . $e->getMessage();
            }
        } else {
            // Update service in place
            $upStmt = mysqli_prepare($conn, "UPDATE service SET name = ?, image = ?, description = ?, serviceCategoryID = ? WHERE serviceID = ?");
            mysqli_stmt_bind_param($upStmt, 'sssii', $name, $imagePath, $description, $categoryID, $serviceID);

            if (mysqli_stmt_execute($upStmt)) {
                $_SESSION['success'] = 'Service "' . htmlspecialchars($name) . '" updated successfully.';
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'Failed to update service: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($upStmt);
        }
    }
}
$activeModule = 'service';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service - <?php echo htmlspecialchars($service['name']); ?> - PedalWorks Dynamics</title>
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
                <li class="breadcrumb-item active" aria-current="page">Edit Service</li>
            </ol>
        </nav>

        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">
                    <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Service: <?php echo htmlspecialchars($service['name']); ?>
                </h1>
                <p class="text-muted small mb-0">Modify service scope, categorization, pricing (SCD Type 2), or media banner.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Services
            </a>
        </div>

        <?php if ($service['endDate'] !== null): ?>
            <div class="alert alert-warning border-warning shadow-sm d-flex align-items-center mb-4" role="alert">
                <i class="fa-solid fa-clock-rotate-left fa-2x me-3 text-warning"></i>
                <div>
                    <strong>Archived Historical Record:</strong> This service package version concluded on <strong><?php echo date('M d, Y h:i A', strtotime($service['endDate'])); ?></strong>.
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
                    <i class="fa-solid fa-sliders text-primary me-2"></i>Service Details &amp; Labor Rate
                </span>
            </div>
            <div class="card-body p-4">
                <form action="editService.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="serviceID" value="<?php echo (int)$service['serviceID']; ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Permanent SKU</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="text" class="form-control font-monospace bg-light" value="<?php echo htmlspecialchars($service['sku']); ?>" disabled readonly>
                            </div>
                            <div class="form-text small">Immutable service code.</div>
                        </div>
                        <div class="col-md-8">
                            <label for="name" class="form-label small fw-semibold text-muted">Service Package Title <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? $service['name']); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="serviceCategoryID" class="form-label small fw-semibold text-muted">Category <span class="text-danger">*</span></label>
                            <select id="serviceCategoryID" name="serviceCategoryID" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <?php 
                                $selectedCat = isset($_POST['serviceCategoryID']) ? (int)$_POST['serviceCategoryID'] : (int)$service['serviceCategoryID'];
                                foreach ($categories as $sc): 
                                ?>
                                    <option value="<?php echo (int)$sc['serviceCategoryID']; ?>" <?php echo ($selectedCat === (int)$sc['serviceCategoryID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="price" class="form-label small fw-semibold text-muted">Standard Labor Fee (&#8369;) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white fw-bold">&#8369;</span>
                                <input type="number" id="price" name="price" class="form-control font-monospace" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['price'] ?? $service['price']); ?>" required>
                            </div>
                            <div class="form-text small text-info"><i class="fa-solid fa-clock-rotate-left me-1"></i>Modifying creates a new SCD Type 2 price record.</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label small fw-semibold text-muted">Scope of Work &amp; Inclusions</label>
                        <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? $service['description'] ?? ''); ?></textarea>
                    </div>

                    <!-- Media Assets Box -->
                    <div class="card bg-light border mb-4">
                        <div class="card-body">
                            <h6 class="fw-semibold text-dark mb-3">
                                <i class="fa-solid fa-image text-primary me-2"></i>Service Media Image
                            </h6>
                            <div class="row align-items-center g-3">
                                <div class="col-md-3 text-center">
                                    <div class="small fw-semibold text-muted mb-2">Current Preview</div>
                                    <?php if (!empty($service['image']) && file_exists(__DIR__ . '/../../' . $service['image'])): ?>
                                        <img src="../../<?php echo htmlspecialchars($service['image']); ?>" alt="Current preview" class="img-thumbnail shadow-sm rounded" style="max-height: 110px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary bg-opacity-10 text-secondary rounded py-4 border">
                                            <i class="fa-solid fa-wrench fa-2x d-block mb-1"></i>
                                            <span class="small">No Image</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9">
                                    <div class="mb-3">
                                        <label for="image_file" class="form-label small fw-semibold text-muted">Upload New Image File (Replaces current)</label>
                                        <input type="file" id="image_file" name="image_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp">
                                    </div>
                                    <div>
                                        <label for="image_select" class="form-label small fw-semibold text-muted">Or Select Existing Asset Path</label>
                                        <select id="image_select" name="image_select" class="form-select form-select-sm">
                                            <option value="">-- Keep Current Image (<?php echo htmlspecialchars($service['image'] ?? 'None'); ?>) --</option>
                                            <?php foreach ($existingImages as $img): ?>
                                                <option value="<?php echo htmlspecialchars($img); ?>" <?php echo (($service['image'] === $img) ? 'selected' : ''); ?>>
                                                    <?php echo htmlspecialchars($img); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fa-solid fa-xmark me-1"></i>Cancel
                        </a>
                        <button type="submit" name="update_service" class="btn btn-primary px-4">
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
