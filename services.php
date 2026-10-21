<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('./includes/config.php');
include('./includes/header.php');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Fetch service categories for filter dropdown and quick pills
$categories = [];
if ($conn) {
    $catQuery = @mysqli_query($conn, "SELECT * FROM serviceCategory ORDER BY name ASC");
    if ($catQuery) {
        while ($row = mysqli_fetch_assoc($catQuery)) {
            $categories[] = $row;
        }
    }
}

// Build service search and filter query
$services = [];
if ($conn) {
    $where = ["s.endDate IS NULL"];
    $params = [];
    $types = "";

    if ($search !== '') {
        $where[] = "(s.name LIKE ? OR s.description LIKE ? OR s.sku LIKE ?)";
        $searchWildcard = "%" . $search . "%";
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $types .= "sss";
    }

    if ($category !== '') {
        $where[] = "sc.name = ?";
        $params[] = $category;
        $types .= "s";
    }

    $sql = "SELECT s.*, sc.name AS categoryName 
            FROM service s 
            LEFT JOIN serviceCategory sc ON s.serviceCategoryID = sc.serviceCategoryID 
            WHERE " . implode(" AND ", $where) . " 
            ORDER BY s.price ASC, s.name ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $services[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Reviews and verified availed service lookups
$serviceReviews = [];
$serviceRatingStats = [];
$customerAccountID = (int)($_SESSION['customerAccountID'] ?? 0);
$availedServices = [];
$userReviewsByService = [];

if ($conn) {
    $revSql = "SELECT r.reviewID, r.serviceID, r.customerAccountID, r.rating, r.cleanText, r.reviewText, r.createdAt, r.updatedAt,
                      ca.username,
                      c.firstName, c.lastName, c.photo
               FROM review r
               JOIN customerAccount ca ON r.customerAccountID = ca.customerAccountID
               JOIN customer c ON ca.customerID = c.customerID
               WHERE r.serviceID IS NOT NULL
               ORDER BY r.createdAt DESC";
    $rRes = @mysqli_query($conn, $revSql);
    if ($rRes) {
        while ($rRow = mysqli_fetch_assoc($rRes)) {
            $sId = (int)$rRow['serviceID'];
            $serviceReviews[$sId][] = $rRow;
            if (!isset($serviceRatingStats[$sId])) {
                $serviceRatingStats[$sId] = ['total' => 0, 'sum' => 0];
            }
            $serviceRatingStats[$sId]['total']++;
            $serviceRatingStats[$sId]['sum'] += (int)$rRow['rating'];
            
            if ($customerAccountID > 0 && (int)$rRow['customerAccountID'] === $customerAccountID) {
                $userReviewsByService[$sId] = $rRow;
            }
        }
    }

    if ($customerAccountID > 0) {
        $avStmt = mysqli_prepare($conn, "SELECT DISTINCT serviceID FROM customerService WHERE customerAccountID = ?");
        if ($avStmt) {
            mysqli_stmt_bind_param($avStmt, 'i', $customerAccountID);
            mysqli_stmt_execute($avStmt);
            $avRes = mysqli_stmt_get_result($avStmt);
            while ($avRow = mysqli_fetch_assoc($avRes)) {
                $availedServices[(int)$avRow['serviceID']] = true;
            }
            mysqli_stmt_close($avStmt);
        }
    }

    // Fetch all service gallery photos
    $serviceAllImages = [];
    $siQuery = @mysqli_query($conn, "SELECT serviceImageID, serviceID, imagePath, isPrimary FROM serviceImage ORDER BY isPrimary DESC, serviceImageID ASC");
    if ($siQuery) {
        while ($siRow = mysqli_fetch_assoc($siQuery)) {
            $serviceAllImages[(int)$siRow['serviceID']][] = $siRow;
        }
    }
}

// Helper function to pick icon by service category
function getServiceIcon($categoryName) {
    switch ($categoryName) {
        case 'Bicycle Repair':
            return 'fa-screwdriver-wrench';
        case 'Cleaning & Detailing':
            return 'fa-spray-can-sparkles';
        case 'Parts Installation':
            return 'fa-gears';
        case 'Custom Bike Building':
            return 'fa-bicycle';
        case 'Frame Painting':
            return 'fa-paint-roller';
        default:
            return 'fa-wrench';
    }
}
?>

<div class="container py-4">
    <?php include('./includes/alert.php'); ?>

    <!-- Workshop Hero Header -->
    <div class="row align-items-center mb-4 g-3">
        <div class="col-12">
            <h1 class="pw-hero-title mb-2">
                Workshop &amp; Repair <span class="pw-hero-title-accent">Services</span>
            </h1>
            <p class="pw-hero-desc mb-0">
                Precision bicycle maintenance, full builds, drivetrain ultrasonic washing, and frame restoration executed by master technicians at our Taguig service bay.
            </p>
        </div>
    </div>

    <!-- Workshop Highlights Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="pw-workshop-spec-card">
                <div class="pw-workshop-spec-icon text-warning">
                    <i class="fa-solid fa-certificate"></i>
                </div>
                <div>
                    <strong class="text-white d-block">Park Tool Calibrated</strong>
                    <span class="small text-white-50">Digital torque wrenches &amp; alignment specs</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="pw-workshop-spec-card">
                <div class="pw-workshop-spec-icon text-info">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <strong class="text-white d-block">Rapid Turnaround</strong>
                    <span class="small text-white-50">Same-day safety tune-ups &amp; installations</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="pw-workshop-spec-card">
                <div class="pw-workshop-spec-icon text-success">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <strong class="text-white d-block">7-Day Trail Warranty</strong>
                    <span class="small text-white-50">Free post-service ride check &amp; tune</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="pw-filter-card">
        <form method="GET" action="services.php" class="row g-3 align-items-center">
            <div class="col-lg-6 col-md-6">
                <label for="search" class="form-label text-secondary small mb-1 fw-semibold">
                    Search Services
                </label>
                <input type="text" class="form-control pw-form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by service name, SKU, or description...">
            </div>

            <div class="col-lg-3 col-md-3">
                <label for="category" class="form-label text-secondary small mb-1 fw-semibold">
                    Category
                </label>
                <select class="form-select pw-form-select" id="category" name="category">
                    <option value="">All Service Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['name']); ?>" <?php echo ($category === $cat['name']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-3 col-md-3 d-flex align-items-end gap-2 mt-md-4 pt-md-2">
                <button type="submit" class="pw-btn-trail w-100">
                    Apply
                </button>
                <?php if ($search !== '' || $category !== ''): ?>
                    <a href="services.php" class="pw-btn-glass text-nowrap" title="Reset Filters">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Quick Category Strip -->
        <div class="pw-category-strip mt-3 mb-0 pt-3 border-top border-secondary border-opacity-25 justify-content-start">
            <a href="services.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" 
               class="pw-filter-pill <?php echo ($category === '') ? 'active' : ''; ?>">
                All Services
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="services.php?category=<?php echo urlencode($cat['name']); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="pw-filter-pill <?php echo ($category === $cat['name']) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Active Filters Summary & Result Count -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div class="text-white-50 small">
            Showing <strong class="text-white"><?php echo count($services); ?></strong> workshop services
            <?php if ($category !== ''): ?>
                in <span class="badge rounded-pill text-white" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); font-weight: 500;"><?php echo htmlspecialchars($category); ?></span>
            <?php endif; ?>
            <?php if ($search !== ''): ?>
                matching <span class="badge rounded-pill" style="background: rgba(209, 102, 41, 0.15); border: 1px solid rgba(209, 102, 41, 0.3); color: var(--pw-trail-amber); font-weight: 500;">"<?php echo htmlspecialchars($search); ?>"</span>
            <?php endif; ?>
        </div>

        <?php if ($search !== '' || $category !== ''): ?>
            <div>
                <a href="services.php" class="text-muted small text-decoration-none">
                    <i class="fa-solid fa-xmark me-1"></i> Clear all filters
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Services Grid -->
    <?php if (empty($services)): ?>
        <div class="pw-filter-card text-center py-5">
            <div class="pw-feature-icon mb-3">
                <i class="fa-solid fa-wrench fa-2x text-muted"></i>
            </div>
            <h4 class="text-white fw-bold">No Services Found</h4>
            <p class="text-white-50 max-w-md mx-auto mb-4" style="max-width: 480px;">
                We couldn't find any workshop services matching your search or filter. Try clearing filters or exploring our standard packages.
            </p>
            <a href="services.php" class="pw-btn-trail">
                <i class="fa-solid fa-arrow-rotate-left me-1"></i> View All Services
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-5">
            <?php foreach ($services as $s): 
                $catName = $s['categoryName'] ?? 'General Service';
                $iconClass = getServiceIcon($catName);
                $modalId = 'servModal' . (int)$s['serviceID'];
            ?>
                <div class="col-lg-4 col-md-6">
                    <div class="pw-service-card">
                        <!-- Top Image / Category Visual Box -->
                        <?php if (!empty($s['image']) && file_exists(__DIR__ . '/' . $s['image'])): ?>
                            <div class="pw-service-img-box">
                                <img src="/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($s['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($s['name']); ?>" 
                                     class="pw-service-img">
                                <span class="pw-category-tag">
                                    <i class="fa-solid <?php echo $iconClass; ?> me-1"></i><?php echo htmlspecialchars($catName); ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="pw-service-icon-box mb-0">
                                    <i class="fa-solid <?php echo $iconClass; ?>"></i>
                                </div>
                                <span class="pw-service-pill">
                                    <i class="fa-solid fa-tag me-1"></i><?php echo htmlspecialchars($catName); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- SKU Badge -->
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="pw-sku-mono">
                                <i class="fa-solid fa-barcode me-1 opacity-75"></i><?php echo htmlspecialchars($s['sku']); ?>
                            </span>
                            <?php if (!empty($s['image']) && file_exists(__DIR__ . '/' . $s['image'])): ?>
                                <span class="small text-white-50">
                                    <?php echo htmlspecialchars($catName); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Service Title -->
                        <h4 class="pw-service-title" title="<?php echo htmlspecialchars($s['name']); ?>">
                            <?php echo htmlspecialchars($s['name']); ?>
                        </h4>

                        <?php 
                        $currSid = (int)$s['serviceID'];
                        if (!empty($serviceRatingStats[$currSid]['total'])): 
                            $sAvg = round($serviceRatingStats[$currSid]['sum'] / $serviceRatingStats[$currSid]['total'], 1);
                        ?>
                            <div class="d-flex align-items-center gap-1 mb-2 small">
                                <span class="text-warning"><i class="fa-solid fa-star"></i> <?php echo number_format($sAvg, 1); ?></span>
                                <span class="text-white-50">(<?php echo $serviceRatingStats[$currSid]['total']; ?> reviews)</span>
                            </div>
                        <?php endif; ?>

                        <!-- Description -->
                        <p class="pw-service-desc">
                            <?php echo htmlspecialchars($s['description'] ?? ''); ?>
                        </p>

                        <?php 
                        $sNameLower = strtolower($s['name']);
                        $turnaround = '24–48h Turnaround';
                        if (str_contains($sNameLower, 'wash') || str_contains($sNameLower, 'inspection') || str_contains($sNameLower, 'bleed') || str_contains($sNameLower, 'tune')) {
                            $turnaround = 'Same-Day / 24h';
                        } elseif (str_contains($sNameLower, 'paint') || str_contains($sNameLower, 'custom') || str_contains($sNameLower, 'overhaul') || str_contains($sNameLower, 'build')) {
                            $turnaround = '3–5 Days';
                        }
                        ?>
                        <div class="d-flex align-items-center justify-content-between mb-3 pt-2 border-top border-white border-opacity-10">
                            <span class="pw-turnaround-pill"><i class="fa-solid fa-clock-rotate-left"></i> <?php echo $turnaround; ?></span>
                            <span class="small text-white-50"><i class="fa-solid fa-screwdriver text-success me-1"></i>Labor Only</span>
                        </div>

                        <!-- Service Meta & Action -->
                        <div class="pw-service-meta mt-auto">
                            <div class="pw-service-fee">
                                <span>Base Labor Fee</span>
                                &#8369;<?php echo number_format($s['price'], 2); ?>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="pw-btn-product-action" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#<?php echo $modalId; ?>"
                                        title="View Service Details">
                                    <span>Details</span>
                                    <i class="fa-solid fa-arrow-right fa-xs"></i>
                                </button>
                                <?php if (!empty($_SESSION['customerAccountID'])): ?>
                                    <button type="button" class="btn btn-sm pw-btn-trail-sm py-2 px-3" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#bookModal_<?php echo $s['serviceID']; ?>"
                                            title="Schedule this service">
                                        <i class="fa-solid fa-calendar-check me-1"></i> Book
                                    </button>
                                <?php else: ?>
                                    <a href="login.php?redirect=services.php" class="btn btn-sm pw-btn-trail-sm py-2 px-3" title="Sign in to schedule service">
                                        <i class="fa-solid fa-calendar-check me-1"></i> Book
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Service Quick View Modal -->
                <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1" aria-labelledby="<?php echo $modalId; ?>Label" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content pw-modal-content">
                            <div class="modal-header pw-modal-header">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="pw-sku-badge"><?php echo htmlspecialchars($s['sku']); ?></span>
                                    <h5 class="modal-title fw-bold text-white mb-0" id="<?php echo $modalId; ?>Label">
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </h5>
                                </div>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="row g-4 align-items-center">
                                    <div class="col-md-5">
                                        <?php 
                                        $sImgs = $serviceAllImages[$s['serviceID']] ?? [];
                                        $sMainImg = !empty($sImgs) ? $sImgs[0]['imagePath'] : ($s['image'] ?? '');
                                        ?>
                                        <?php if (!empty($sMainImg) && file_exists(__DIR__ . '/' . $sMainImg)): ?>
                                            <div class="pw-service-img-box" style="height: 240px;">
                                                <img id="servMainImg_<?php echo $s['serviceID']; ?>" 
                                                     src="/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($sMainImg); ?>" 
                                                     alt="<?php echo htmlspecialchars($s['name']); ?>" 
                                                     class="pw-service-img" style="transition: opacity 0.2s ease;">
                                            </div>
                                            <?php if (count($sImgs) > 1): ?>
                                                <div class="d-flex flex-wrap gap-2 mt-2 justify-content-center">
                                                    <?php foreach ($sImgs as $sIdx => $sImg): ?>
                                                        <button type="button" 
                                                                class="btn p-0 rounded-2 border overflow-hidden" 
                                                                style="width: 48px; height: 48px; border-color: <?php echo ($sIdx === 0) ? '#ea580c' : 'rgba(255,255,255,0.2)'; ?> !important;"
                                                                onclick="document.getElementById('servMainImg_<?php echo $s['serviceID']; ?>').src = '/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($sImg['imagePath']); ?>'; this.parentElement.querySelectorAll('button').forEach(b => b.style.borderColor = 'rgba(255,255,255,0.2)'); this.style.borderColor = '#ea580c';">
                                                            <img src="/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($sImg['imagePath']); ?>" 
                                                                 style="width: 100%; height: 100%; object-fit: cover;">
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="p-4 rounded-3 text-center d-flex flex-column align-items-center justify-content-center" style="min-height: 240px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1);">
                                                <i class="fa-solid <?php echo $iconClass; ?> fa-3x text-warning mb-2"></i>
                                                <h6 class="text-white"><?php echo htmlspecialchars($catName); ?></h6>
                                                <span class="pw-sku-badge"><?php echo htmlspecialchars($s['sku']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-7">
                                        <div class="mb-3">
                                            <span class="badge rounded-pill text-white me-2" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); font-weight: 500;">
                                                <i class="fa-solid fa-tag me-1"></i><?php echo htmlspecialchars($catName); ?>
                                            </span>
                                            <span class="badge rounded-pill" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399; font-weight: 500;">
                                                <i class="fa-solid fa-shield-check me-1"></i>Workshop Certified
                                            </span>
                                        </div>

                                        <div class="pw-service-fee mb-3">
                                            <span class="text-white-50">Standard Labor Fee</span>
                                            <span style="font-size: 2rem; color: var(--pw-trail-amber); font-weight: 800; font-family: var(--pw-font-display);">
                                                &#8369;<?php echo number_format($s['price'], 2); ?>
                                            </span>
                                        </div>

                                        <h6 class="text-white fw-bold mb-2">Scope of Service &amp; Inclusions</h6>
                                        <p class="text-white-50 small mb-4" style="line-height: 1.7;">
                                            <?php echo nl2br(htmlspecialchars($s['description'] ?? '')); ?>
                                        </p>

                                        <div class="p-3 mb-4 rounded" style="background: rgba(255,255,255,0.04); border: 1px solid var(--pw-glass-border);">
                                            <div class="d-flex align-items-center gap-2 text-white-50 small mb-1">
                                                <i class="fa-solid fa-circle-info text-warning"></i>
                                                <span>Walk-ins welcome at our Taguig Basecamp Service Bay</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 text-white-50 small">
                                                <i class="fa-solid fa-phone text-success"></i>
                                                <span>Call dispatch: (02) 8823-2457 / +63 917 555 BIKE</span>
                                            </div>
                                        </div>

                                        <div class="d-flex flex-wrap gap-2">
                                            <?php if (!empty($_SESSION['customerAccountID'])): ?>
                                                <button type="button" class="pw-btn-trail py-2 px-3" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#bookModal_<?php echo $s['serviceID']; ?>">
                                                    <i class="fa-solid fa-calendar-check me-1"></i> Book Appointment
                                                </button>
                                            <?php else: ?>
                                                <a href="login.php?redirect=services.php" class="pw-btn-trail py-2 px-3">
                                                    <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Sign In to Book
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" class="pw-btn-glass-sm py-2 px-3" data-bs-dismiss="modal">
                                                Close
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Service Reviews Section -->
                                <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
                                <div class="mt-3">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                        <h6 class="text-white fw-bold mb-0 d-flex align-items-center gap-2">
                                            <i class="fa-solid fa-star text-warning"></i>
                                            <span>Customer Reviews (<?php echo count($serviceReviews[$currSid] ?? []); ?>)</span>
                                        </h6>
                                        <?php if (!empty($serviceRatingStats[$currSid]['total'])): ?>
                                            <div class="small text-white-50">
                                                Average Rating: <strong class="text-warning"><?php echo number_format($serviceRatingStats[$currSid]['sum'] / $serviceRatingStats[$currSid]['total'], 1); ?></strong> / 5.0
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Customer Availed Service Review Form -->
                                    <?php 
                                    $isServiceAvailed = !empty($availedServices[$currSid]);
                                    $sUserReview = $userReviewsByService[$currSid] ?? null;
                                    ?>
                                    <?php if ($isServiceAvailed): ?>
                                        <div class="p-3 mb-4 rounded-3" style="background: rgba(217, 119, 6, 0.08); border: 1px solid rgba(217, 119, 6, 0.25);">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="small fw-bold text-white d-flex align-items-center gap-1">
                                                    <i class="fa-solid fa-pen-to-square text-warning"></i>
                                                    <?php echo $sUserReview ? 'Update Your Workshop Experience' : 'Rate & Review This Service'; ?>
                                                </span>
                                                <span class="badge rounded-pill bg-success bg-opacity-25 text-success border border-success border-opacity-25 small" style="font-size: 0.65rem;">
                                                    <i class="fa-solid fa-check me-1"></i>Verified Customer
                                                </span>
                                            </div>

                                            <form action="review_action.php" method="POST" novalidate>
                                                <input type="hidden" name="serviceID" value="<?php echo $currSid; ?>">
                                                <input type="hidden" name="action" value="<?php echo $sUserReview ? 'update' : 'create'; ?>">
                                                <?php if ($sUserReview): ?>
                                                    <input type="hidden" name="reviewID" value="<?php echo (int)$sUserReview['reviewID']; ?>">
                                                <?php endif; ?>

                                                <div class="row g-2 mb-2 align-items-center">
                                                    <div class="col-sm-4">
                                                        <label class="form-label text-white-50 small mb-1">Your Rating</label>
                                                        <select name="rating" class="form-select form-select-sm pw-form-select">
                                                            <?php 
                                                            $currentRating = $sUserReview ? (int)$sUserReview['rating'] : 5;
                                                            for ($r = 5; $r >= 1; $r--): ?>
                                                                <option value="<?php echo $r; ?>" <?php echo ($currentRating === $r) ? 'selected' : ''; ?>>
                                                                    <?php echo str_repeat('★', $r) . str_repeat('☆', 5 - $r); ?> (<?php echo $r; ?> Star<?php echo $r > 1 ? 's' : ''; ?>)
                                                                </option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="mb-2">
                                                    <textarea name="reviewText" class="form-control form-control-sm pw-form-control" rows="2" 
                                                              placeholder="Share feedback on technician skill, turnaround time, or ride feel..."><?php echo htmlspecialchars($sUserReview['reviewText'] ?? ''); ?></textarea>
                                                </div>

                                                <div class="d-flex gap-2">
                                                    <button type="submit" class="btn btn-sm pw-btn-trail-sm py-1 px-3">
                                                        <i class="fa-solid fa-paper-plane me-1"></i> <?php echo $sUserReview ? 'Save Changes' : 'Submit Review'; ?>
                                                    </button>
                                                    <?php if ($sUserReview): ?>
                                                        <button type="submit" name="action" value="delete" class="btn btn-outline-danger btn-sm py-1 px-2" onclick="return confirm('Delete your review?');">
                                                            <i class="fa-solid fa-trash-can me-1"></i> Remove
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="p-3 mb-3 rounded text-center" style="background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1);">
                                            <span class="small text-white-50">
                                                <i class="fa-solid fa-circle-check text-warning me-1"></i>
                                                Reviews are unlocked for verified riders who have availed this workshop service.
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Reviews List -->
                                    <?php $currentReviews = $serviceReviews[$currSid] ?? []; ?>
                                    <?php if (empty($currentReviews)): ?>
                                        <p class="text-white-50 small mb-0 fst-italic">No reviews yet for this workshop service. Be the first to share your experience!</p>
                                    <?php else: ?>
                                        <div class="d-flex flex-column gap-2" style="max-height: 250px; overflow-y: auto;">
                                            <?php foreach ($currentReviews as $sRev): 
                                                $sRevName = trim($sRev['firstName'] . ' ' . $sRev['lastName']);
                                                if ($sRevName === '') $sRevName = $sRev['username'];
                                                $sAvatar = strtoupper(substr($sRevName, 0, 1));
                                            ?>
                                                <div class="p-2 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                                                                 style="width: 24px; height: 24px; font-size: 0.7rem; background: linear-gradient(135deg, #d97706, #ea580c);">
                                                                <?php echo htmlspecialchars($sAvatar); ?>
                                                            </div>
                                                            <span class="small fw-semibold text-white"><?php echo htmlspecialchars($sRevName); ?></span>
                                                            <span class="badge rounded-pill bg-success bg-opacity-20 text-success border border-success border-opacity-25" style="font-size: 0.6rem;">Verified</span>
                                                        </div>
                                                        <div class="text-warning small" style="font-size: 0.75rem;">
                                                            <?php 
                                                            for ($st = 1; $st <= 5; $st++) {
                                                                echo ($st <= (int)$sRev['rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star text-white-50"></i>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                    <div class="text-white-50 small ps-4">
                                                        <?php echo nl2br(htmlspecialchars($sRev['cleanText'])); ?>
                                                    </div>
                                                    <div class="text-end text-white-50" style="font-size: 0.65rem;">
                                                        <?php echo date('M d, Y', strtotime($sRev['createdAt'])); ?>
                                                        <?php if ($sRev['updatedAt'] && $sRev['updatedAt'] !== $sRev['createdAt']): ?>
                                                            &bull; (Updated)
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="modal-footer pw-modal-footer text-muted small justify-content-between">
                                <span><i class="fa-solid fa-wrench me-1 text-success"></i> Professional Mechanics Lab</span>
                                <span>Service SKU: <?php echo htmlspecialchars($s['sku']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Standalone Appointment Booking Modal (FR15) -->
                <?php if (!empty($_SESSION['customerAccountID'])): ?>
                <div class="modal fade" id="bookModal_<?php echo $s['serviceID']; ?>" tabindex="-1" aria-labelledby="bookModalLabel_<?php echo $s['serviceID']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content pw-modal-content">
                            <form action="service_booking_action.php" method="POST" novalidate>
                                <input type="hidden" name="serviceID" value="<?php echo (int)$s['serviceID']; ?>">
                                <div class="modal-header pw-modal-header">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-calendar-plus text-warning"></i>
                                        <h5 class="modal-title fw-bold text-white mb-0" id="bookModalLabel_<?php echo $s['serviceID']; ?>">
                                            Schedule Workshop Service
                                        </h5>
                                    </div>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <!-- Service Summary Card -->
                                    <div class="p-3 mb-3 rounded d-flex align-items-center justify-content-between" style="background: rgba(255,255,255,0.04); border: 1px solid var(--pw-glass-border);">
                                        <div>
                                            <div class="small text-white-50"><?php echo htmlspecialchars($catName); ?></div>
                                            <div class="fw-bold text-white"><?php echo htmlspecialchars($s['name']); ?></div>
                                        </div>
                                        <div class="text-end">
                                            <div class="small text-white-50">Base Labor Fee</div>
                                            <div class="fw-bold" style="color: var(--pw-trail-amber); font-size: 1.15rem;">&#8369;<?php echo number_format($s['price'], 2); ?></div>
                                        </div>
                                    </div>

                                    <!-- Preferred Scheduled Date & Time -->
                                    <div class="mb-3">
                                        <label class="form-label text-white small fw-bold">
                                            <i class="fa-solid fa-clock me-1 text-warning"></i> Preferred Date &amp; Time
                                        </label>
                                        <input type="datetime-local" 
                                               name="scheduledDate" 
                                               class="form-control pw-form-control" 
                                               min="<?php echo date('Y-m-d\TH:i'); ?>"
                                               value="<?php echo date('Y-m-d\TH:00', strtotime('+1 day 09:00')); ?>"
                                               required>
                                        <div class="form-text text-white-50 small">Workshop operates Monday to Saturday, 8:00 AM - 6:00 PM.</div>
                                    </div>

                                    <!-- Bike Model & Issue Description -->
                                    <div class="mb-3">
                                        <label class="form-label text-white small fw-bold">
                                            <i class="fa-solid fa-bicycle me-1 text-warning"></i> Bike Model &amp; Issue Notes
                                        </label>
                                        <textarea name="notes" 
                                                  class="form-control pw-form-control" 
                                                  rows="3" 
                                                  placeholder="e.g., Specialized Rockhopper 29er. Rear hydraulic brake lever feels spongy, needs bleeding and new organic pads."></textarea>
                                        <div class="form-text text-white-50 small">Briefly describe the symptoms or specific parts you'd like us to inspect.</div>
                                    </div>

                                    <!-- REI-Inspired Pre-Dropoff Checklist -->
                                    <div class="p-3 rounded mb-2" style="background: rgba(255,255,255,0.03); border: 1px dashed rgba(255,255,255,0.15);">
                                        <div class="small fw-bold text-white mb-1">
                                            <i class="fa-solid fa-clipboard-check text-warning me-1"></i> Pre-Dropoff Checklist &amp; Trust Policy:
                                        </div>
                                        <ul class="text-white-50 small mb-0 ps-3" style="font-size: 0.78rem; line-height: 1.6;">
                                            <li>Please wipe down excessive mud before bringing the bike into the service bay.</li>
                                            <li>Bring proprietary battery keys/chargers if bringing an e-bike.</li>
                                            <li>Labor is guaranteed for 7 days of post-service trail riding.</li>
                                            <li>Any required replacement parts will be quoted for your approval before installation.</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="modal-footer pw-modal-footer">
                                    <button type="button" class="pw-btn-glass-sm py-2 px-3" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="pw-btn-trail py-2 px-3">
                                        <i class="fa-solid fa-paper-plane me-1"></i> Submit Request
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Basecamp Walk-in & Workshop Guarantee Banner -->
    <div class="pw-workshop-banner" id="basecamp-info">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="pw-section-eyebrow mb-2">
                    <i class="fa-solid fa-location-dot me-1"></i> Taguig Basecamp &amp; Service Bay
                </div>
                <h3 class="pw-workshop-title text-white">
                    Walk-in Bike Checkup &amp; Diagnostic Bay
                </h3>
                <p class="pw-workshop-desc">
                    Bring your bicycle directly to our workshop lab in Western Bicutan, Taguig. Our mechanics conduct a comprehensive multi-point safety assessment before quoting and commencing any repairs.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <div class="pw-store-badge">
                        <i class="fa-solid fa-map-pin text-warning fs-5"></i>
                        <div class="text-start">
                            <div class="text-white fw-bold small">Service Location</div>
                            <div class="small text-white-50">Km. 14 East Service Rd, Taguig City 1630</div>
                        </div>
                    </div>
                    <div class="pw-store-badge">
                        <i class="fa-solid fa-clock text-info fs-5"></i>
                        <div class="text-start">
                            <div class="text-white fw-bold small">Operating Hours</div>
                            <div class="small text-white-50">Mon – Sat: 8:00 AM – 6:30 PM | Sun: 9:00 AM – 3:00 PM</div>
                        </div>
                    </div>
                    <div class="pw-store-badge">
                        <i class="fa-solid fa-phone text-success fs-5"></i>
                        <div class="text-start">
                            <div class="text-white fw-bold small">Direct Dispatch</div>
                            <div class="small text-white-50">(02) 8823-2457 / +63 917 555 BIKE</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end text-center mt-4 mt-lg-0">
                <a href="products.php" class="pw-btn-glass py-3 px-4 d-inline-flex align-items-center gap-2">
                    <i class="fa-solid fa-boxes-stacked"></i> Browse Parts &amp; Gear
                </a>
            </div>
        </div>
    </div>
</div>

<?php include('./includes/footer.php'); ?>
