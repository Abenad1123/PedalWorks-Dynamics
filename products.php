<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('./includes/config.php');
include('./includes/header.php');

$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$category      = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort          = isset($_GET['sort']) ? trim($_GET['sort']) : 'name_asc';
$inStockOnly   = isset($_GET['in_stock']) && $_GET['in_stock'] === '1';
$minPriceRaw   = isset($_GET['min_price']) ? trim($_GET['min_price']) : '';
$maxPriceRaw   = isset($_GET['max_price']) ? trim($_GET['max_price']) : '';
$priceRange    = isset($_GET['price_range']) ? trim($_GET['price_range']) : '';
$ratingFilter  = isset($_GET['rating']) ? trim($_GET['rating']) : '';
$reviewFilter  = isset($_GET['review_filter']) ? trim($_GET['review_filter']) : '';

if ($priceRange === 'under_2k' && $minPriceRaw === '' && $maxPriceRaw === '') {
    $minPriceRaw = '0';
    $maxPriceRaw = '2000';
} elseif ($priceRange === '2k_10k' && $minPriceRaw === '' && $maxPriceRaw === '') {
    $minPriceRaw = '2000';
    $maxPriceRaw = '10000';
} elseif ($priceRange === '10k_30k' && $minPriceRaw === '' && $maxPriceRaw === '') {
    $minPriceRaw = '10000';
    $maxPriceRaw = '30000';
} elseif ($priceRange === 'above_30k' && $minPriceRaw === '' && $maxPriceRaw === '') {
    $minPriceRaw = '30000';
    $maxPriceRaw = '';
}

$categories = [];
$categoryCounts = [];
$totalCatalogCount = 0;

if ($conn) {
    $catQuery = @mysqli_query($conn, "SELECT pc.name, COUNT(p.productID) AS cnt 
                                      FROM productCategory pc 
                                      LEFT JOIN product p ON pc.productCategoryID = p.productCategoryID AND p.endDate IS NULL 
                                      GROUP BY pc.productCategoryID 
                                      ORDER BY pc.name ASC");
    if ($catQuery) {
        while ($row = mysqli_fetch_assoc($catQuery)) {
            $categories[] = $row;
            $categoryCounts[$row['name']] = (int)$row['cnt'];
        }
    }

    $totRes = @mysqli_query($conn, "SELECT COUNT(*) AS total FROM product WHERE endDate IS NULL");
    if ($totRes && $tRow = mysqli_fetch_assoc($totRes)) {
        $totalCatalogCount = (int)$tRow['total'];
    }
}

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

    if ($category !== '' && $category !== 'all') {
        $where[] = "pc.name = ?";
        $params[] = $category;
        $types .= "s";
    }

    if ($minPriceRaw !== '' && is_numeric($minPriceRaw)) {
        $minVal = (float)$minPriceRaw;
        if ($minVal >= 0) {
            $where[] = "p.price >= ?";
            $params[] = $minVal;
            $types .= "d";
        }
    }
    if ($maxPriceRaw !== '' && is_numeric($maxPriceRaw)) {
        $maxVal = (float)$maxPriceRaw;
        if ($maxVal > 0) {
            $where[] = "p.price <= ?";
            $params[] = $maxVal;
            $types .= "d";
        }
    }

    if ($inStockOnly) {
        $where[] = "p.stock > 0";
    }

    if ($ratingFilter !== '' && $ratingFilter !== 'all') {
        $rVal = (float)$ratingFilter;
        if ($rVal >= 5.0) {
            $where[] = "COALESCE(rev.avgRating, 0) >= 4.95";
        } elseif ($rVal > 0) {
            $where[] = "COALESCE(rev.avgRating, 0) >= ?";
            $params[] = $rVal;
            $types .= "d";
        }
    }

    if ($reviewFilter === 'with_reviews') {
        $where[] = "COALESCE(rev.reviewCount, 0) > 0";
    } elseif ($reviewFilter === 'no_reviews') {
        $where[] = "COALESCE(rev.reviewCount, 0) = 0";
    } elseif ($reviewFilter === 'top_reviewed') {
        $where[] = "COALESCE(rev.reviewCount, 0) >= 2";
    }

    $orderBy = "p.name ASC";
    if ($sort === 'price_asc') {
        $orderBy = "p.price ASC";
    } elseif ($sort === 'price_desc') {
        $orderBy = "p.price DESC";
    } elseif ($sort === 'rating_desc') {
        $orderBy = "rev.avgRating DESC, rev.reviewCount DESC, p.name ASC";
    } elseif ($sort === 'reviews_desc') {
        $orderBy = "rev.reviewCount DESC, rev.avgRating DESC, p.name ASC";
    } elseif ($sort === 'name_desc') {
        $orderBy = "p.name DESC";
    }

    $sql = "SELECT p.*, pc.name AS categoryName,
                   COALESCE(rev.avgRating, 0) AS avgRating,
                   COALESCE(rev.reviewCount, 0) AS reviewCount
            FROM product p 
            LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID 
            LEFT JOIN (
                SELECT productID, 
                       ROUND(AVG(rating), 1) AS avgRating, 
                       COUNT(reviewID) AS reviewCount 
                FROM review 
                WHERE productID IS NOT NULL 
                GROUP BY productID
            ) rev ON p.productID = rev.productID
            WHERE " . implode(" AND ", $where) . " 
            ORDER BY " . $orderBy;

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

function buildFilterUrl($overrides = [], $removals = []) {
    $current = $_GET;
    foreach ($removals as $rem) {
        unset($current[$rem]);
    }
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($current[$k]);
        } else {
            $current[$k] = $v;
        }
    }
    return !empty($current) ? 'products.php?' . http_build_query($current) : 'products.php';
}

