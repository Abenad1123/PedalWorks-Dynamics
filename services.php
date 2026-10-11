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
                <label for="search" class="form-label text-white-50 small mb-1 fw-semibold">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Search Service or SKU
                </label>
                <div class="input-group pw-input-group">
                    <span class="input-group-text pw-input-group-text">
                        <i class="fa-solid fa-search"></i>
                    </span>
                    <input type="text" class="form-control pw-form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="e.g. Repair, Cleaning, SERV-REP-001...">
                </div>
            </div>

            <div class="col-lg-3 col-md-3">
                <label for="category" class="form-label text-white-50 small mb-1 fw-semibold">
                    <i class="fa-solid fa-filter me-1"></i> Filter by Category
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
                    <i class="fa-solid fa-filter"></i> Apply
                </button>
                <?php if ($search !== '' || $category !== ''): ?>
                    <a href="services.php" class="pw-btn-glass text-nowrap" title="Reset Filters">
                        <i class="fa-solid fa-arrow-rotate-left"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Quick Category Strip -->
        <div class="pw-category-strip mt-3 mb-0 pt-3 border-top border-secondary border-opacity-25 justify-content-start">
            <a href="services.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" 
               class="pw-filter-pill <?php echo ($category === '') ? 'active' : ''; ?>">
                <i class="fa-solid fa-toolbox"></i> All Services
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="services.php?category=<?php echo urlencode($cat['name']); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="pw-filter-pill <?php echo ($category === $cat['name']) ? 'active' : ''; ?>">
                    <i class="fa-solid <?php echo getServiceIcon($cat['name']); ?> fa-xs"></i>
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

                        <!-- Description -->
                        <p class="pw-service-desc">
                            <?php echo htmlspecialchars($s['description'] ?? ''); ?>
                        </p>

                        <!-- Service Meta & Action -->
                        <div class="pw-service-meta mt-auto">
                            <div class="pw-service-fee">
                                <span>Base Labor Fee</span>
                                &#8369;<?php echo number_format($s['price'], 2); ?>
                            </div>
                            <button type="button" class="pw-btn-product-action" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#<?php echo $modalId; ?>"
                                    title="View Service Details">
                                <span>Details</span>
                                <i class="fa-solid fa-arrow-right fa-xs"></i>
                            </button>
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
                                        <?php if (!empty($s['image']) && file_exists(__DIR__ . '/' . $s['image'])): ?>
                                            <div class="pw-service-img-box" style="height: 240px;">
                                                <img src="/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($s['image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($s['name']); ?>" 
                                                     class="pw-service-img">
                                            </div>
                                        <?php else: ?>
                                            <div class="pw-hero-placeholder-inner" style="min-height: 240px;">
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
                                            <a href="#basecamp-info" class="pw-btn-trail-sm py-2 px-3" data-bs-dismiss="modal">
                                                <i class="fa-solid fa-location-dot me-1"></i> View Basecamp Location
                                            </a>
                                            <button type="button" class="pw-btn-glass-sm py-2 px-3" data-bs-dismiss="modal">
                                                Close
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer pw-modal-footer text-muted small justify-content-between">
                                <span><i class="fa-solid fa-wrench me-1 text-success"></i> Professional Mechanics Lab</span>
                                <span>Service SKU: <?php echo htmlspecialchars($s['sku']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

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
