<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/config.php';

// Auth Guard: Customer Login Required (FR15/FR16 Live Tracking)
if (empty($_SESSION['customerAccountID'])) {
    $_SESSION['error'] = 'Please sign in to view your workshop service requests.';
    header('Location: ../login.php');
    exit;
}

$customerAccountID = (int)$_SESSION['customerAccountID'];
$highlightId       = isset($_GET['highlight']) ? (int)$_GET['highlight'] : 0;
$statusFilter      = isset($_GET['status']) ? trim($_GET['status']) : 'all';

$services = [];

if ($conn) {
    // Attempt querying MySQL VIEW vw_service_request_details first
    $sql = "SELECT * FROM vw_service_request_details WHERE customerAccountID = ?";
    $params = [$customerAccountID];
    $types = "i";

    if ($statusFilter === 'active') {
        $sql .= " AND serviceStatus IN ('Pending', 'Received', 'In Progress', 'Ready for Pickup')";
    } elseif (in_array($statusFilter, ['Pending', 'Received', 'In Progress', 'Ready for Pickup', 'Completed', 'Cancelled'], true)) {
        $sql .= " AND serviceStatus = ?";
        $params[] = $statusFilter;
        $types .= "s";
    }

    $sql .= " ORDER BY requestDate DESC, customerServiceID DESC";

    $stmt = @mysqli_prepare($conn, $sql);
    if (!$stmt) {
        // Fallback directly to tables if view is not yet defined
        $fallbackSql = "SELECT cs.customerServiceID, cs.requestDate, cs.scheduledDate, cs.notes, cs.status AS serviceStatus, cs.productSaleID,
                               s.serviceID, s.sku AS serviceSku, s.name AS serviceName, s.description AS serviceDescription, s.price AS servicePrice,
                               sc.name AS serviceCategoryName,
                               adm.username AS technicianUsername
                        FROM customerService cs
                        JOIN service s ON cs.serviceID = s.serviceID
                        LEFT JOIN serviceCategory sc ON s.serviceCategoryID = sc.serviceCategoryID
                        LEFT JOIN adminAccount adm ON cs.adminAccountID = adm.adminAccountID
                        WHERE cs.customerAccountID = ?";
        if ($statusFilter === 'active') {
            $fallbackSql .= " AND cs.status IN ('Pending', 'Received', 'In Progress', 'Ready for Pickup')";
        } elseif (in_array($statusFilter, ['Pending', 'Received', 'In Progress', 'Ready for Pickup', 'Completed', 'Cancelled'], true)) {
            $fallbackSql .= " AND cs.status = ?";
        }
        $fallbackSql .= " ORDER BY cs.requestDate DESC, cs.customerServiceID DESC";
        $stmt = mysqli_prepare($conn, $fallbackSql);
    }

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $services[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

// Helper to determine status style and step order
function getServiceStatusMeta($status) {
    switch ($status) {
        case 'Pending':
            return ['badge' => 'bg-warning text-dark', 'step' => 1, 'icon' => 'fa-clock', 'label' => 'Request Pending'];
        case 'Received':
            return ['badge' => 'bg-info text-dark', 'step' => 2, 'icon' => 'fa-box-archive', 'label' => 'Received at Bay'];
        case 'In Progress':
            return ['badge' => 'bg-primary text-white', 'step' => 3, 'icon' => 'fa-screwdriver-wrench', 'label' => 'Service In Progress'];
        case 'Ready for Pickup':
            return ['badge' => 'bg-success text-white', 'step' => 4, 'icon' => 'fa-bicycle', 'label' => 'Ready for Pickup'];
        case 'Completed':
            return ['badge' => 'bg-success text-white', 'step' => 5, 'icon' => 'fa-circle-check', 'label' => 'Service Completed'];
        case 'Cancelled':
            return ['badge' => 'bg-danger text-white', 'step' => 0, 'icon' => 'fa-circle-xmark', 'label' => 'Cancelled'];
        default:
            return ['badge' => 'bg-secondary text-white', 'step' => 1, 'icon' => 'fa-question', 'label' => $status];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small text-white-50">
                    <li class="breadcrumb-item"><a href="../index.php" class="text-white-50 text-decoration-none">Home</a></li>
                    <li class="breadcrumb-item"><a href="profile.php" class="text-white-50 text-decoration-none">Account</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Workshop Requests</li>
                </ol>
            </nav>
            <h1 class="h2 fw-bold text-white mb-0">Workshop Service Requests</h1>
            <p class="text-white-50 small mb-0">Live technician diagnostic tracking and maintenance scheduling</p>
        </div>
        <div class="d-flex gap-2">
            <a href="../services.php" class="pw-btn-trail py-2 px-3">
                <i class="fa-solid fa-calendar-plus me-1"></i> Book New Service
            </a>
            <a href="orders.php" class="pw-btn-glass py-2 px-3">
                <i class="fa-solid fa-box me-1"></i> Product Orders
            </a>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/alert.php'; ?>

    <!-- Filter Pills -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="services.php?status=all" class="pw-filter-pill <?php echo ($statusFilter === 'all') ? 'active' : ''; ?>">
            All Requests (<?php echo count($services); ?>)
        </a>
        <a href="services.php?status=active" class="pw-filter-pill <?php echo ($statusFilter === 'active') ? 'active' : ''; ?>">
            Active In-Shop
        </a>
        <a href="services.php?status=Completed" class="pw-filter-pill <?php echo ($statusFilter === 'Completed') ? 'active' : ''; ?>">
            Completed
        </a>
        <a href="services.php?status=Cancelled" class="pw-filter-pill <?php echo ($statusFilter === 'Cancelled') ? 'active' : ''; ?>">
            Cancelled
        </a>
    </div>

    <?php if (empty($services)): ?>
        <div class="text-center py-5 pw-order-card p-5">
            <i class="fa-solid fa-screwdriver-wrench fa-3x text-white-50 mb-3 opacity-50"></i>
            <h4 class="text-white fw-bold mb-2">No Workshop Requests Found</h4>
            <p class="text-white-50 mb-4" style="max-width: 480px; margin: 0 auto;">
                You haven't scheduled any repair or maintenance appointments yet. Need a brake bleed, drivetrain overhaul, or custom assembly?
            </p>
            <a href="../services.php" class="pw-btn-trail py-2 px-4">
                <i class="fa-solid fa-compass me-1"></i> Explore Workshop Services
            </a>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-4">
            <?php foreach ($services as $svc): 
                $statusMeta = getServiceStatusMeta($svc['serviceStatus']);
                $isHighlighted = ($highlightId === (int)$svc['customerServiceID']);
                $isCancelled = ($svc['serviceStatus'] === 'Cancelled');
            ?>
            <div class="pw-order-card p-4 <?php echo $isHighlighted ? 'border border-warning' : ''; ?>" id="req-<?php echo $svc['customerServiceID']; ?>">
                <!-- Top Row: Ref, Service Name, Status Badge -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom border-white border-opacity-10">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="pw-order-ref">#PW-SRV-<?php echo str_pad($svc['customerServiceID'], 5, '0', STR_PAD_LEFT); ?></span>
                            <span class="badge rounded-pill <?php echo $statusMeta['badge']; ?> small px-3 py-1">
                                <i class="fa-solid <?php echo $statusMeta['icon']; ?> me-1"></i>
                                <?php echo htmlspecialchars($statusMeta['label']); ?>
                            </span>
                            <?php if (!empty($svc['productSaleID'])): ?>
                                <a href="orders.php?order_id=<?php echo (int)$svc['productSaleID']; ?>" class="badge rounded-pill bg-white bg-opacity-10 text-white text-decoration-none small px-2 py-1" title="View associated product order">
                                    <i class="fa-solid fa-link me-1 text-warning"></i> Linked to Order #PW-ORD-<?php echo (int)$svc['productSaleID']; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <h4 class="text-white fw-bold mb-0"><?php echo htmlspecialchars($svc['serviceName']); ?></h4>
                        <span class="small text-white-50">
                            Category: <?php echo htmlspecialchars($svc['serviceCategoryName'] ?? 'Workshop'); ?> &bull; SKU: <?php echo htmlspecialchars($svc['serviceSku']); ?>
                        </span>
                    </div>

                    <div class="text-end">
                        <div class="small text-white-50">Base Labor Fee</div>
                        <div class="h4 fw-bold mb-0" style="color: var(--pw-trail-amber); font-family: var(--pw-font-display);">
                            &#8369;<?php echo number_format($svc['servicePrice'], 2); ?>
                        </div>
                    </div>
                </div>

                <!-- Apple HIG Step Progress Tracker (FR16) -->
                <?php if (!$isCancelled): ?>
                <div class="mb-4 p-3 rounded" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);">
                    <div class="small text-white-50 fw-semibold mb-3">Service Lifecycle Stage:</div>
                    <div class="row g-2 text-center">
                        <?php 
                        $steps = [
                            1 => ['label' => '1. Request Pending', 'icon' => 'fa-clock'],
                            2 => ['label' => '2. Received at Bay', 'icon' => 'fa-box-archive'],
                            3 => ['label' => '3. In Progress', 'icon' => 'fa-screwdriver-wrench'],
                            4 => ['label' => '4. Ready for Pickup', 'icon' => 'fa-bicycle'],
                            5 => ['label' => '5. Completed', 'icon' => 'fa-circle-check']
                        ];
                        foreach ($steps as $stepNum => $stepData):
                            $isPassed = ($statusMeta['step'] >= $stepNum);
                            $isCurrent = ($statusMeta['step'] === $stepNum);
                        ?>
                        <div class="col">
                            <div class="p-2 rounded <?php echo $isCurrent ? 'bg-warning text-dark fw-bold' : ($isPassed ? 'text-white' : 'text-white-50'); ?>" 
                                 style="<?php echo ($isPassed && !$isCurrent) ? 'background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4);' : (!$isCurrent ? 'background: rgba(255,255,255,0.03);' : ''); ?>">
                                <i class="fa-solid <?php echo $stepData['icon']; ?> d-block mb-1 <?php echo $isCurrent ? 'text-dark' : ($isPassed ? 'text-success' : 'opacity-50'); ?>"></i>
                                <span style="font-size: 0.75rem;"><?php echo $stepData['label']; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-danger pw-glass-alert d-flex align-items-center gap-2 mb-4 py-2">
                    <i class="fa-solid fa-ban text-danger"></i>
                    <span>This service request has been cancelled.</span>
                </div>
                <?php endif; ?>

                <!-- Details Metadata Grid -->
                <div class="row g-3 p-3 rounded mb-3 pw-order-meta-bar">
                    <div class="col-sm-6 col-md-3">
                        <span class="small text-white-50 d-block">Requested On</span>
                        <span class="text-white fw-semibold small">
                            <i class="fa-regular fa-calendar me-1 text-white-50"></i>
                            <?php echo date('M d, Y h:i A', strtotime($svc['requestDate'])); ?>
                        </span>
                    </div>

                    <div class="col-sm-6 col-md-3">
                        <span class="small text-white-50 d-block">Scheduled Appointment</span>
                        <span class="text-white fw-semibold small">
                            <?php if (!empty($svc['scheduledDate'])): ?>
                                <i class="fa-regular fa-clock me-1 text-warning"></i>
                                <?php echo date('M d, Y h:i A', strtotime($svc['scheduledDate'])); ?>
                            <?php else: ?>
                                <span class="text-white-50">Flexible / Walk-in</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="col-sm-6 col-md-3">
                        <span class="small text-white-50 d-block">Assigned Technician</span>
                        <span class="text-white fw-semibold small">
                            <i class="fa-solid fa-user-gear me-1 text-success"></i>
                            <?php echo !empty($svc['technicianUsername']) ? htmlspecialchars($svc['technicianUsername']) : '<span class="text-white-50">Workshop Team</span>'; ?>
                        </span>
                    </div>

                    <div class="col-sm-6 col-md-3">
                        <span class="small text-white-50 d-block">Basecamp Service Bay</span>
                        <span class="text-white fw-semibold small">
                            <i class="fa-solid fa-location-dot me-1 text-danger"></i> Taguig Workshop
                        </span>
                    </div>
                </div>

                <!-- Notes / Issue Description -->
                <?php if (!empty($svc['notes'])): ?>
                <div class="p-3 rounded mb-3" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
                    <span class="small text-white-50 d-block fw-semibold mb-1">
                        <i class="fa-solid fa-comment-dots text-warning me-1"></i> Bike Symptoms &amp; Service Notes:
                    </span>
                    <p class="small text-white mb-0" style="line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($svc['notes'])); ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2">
                    <span class="small text-white-50">
                        <i class="fa-solid fa-shield-halved text-success me-1"></i> All repairs covered by our 30-day trail safety warranty
                    </span>

                    <div class="d-flex gap-2">
                        <?php if ($svc['serviceStatus'] === 'Pending'): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger py-2 px-3" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#cancelModal_<?php echo $svc['customerServiceID']; ?>">
                                <i class="fa-solid fa-xmark me-1"></i> Cancel Request
                            </button>
                        <?php endif; ?>
                        <a href="tel:0288232457" class="pw-btn-glass-sm py-2 px-3">
                            <i class="fa-solid fa-phone me-1"></i> Call Workshop
                        </a>
                    </div>
                </div>
            </div>

            <!-- Cancel Confirmation Modal -->
            <?php if ($svc['serviceStatus'] === 'Pending'): ?>
            <div class="modal fade" id="cancelModal_<?php echo $svc['customerServiceID']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content pw-modal-content">
                        <form action="cancel_service_action.php" method="POST">
                            <input type="hidden" name="customerServiceID" value="<?php echo (int)$svc['customerServiceID']; ?>">
                            <div class="modal-header pw-modal-header">
                                <h5 class="modal-title fw-bold text-white">Cancel Service Request</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4 text-white">
                                <p class="mb-3">Are you sure you want to cancel service request <strong>#PW-SRV-<?php echo (int)$svc['customerServiceID']; ?></strong> (<?php echo htmlspecialchars($svc['serviceName']); ?>)?</p>
                                <p class="small text-white-50 mb-0">This action cannot be undone. You may schedule a new appointment at any time.</p>
                            </div>
                            <div class="modal-footer pw-modal-footer">
                                <button type="button" class="pw-btn-glass-sm py-2 px-3" data-bs-dismiss="modal">Keep Request</button>
                                <button type="submit" class="btn btn-danger py-2 px-3">
                                    <i class="fa-solid fa-xmark me-1"></i> Confirm Cancellation
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
