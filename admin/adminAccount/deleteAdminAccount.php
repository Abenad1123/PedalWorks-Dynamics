<?php
session_start();
include('../../includes/config.php');

$deleteID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleteID = isset($_POST['id']) ? (int)$_POST['id'] : 0;
}

if ($deleteID <= 0) {
    $_SESSION['error'] = 'Invalid administrator account ID for deletion.';
    header('Location: index.php');
    exit;
}

if (!$conn) {
    $_SESSION['error'] = 'Database is offline.';
    header('Location: index.php');
    exit;
}

// 1. Prevent deleting currently logged-in account
if (isset($_SESSION['adminAccountID']) && (int)$_SESSION['adminAccountID'] === $deleteID) {
    $_SESSION['error'] = 'You cannot delete your own active administrator account.';
    header('Location: index.php');
    exit;
}

// 2. Fetch the target admin record
$checkStmt = mysqli_prepare($conn, "SELECT adminAccountID, username, role FROM adminAccount WHERE adminAccountID = ? LIMIT 1");
mysqli_stmt_bind_param($checkStmt, 'i', $deleteID);
mysqli_stmt_execute($checkStmt);
$checkRes = mysqli_stmt_get_result($checkStmt);
$targetAdmin = mysqli_fetch_assoc($checkRes);
mysqli_stmt_close($checkStmt);

if (!$targetAdmin) {
    $_SESSION['error'] = 'Administrator account not found.';
    header('Location: index.php');
    exit;
}

// 3. Prevent deleting the last Super Administrator
if ($targetAdmin['role'] === 'Super Administrator') {
    $countSuperRes = mysqli_query($conn, "SELECT COUNT(*) as c FROM adminAccount WHERE role = 'Super Administrator'");
    $countSuper = ($countSuperRes && ($row = mysqli_fetch_assoc($countSuperRes))) ? (int)$row['c'] : 0;
    if ($countSuper <= 1) {
        $_SESSION['error'] = 'Cannot delete the only Super Administrator account. At least one Super Administrator must remain.';
        header('Location: index.php');
        exit;
    }
}

// 4. Foreign Key Safeguards: Check customerService and expense
$hasServices = false;
$hasExpenses = false;

$srvCheck = mysqli_query($conn, "SELECT COUNT(*) as c FROM customerService WHERE adminAccountID = $deleteID");
if ($srvCheck && ($r = mysqli_fetch_assoc($srvCheck)) && $r['c'] > 0) {
    $hasServices = true;
}

$expCheck = mysqli_query($conn, "SELECT COUNT(*) as c FROM expense WHERE recordedBy = $deleteID");
if ($expCheck && ($r = mysqli_fetch_assoc($expCheck)) && $r['c'] > 0) {
    $hasExpenses = true;
}

if ($hasServices || $hasExpenses) {
    $reasons = [];
    if ($hasServices) $reasons[] = 'assigned customer service requests';
    if ($hasExpenses) $reasons[] = 'recorded expense entries';
    $_SESSION['error'] = 'Cannot delete administrator "' . htmlspecialchars($targetAdmin['username']) . '" because of existing database references (' . implode(' and ', $reasons) . '). These records are required for business audit history.';
    header('Location: index.php');
    exit;
}

// 5. Execute Delete
$delStmt = mysqli_prepare($conn, "DELETE FROM adminAccount WHERE adminAccountID = ?");
mysqli_stmt_bind_param($delStmt, 'i', $deleteID);
if (mysqli_stmt_execute($delStmt)) {
    $_SESSION['success'] = 'Administrator "' . htmlspecialchars($targetAdmin['username']) . '" deleted successfully.';
} else {
    $_SESSION['error'] = 'Failed to delete administrator: ' . mysqli_error($conn);
}
mysqli_stmt_close($delStmt);

header('Location: index.php');
exit;
