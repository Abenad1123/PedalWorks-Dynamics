<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('./includes/config.php');
include('./includes/header.php');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Fetch categories for filter dropdown and pills
$categories = [];
if ($conn) {
    $catQuery = @mysqli_query($conn, "SELECT * FROM productCategory ORDER BY name ASC");
    if ($catQuery) {
        while ($row = mysqli_fetch_assoc($catQuery)) {
            $categories[] = $row;
        }
    }
}

// Build product search and filter query
$products = [];
if ($conn) {
    $where = ["p.endDate IS NULL"];
    $params = [];
    $types = "";

    if ($search !== '') {
        $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
        $searchWildcard = "%" . $search . "%";
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $types .= "sss";
    }

    if ($category !== '') {
        $where[] = "pc.name = ?";
        $params[] = $category;
        $types .= "s";
    }

    $sql = "SELECT p.*, pc.name AS categoryName 
            FROM product p 
            LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID 
            WHERE " . implode(" AND ", $where) . " 
            ORDER BY p.name ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $products[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<div class="container py-4">
    <?php include('./includes/alert.php'); ?>

    <!-- Catalog Hero Section -->
    <div class="row align-items-center mb-4 g-3">
        <div class="col-lg-8">
            <h1 class="pw-hero-title mb-2">
                Adventure Bicycles &amp; <span class="pw-hero-title-accent">Gear</span>
            </h1>
            <p class="pw-hero-desc mb-0">
                Browse our curated trail catalog featuring mountain rigs, gravel racers, road machines, frames, and high-precision replacement components.
            </p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <div class="pw-stat-pill d-inline-flex">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span>Total Active Gear: <strong><?php echo count($products); ?></strong> items</span>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="pw-filter-card">
        <form method="GET" action="products.php" class="row g-3 align-items-center">
            <div class="col-lg-6 col-md-6">
                <label for="search" class="form-label text-white-50 small mb-1 fw-semibold">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Search by Name, SKU, or Description
                </label>
                <div class="input-group pw-input-group">
                    <span class="input-group-text pw-input-group-text">
                        <i class="fa-solid fa-search"></i>
                    </span>
                    <input type="text" class="form-control pw-form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="e.g. Mountain Bike, Shimano, PROD-001...">
                </div>
            </div>

            <div class="col-lg-3 col-md-3">
                <label for="category" class="form-label text-white-50 small mb-1 fw-semibold">
                    <i class="fa-solid fa-filter me-1"></i> Filter by Category
                </label>
                <select class="form-select pw-form-select" id="category" name="category">
                    <option value="">All Categories</option>
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
                    <a href="products.php" class="pw-btn-glass text-nowrap" title="Reset Filters">
                        <i class="fa-solid fa-arrow-rotate-left"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Quick Category Strip -->
        <div class="pw-category-strip mt-3 mb-0 pt-3 border-top border-secondary border-opacity-25 justify-content-start">
            <a href="products.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" 
               class="pw-filter-pill <?php echo ($category === '') ? 'active' : ''; ?>">
                <i class="fa-solid fa-layer-group"></i> All Items
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="products.php?category=<?php echo urlencode($cat['name']); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="pw-filter-pill <?php echo ($category === $cat['name']) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Active Filters Summary & Result Count -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div class="text-white-50 small">
            Showing <strong class="text-white"><?php echo count($products); ?></strong> products
            <?php if ($category !== ''): ?>
                in <span class="badge rounded-pill text-white" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); font-weight: 500;"><?php echo htmlspecialchars($category); ?></span>
            <?php endif; ?>
            <?php if ($search !== ''): ?>
                matching <span class="badge rounded-pill" style="background: rgba(209, 102, 41, 0.15); border: 1px solid rgba(209, 102, 41, 0.3); color: var(--pw-trail-amber); font-weight: 500;">"<?php echo htmlspecialchars($search); ?>"</span>
            <?php endif; ?>
        </div>

        <?php if ($search !== '' || $category !== ''): ?>
            <div>
                <a href="products.php" class="text-muted small text-decoration-none">
                    <i class="fa-solid fa-xmark me-1"></i> Clear all filters
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Product Grid -->
    <?php if (empty($products)): ?>
        <div class="pw-filter-card text-center py-5">
            <div class="pw-feature-icon mb-3">
                <i class="fa-solid fa-bicycle fa-2x text-muted"></i>
            </div>
            <h4 class="text-white fw-bold">No Products Found</h4>
            <p class="text-white-50 max-w-md mx-auto mb-4" style="max-width: 480px;">
                We couldn't find any bikes or gear matching your search or category filter. Try clearing filters or using different keywords.
            </p>
            <a href="products.php" class="pw-btn-trail">
                <i class="fa-solid fa-arrow-rotate-left me-1"></i> View All Products
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($products as $p): 
                $catName = $p['categoryName'] ?? 'Uncategorized';
                $isLowStock = ($p['stock'] <= $p['lowStockThreshold'] && $p['stock'] > 0);
                $isOutOfStock = ($p['stock'] <= 0);
                $modalId = 'prodModal' . (int)$p['productID'];
            ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="pw-product-card">
                        <!-- Product Showcase Stage -->
                        <div class="pw-product-img-box">
                            <span class="pw-category-tag"><?php echo htmlspecialchars($catName); ?></span>
                            
                            <span class="pw-stock-indicator <?php echo $isOutOfStock ? 'out-of-stock' : ($isLowStock ? 'low-stock' : 'in-stock'); ?>">
                                <span class="pw-dot"></span>
                                <?php 
                                if ($isOutOfStock) {
                                    echo 'Out of Stock';
                                } elseif ($isLowStock) {
                                    echo 'Low Stock (' . (int)$p['stock'] . ')';
                                } else {
                                    echo 'In Stock (' . (int)$p['stock'] . ')';
                                }
                                ?>
                            </span>

                            <?php if (!empty($p['image']) && file_exists(__DIR__ . '/' . $p['image'])): ?>
                                <img src="/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($p['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                     class="pw-product-img">
                            <?php else: ?>
                                <div class="pw-product-img-icon">
                                    <i class="fa-solid fa-bicycle"></i>
                                </div>
                                <div class="pw-product-img-text"><?php echo htmlspecialchars($p['name']); ?></div>
                                <div class="pw-product-dim">SKU: <?php echo htmlspecialchars($p['sku']); ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Product Body -->
                        <div class="pw-product-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="pw-sku-mono">
                                    <i class="fa-solid fa-barcode me-1 opacity-75"></i><?php echo htmlspecialchars($p['sku']); ?>
                                </span>
                                <span class="small text-white-50">
                                    <?php echo htmlspecialchars($catName); ?>
                                </span>
                            </div>

                            <h5 class="pw-product-title" title="<?php echo htmlspecialchars($p['name']); ?>">
                                <?php echo htmlspecialchars($p['name']); ?>
                            </h5>

                            <p class="pw-product-spec">
                                <?php 
                                $desc = $p['description'] ?? '';
                                echo htmlspecialchars(mb_strimwidth($desc, 0, 110, '...')); 
                                ?>
                            </p>

                            <!-- Product Card Footer -->
                            <div class="pw-product-footer">
                                <div class="pw-product-price">
                                    <span class="pw-price-label">PRICE</span>
                                    <span class="pw-price-amount">&#8369;<?php echo number_format($p['price'], 2); ?></span>
                                </div>
                                <button type="button" class="pw-btn-product-action" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#<?php echo $modalId; ?>"
                                        title="View Details">
                                    <span>Details</span>
                                    <i class="fa-solid fa-arrow-right fa-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Quick View Modal -->
                <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1" aria-labelledby="<?php echo $modalId; ?>Label" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content pw-modal-content">
                            <div class="modal-header pw-modal-header">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="pw-sku-mono text-warning"><?php echo htmlspecialchars($p['sku']); ?></span>
                                    <h5 class="modal-title fw-bold text-white mb-0" id="<?php echo $modalId; ?>Label">
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </h5>
                                </div>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="row g-4 align-items-center">
                                    <div class="col-md-6">
                                        <div class="pw-product-img-box" style="height: 300px; background: #ffffff;">
                                            <?php if (!empty($p['image']) && file_exists(__DIR__ . '/' . $p['image'])): ?>
                                                <img src="/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($p['image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                                     class="pw-product-img" style="max-height: 260px;">
                                            <?php else: ?>
                                                <div class="pw-product-img-icon">
                                                    <i class="fa-solid fa-bicycle fa-2x"></i>
                                                </div>
                                                <div class="pw-product-img-text"><?php echo htmlspecialchars($p['name']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <span class="badge rounded-pill text-white me-2" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); font-weight: 500;">
                                                <i class="fa-solid fa-tag me-1"></i><?php echo htmlspecialchars($catName); ?>
                                            </span>
                                            <span class="badge rounded-pill <?php echo $isOutOfStock ? 'bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50' : ($isLowStock ? 'bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50' : 'bg-success bg-opacity-25 text-success border border-success border-opacity-50'); ?>" style="font-weight: 500;">
                                                <i class="fa-solid fa-circle-check me-1"></i>
                                                <?php 
                                                if ($isOutOfStock) {
                                                    echo 'Out of Stock';
                                                } elseif ($isLowStock) {
                                                    echo 'Low Stock (' . (int)$p['stock'] . ' available)';
                                                } else {
                                                    echo 'In Stock (' . (int)$p['stock'] . ' available)';
                                                }
                                                ?>
                                            </span>
                                        </div>

                                        <h3 class="pw-price-amount mb-3" style="font-size: 2rem;">
                                            &#8369;<?php echo number_format($p['price'], 2); ?>
                                        </h3>

                                        <h6 class="text-white fw-bold mb-2">Specifications &amp; Overview</h6>
                                        <p class="text-white-50 small mb-4" style="line-height: 1.7;">
                                            <?php echo nl2br(htmlspecialchars($p['description'] ?? 'No description provided.')); ?>
                                        </p>

                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="index.php#workshop-basecamp" class="pw-btn-trail-sm py-2 px-3">
                                                <i class="fa-solid fa-location-dot me-1"></i> Inquire at Basecamp
                                            </a>
                                            <button type="button" class="pw-btn-glass-sm py-2 px-3" data-bs-dismiss="modal">
                                                Close
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer pw-modal-footer text-muted small justify-content-between">
                                <span><i class="fa-solid fa-shield-halved me-1 text-success"></i> Genuine Gear Guarantee</span>
                                <span>Item ID: #<?php echo (int)$p['productID']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include('./includes/footer.php'); ?>
