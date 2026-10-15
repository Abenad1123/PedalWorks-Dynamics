<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';

// Verify database connection
if (!$conn) {
    $_SESSION['error'] = 'Database service unavailable. Please try again later.';
    header('Location: products.php');
    exit;
}

// Ensure user is an authenticated customer (FR6 Requirement)
if (empty($_SESSION['customerAccountID'])) {
    $_SESSION['error'] = 'Please sign in with your customer account to manage your shopping cart.';
    header('Location: login.php');
    exit;
}

$customerAccountID = (int)$_SESSION['customerAccountID'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? 'cart.php';

// Sanitize redirect target to avoid open redirects
if (!in_array($redirect, ['cart.php', 'products.php', 'index.php']) && !str_starts_with($redirect, 'products.php')) {
    $redirect = 'cart.php';
}

switch ($action) {
    case 'add':
        $productID = isset($_POST['productID']) ? (int)$_POST['productID'] : 0;
        $quantity  = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

        if ($productID <= 0 || $quantity <= 0) {
            $_SESSION['error'] = 'Invalid product or quantity specified.';
            header('Location: ' . $redirect);
            exit;
        }

        // Fetch current active product version and check stock
        $stmt = mysqli_prepare($conn, "SELECT productID, name, stock, price FROM product WHERE productID = ? AND endDate IS NULL LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $productID);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $product = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$product) {
            $_SESSION['error'] = 'Product not found or is no longer available.';
            header('Location: ' . $redirect);
            exit;
        }

        $availableStock = (int)$product['stock'];
        if ($availableStock <= 0) {
            $_SESSION['error'] = 'Sorry, "' . htmlspecialchars($product['name']) . '" is currently out of stock.';
            header('Location: ' . $redirect);
            exit;
        }

        // Check current quantity in cart
        $cartStmt = mysqli_prepare($conn, "SELECT quantity FROM customerCart WHERE customerAccountID = ? AND productID = ? LIMIT 1");
        mysqli_stmt_bind_param($cartStmt, 'ii', $customerAccountID, $productID);
        mysqli_stmt_execute($cartStmt);
        $cartRes = mysqli_stmt_get_result($cartStmt);
        $cartRow = mysqli_fetch_assoc($cartRes);
        mysqli_stmt_close($cartStmt);

        $currentCartQty = $cartRow ? (int)$cartRow['quantity'] : 0;
        $newQty = $currentCartQty + $quantity;

        if ($newQty > $availableStock) {
            $newQty = $availableStock;
            $_SESSION['warning'] = 'Quantity adjusted to maximum available stock (' . $availableStock . ') for "' . htmlspecialchars($product['name']) . '".';
        } else {
            $_SESSION['success'] = 'Added ' . $quantity . ' &times; <strong>' . htmlspecialchars($product['name']) . '</strong> to your cart!';
        }

        // Upsert cart item
        $upStmt = mysqli_prepare($conn, "INSERT INTO customerCart (customerAccountID, productID, quantity) 
                                         VALUES (?, ?, ?) 
                                         ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)");
        mysqli_stmt_bind_param($upStmt, 'iii', $customerAccountID, $productID, $newQty);
        mysqli_stmt_execute($upStmt);
        mysqli_stmt_close($upStmt);

        header('Location: ' . $redirect);
        exit;

    case 'update':
        $productID = isset($_POST['productID']) ? (int)$_POST['productID'] : 0;
        $quantity  = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

        if ($productID <= 0) {
            $_SESSION['error'] = 'Invalid product reference.';
            header('Location: cart.php');
            exit;
        }

        if ($quantity <= 0) {
            // Remove if quantity is 0 or less
            $delStmt = mysqli_prepare($conn, "DELETE FROM customerCart WHERE customerAccountID = ? AND productID = ?");
            mysqli_stmt_bind_param($delStmt, 'ii', $customerAccountID, $productID);
            mysqli_stmt_execute($delStmt);
            mysqli_stmt_close($delStmt);
            $_SESSION['success'] = 'Item removed from your shopping cart.';
        } else {
            // Check available stock
            $stmt = mysqli_prepare($conn, "SELECT name, stock FROM product WHERE productID = ? AND endDate IS NULL LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $productID);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $product = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($product) {
                $availableStock = (int)$product['stock'];
                if ($quantity > $availableStock) {
                    $quantity = $availableStock;
                    $_SESSION['warning'] = 'Quantity adjusted to available stock limit (' . $availableStock . ').';
                }
                $upStmt = mysqli_prepare($conn, "UPDATE customerCart SET quantity = ? WHERE customerAccountID = ? AND productID = ?");
                mysqli_stmt_bind_param($upStmt, 'iii', $quantity, $customerAccountID, $productID);
                mysqli_stmt_execute($upStmt);
                mysqli_stmt_close($upStmt);
                $_SESSION['success'] = 'Cart updated successfully.';
            }
        }

        header('Location: cart.php');
        exit;

    case 'remove':
        $productID = isset($_POST['productID']) ? (int)$_POST['productID'] : (isset($_GET['productID']) ? (int)$_GET['productID'] : 0);
        if ($productID > 0) {
            $delStmt = mysqli_prepare($conn, "DELETE FROM customerCart WHERE customerAccountID = ? AND productID = ?");
            mysqli_stmt_bind_param($delStmt, 'ii', $customerAccountID, $productID);
            mysqli_stmt_execute($delStmt);
            mysqli_stmt_close($delStmt);
            $_SESSION['success'] = 'Item removed from your cart.';
        }
        header('Location: cart.php');
        exit;

    case 'clear':
        $clearStmt = mysqli_prepare($conn, "DELETE FROM customerCart WHERE customerAccountID = ?");
        mysqli_stmt_bind_param($clearStmt, 'i', $customerAccountID);
        mysqli_stmt_execute($clearStmt);
        mysqli_stmt_close($clearStmt);
        $_SESSION['success'] = 'Shopping cart cleared.';
        header('Location: cart.php');
        exit;

    default:
        header('Location: cart.php');
        exit;
}
