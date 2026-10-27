<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('includes/config.php');

if (empty($_SESSION['customerAccountID'])) {
    $_SESSION['error'] = 'Please sign in to view and manage your shopping cart.';
    header('Location: login.php');
    exit;
}

$customerAccountID = (int)$_SESSION['customerAccountID'];
$cartItems = [];
$subtotal = 0.0;
$totalQuantity = 0;

if ($conn) {
    $sql = "SELECT c.customerCartID, c.quantity, 
                   p.productID, p.sku, p.name, p.image, p.price, p.stock, p.lowStockThreshold,
                   pc.name AS categoryName
            FROM customerCart c
            JOIN product p ON c.productID = p.productID
            LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID
            WHERE c.customerAccountID = ? AND p.endDate IS NULL
            ORDER BY c.customerCartID DESC";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $customerAccountID);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $lineSubtotal = (float)$row['price'] * (int)$row['quantity'];
            $row['lineSubtotal'] = $lineSubtotal;
            $subtotal += $lineSubtotal;
            $totalQuantity += (int)$row['quantity'];
            $cartItems[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

include('includes/header.php');
?>

<div class="container py-5">

    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small text-white-50">
                    <li class="breadcrumb-item"><a href="index.php" class="text-white-50 text-decoration-none">Home</a></li>
                    <li class="breadcrumb-item"><a href="products.php" class="text-white-50 text-decoration-none">Catalog</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Shopping Cart</li>
                </ol>
            </nav>
            <h1 class="h2 fw-bold text-white mb-0 d-flex align-items-center gap-2">
                <span>Shopping Cart</span>
                <?php if (!empty($cartItems)): ?>
                    <span class="badge rounded-pill bg-white bg-opacity-10 text-white fs-6 fw-normal">
                        <?php echo $totalQuantity; ?> <?php echo ($totalQuantity === 1) ? 'item' : 'items'; ?>
                    </span>
                <?php endif; ?>
            </h1>
        </div>

        <?php if (!empty($cartItems)): ?>
            <a href="products.php" class="pw-btn-glass text-nowrap">
                <i class="fa-solid fa-arrow-left me-1"></i> Continue Shopping
            </a>
        <?php endif; ?>
    </div>

    <?php include('includes/alert.php'); ?>

    <?php if (empty($cartItems)): ?>

        <div class="pw-cart-card text-center py-5 px-4 my-4">
            <div class="mb-3">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle" 
                     style="width: 80px; height: 80px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1);">
                    <i class="fa-solid fa-bag-shopping fa-2x text-white-50"></i>
                </div>
            </div>
            <h3 class="fw-bold text-white mb-2">Your Bag is Empty</h3>
            <p class="text-white-50 mx-auto mb-4" style="max-width: 440px; font-size: 0.95rem;">
                Explore our catalog of gravel bikes, precision drivetrains, safety helmets, and accessories built for the trail.
            </p>
            <a href="products.php" class="pw-btn-trail px-4 py-2">
                <i class="fa-solid fa-bicycle me-2"></i> Explore Products
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">

            <div class="col-lg-8">
                <div class="pw-cart-card">
                    <div class="p-3 border-bottom border-white border-opacity-10 d-flex justify-content-between align-items-center">
                        <span class="text-uppercase fw-semibold small text-white-50" style="letter-spacing: 0.05em;">Cart Items</span>
                        <form action="cart_action.php" method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to clear your cart?');">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-link text-white-50 text-decoration-none p-0 small hover-white">
                                <i class="fa-regular fa-trash-can me-1"></i> Clear Cart
                            </button>
                        </form>
                    </div>

                    <div>
                        <?php foreach ($cartItems as $item): 
                            $maxStock = (int)$item['stock'];
                            $currentQty = (int)$item['quantity'];
                            $isOutOfStock = ($maxStock <= 0);
                        ?>
                            <div class="pw-cart-item-row flex-column flex-sm-row align-items-start align-items-sm-center">

                                <div class="pw-cart-thumb">
                                    <?php if (!empty($item['image']) && file_exists(__DIR__ . '/' . $item['image'])): ?>
                                        <img src="/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($item['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php else: ?>
                                        <i class="fa-solid fa-bicycle text-muted fa-xl"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge rounded-pill bg-white bg-opacity-10 text-white-50 small" style="font-size: 0.725rem;">
                                            <?php echo htmlspecialchars($item['categoryName'] ?? 'Gear'); ?>
                                        </span>
                                        <span class="pw-sku-mono small opacity-75">
                                            <?php echo htmlspecialchars($item['sku']); ?>
                                        </span>
                                    </div>
                                    <h5 class="fw-semibold text-white mb-1 text-truncate" title="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </h5>
                                    <div class="text-white-50 small mb-2 mb-sm-0">
                                        &#8369;<?php echo number_format($item['price'], 2); ?> each
                                        <?php if ($maxStock <= 5 && $maxStock > 0): ?>
                                            <span class="text-warning ms-2">&bull; Only <?php echo $maxStock; ?> left</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-3">
                                    <form action="cart_action.php" method="POST" class="d-flex align-items-center gap-2 m-0">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="productID" value="<?php echo (int)$item['productID']; ?>">

                                        <div class="pw-stepper-group">
                                            <button type="submit" name="quantity" value="<?php echo max(0, $currentQty - 1); ?>" 
                                                    class="pw-stepper-btn" title="Decrease quantity">
                                                <i class="fa-solid fa-minus"></i>
                                            </button>
                                            <input type="text" readonly class="pw-stepper-input" value="<?php echo $currentQty; ?>">
                                            <button type="submit" name="quantity" value="<?php echo min($maxStock, $currentQty + 1); ?>" 
                                                    class="pw-stepper-btn" title="Increase quantity"
                                                    <?php echo ($currentQty >= $maxStock) ? 'disabled' : ''; ?>>
                                                <i class="fa-solid fa-plus"></i>
                                            </button>
                                        </div>
                                    </form>

                                    <div class="text-end" style="min-width: 95px;">
                                        <span class="text-white fw-bold d-block">
                                            &#8369;<?php echo number_format($item['lineSubtotal'], 2); ?>
                                        </span>
                                    </div>

                                    <form action="cart_action.php" method="POST" class="m-0">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="productID" value="<?php echo (int)$item['productID']; ?>">
                                        <button type="submit" class="btn btn-link text-white-50 p-2 hover-danger" title="Remove Item">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="pw-cart-card p-4 sticky-top" style="top: 90px;">
                    <h4 class="fw-bold text-white mb-3">Order Summary</h4>

                    <div class="d-flex justify-content-between text-white-50 small mb-2">
                        <span>Items Subtotal (<?php echo $totalQuantity; ?>)</span>
                        <span class="text-white fw-semibold">&#8369;<?php echo number_format($subtotal, 2); ?></span>
                    </div>

                    <div class="d-flex justify-content-between text-white-50 small mb-2">
                        <span>Fulfillment</span>
                        <span class="text-white-50">Free Pickup / COD</span>
                    </div>

                    <hr class="border-white border-opacity-10 my-3">

                    <div class="d-flex justify-content-between align-items-baseline mb-4">
                        <span class="text-white fw-semibold">Estimated Total</span>
                        <span class="h3 fw-bold text-white mb-0" style="color: var(--pw-trail-amber) !important;">
                            &#8369;<?php echo number_format($subtotal, 2); ?>
                        </span>
                    </div>

                    <a href="checkout.php" class="pw-btn-trail w-100 text-center py-3 mb-2 justify-content-center">
                        Proceed to Checkout <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>

                    <div class="text-center text-white-50 small pt-3">
                        <i class="fa-solid fa-shield-halved text-success me-1"></i>
                        <span>Secure Checkout with Cash on Delivery / Pickup</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.hover-white:hover {
    color: #ffffff !important;
}
.hover-danger:hover {
    color: #f87171 !important;
}
</style>

<?php include('includes/footer.php'); ?>
