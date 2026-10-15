<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';

$productID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sku       = isset($_GET['sku']) ? trim($_GET['sku']) : '';

$product = null;

if ($conn && ($productID > 0 || $sku !== '')) {
    if ($productID > 0) {
        $sql = "SELECT p.*, pc.name AS categoryName 
                FROM product p 
                LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID 
                WHERE p.productID = ? AND p.endDate IS NULL 
                LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $productID);
    } else {
        $sql = "SELECT p.*, pc.name AS categoryName 
                FROM product p 
                LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID 
                WHERE p.sku = ? AND p.endDate IS NULL 
                LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $sku);
    }

    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $product = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
}

// Check if user is logged in and if they have purchased this product (MP4 Verified Customer Check)
$hasPurchased = false;
$customerAccountID = (int)($_SESSION['customerAccountID'] ?? 0);
$userReview = null;
$reviews = [];
$avgRating = 5.0;
$totalReviews = 0;

if ($conn && $product) {
    $pID = (int)$product['productID'];

    // 1. Check purchase verification
    if ($customerAccountID > 0) {
        $checkPurchaseSql = "SELECT COUNT(*) AS c 
                             FROM productSale ps 
                             JOIN orderLineProduct olp ON ps.productSaleID = olp.productSaleID 
                             WHERE ps.customerAccountID = ? AND olp.productID = ?";
        $cpStmt = mysqli_prepare($conn, $checkPurchaseSql);
        if ($cpStmt) {
            mysqli_stmt_bind_param($cpStmt, 'ii', $customerAccountID, $pID);
            mysqli_stmt_execute($cpStmt);
            $cpRes = mysqli_stmt_get_result($cpStmt);
            if ($cpRow = mysqli_fetch_assoc($cpRes)) {
                $hasPurchased = ((int)$cpRow['c'] > 0);
            }
            mysqli_stmt_close($cpStmt);
        }

        // Fetch user's own review if already submitted
        $urStmt = mysqli_prepare($conn, "SELECT reviewID, rating, reviewText, cleanText, createdAt, updatedAt FROM review WHERE customerAccountID = ? AND productID = ? LIMIT 1");
        if ($urStmt) {
            mysqli_stmt_bind_param($urStmt, 'ii', $customerAccountID, $pID);
            mysqli_stmt_execute($urStmt);
            $urRes = mysqli_stmt_get_result($urStmt);
            $userReview = mysqli_fetch_assoc($urRes);
            mysqli_stmt_close($urStmt);
        }
    }

    // 2. Fetch all published reviews for this product
    $revSql = "SELECT r.reviewID, r.customerAccountID, r.rating, r.cleanText, r.createdAt, r.updatedAt,
                      ca.username,
                      c.firstName, c.lastName, c.photo
               FROM review r
               JOIN customerAccount ca ON r.customerAccountID = ca.customerAccountID
               JOIN customer c ON ca.customerID = c.customerID
               WHERE r.productID = ?
               ORDER BY r.createdAt DESC";
    $rStmt = mysqli_prepare($conn, $revSql);
    if ($rStmt) {
        mysqli_stmt_bind_param($rStmt, 'i', $pID);
        mysqli_stmt_execute($rStmt);
        $rRes = mysqli_stmt_get_result($rStmt);
        $ratingSum = 0;
        while ($rRow = mysqli_fetch_assoc($rRes)) {
            $reviews[] = $rRow;
            $ratingSum += (int)$rRow['rating'];
        }
        mysqli_stmt_close($rStmt);

        $totalReviews = count($reviews);
        if ($totalReviews > 0) {
            $avgRating = round($ratingSum / $totalReviews, 1);
        }
    }

    // 3. Fetch all photos for multiple photo viewing (MP1)
    $productImages = [];
    $imgStmt = mysqli_prepare($conn, "SELECT productImageID, imagePath, isPrimary FROM productImage WHERE productID = ? ORDER BY isPrimary DESC, productImageID ASC");
    if ($imgStmt) {
        mysqli_stmt_bind_param($imgStmt, 'i', $pID);
        mysqli_stmt_execute($imgStmt);
        $imgRes = mysqli_stmt_get_result($imgStmt);
        while ($iRow = mysqli_fetch_assoc($imgRes)) {
            $productImages[] = $iRow;
        }
        mysqli_stmt_close($imgStmt);
    }
    if (empty($productImages) && !empty($product['image'])) {
        $productImages[] = [
            'productImageID' => 0,
            'imagePath' => $product['image'],
            'isPrimary' => 1
        ];
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <?php include __DIR__ . '/includes/alert.php'; ?>

    <?php if (!$product): ?>
        <div class="pw-cart-card text-center py-5 px-4 my-4">
            <div class="mb-3">
                <i class="fa-solid fa-triangle-exclamation fa-3x text-warning"></i>
            </div>
            <h2 class="fw-bold text-white mb-2">Product Not Found</h2>
            <p class="text-white-50 mx-auto mb-4" style="max-width: 440px;">
                The bicycle or component you are looking for may have been archived, discontinued, or does not exist.
            </p>
            <a href="products.php" class="pw-btn-trail px-4 py-2">
                <i class="fa-solid fa-arrow-left me-2"></i> Return to Catalog
            </a>
        </div>
    <?php else: 
        $catName      = $product['categoryName'] ?? 'Gear & Components';
        $stock        = (int)$product['stock'];
        $threshold    = (int)$product['lowStockThreshold'];
        $isOutOfStock = ($stock <= 0);
        $isLowStock   = ($stock > 0 && $stock <= $threshold);
    ?>
        <!-- Breadcrumb Navigation -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb small text-white-50">
                <li class="breadcrumb-item"><a href="index.php" class="text-white-50 text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="products.php" class="text-white-50 text-decoration-none">Catalog</a></li>
                <li class="breadcrumb-item"><a href="products.php?category=<?php echo urlencode($catName); ?>" class="text-white-50 text-decoration-none"><?php echo htmlspecialchars($catName); ?></a></li>
                <li class="breadcrumb-item active text-white" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>

        <!-- Product Primary Showcase -->
        <div class="row g-4 mb-5">
            <!-- Left: Product Image Stage & Multi-Photo Showcase (MP1) -->
            <div class="col-lg-6">
                <div class="pw-cart-card p-4 p-md-5 d-flex align-items-center justify-content-center position-relative" 
                     style="min-height: 440px; background: #ffffff; border-radius: 20px;">
                    <div class="position-absolute top-0 start-0 m-3 d-flex gap-2">
                        <span class="badge rounded-pill bg-dark text-white px-3 py-2 fw-semibold" style="letter-spacing: 0.03em;">
                            <?php echo htmlspecialchars($catName); ?>
                        </span>
                        <?php if (count($productImages) > 1): ?>
                            <span class="badge rounded-pill bg-secondary text-white px-3 py-2 fw-semibold">
                                <i class="fa-solid fa-images me-1"></i><?php echo count($productImages); ?> Photos
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="position-absolute top-0 end-0 m-3">
                        <span class="badge rounded-pill <?php echo $isOutOfStock ? 'bg-danger text-white' : ($isLowStock ? 'bg-warning text-dark' : 'bg-success text-white'); ?> px-3 py-2 fw-semibold">
                            <i class="fa-solid fa-circle-dot me-1"></i>
                            <?php 
                            if ($isOutOfStock) {
                                echo 'Out of Stock';
                            } elseif ($isLowStock) {
                                echo 'Low Stock (' . $stock . ' left)';
                            } else {
                                echo 'In Stock (' . $stock . ' available)';
                            }
                            ?>
                        </span>
                    </div>

                    <?php 
                    $mainImageSrc = !empty($productImages) ? $productImages[0]['imagePath'] : $product['image'];
                    ?>
                    <?php if (!empty($mainImageSrc) && file_exists(__DIR__ . '/' . $mainImageSrc)): ?>
                        <img id="pwMainProductImage" 
                             src="/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($mainImageSrc); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="img-fluid" style="max-height: 380px; object-fit: contain; transition: opacity 0.2s ease;">
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-bicycle fa-4x mb-3 text-secondary"></i>
                            <div class="fw-semibold"><?php echo htmlspecialchars($product['name']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Multiple Photo Thumbnails Strip (MP1: View Multiple Photos) -->
                <?php if (count($productImages) > 1): ?>
                    <div class="d-flex flex-wrap gap-2 mt-3 justify-content-center">
                        <?php foreach ($productImages as $tIdx => $tImg): ?>
                            <button type="button" 
                                    class="btn p-1 rounded-3 pw-thumb-btn <?php echo ($tIdx === 0) ? 'active' : ''; ?>" 
                                    style="width: 72px; height: 72px; background: #ffffff; border: 2px solid <?php echo ($tIdx === 0) ? '#ea580c' : 'rgba(255,255,255,0.2)'; ?>; overflow: hidden; cursor: pointer; transition: all 0.2s;"
                                    onclick="pwSwitchMainImage('/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($tImg['imagePath']); ?>', this)">
                                <img src="/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($tImg['imagePath']); ?>" 
                                     alt="Photo <?php echo $tIdx + 1; ?>" 
                                     style="width: 100%; height: 100%; object-fit: contain;">
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <script>
                    function pwSwitchMainImage(src, btn) {
                        const mainImg = document.getElementById('pwMainProductImage');
                        if (mainImg) {
                            mainImg.style.opacity = '0.3';
                            setTimeout(() => {
                                mainImg.src = src;
                                mainImg.style.opacity = '1';
                            }, 150);
                        }
                        document.querySelectorAll('.pw-thumb-btn').forEach(b => {
                            b.style.borderColor = 'rgba(255,255,255,0.2)';
                            b.classList.remove('active');
                        });
                        btn.style.borderColor = '#ea580c';
                        btn.classList.add('active');
                    }
                    </script>
                <?php endif; ?>
            </div>

            <!-- Right: Product Purchasing & Overview -->
            <div class="col-lg-6">
                <div class="pw-cart-card p-4 p-md-5 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <!-- SKU and Category Header -->
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="pw-sku-mono text-warning">
                                <i class="fa-solid fa-barcode me-1"></i><?php echo htmlspecialchars($product['sku']); ?>
                            </span>
                            <span class="text-white-50 small">&bull;</span>
                            <span class="text-white-50 small"><?php echo htmlspecialchars($catName); ?></span>
                        </div>

                        <!-- Product Title -->
                        <h1 class="h2 fw-bold text-white mb-3" style="letter-spacing: -0.02em; line-height: 1.25;">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </h1>

                        <!-- Price Tag -->
                        <div class="mb-4">
                            <div class="text-white-50 small text-uppercase fw-semibold" style="letter-spacing: 0.05em;">Price</div>
                            <div class="h2 fw-bold text-white mb-0" style="color: var(--pw-trail-amber) !important; font-size: 2.4rem;">
                                &#8369;<?php echo number_format($product['price'], 2); ?>
                            </div>
                            <div class="text-white-50 small mt-1">
                                <i class="fa-solid fa-circle-check text-success me-1"></i> Inclusive of standard basecamp tax &amp; preparation
                            </div>
                        </div>

                        <!-- Short Description -->
                        <div class="mb-4 pb-3 border-bottom border-white border-opacity-10">
                            <h6 class="text-white fw-bold mb-2">Overview</h6>
                            <p class="text-white-50 mb-0" style="line-height: 1.7; font-size: 0.95rem;">
                                <?php echo nl2br(htmlspecialchars($product['description'] ?? '')); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Add to Cart Form -->
                    <div>
                        <?php if (!$isOutOfStock): ?>
                            <form action="cart_action.php" method="POST" class="mb-4">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="productID" value="<?php echo (int)$product['productID']; ?>">
                                <input type="hidden" name="redirect" value="product_details.php?id=<?php echo (int)$product['productID']; ?>">

                                <div class="d-flex flex-wrap align-items-center gap-3">
                                    <div>
                                        <label class="form-label text-white-50 small mb-1 d-block">Quantity</label>
                                        <div class="pw-stepper-group">
                                            <button type="button" class="pw-stepper-btn" onclick="let input=this.nextElementSibling; if(parseInt(input.value)>1) input.value=parseInt(input.value)-1;">
                                                <i class="fa-solid fa-minus"></i>
                                            </button>
                                            <input type="number" name="quantity" class="pw-stepper-input" value="1" min="1" max="<?php echo $stock; ?>" readonly>
                                            <button type="button" class="pw-stepper-btn" onclick="let input=this.previousElementSibling; if(parseInt(input.value)< <?php echo $stock; ?>) input.value=parseInt(input.value)+1;">
                                                <i class="fa-solid fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex-grow-1" style="min-width: 180px;">
                                        <label class="form-label text-transparent small mb-1 d-block select-none">Action</label>
                                        <button type="submit" class="pw-btn-trail w-100 py-2 justify-content-center text-center">
                                            <i class="fa-solid fa-cart-shopping me-2"></i> Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-secondary bg-white bg-opacity-10 text-white border-0 py-3 px-4 rounded-3 mb-4">
                                <i class="fa-solid fa-circle-info me-2 text-warning"></i>
                                <strong>Currently Out of Stock.</strong> Please check back soon or inquire at our workshop for replenishment updates.
                            </div>
                        <?php endif; ?>

                        <!-- Feature highlights -->
                        <div class="row g-2 pt-2 border-top border-white border-opacity-10 small text-white-50">
                            <div class="col-sm-6">
                                <i class="fa-solid fa-shield-halved text-success me-1"></i> Genuine Trail Guarantee
                            </div>
                            <div class="col-sm-6">
                                <i class="fa-solid fa-store text-warning me-1"></i> Free Taguig Basecamp Pickup
                            </div>
                            <div class="col-sm-6">
                                <i class="fa-solid fa-truck-fast text-primary me-1"></i> Doorstep COD Available
                            </div>
                            <div class="col-sm-6">
                                <i class="fa-solid fa-wrench text-info me-1"></i> Workshop Installation Support
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Specifications & Technical Details -->
        <div class="row g-4 mb-5">
            <div class="col-lg-8">
                <div class="pw-cart-card p-4">
                    <h4 class="fw-bold text-white mb-3 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-list-check text-warning"></i>
                        <span>Technical Specifications</span>
                    </h4>
                    <div class="table-responsive">
                        <table class="table table-dark table-borderless align-middle mb-0" style="background: transparent;">
                            <tbody>
                                <tr class="border-bottom border-white border-opacity-10">
                                    <th class="text-white-50 py-3" style="width: 30%;">Product SKU</th>
                                    <td class="text-white fw-semibold py-3 font-monospace"><?php echo htmlspecialchars($product['sku']); ?></td>
                                </tr>
                                <tr class="border-bottom border-white border-opacity-10">
                                    <th class="text-white-50 py-3">Category</th>
                                    <td class="text-white py-3"><?php echo htmlspecialchars($catName); ?></td>
                                </tr>
                                <tr class="border-bottom border-white border-opacity-10">
                                    <th class="text-white-50 py-3">Current Unit Price</th>
                                    <td class="text-white fw-bold py-3">&#8369;<?php echo number_format($product['price'], 2); ?></td>
                                </tr>
                                <tr class="border-bottom border-white border-opacity-10">
                                    <th class="text-white-50 py-3">Availability</th>
                                    <td class="text-white py-3">
                                        <?php echo $isOutOfStock ? '<span class="text-danger">Out of Stock</span>' : '<span class="text-success">' . $stock . ' Units Ready for Dispatch</span>'; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-white-50 py-3">Warranty &amp; Service</th>
                                    <td class="text-white py-3">Covered by PedalWorks Standard 30-Day Workshop Support &amp; Component Verification.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Workshop Services Callout -->
            <div class="col-lg-4">
                <div class="pw-cart-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="pw-brand-icon mb-3" style="width: 44px; height: 44px; font-size: 1.25rem;">
                            <i class="fa-solid fa-wrench"></i>
                        </div>
                        <h5 class="fw-bold text-white mb-2">Need Professional Fitting?</h5>
                        <p class="text-white-50 small mb-4" style="line-height: 1.6;">
                            Our certified mechanics can install or tune this component directly on your bike at the Taguig Basecamp workshop.
                        </p>
                    </div>
                    <a href="services.php" class="pw-btn-glass w-100 py-2 text-center justify-content-center">
                        <i class="fa-solid fa-toolbox me-2"></i> View Workshop Services
                    </a>
                </div>
            </div>
        </div>

        <!-- Section: Customer Reviews & Ratings (MP4 Review CRUD) -->
        <div class="pw-cart-card p-4 p-md-5" id="reviews">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom border-white border-opacity-10 gap-3">
                <div>
                    <h3 class="fw-bold text-white mb-1 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-star text-warning"></i>
                        <span>Customer Reviews &amp; Ratings</span>
                    </h3>
                    <p class="text-white-50 small mb-0">
                        Authentic feedback from verified riders who purchased or availed this gear.
                    </p>
                </div>

                <div>
                    <?php if ($hasPurchased): ?>
                        <span class="badge rounded-pill bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-3 py-2 small">
                            <i class="fa-solid fa-circle-check me-1"></i> Verified Purchaser
                        </span>
                    <?php else: ?>
                        <span class="badge rounded-pill bg-white bg-opacity-10 text-white-50 px-3 py-2 small">
                            <i class="fa-solid fa-lock me-1"></i> Reviews open to verified purchasers
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Review Summary Cards -->
            <div class="row g-4 align-items-center mb-4">
                <div class="col-md-4 text-center border-end border-white border-opacity-10 py-3">
                    <div class="h1 fw-bold text-white mb-1"><?php echo number_format($avgRating, 1); ?></div>
                    <div class="text-warning mb-2" style="letter-spacing: 2px;">
                        <?php 
                        $fullStars = floor($avgRating);
                        for ($s = 1; $s <= 5; $s++) {
                            if ($s <= $fullStars) {
                                echo '<i class="fa-solid fa-star"></i>';
                            } else {
                                echo '<i class="fa-regular fa-star text-white-50"></i>';
                            }
                        }
                        ?>
                    </div>
                    <span class="text-white-50 small">Based on <?php echo $totalReviews; ?> verified <?php echo ($totalReviews === 1) ? 'review' : 'reviews'; ?></span>
                </div>

                <div class="col-md-8">
                    <?php if ($hasPurchased): ?>
                        <div class="p-4 rounded-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08);">
                            <h5 class="fw-bold text-white mb-2 d-flex align-items-center gap-2">
                                <i class="fa-solid fa-pen-to-square text-warning"></i>
                                <span><?php echo $userReview ? 'Update Your Review' : 'Share Your Rider Experience'; ?></span>
                            </h5>
                            <p class="text-white-50 small mb-3">
                                Tell other mountain and trail riders how this gear performed in real conditions. Inappropriate language will be automatically masked.
                            </p>

                            <!-- Pure PHP Review Form (MP5 novalidate) -->
                            <form action="review_action.php" method="POST" novalidate>
                                <input type="hidden" name="action" value="<?php echo $userReview ? 'update' : 'create'; ?>">
                                <input type="hidden" name="productID" value="<?php echo (int)$product['productID']; ?>">
                                <?php if ($userReview): ?>
                                    <input type="hidden" name="reviewID" value="<?php echo (int)$userReview['reviewID']; ?>">
                                <?php endif; ?>

                                <div class="row g-3">
                                    <div class="col-sm-5 col-md-4">
                                        <label class="form-label text-white-50 small text-uppercase fw-bold">Star Rating</label>
                                        <select name="rating" class="form-select bg-dark text-white border-secondary">
                                            <option value="5" <?php echo (($userReview['rating'] ?? 5) == 5) ? 'selected' : ''; ?>>★★★★★ (5 - Excellent)</option>
                                            <option value="4" <?php echo (($userReview['rating'] ?? 5) == 4) ? 'selected' : ''; ?>>★★★★☆ (4 - Good)</option>
                                            <option value="3" <?php echo (($userReview['rating'] ?? 5) == 3) ? 'selected' : ''; ?>>★★★☆☆ (3 - Average)</option>
                                            <option value="2" <?php echo (($userReview['rating'] ?? 5) == 2) ? 'selected' : ''; ?>>★★☆☆☆ (2 - Below Expectation)</option>
                                            <option value="1" <?php echo (($userReview['rating'] ?? 5) == 1) ? 'selected' : ''; ?>>★☆☆☆☆ (1 - Poor)</option>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label text-white-50 small text-uppercase fw-bold">Your Review</label>
                                        <textarea name="reviewText" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Write your thoughts about durability, trail performance, fit, and build quality..."><?php echo htmlspecialchars($userReview['reviewText'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="col-12 d-flex flex-wrap gap-2">
                                        <button type="submit" class="pw-btn-trail-sm px-4">
                                            <i class="fa-solid fa-paper-plane me-1"></i>
                                            <?php echo $userReview ? 'Save Updated Review' : 'Publish Review'; ?>
                                        </button>
                                        <?php if ($userReview): ?>
                                            <button type="submit" name="action" value="delete" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete your review?');">
                                                <i class="fa-solid fa-trash-can me-1"></i> Remove Review
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="p-4 rounded-3 text-center" style="background: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255, 255, 255, 0.1);">
                            <i class="fa-solid fa-cart-shopping text-warning fs-3 mb-2 d-block"></i>
                            <h6 class="text-white fw-bold mb-1">Purchased This Product?</h6>
                            <p class="text-white-50 small mb-2">
                                Review submissions are exclusively unlocked for verified customers who have completed an order for this gear.
                            </p>
                            <?php if (empty($_SESSION['customerAccountID'])): ?>
                                <a href="login.php" class="pw-btn-glass-sm px-3 py-1">
                                    <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Sign In to Check Purchase Status
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Customer Reviews List -->
            <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                <h5 class="fw-bold text-white mb-3 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-comments text-warning"></i>
                    <span>Rider Experiences (<?php echo count($reviews); ?>)</span>
                </h5>

                <?php if (empty($reviews)): ?>
                    <div class="text-center py-4 text-white-50">
                        <i class="fa-regular fa-message fa-2x mb-2 opacity-50 d-block"></i>
                        <p class="small mb-0">No reviews have been submitted for this item yet. Be the first verified customer to share your thoughts!</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($reviews as $rev): 
                            $isMyReview = ($customerAccountID > 0 && (int)$rev['customerAccountID'] === $customerAccountID);
                            $reviewerName = trim($rev['firstName'] . ' ' . $rev['lastName']);
                            if ($reviewerName === '') {
                                $reviewerName = $rev['username'];
                            }
                            $avatarInitial = strtoupper(substr($reviewerName, 0, 1));
                        ?>
                            <div class="col-12">
                                <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, <?php echo $isMyReview ? '0.05' : '0.02'; ?>); border: 1px solid rgba(255, 255, 255, <?php echo $isMyReview ? '0.15' : '0.06'; ?>);">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($rev['photo']) && file_exists(__DIR__ . '/assets/images/uploads/users/' . $rev['photo'])): ?>
                                                <img src="/project/PedalWorks-Dynamics/assets/images/uploads/users/<?php echo htmlspecialchars($rev['photo']); ?>" 
                                                     alt="Reviewer" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold small" 
                                                     style="width: 32px; height: 32px; background: linear-gradient(135deg, #d97706, #ea580c);">
                                                    <?php echo htmlspecialchars($avatarInitial); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-semibold text-white small d-flex align-items-center gap-2">
                                                    <span><?php echo htmlspecialchars($reviewerName); ?></span>
                                                    <span class="badge rounded-pill bg-success bg-opacity-20 text-success border border-success border-opacity-25" style="font-size: 0.65rem;">
                                                        <i class="fa-solid fa-check me-1"></i>Verified Purchaser
                                                    </span>
                                                    <?php if ($isMyReview): ?>
                                                        <span class="badge rounded-pill bg-primary bg-opacity-20 text-primary border border-primary border-opacity-25" style="font-size: 0.65rem;">
                                                            Your Review
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-white-50" style="font-size: 0.72rem;">
                                                    <?php echo date('M d, Y', strtotime($rev['createdAt'])); ?>
                                                    <?php if ($rev['updatedAt'] && $rev['updatedAt'] !== $rev['createdAt']): ?>
                                                        &bull; (Updated <?php echo date('M d, Y', strtotime($rev['updatedAt'])); ?>)
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>

                                        <div class="text-warning small" style="letter-spacing: 1px;">
                                            <?php 
                                            for ($s = 1; $s <= 5; $s++) {
                                                if ($s <= (int)$rev['rating']) {
                                                    echo '<i class="fa-solid fa-star"></i>';
                                                } else {
                                                    echo '<i class="fa-regular fa-star text-white-50"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <div class="text-light small mt-2" style="line-height: 1.6;">
                                        <?php echo nl2br(htmlspecialchars($rev['cleanText'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center pt-4">
                <a href="products.php" class="pw-btn-glass-sm px-4 py-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Explore More Products
                </a>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
