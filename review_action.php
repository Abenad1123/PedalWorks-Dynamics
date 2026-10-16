<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('includes/config.php');
include('includes/profanity_filter.php');

// Auth Guard: Only logged-in customers can submit or edit reviews
if (empty($_SESSION['customerAccountID'])) {
    $_SESSION['error'] = 'Please sign in to share or edit your gear review.';
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

$customerAccountID = (int)$_SESSION['customerAccountID'];
$action     = isset($_POST['action']) ? trim($_POST['action']) : 'create';
$productID  = !empty($_POST['productID']) ? (int)$_POST['productID'] : null;
$serviceID  = !empty($_POST['serviceID']) ? (int)$_POST['serviceID'] : null;
$reviewID   = !empty($_POST['reviewID']) ? (int)$_POST['reviewID'] : 0;
$rating     = isset($_POST['rating']) ? (int)$_POST['rating'] : 5;
$reviewText = isset($_POST['reviewText']) ? trim($_POST['reviewText']) : '';

// Determine redirect destination
$redirect = 'products.php';
if ($productID !== null && $productID > 0) {
    $redirect = 'product_details.php?id=' . $productID . '#reviews';
} elseif ($serviceID !== null && $serviceID > 0) {
    $redirect = 'services.php#reviews';
}

if (!$conn) {
    $_SESSION['error'] = 'Database connection offline. Please try again.';
    header('Location: ' . $redirect);
    exit;
}

// 1. Validation (Pure PHP validation - MP5 compliant)
$fieldErrors = [];
if ($rating < 1 || $rating > 5) {
    $fieldErrors[] = 'Please provide a valid rating between 1 and 5 stars.';
}
if ($reviewText === '') {
    $fieldErrors[] = 'Please write a brief description of your experience.';
} elseif (strlen($reviewText) < 5) {
    $fieldErrors[] = 'Your review must be at least 5 characters long.';
} elseif (strlen($reviewText) > 2000) {
    $fieldErrors[] = 'Your review cannot exceed 2,000 characters.';
}

if (!empty($fieldErrors)) {
    $_SESSION['error'] = implode(' ', $fieldErrors);
    header('Location: ' . $redirect);
    exit;
}

// 2. Verified Customer Check (MP4: "a product or service can be reviewed by a customer that availed the specified product or service")
$isVerified = false;

if ($productID !== null && $productID > 0) {
    // Check if customer purchased this product
    $vpSql = "SELECT COUNT(*) AS c 
              FROM productSale ps 
              JOIN orderLineProduct olp ON ps.productSaleID = olp.productSaleID 
              WHERE ps.customerAccountID = ? AND olp.productID = ?";
    $vpStmt = mysqli_prepare($conn, $vpSql);
    if ($vpStmt) {
        mysqli_stmt_bind_param($vpStmt, 'ii', $customerAccountID, $productID);
        mysqli_stmt_execute($vpStmt);
        $vpRes = mysqli_stmt_get_result($vpStmt);
        if ($vpRow = mysqli_fetch_assoc($vpRes)) {
            $isVerified = ((int)$vpRow['c'] > 0);
        }
        mysqli_stmt_close($vpStmt);
    }
} elseif ($serviceID !== null && $serviceID > 0) {
    // Check if customer availed this service
    $vsSql = "SELECT COUNT(*) AS c 
              FROM customerService 
              WHERE customerAccountID = ? AND serviceID = ?";
    $vsStmt = mysqli_prepare($conn, $vsSql);
    if ($vsStmt) {
        mysqli_stmt_bind_param($vsStmt, 'ii', $customerAccountID, $serviceID);
        mysqli_stmt_execute($vsStmt);
        $vsRes = mysqli_stmt_get_result($vsStmt);
        if ($vsRow = mysqli_fetch_assoc($vsRes)) {
            $isVerified = ((int)$vsRow['c'] > 0);
        }
        mysqli_stmt_close($vsStmt);
    }
}

if (!$isVerified) {
    $_SESSION['error'] = 'Only verified customers who have availed this item or service can submit a review.';
    header('Location: ' . $redirect);
    exit;
}

// 3. Mask foul words via regex (MP4 Rubric)
$cleanText = maskProfanity($reviewText);

// 4. Handle Create or Update
if ($action === 'update' && $reviewID > 0) {
    // Ensure review exists and belongs to the current user
    $chkStmt = mysqli_prepare($conn, "SELECT reviewID FROM review WHERE reviewID = ? AND customerAccountID = ? LIMIT 1");
    mysqli_stmt_bind_param($chkStmt, 'ii', $reviewID, $customerAccountID);
    mysqli_stmt_execute($chkStmt);
    $chkRes = mysqli_stmt_get_result($chkStmt);
    $existing = mysqli_fetch_assoc($chkRes);
    mysqli_stmt_close($chkStmt);

    if (!$existing) {
        $_SESSION['error'] = 'Review record not found or unauthorized to edit.';
        header('Location: ' . $redirect);
        exit;
    }

    $uStmt = mysqli_prepare($conn, "UPDATE review SET rating = ?, reviewText = ?, cleanText = ?, updatedAt = NOW() WHERE reviewID = ? AND customerAccountID = ?");
    if ($uStmt) {
        mysqli_stmt_bind_param($uStmt, 'issii', $rating, $reviewText, $cleanText, $reviewID, $customerAccountID);
        if (mysqli_stmt_execute($uStmt)) {
            $_SESSION['success'] = 'Your review has been updated successfully!';
        } else {
            $_SESSION['error'] = 'Failed to update review: ' . mysqli_stmt_error($uStmt);
        }
        mysqli_stmt_close($uStmt);
    }
} elseif ($action === 'delete' && $reviewID > 0) {
    $dStmt = mysqli_prepare($conn, "DELETE FROM review WHERE reviewID = ? AND customerAccountID = ?");
    if ($dStmt) {
        mysqli_stmt_bind_param($dStmt, 'ii', $reviewID, $customerAccountID);
        if (mysqli_stmt_execute($dStmt)) {
            $_SESSION['success'] = 'Your review has been removed.';
        } else {
            $_SESSION['error'] = 'Failed to delete review: ' . mysqli_stmt_error($dStmt);
        }
        mysqli_stmt_close($dStmt);
    }
} else {
    // Create new review
    // Check if user already reviewed this item; if so, update instead or notify
    $dupSql = "SELECT reviewID FROM review WHERE customerAccountID = ? AND " . 
              ($productID !== null ? "productID = ?" : "serviceID = ?") . " LIMIT 1";
    $dupStmt = mysqli_prepare($conn, $dupSql);
    $targetID = ($productID !== null) ? $productID : $serviceID;
    mysqli_stmt_bind_param($dupStmt, 'ii', $customerAccountID, $targetID);
    mysqli_stmt_execute($dupStmt);
    $dupRes = mysqli_stmt_get_result($dupStmt);
    $dupRow = mysqli_fetch_assoc($dupRes);
    mysqli_stmt_close($dupStmt);

    if ($dupRow) {
        // Automatically update the user's existing review
        $existID = (int)$dupRow['reviewID'];
        $uStmt = mysqli_prepare($conn, "UPDATE review SET rating = ?, reviewText = ?, cleanText = ?, updatedAt = NOW() WHERE reviewID = ?");
        mysqli_stmt_bind_param($uStmt, 'issi', $rating, $reviewText, $cleanText, $existID);
        if (mysqli_stmt_execute($uStmt)) {
            $_SESSION['success'] = 'Your existing review has been updated!';
        } else {
            $_SESSION['error'] = 'Failed to update review: ' . mysqli_stmt_error($uStmt);
        }
        mysqli_stmt_close($uStmt);
    } else {
        // Insert new review record
        $insSql = "INSERT INTO review (customerAccountID, productID, serviceID, rating, reviewText, cleanText, createdAt) 
                   VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $insStmt = mysqli_prepare($conn, $insSql);
        if ($insStmt) {
            mysqli_stmt_bind_param($insStmt, 'iiiiss', $customerAccountID, $productID, $serviceID, $rating, $reviewText, $cleanText);
            if (mysqli_stmt_execute($insStmt)) {
                $_SESSION['success'] = 'Thank you! Your verified review has been published.';
            } else {
                $_SESSION['error'] = 'Failed to submit review: ' . mysqli_stmt_error($insStmt);
            }
            mysqli_stmt_close($insStmt);
        }
    }
}

header('Location: ' . $redirect);
exit;
