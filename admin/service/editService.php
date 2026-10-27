<?php
session_start();
include('../../includes/config.php');
include('../../includes/admin_auth.php');
requireAdminRole(['Service & Repair Manager']);

$errors = [];
$fieldErrors = [];

$serviceID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceID = isset($_POST['serviceID']) ? (int)$_POST['serviceID'] : 0;
}

$allServices = [];
if ($conn) {
    $asRes = mysqli_query($conn, "SELECT serviceID, sku, name, price, endDate FROM service ORDER BY (endDate IS NULL) DESC, name ASC");
    if ($asRes) {
        while ($r = mysqli_fetch_assoc($asRes)) {
            $allServices[] = $r;
        }
    }
}

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

$categories = [];
if ($conn) {
    $catRes = mysqli_query($conn, "SELECT serviceCategoryID, name FROM serviceCategory ORDER BY name ASC");
    if ($catRes) {
        while ($row = mysqli_fetch_assoc($catRes)) {
            $categories[] = $row;
        }
    }
}

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

if (isset($_GET['set_primary_img']) && $conn) {
    $targetImgID = (int)$_GET['set_primary_img'];
    $findStmt = mysqli_prepare($conn, "SELECT imagePath FROM serviceImage WHERE serviceImageID = ? AND serviceID = ? LIMIT 1");
    if ($findStmt) {
        mysqli_stmt_bind_param($findStmt, 'ii', $targetImgID, $serviceID);
        mysqli_stmt_execute($findStmt);
        $findRes = mysqli_stmt_get_result($findStmt);
        if ($targetRow = mysqli_fetch_assoc($findRes)) {
            $newPrimaryPath = $targetRow['imagePath'];
            mysqli_query($conn, "UPDATE serviceImage SET isPrimary = 0 WHERE serviceID = " . (int)$serviceID);
            mysqli_query($conn, "UPDATE serviceImage SET isPrimary = 1 WHERE serviceImageID = " . (int)$targetImgID);
            $uServStmt = mysqli_prepare($conn, "UPDATE service SET image = ? WHERE serviceID = ?");
            mysqli_stmt_bind_param($uServStmt, 'si', $newPrimaryPath, $serviceID);
            mysqli_stmt_execute($uServStmt);
            mysqli_stmt_close($uServStmt);

            $_SESSION['success'] = 'Primary service photo updated.';
        }
        mysqli_stmt_close($findStmt);
    }
    header('Location: editService.php?id=' . $serviceID);
    exit;
}

if (isset($_GET['delete_img']) && $conn) {
    $targetImgID = (int)$_GET['delete_img'];
    $delStmt = mysqli_prepare($conn, "DELETE FROM serviceImage WHERE serviceImageID = ? AND serviceID = ?");
    if ($delStmt) {
        mysqli_stmt_bind_param($delStmt, 'ii', $targetImgID, $serviceID);
        mysqli_stmt_execute($delStmt);
        mysqli_stmt_close($delStmt);

        $chkPri = mysqli_query($conn, "SELECT imagePath FROM serviceImage WHERE serviceID = " . (int)$serviceID . " ORDER BY isPrimary DESC, serviceImageID ASC LIMIT 1");
        if ($chkRow = mysqli_fetch_assoc($chkPri)) {
            mysqli_query($conn, "UPDATE serviceImage SET isPrimary = 1 WHERE serviceID = " . (int)$serviceID . " AND imagePath = '" . mysqli_real_escape_string($conn, $chkRow['imagePath']) . "'");
            $uServ = mysqli_prepare($conn, "UPDATE service SET image = ? WHERE serviceID = ?");
            mysqli_stmt_bind_param($uServ, 'si', $chkRow['imagePath'], $serviceID);
            mysqli_stmt_execute($uServ);
            mysqli_stmt_close($uServ);
        } else {
            mysqli_query($conn, "UPDATE service SET image = '' WHERE serviceID = " . (int)$serviceID);
        }

        $_SESSION['success'] = 'Photo removed from service gallery.';
    }
    header('Location: editService.php?id=' . $serviceID);
    exit;
}

