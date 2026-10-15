<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/config.php';

// Auth Guard: Require logged-in customer (FR15 Requirement)
if (empty($_SESSION['customerAccountID'])) {
    $_SESSION['warning'] = 'Please sign in to schedule a workshop repair appointment.';
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: services.php');
    exit;
}

$customerAccountID = (int)$_SESSION['customerAccountID'];
$serviceID         = isset($_POST['serviceID']) ? (int)$_POST['serviceID'] : 0;
$scheduledDateRaw  = isset($_POST['scheduledDate']) ? trim($_POST['scheduledDate']) : '';
$notes             = isset($_POST['notes']) ? trim($_POST['notes']) : '';

$errors = [];

// 1. Validate Service ID
if ($serviceID <= 0 || !$conn) {
    $errors[] = 'Please select a valid workshop service.';
} else {
    $svcStmt = mysqli_prepare($conn, "SELECT serviceID, name, price FROM service WHERE serviceID = ? AND endDate IS NULL LIMIT 1");
    if ($svcStmt) {
        mysqli_stmt_bind_param($svcStmt, 'i', $serviceID);
        mysqli_stmt_execute($svcStmt);
        $svcRes = mysqli_stmt_get_result($svcStmt);
        $service = mysqli_fetch_assoc($svcRes);
        mysqli_stmt_close($svcStmt);
        if (!$service) {
            $errors[] = 'The selected service is no longer available.';
        }
    } else {
        $errors[] = 'Database query failed.';
    }
}

// 2. Validate Scheduled Date
$scheduledDateFormatted = null;
if ($scheduledDateRaw === '') {
    $errors[] = 'Please choose a preferred appointment date.';
} else {
    $timestamp = strtotime($scheduledDateRaw);
    if (!$timestamp) {
        $errors[] = 'Invalid appointment date format provided.';
    } else {
        // Must be in the future (at least current time or later)
        $minTime = strtotime(date('Y-m-d 08:00:00'));
        if ($timestamp < $minTime) {
            $errors[] = 'Appointment date cannot be in the past.';
        } else {
            $scheduledDateFormatted = date('Y-m-d H:i:s', $timestamp);
        }
    }
}

// 3. Validate Notes
if (mb_strlen($notes) > 1000) {
    $errors[] = 'Notes cannot exceed 1,000 characters.';
}

if (!empty($errors)) {
    $_SESSION['error'] = implode(' ', $errors);
    header('Location: services.php');
    exit;
}

// Insert into customerService with initial 'Pending' status
$insertSql = "INSERT INTO customerService (serviceID, customerAccountID, requestDate, scheduledDate, notes, status) 
              VALUES (?, ?, NOW(), ?, ?, 'Pending')";
$stmt = mysqli_prepare($conn, $insertSql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'iiss', $serviceID, $customerAccountID, $scheduledDateFormatted, $notes);
    if (mysqli_stmt_execute($stmt)) {
        $customerServiceID = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $_SESSION['success'] = 'Workshop appointment for "' . htmlspecialchars($service['name']) . '" scheduled successfully! Tracking Reference: #PW-SRV-' . $customerServiceID;
        header('Location: user/services.php?highlight=' . $customerServiceID);
        exit;
    } else {
        $_SESSION['error'] = 'Failed to submit service request: ' . mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        header('Location: services.php');
        exit;
    }
} else {
    $_SESSION['error'] = 'Database error while preparing service request.';
    header('Location: services.php');
    exit;
}
