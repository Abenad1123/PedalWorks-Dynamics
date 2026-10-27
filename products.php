<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('./includes/config.php');
include('./includes/header.php');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'name_asc';
$inStockOnly = isset($_GET['in_stock']) && $_GET['in_stock'] === '1';

$categories = [];
if ($conn) {
    $catQuery = @mysqli_query($conn, "SELECT * FROM productCategory ORDER BY name ASC");
    if ($catQuery) {
        while ($row = mysqli_fetch_assoc($catQuery)) {
            $categories[] = $row;
        }
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

    if ($category !== '') {
        $where[] = "pc.name = ?";
        $params[] = $category;
        $types .= "s";
    }

    if ($inStockOnly) {
        $where[] = "p.stock > 0";
    }

    $orderBy = "p.name ASC";
    if ($sort === 'price_asc') {
        $orderBy = "p.price ASC";
    } elseif ($sort === 'price_desc') {
        $orderBy = "p.price DESC";
    }

    $sql = "SELECT p.*, pc.name AS categoryName 
            FROM product p 
            LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID 
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
?>

<div class="container py-4">
    <?php include('./includes/alert.php'); ?>

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

    <div class="pw-filter-card">
        <form method="GET" action="products.php" class="row g-3 align-items-center">
            <div class="col-lg-4 col-md-6">
                <label for="search" class="pw-form-label">
                    Search Catalog
                </label>
                <input type="text" class="form-control pw-form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by product name, SKU, or specs...">
            </div>

            <div class="col-lg-3 col-md-3">
                <label for="category" class="pw-form-label">
                    Category
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

            <div class="col-lg-2 col-md-3">
                <label for="sort" class="pw-form-label">
                    Sort By
                </label>
                <select class="form-select pw-form-select" id="sort" name="sort">
                    <option value="name_asc" <?php echo ($sort === 'name_asc') ? 'selected' : ''; ?>>Name (A – Z)</option>
                    <option value="price_asc" <?php echo ($sort === 'price_asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_desc" <?php echo ($sort === 'price_desc') ? 'selected' : ''; ?>>Price: High to Low</option>
                </select>
            </div>

            <div class="col-lg-3 col-md-12 d-flex flex-wrap align-items-end gap-2 mt-md-4 pt-md-2">
                <div class="form-check form-switch me-auto mb-2 mb-lg-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="in_stock" name="in_stock" value="1" <?php echo $inStockOnly ? 'checked' : ''; ?>>
                    <label class="form-check-label text-white small" for="in_stock">In-Stock Only</label>
                </div>
                <button type="submit" class="pw-btn-trail flex-grow-1">
                    Apply
                </button>
                <?php if ($search !== '' || $category !== '' || $inStockOnly || $sort !== 'name_asc'): ?>
                    <a href="products.php" class="pw-btn-glass text-nowrap" title="Reset Filters">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <div class="pw-category-strip mt-3 mb-0 pt-3 border-top border-secondary border-opacity-25 justify-content-start">
            <a href="products.php<?php 
                $qParams = [];
                if (!empty($search)) $qParams['search'] = $search;
                if ($sort !== 'name_asc') $qParams['sort'] = $sort;
                if ($inStockOnly) $qParams['in_stock'] = '1';
                echo !empty($qParams) ? '?' . http_build_query($qParams) : '';
               ?>" 
               class="pw-filter-pill <?php echo ($category === '') ? 'active' : ''; ?>">
                All Items
            </a>
            <?php foreach ($categories as $cat): 
                $cParams = $qParams;
                $cParams['category'] = $cat['name'];
            ?>
                <a href="products.php?<?php echo http_build_query($cParams); ?>" 
                   class="pw-filter-pill <?php echo ($category === $cat['name']) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div class="text-white-50 small d-flex flex-wrap align-items-center gap-2">
            <span>Showing <strong class="text-white"><?php echo count($products); ?></strong> products</span>
            <?php if ($category !== ''): ?>
                <span class="badge rounded-pill text-white" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); font-weight: 500;">
                    Category: <?php echo htmlspecialchars($category); ?>
                </span>
            <?php endif; ?>
            <?php if ($search !== ''): ?>
                <span class="badge rounded-pill" style="background: rgba(209, 102, 41, 0.15); border: 1px solid rgba(209, 102, 41, 0.3); color: var(--pw-trail-amber); font-weight: 500;">
                    Keyword: "<?php echo htmlspecialchars($search); ?>"
                </span>
            <?php endif; ?>
            <?php if ($inStockOnly): ?>
                <span class="badge rounded-pill bg-success bg-opacity-20 text-success border border-success border-opacity-25">
                    <i class="fa-solid fa-check me-1"></i>In-Stock Only
                </span>
            <?php endif; ?>
            <?php if ($sort === 'price_asc'): ?>
                <span class="badge rounded-pill text-white-50 border border-white border-opacity-10">Price: Low to High</span>
            <?php elseif ($sort === 'price_desc'): ?>
                <span class="badge rounded-pill text-white-50 border border-white border-opacity-10">Price: High to Low</span>
            <?php endif; ?>
        </div>

        <div class="d-flex align-items-center gap-2">

            <button class="btn btn-sm pw-btn-glass d-md-none text-white rounded-pill px-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileFilterOffcanvas" aria-controls="mobileFilterOffcanvas">
                <i class="fa-solid fa-sliders me-1 text-warning"></i> Filter &amp; Sort
            </button>
            <?php if ($search !== '' || $category !== '' || $inStockOnly || $sort !== 'name_asc'): ?>
                <a href="products.php" class="text-muted small text-decoration-none">
                    <i class="fa-solid fa-xmark me-1"></i> Clear all filters
                </a>
            <?php endif; ?>
        </div>
    </div>

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
                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Full Details &amp; Reviews
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

    <div class="offcanvas offcanvas-bottom pw-mobile-filter-drawer" tabindex="-1" id="mobileFilterOffcanvas" aria-labelledby="mobileFilterOffcanvasLabel" style="height: 70vh; border-top-left-radius: 20px; border-top-right-radius: 20px;">
        <div class="offcanvas-header py-3 px-4">
            <h5 class="offcanvas-title fw-bold text-white d-flex align-items-center gap-2" id="mobileFilterOffcanvasLabel">
                <i class="fa-solid fa-sliders text-warning"></i> Refine Catalog
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
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['name']); ?>" <?php echo ($category === $cat['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="mSort" class="pw-form-label text-white">Sort By</label>
                    <select class="form-select pw-form-select" id="mSort" name="sort">
                        <option value="name_asc" <?php echo ($sort === 'name_asc') ? 'selected' : ''; ?>>Name (A – Z)</option>
                        <option value="price_asc" <?php echo ($sort === 'price_asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo ($sort === 'price_desc') ? 'selected' : ''; ?>>Price: High to Low</option>
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