$serviceImages = [];
if ($conn) {
    $imgStmt = mysqli_prepare($conn, "SELECT serviceImageID, imagePath, isPrimary FROM serviceImage WHERE serviceID = ? ORDER BY isPrimary DESC, serviceImageID ASC");
    if ($imgStmt) {
        mysqli_stmt_bind_param($imgStmt, 'i', $serviceID);
        mysqli_stmt_execute($imgStmt);
        $imgRes = mysqli_stmt_get_result($imgStmt);
        while ($iRow = mysqli_fetch_assoc($imgRes)) {
            $serviceImages[] = $iRow;
        }
        mysqli_stmt_close($imgStmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service'])) {
    $rawName        = $_POST['name'] ?? '';
    $rawCategoryID  = $_POST['serviceCategoryID'] ?? '';
    $rawPrice       = $_POST['price'] ?? '';
    $rawDescription = $_POST['description'] ?? '';
    $rawImageSelect = $_POST['image_select'] ?? '';

    $name        = trim($rawName);
    $categoryID  = ($rawCategoryID !== '') ? (int)$rawCategoryID : 0;
    $newPrice    = -1;
    $description = trim($rawDescription);
    $imagePath   = trim($rawImageSelect ?: $service['image']);

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
        $newPrice = round((float)$rawPrice, 2);
    }

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
                $newFileName = 'serv_' . time() . '_' . $cleanBase . '.' . $ext;
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
        $oldPrice = (float)$service['price'];
        $isPriceChanged = (abs($newPrice - $oldPrice) >= 0.01);
        $sku = $service['sku'];

        if ($isPriceChanged) {

            mysqli_begin_transaction($conn);
            try {

                $closeStmt = mysqli_prepare($conn, "UPDATE service SET endDate = NOW() WHERE sku = ? AND endDate IS NULL");
                mysqli_stmt_bind_param($closeStmt, 's', $sku);
                mysqli_stmt_execute($closeStmt);
                mysqli_stmt_close($closeStmt);

                $insStmt = mysqli_prepare($conn, "INSERT INTO service (sku, name, image, description, price, serviceCategoryID, startDate, endDate) VALUES (?, ?, ?, ?, ?, ?, NOW(), NULL)");
                mysqli_stmt_bind_param($insStmt, 'ssssdi', $sku, $name, $imagePath, $description, $newPrice, $categoryID);
                mysqli_stmt_execute($insStmt);
                $newServiceID = mysqli_insert_id($conn);
                mysqli_stmt_close($insStmt);

                mysqli_query($conn, "INSERT INTO serviceImage (serviceID, imagePath, isPrimary)
                    SELECT $newServiceID, imagePath, isPrimary FROM serviceImage WHERE serviceID = " . (int)$serviceID);

                if (!empty($imagePath)) {
                    mysqli_query($conn, "UPDATE serviceImage SET isPrimary = 0 WHERE serviceID = $newServiceID");
                    $chkImg = mysqli_query($conn, "SELECT serviceImageID FROM serviceImage WHERE serviceID = $newServiceID AND imagePath = '" . mysqli_real_escape_string($conn, $imagePath) . "' LIMIT 1");
                    if ($chkRow = mysqli_fetch_assoc($chkImg)) {
                        mysqli_query($conn, "UPDATE serviceImage SET isPrimary = 1 WHERE serviceImageID = " . (int)$chkRow['serviceImageID']);
                    } else {
                        mysqli_query($conn, "INSERT INTO serviceImage (serviceID, imagePath, isPrimary) VALUES ($newServiceID, '" . mysqli_real_escape_string($conn, $imagePath) . "', 1)");
                    }
                }

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
                                $gFileName = 'serv_gal_' . time() . '_' . $idx . '_' . $cleanBase . '.' . $gExt;
                                if (move_uploaded_file($gTmp, $uploadTargetDir . $gFileName)) {
                                    $galPath = 'assets/images/uploads/' . $gFileName;
                                    mysqli_query($conn, "INSERT INTO serviceImage (serviceID, imagePath, isPrimary) VALUES ($newServiceID, '$galPath', 0)");
                                }
                            }
                        }
                    }
                }

                mysqli_commit($conn);
                $_SESSION['success'] = 'Service "' . htmlspecialchars($name) . '" updated with new labor fee &#8369;' . number_format($newPrice, 2) . ' (New rate history version created).';
                header('Location: index.php');
                exit;
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $errors[] = 'Failed to update service rate history: ' . $e->getMessage();
            }
        } else {

            $upStmt = mysqli_prepare($conn, "UPDATE service SET name = ?, image = ?, description = ?, serviceCategoryID = ? WHERE serviceID = ?");
            mysqli_stmt_bind_param($upStmt, 'sssii', $name, $imagePath, $description, $categoryID, $serviceID);

            if (mysqli_stmt_execute($upStmt)) {

                if (!empty($imagePath)) {
                    mysqli_query($conn, "UPDATE serviceImage SET isPrimary = 0 WHERE serviceID = " . (int)$serviceID);
                    $chkImg = mysqli_query($conn, "SELECT serviceImageID FROM serviceImage WHERE serviceID = " . (int)$serviceID . " AND imagePath = '" . mysqli_real_escape_string($conn, $imagePath) . "' LIMIT 1");
                    if ($chkRow = mysqli_fetch_assoc($chkImg)) {
                        mysqli_query($conn, "UPDATE serviceImage SET isPrimary = 1 WHERE serviceImageID = " . (int)$chkRow['serviceImageID']);
                    } else {
                        mysqli_query($conn, "INSERT INTO serviceImage (serviceID, imagePath, isPrimary) VALUES (" . (int)$serviceID . ", '" . mysqli_real_escape_string($conn, $imagePath) . "', 1)");
                    }
                }

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
                                $gFileName = 'serv_gal_' . time() . '_' . $idx . '_' . $cleanBase . '.' . $gExt;
                                if (move_uploaded_file($gTmp, $uploadTargetDir . $gFileName)) {
                                    $galPath = 'assets/images/uploads/' . $gFileName;
                                    mysqli_query($conn, "INSERT INTO serviceImage (serviceID, imagePath, isPrimary) VALUES (" . (int)$serviceID . ", '$galPath', 0)");
                                }
                            }
                        }
                    }
                }

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

    <?php include('../../includes/admin_nav.php'); ?>

    <div class="container py-4" style="max-width: 900px;">

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Service Management</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Service</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">
                    <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Service: <?php echo htmlspecialchars($service['name']); ?>
                </h1>
                <p class="text-muted small mb-0">Update service scope, labor fees, and workshop gallery.</p>
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

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <span class="fw-semibold text-dark">
                    <i class="fa-solid fa-sliders text-primary me-2"></i>Service Details &amp; Labor Rate
                </span>
            </div>
            <div class="card-body p-4">

                <form action="editService.php" method="post" enctype="multipart/form-data" novalidate>
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
                            <input type="text" id="name" name="name" 
                                   class="form-control <?php echo isset($fieldErrors['name']) ? 'is-invalid border-danger' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? $service['name']); ?>">
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
                                <?php 
                                $selectedCat = isset($_POST['serviceCategoryID']) ? (int)$_POST['serviceCategoryID'] : (int)$service['serviceCategoryID'];
                                foreach ($categories as $sc): 
                                ?>
                                    <option value="<?php echo (int)$sc['serviceCategoryID']; ?>" <?php echo ($selectedCat === (int)$sc['serviceCategoryID']) ? 'selected' : ''; ?>>
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
                                       value="<?php echo htmlspecialchars($_POST['price'] ?? $service['price']); ?>" 
                                       placeholder="0.00">
                            </div>
                            <?php if (isset($fieldErrors['price'])): ?>
                                <div class="text-danger small mt-1 ps-1 fw-medium"><?php echo htmlspecialchars($fieldErrors['price']); ?></div>
                            <?php else: ?>
                                <div class="form-text small text-muted"><i class="fa-solid fa-clock-rotate-left me-1"></i>Rate changes apply to new bookings while preserving historical records.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label small fw-semibold text-muted">Scope of Work &amp; Inclusions</label>
                        <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? $service['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="card bg-light border mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-semibold text-dark mb-0">
                                    <i class="fa-solid fa-images text-primary me-2"></i>Service Gallery Photos (<?php echo count($serviceImages); ?>)
                                </h6>
                                <span class="badge bg-light text-secondary border">
                                    Photo Showcase
                                </span>
                            </div>

                            <?php if (!empty($serviceImages)): ?>
                                <div class="row g-3 mb-4">
                                    <?php foreach ($serviceImages as $si): ?>
                                        <div class="col-6 col-sm-4 col-md-3">
                                            <div class="card h-100 border text-center p-2 shadow-sm position-relative <?php echo $si['isPrimary'] ? 'border-primary border-2 bg-white' : 'bg-white'; ?>">
                                                <div class="d-flex align-items-center justify-content-center bg-light rounded mb-2" style="height: 110px; overflow: hidden;">
                                                    <?php if (file_exists(__DIR__ . '/../../' . $si['imagePath'])): ?>
                                                        <img src="../../<?php echo htmlspecialchars($si['imagePath']); ?>" class="img-fluid rounded" style="max-height: 100%; object-fit: contain;">
                                                    <?php else: ?>
                                                        <div class="text-muted small"><i class="fa-solid fa-wrench fa-2x mb-1 d-block opacity-50"></i><?php echo htmlspecialchars(basename($si['imagePath'])); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-auto">
                                                    <?php if ($si['isPrimary']): ?>
                                                        <span class="badge bg-success w-100 py-1 mb-1"><i class="fa-solid fa-star me-1"></i>Primary Photo</span>
                                                    <?php else: ?>
                                                        <a href="editService.php?id=<?php echo $serviceID; ?>&set_primary_img=<?php echo $si['serviceImageID']; ?>" class="btn btn-outline-primary btn-sm w-100 py-1 mb-1" style="font-size: 0.72rem;">
                                                            Set as Primary
                                                        </a>
                                                        <a href="editService.php?id=<?php echo $serviceID; ?>&delete_img=<?php echo $si['serviceImageID']; ?>" class="btn btn-outline-danger btn-sm w-100 py-1" style="font-size: 0.72rem;" onclick="return confirm('Remove this photo from service gallery?');">
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
                                    <i class="fa-solid fa-image me-1"></i> No photos linked to this service yet. Upload photos below.
                                </div>
                            <?php endif; ?>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="image_file" class="form-label small fw-semibold text-muted">Upload / Replace Primary Image</label>
                                    <input type="file" id="image_file" name="image_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp">
                                    <div class="form-text small">Sets the main banner image for the service card.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="image_select" class="form-label small fw-semibold text-muted">Or Select Asset Path</label>
                                    <select id="image_select" name="image_select" class="form-select form-select-sm">
                                        <option value="">-- Keep Current Image (<?php echo htmlspecialchars($service['image'] ?? 'None'); ?>) --</option>
                                        <?php foreach ($existingImages as $img): ?>
                                            <option value="<?php echo htmlspecialchars($img); ?>" <?php echo (($service['image'] === $img) ? 'selected' : ''); ?>>
                                                <?php echo htmlspecialchars($img); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text small">Uses preloaded workshop images.</div>
                                </div>
                            </div>

                            <hr class="my-3 text-secondary opacity-25">

                            <div>
                                <label for="gallery_images" class="form-label small fw-semibold text-muted d-flex align-items-center gap-1">
                                    <i class="fa-solid fa-cloud-arrow-up text-info"></i>
                                    <span>Upload Additional Service Gallery Photos (Multiple)</span>
                                </label>
                                <input type="file" id="gallery_images" name="gallery_images[]" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp" multiple>
                                <div class="form-text small">
                                    Select multiple workshop photos, before/after shots, or equipment angles (Ctrl+Click or drag files). Allowed: JPG, PNG, WEBP (Max 5MB each).
                                </div>
                            </div>
                        </div>
                    </div>

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