$hasActiveFilters = ($search !== '' || ($category !== '' && $category !== 'all') || $minPriceRaw !== '' || $maxPriceRaw !== '' || $ratingFilter !== '' || $reviewFilter !== '' || $inStockOnly || $sort !== 'name_asc');
?>

<div class="container py-4">
    <?php include('./includes/alert.php'); ?>

    <div class="row align-items-center mb-4 g-3">
        <div class="col-lg-8">
            <h1 class="pw-hero-title mb-2">
                Adventure Bicycles &amp; <span class="pw-hero-title-accent">Gear</span>
            </h1>
            <p class="pw-hero-desc mb-0">
                Explore trail-tested bikes, high-precision drivetrain parts, and safety essentials. Filter by price, category, rider ratings, and verified reviews.
            </p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <div class="pw-stat-pill d-inline-flex">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span>Showing <strong><?php echo count($products); ?></strong> of <?php echo $totalCatalogCount; ?> gear items</span>
            </div>
        </div>
    </div>

    <!-- MAIN FILTER CARD -->
    <div class="pw-filter-card p-3 p-md-4 shadow-sm">
        <form method="GET" action="products.php" id="filterForm">
            <!-- Row 1: Search, Category, Sort, Stock -->
            <div class="row g-3 align-items-end mb-3">
                <div class="col-lg-4 col-md-6">
                    <label for="search" class="pw-form-label d-flex align-items-center gap-1">
                        <i class="fa-solid fa-magnifying-glass text-warning fa-xs"></i>
                        <span>Search Catalog</span>
                    </label>
                    <input type="text" class="form-control pw-form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by model, SKU, or specs...">
                </div>

                <!-- CATEGORY FILTER -->
                <div class="col-lg-3 col-md-6">
                    <label for="category" class="pw-form-label d-flex align-items-center gap-1">
                        <i class="fa-solid fa-layer-group text-warning fa-xs"></i>
                        <span>Category</span>
                    </label>
                    <select class="form-select pw-form-select" id="category" name="category">
                        <option value="">All Categories (<?php echo $totalCatalogCount; ?>)</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['name']); ?>" <?php echo ($category === $cat['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?> (<?php echo (int)($categoryCounts[$cat['name']] ?? 0); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label for="sort" class="pw-form-label d-flex align-items-center gap-1">
                        <i class="fa-solid fa-arrow-down-wide-short text-warning fa-xs"></i>
                        <span>Sort Catalog</span>
                    </label>
                    <select class="form-select pw-form-select" id="sort" name="sort">
                        <option value="name_asc" <?php echo ($sort === 'name_asc') ? 'selected' : ''; ?>>Name (A – Z)</option>
                        <option value="name_desc" <?php echo ($sort === 'name_desc') ? 'selected' : ''; ?>>Name (Z – A)</option>
                        <option value="price_asc" <?php echo ($sort === 'price_asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo ($sort === 'price_desc') ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="rating_desc" <?php echo ($sort === 'rating_desc') ? 'selected' : ''; ?>>Customer Rating: High to Low</option>
                        <option value="reviews_desc" <?php echo ($sort === 'reviews_desc') ? 'selected' : ''; ?>>Most Reviewed First</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-6">
                    <div class="form-check form-switch pb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="in_stock" name="in_stock" value="1" <?php echo $inStockOnly ? 'checked' : ''; ?>>
                        <label class="form-check-label text-white small" for="in_stock">In-Stock Only</label>
                    </div>
                </div>
            </div>

            <!-- Row 2: Price Filter, Ratings Filter, Review Filter, Actions -->
            <div class="row g-3 align-items-end pt-3 border-top border-white border-opacity-10">
                <!-- PRICE FILTER -->
                <div class="col-lg-4 col-md-6">
                    <label class="pw-form-label d-flex align-items-center gap-1">
                        <i class="fa-solid fa-peso-sign text-warning fa-xs"></i>
                        <span>Price Range</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark text-white-50 border-secondary border-opacity-25">&#8369;</span>
                        <input type="number" step="0.01" min="0" class="form-control pw-form-control" 
                               id="min_price" name="min_price" value="<?php echo htmlspecialchars($minPriceRaw); ?>" 
                               placeholder="Min Price">
                        <span class="input-group-text bg-dark text-white-50 border-secondary border-opacity-25">–</span>
                        <input type="number" step="0.01" min="0" class="form-control pw-form-control" 
                               id="max_price" name="max_price" value="<?php echo htmlspecialchars($maxPriceRaw); ?>" 
                               placeholder="Max Price">
                    </div>
                </div>

                <!-- RATINGS FILTER -->
                <div class="col-lg-3 col-md-6">
                    <label for="rating" class="pw-form-label d-flex align-items-center gap-1">
                        <i class="fa-solid fa-star text-warning fa-xs"></i>
                        <span>Customer Rating</span>
                    </label>
                    <select class="form-select pw-form-select" id="rating" name="rating">
                        <option value="">Any Star Rating</option>
                        <option value="5" <?php echo ($ratingFilter === '5') ? 'selected' : ''; ?>>★★★★★ (5.0 Stars Only)</option>
                        <option value="4" <?php echo ($ratingFilter === '4') ? 'selected' : ''; ?>>★★★★☆ &amp; Up (4.0+ Stars)</option>
                        <option value="3" <?php echo ($ratingFilter === '3') ? 'selected' : ''; ?>>★★★☆☆ &amp; Up (3.0+ Stars)</option>
                        <option value="2" <?php echo ($ratingFilter === '2') ? 'selected' : ''; ?>>★★☆☆☆ &amp; Up (2.0+ Stars)</option>
                        <option value="1" <?php echo ($ratingFilter === '1') ? 'selected' : ''; ?>>★☆☆☆☆ &amp; Up (1.0+ Stars)</option>
                    </select>
                </div>

                <!-- REVIEW FILTER -->
                <div class="col-lg-3 col-md-6">
                    <label for="review_filter" class="pw-form-label d-flex align-items-center gap-1">
                        <i class="fa-solid fa-comments text-warning fa-xs"></i>
                        <span>Review Status</span>
                    </label>
                    <select class="form-select pw-form-select" id="review_filter" name="review_filter">
                        <option value="">All Review Statuses</option>
                        <option value="with_reviews" <?php echo ($reviewFilter === 'with_reviews') ? 'selected' : ''; ?>>★ Has Customer Reviews</option>
                        <option value="top_reviewed" <?php echo ($reviewFilter === 'top_reviewed') ? 'selected' : ''; ?>>💬 Most Reviewed (2+ Reviews)</option>
                        <option value="no_reviews" <?php echo ($reviewFilter === 'no_reviews') ? 'selected' : ''; ?>>Unreviewed Items</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="col-lg-2 col-md-6 d-flex gap-2">
                    <button type="submit" class="pw-btn-trail flex-grow-1 text-nowrap">
                        <i class="fa-solid fa-filter me-1"></i> Apply
                    </button>
                    <?php if ($hasActiveFilters): ?>
                        <a href="products.php" class="pw-btn-glass text-nowrap" title="Reset all filters">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Price Range Shortcuts -->
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top border-white border-opacity-10 small text-white-50">
                <span class="me-1"><i class="fa-solid fa-tags text-warning me-1"></i>Quick Price:</span>
                <a href="<?php echo buildFilterUrl([], ['min_price', 'max_price', 'price_range']); ?>" 
                   class="badge rounded-pill text-decoration-none px-3 py-2 <?php echo ($minPriceRaw === '' && $maxPriceRaw === '') ? 'bg-warning text-dark fw-bold' : 'bg-white bg-opacity-10 text-white'; ?>">
                    All Prices
                </a>
                <a href="<?php echo buildFilterUrl(['min_price' => '0', 'max_price' => '2000', 'price_range' => 'under_2k']); ?>" 
                   class="badge rounded-pill text-decoration-none px-3 py-2 <?php echo ($maxPriceRaw === '2000' && $minPriceRaw === '0') ? 'bg-warning text-dark fw-bold' : 'bg-white bg-opacity-10 text-white'; ?>">
                    Under &#8369;2,000
                </a>
                <a href="<?php echo buildFilterUrl(['min_price' => '2000', 'max_price' => '10000', 'price_range' => '2k_10k']); ?>" 
                   class="badge rounded-pill text-decoration-none px-3 py-2 <?php echo ($minPriceRaw === '2000' && $maxPriceRaw === '10000') ? 'bg-warning text-dark fw-bold' : 'bg-white bg-opacity-10 text-white'; ?>">
                    &#8369;2,000 – &#8369;10,000
                </a>
                <a href="<?php echo buildFilterUrl(['min_price' => '10000', 'max_price' => '30000', 'price_range' => '10k_30k']); ?>" 
                   class="badge rounded-pill text-decoration-none px-3 py-2 <?php echo ($minPriceRaw === '10000' && $maxPriceRaw === '30000') ? 'bg-warning text-dark fw-bold' : 'bg-white bg-opacity-10 text-white'; ?>">
                    &#8369;10,000 – &#8369;30,000
                </a>
                <a href="<?php echo buildFilterUrl(['min_price' => '30000', 'max_price' => '', 'price_range' => 'above_30k']); ?>" 
                   class="badge rounded-pill text-decoration-none px-3 py-2 <?php echo ($minPriceRaw === '30000' && ($maxPriceRaw === '' || $maxPriceRaw === null)) ? 'bg-warning text-dark fw-bold' : 'bg-white bg-opacity-10 text-white'; ?>">
                    &#8369;30,000+
                </a>
            </div>
        </form>

        <!-- Category Strip / Navigation Pills -->
        <div class="pw-category-strip mt-3 mb-0 pt-3 border-top border-secondary border-opacity-25 justify-content-start">
            <a href="<?php echo buildFilterUrl([], ['category']); ?>" 
               class="pw-filter-pill <?php echo ($category === '' || $category === 'all') ? 'active' : ''; ?>">
                All Categories (<?php echo $totalCatalogCount; ?>)
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="<?php echo buildFilterUrl(['category' => $cat['name']]); ?>" 
                   class="pw-filter-pill <?php echo ($category === $cat['name']) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?> (<?php echo (int)($categoryCounts[$cat['name']] ?? 0); ?>)
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ACTIVE FILTER BADGES ROW -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div class="text-white-50 small d-flex flex-wrap align-items-center gap-2">
            <span>Showing <strong class="text-white"><?php echo count($products); ?></strong> gear items</span>

            <?php if ($category !== '' && $category !== 'all'): ?>
                <a href="<?php echo buildFilterUrl([], ['category']); ?>" class="badge rounded-pill text-white text-decoration-none d-inline-flex align-items-center gap-1" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); font-weight: 500;">
                    <span>Category: <?php echo htmlspecialchars($category); ?></span>
                    <i class="fa-solid fa-xmark fa-xs"></i>
                </a>
            <?php endif; ?>

            <?php if ($minPriceRaw !== '' || $maxPriceRaw !== ''): ?>
                <a href="<?php echo buildFilterUrl([], ['min_price', 'max_price', 'price_range']); ?>" class="badge rounded-pill text-warning text-decoration-none d-inline-flex align-items-center gap-1" style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 500;">
                    <span>Price: 
                        <?php 
                        if ($minPriceRaw !== '' && $maxPriceRaw !== '') {
                            echo '&#8369;' . number_format((float)$minPriceRaw) . ' – &#8369;' . number_format((float)$maxPriceRaw);
                        } elseif ($minPriceRaw !== '') {
                            echo '&#8369;' . number_format((float)$minPriceRaw) . '+';
                        } else {
                            echo 'Up to &#8369;' . number_format((float)$maxPriceRaw);
                        }
                        ?>
                    </span>
                    <i class="fa-solid fa-xmark fa-xs"></i>
                </a>
            <?php endif; ?>

            <?php if ($ratingFilter !== '' && $ratingFilter !== 'all'): ?>
                <a href="<?php echo buildFilterUrl([], ['rating']); ?>" class="badge rounded-pill text-warning text-decoration-none d-inline-flex align-items-center gap-1" style="background: rgba(234, 179, 8, 0.15); border: 1px solid rgba(234, 179, 8, 0.3); font-weight: 500;">
                    <span>Rating: <?php echo htmlspecialchars($ratingFilter); ?>★ &amp; Up</span>
                    <i class="fa-solid fa-xmark fa-xs"></i>
                </a>
            <?php endif; ?>

            <?php if ($reviewFilter !== '' && $reviewFilter !== 'all'): ?>
                <a href="<?php echo buildFilterUrl([], ['review_filter']); ?>" class="badge rounded-pill text-info text-decoration-none d-inline-flex align-items-center gap-1" style="background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3); font-weight: 500;">
                    <span>Reviews: 
                        <?php 
                        if ($reviewFilter === 'with_reviews') echo 'Has Reviews';
                        elseif ($reviewFilter === 'top_reviewed') echo 'Top Reviewed';
                        elseif ($reviewFilter === 'no_reviews') echo 'Unreviewed';
                        ?>
                    </span>
                    <i class="fa-solid fa-xmark fa-xs"></i>
                </a>
            <?php endif; ?>

            <?php if ($search !== ''): ?>
                <a href="<?php echo buildFilterUrl([], ['search']); ?>" class="badge rounded-pill text-decoration-none d-inline-flex align-items-center gap-1" style="background: rgba(209, 102, 41, 0.15); border: 1px solid rgba(209, 102, 41, 0.3); color: var(--pw-trail-amber); font-weight: 500;">
                    <span>Keyword: "<?php echo htmlspecialchars($search); ?>"</span>
                    <i class="fa-solid fa-xmark fa-xs"></i>
                </a>
            <?php endif; ?>

            <?php if ($inStockOnly): ?>
                <a href="<?php echo buildFilterUrl([], ['in_stock']); ?>" class="badge rounded-pill bg-success bg-opacity-20 text-success border border-success border-opacity-25 text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="fa-solid fa-check me-1"></i>In-Stock Only
                    <i class="fa-solid fa-xmark fa-xs"></i>
                </a>
            <?php endif; ?>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm pw-btn-glass d-md-none text-white rounded-pill px-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileFilterOffcanvas" aria-controls="mobileFilterOffcanvas">
                <i class="fa-solid fa-sliders me-1 text-warning"></i> Filter &amp; Sort
            </button>
            <?php if ($hasActiveFilters): ?>
                <a href="products.php" class="text-muted small text-decoration-none">
                    <i class="fa-solid fa-xmark me-1"></i> Clear all filters
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- PRODUCT GRID -->
    <?php if (empty($products)): ?>
        <div class="pw-filter-card text-center py-5">
            <div class="pw-feature-icon mb-3">
                <i class="fa-solid fa-bicycle fa-2x text-muted"></i>
            </div>
            <h4 class="text-white fw-bold">No Products Match Your Filter Criteria</h4>
            <p class="text-white-50 max-w-md mx-auto mb-4" style="max-width: 480px;">
                We couldn't find any bikes or gear matching your current combination of price, category, rating, or review filters.
            </p>
            <a href="products.php" class="pw-btn-trail">
                <i class="fa-solid fa-arrow-rotate-left me-1"></i> Reset All Filters
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($products as $p): 
                $catName = $p['categoryName'] ?? 'Uncategorized';
                $isLowStock = ($p['stock'] <= $p['lowStockThreshold'] && $p['stock'] > 0);
                $isOutOfStock = ($p['stock'] <= 0);
                $modalId = 'prodModal' . (int)$p['productID'];
                $ratingVal = (float)($p['avgRating'] ?? 0);
                $reviewCnt = (int)($p['reviewCount'] ?? 0);
            ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="pw-product-card">

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
                                <a href="product_details.php?id=<?php echo (int)$p['productID']; ?>" class="d-block w-100 h-100 text-decoration-none">
                                    <img src="/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($p['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                         class="pw-product-img">
                                </a>
                            <?php else: ?>
                                <a href="product_details.php?id=<?php echo (int)$p['productID']; ?>" class="d-block w-100 text-decoration-none">
                                    <div class="pw-product-img-icon">
                                        <i class="fa-solid fa-bicycle"></i>
                                    </div>
                                    <div class="pw-product-img-text"><?php echo htmlspecialchars($p['name']); ?></div>
                                    <div class="pw-product-dim">SKU: <?php echo htmlspecialchars($p['sku']); ?></div>
                                </a>
                            <?php endif; ?>
                        </div>

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
                                <a href="product_details.php?id=<?php echo (int)$p['productID']; ?>" class="text-white text-decoration-none">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </a>
                            </h5>

                            <!-- RATINGS & REVIEW COUNTER DISPLAY ON CARD -->
                            <div class="d-flex align-items-center justify-content-between mb-2 py-1 px-2 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
                                <?php if ($reviewCnt > 0): ?>
                                    <div class="d-flex align-items-center gap-1 text-warning small">
                                        <i class="fa-solid fa-star"></i>
                                        <span class="fw-bold text-white"><?php echo number_format($ratingVal, 1); ?></span>
                                    </div>
                                    <a href="product_details.php?id=<?php echo (int)$p['productID']; ?>#reviews" class="text-white-50 small text-decoration-none" title="View customer reviews">
                                        <span><?php echo $reviewCnt; ?> <?php echo ($reviewCnt === 1) ? 'review' : 'reviews'; ?></span>
                                        <i class="fa-solid fa-chevron-right fa-2xs ms-1 opacity-50"></i>
                                    </a>
                                <?php else: ?>
                                    <div class="d-flex align-items-center gap-1 text-white-50 small" style="font-size: 0.75rem;">
                                        <i class="fa-regular fa-star opacity-50"></i>
                                        <span>No reviews yet</span>
                                    </div>
                                    <a href="product_details.php?id=<?php echo (int)$p['productID']; ?>#reviews" class="text-warning small text-decoration-none" style="font-size: 0.72rem;">
                                        Be first
                                    </a>
                                <?php endif; ?>
                            </div>

                            <p class="pw-product-spec">
                                <?php 
                                $desc = $p['description'] ?? '';
                                echo htmlspecialchars(mb_strimwidth($desc, 0, 110, '...')); 
                                ?>
                            </p>

                            <div class="d-flex align-items-center gap-2 mb-3 text-white-50" style="font-size: 0.72rem;">
                                <span><i class="fa-solid fa-store text-success me-1"></i>In-Store Pickup</span>
                                <span>&bull;</span>
                                <span><i class="fa-solid fa-truck-fast text-warning me-1"></i>Courier COD</span>
                            </div>

                            <div class="pw-product-footer d-flex align-items-center justify-content-between gap-2">
                                <div class="pw-product-price">
                                    <span class="pw-price-label">PRICE</span>
                                    <span class="pw-price-amount">&#8369;<?php echo number_format($p['price'], 2); ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <?php if (!$isOutOfStock): ?>
                                        <form action="cart_action.php" method="POST" class="m-0">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="productID" value="<?php echo (int)$p['productID']; ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <input type="hidden" name="redirect" value="products.php">
                                            <button type="submit" class="btn btn-sm pw-btn-glass rounded-pill px-2 py-1 text-white" title="Quick Add 1 to Cart">
                                                <i class="fa-solid fa-cart-plus"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="pw-btn-product-action" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#<?php echo $modalId; ?>"
                                            title="Quick View Details">
                                        <span>Details</span>
                                        <i class="fa-solid fa-expand fa-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QUICK VIEW MODAL -->
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
                                        <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
                                            <span class="badge rounded-pill text-white" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); font-weight: 500;">
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
                                            <?php if ($reviewCnt > 0): ?>
                                                <span class="badge rounded-pill bg-warning bg-opacity-20 text-warning border border-warning border-opacity-30">
                                                    <i class="fa-solid fa-star me-1"></i><?php echo number_format($ratingVal, 1); ?> (<?php echo $reviewCnt; ?> reviews)
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <h3 class="pw-price-amount mb-3" style="font-size: 2rem;">
                                            &#8369;<?php echo number_format($p['price'], 2); ?>
                                        </h3>

                                        <h6 class="text-white fw-bold mb-2">Specifications &amp; Overview</h6>
                                        <p class="text-white-50 small mb-4" style="line-height: 1.7;">
                                            <?php echo nl2br(htmlspecialchars($p['description'] ?? 'No description provided.')); ?>
                                        </p>

                                        <?php if (!$isOutOfStock): ?>
                                            <form action="cart_action.php" method="POST" class="d-flex flex-wrap align-items-center gap-2 mb-4">
                                                <input type="hidden" name="action" value="add">
                                                <input type="hidden" name="productID" value="<?php echo (int)$p['productID']; ?>">
                                                <input type="hidden" name="redirect" value="products.php">

                                                <div class="pw-stepper-group">
                                                    <button type="button" class="pw-stepper-btn" onclick="let input=this.nextElementSibling; if(parseInt(input.value)>1) input.value=parseInt(input.value)-1;">
                                                        <i class="fa-solid fa-minus"></i>
                                                    </button>
                                                    <input type="number" name="quantity" class="pw-stepper-input" value="1" min="1" max="<?php echo (int)$p['stock']; ?>" readonly>
                                                    <button type="button" class="pw-stepper-btn" onclick="let input=this.previousElementSibling; if(parseInt(input.value)< <?php echo (int)$p['stock']; ?>) input.value=parseInt(input.value)+1;">
                                                        <i class="fa-solid fa-plus"></i>
                                                    </button>
                                                </div>

                                                <button type="submit" class="pw-btn-trail-sm py-2 px-3">
                                                    <i class="fa-solid fa-cart-shopping me-1"></i> Add to Cart
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="alert alert-secondary bg-white bg-opacity-10 text-white border-0 py-2 px-3 rounded-pill small mb-4">
                                                <i class="fa-solid fa-circle-info me-1"></i> This item is currently out of stock.
                                            </div>
                                        <?php endif; ?>

                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="product_details.php?id=<?php echo (int)$p['productID']; ?>" class="pw-btn-trail-sm py-2 px-3 text-white text-decoration-none">
                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Full Details &amp; Reviews (<?php echo $reviewCnt; ?>)
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
                                <span><i class="fa-solid fa-truck-fast me-1 text-primary"></i> Doorstep Delivery &bull; Basecamp Pickup</span>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- MOBILE FILTER DRAWER -->
    <div class="offcanvas offcanvas-bottom pw-mobile-filter-drawer" tabindex="-1" id="mobileFilterOffcanvas" aria-labelledby="mobileFilterOffcanvasLabel" style="height: 85vh; border-top-left-radius: 20px; border-top-right-radius: 20px;">
        <div class="offcanvas-header py-3 px-4">
            <h5 class="offcanvas-title fw-bold text-white d-flex align-items-center gap-2" id="mobileFilterOffcanvasLabel">
                <i class="fa-solid fa-sliders text-warning"></i> Filter &amp; Refine Gear
            </h5>
            <button type="button" class="btn-close btn-close-white text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-4">
            <form method="GET" action="products.php" class="d-flex flex-column gap-3">
                <div>
                    <label for="mSearch" class="pw-form-label text-white">Search Keywords</label>
                    <input type="text" class="form-control pw-form-control" id="mSearch" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, SKU, or specs...">
                </div>

                <div>
                    <label for="mCategory" class="pw-form-label text-white">Category</label>
                    <select class="form-select pw-form-select" id="mCategory" name="category">
                        <option value="">All Categories (<?php echo $totalCatalogCount; ?>)</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['name']); ?>" <?php echo ($category === $cat['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="pw-form-label text-white">Price Range</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark text-white-50 border-secondary border-opacity-25">&#8369;</span>
                        <input type="number" step="0.01" min="0" class="form-control pw-form-control" name="min_price" value="<?php echo htmlspecialchars($minPriceRaw); ?>" placeholder="Min">
                        <span class="input-group-text bg-dark text-white-50 border-secondary border-opacity-25">–</span>
                        <input type="number" step="0.01" min="0" class="form-control pw-form-control" name="max_price" value="<?php echo htmlspecialchars($maxPriceRaw); ?>" placeholder="Max">
                    </div>
                </div>

                <div>
                    <label for="mRating" class="pw-form-label text-white">Customer Rating</label>
                    <select class="form-select pw-form-select" id="mRating" name="rating">
                        <option value="">Any Star Rating</option>
                        <option value="5" <?php echo ($ratingFilter === '5') ? 'selected' : ''; ?>>★★★★★ (5.0 Only)</option>
                        <option value="4" <?php echo ($ratingFilter === '4') ? 'selected' : ''; ?>>★★★★☆ &amp; Up (4.0+ Stars)</option>
                        <option value="3" <?php echo ($ratingFilter === '3') ? 'selected' : ''; ?>>★★★☆☆ &amp; Up (3.0+ Stars)</option>
                        <option value="2" <?php echo ($ratingFilter === '2') ? 'selected' : ''; ?>>★★☆☆☆ &amp; Up (2.0+ Stars)</option>
                        <option value="1" <?php echo ($ratingFilter === '1') ? 'selected' : ''; ?>>★☆☆☆☆ &amp; Up (1.0+ Stars)</option>
                    </select>
                </div>

                <div>
                    <label for="mReviewFilter" class="pw-form-label text-white">Review Status</label>
                    <select class="form-select pw-form-select" id="mReviewFilter" name="review_filter">
                        <option value="">All Review Statuses</option>
                        <option value="with_reviews" <?php echo ($reviewFilter === 'with_reviews') ? 'selected' : ''; ?>>★ Has Customer Reviews</option>
                        <option value="top_reviewed" <?php echo ($reviewFilter === 'top_reviewed') ? 'selected' : ''; ?>>💬 Most Reviewed (2+ Reviews)</option>
                        <option value="no_reviews" <?php echo ($reviewFilter === 'no_reviews') ? 'selected' : ''; ?>>Unreviewed Items</option>
                    </select>
                </div>

                <div>
                    <label for="mSort" class="pw-form-label text-white">Sort By</label>
                    <select class="form-select pw-form-select" id="mSort" name="sort">
                        <option value="name_asc" <?php echo ($sort === 'name_asc') ? 'selected' : ''; ?>>Name (A – Z)</option>
                        <option value="name_desc" <?php echo ($sort === 'name_desc') ? 'selected' : ''; ?>>Name (Z – A)</option>
                        <option value="price_asc" <?php echo ($sort === 'price_asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo ($sort === 'price_desc') ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="rating_desc" <?php echo ($sort === 'rating_desc') ? 'selected' : ''; ?>>Rating: High to Low</option>
                        <option value="reviews_desc" <?php echo ($sort === 'reviews_desc') ? 'selected' : ''; ?>>Most Reviewed First</option>
                    </select>
                </div>

                <div class="form-check form-switch py-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="mInStock" name="in_stock" value="1" <?php echo $inStockOnly ? 'checked' : ''; ?>>
                    <label class="form-check-label text-white small" for="mInStock">In-Stock Gear Only</label>
                </div>

                <div class="d-flex gap-2 mt-auto pt-3 border-top border-white border-opacity-10">
                    <button type="submit" class="pw-btn-trail flex-grow-1 py-2 text-center justify-content-center">
                        Apply Filters
                    </button>
                    <a href="products.php" class="pw-btn-glass py-2 px-3 text-center">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('./includes/footer.php'); ?>
