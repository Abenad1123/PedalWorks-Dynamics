<?php
session_start();
include('../includes/config.php');

// Enforce customer authentication
if (!isset($_SESSION['customerAccountID']) && !isset($_SESSION['customerID']) && !isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please log in to access your account.';
    header('Location: ../login.php');
    exit;
}

// Resolve customer ID
$customerID = (int)($_SESSION['customerID'] ?? $_SESSION['user_id'] ?? 0);
$customerAccountID = (int)($_SESSION['customerAccountID'] ?? 0);

if ($customerID <= 0 && $customerAccountID > 0 && $conn) {
    $lookup = mysqli_prepare($conn, "SELECT customerID FROM customerAccount WHERE customerAccountID = ? LIMIT 1");
    mysqli_stmt_bind_param($lookup, 'i', $customerAccountID);
    mysqli_stmt_execute($lookup);
    $res = mysqli_stmt_get_result($lookup);
    if ($row = mysqli_fetch_assoc($res)) {
        $customerID = (int)$row['customerID'];
        $_SESSION['customerID'] = $customerID;
    }
    mysqli_stmt_close($lookup);
}

// Fetch current customer details first to retain existing photo on error or non-update
$customer = null;
if ($conn && $customerID > 0) {
    $stmt = mysqli_prepare($conn, "SELECT c.customerID, c.firstName, c.lastName, c.birthDate, c.email, c.address, c.photo, 
                                          ca.customerAccountID, ca.username, ca.status 
                                   FROM customer c 
                                   LEFT JOIN customerAccount ca ON c.customerID = ca.customerID 
                                   WHERE c.customerID = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $customerID);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $customer = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
}

if (!$customer) {
    $_SESSION['error'] = 'Customer record not found.';
    header('Location: ../login.php');
    exit;
}

$errors = [];

// Handle Profile Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $firstName       = trim($_POST['firstName'] ?? '');
    $lastName        = trim($_POST['lastName'] ?? '');
    $birthDate       = trim($_POST['birthDate'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Validation (MP5: PHP server-side validation)
    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if ($address === '') {
        $errors[] = 'Address is required.';
    }
    if ($password !== '') {
        if (strlen($password) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }
    }

    if (!$conn) {
        $errors[] = 'Database is offline. Please check your MySQL connection.';
    }

    // Photo Upload Validation (MP2: User can update profile with upload photo)
    $uploadedPhotoFilename = null;
    $shouldUpdatePhoto = false;

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileError = $_FILES['photo']['error'];
        if ($fileError === UPLOAD_ERR_OK) {
            $fileTmpName  = $_FILES['photo']['tmp_name'];
            $fileSize     = $_FILES['photo']['size'];
            $fileOriginal = $_FILES['photo']['name'];
            $fileExt      = strtolower(pathinfo($fileOriginal, PATHINFO_EXTENSION));

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $maxFileSize       = 5 * 1024 * 1024; // 5 MB

            if (!in_array($fileExt, $allowedExtensions, true)) {
                $errors[] = 'Photo must be an image file (.jpg, .jpeg, .png, .webp, .gif).';
            } elseif ($fileSize > $maxFileSize) {
                $errors[] = 'Photo size cannot exceed 5 MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $fileTmpName);
                finfo_close($finfo);

                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array($mimeType, $allowedMimes, true)) {
                    $errors[] = 'Invalid image format uploaded.';
                } else {
                    $uploadDir = __DIR__ . '/../assets/images/uploads/users/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $uniqueFilename = 'user_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $fileExt;
                    $destination    = $uploadDir . $uniqueFilename;

                    if (move_uploaded_file($fileTmpName, $destination)) {
                        $uploadedPhotoFilename = $uniqueFilename;
                        $shouldUpdatePhoto = true;
                    } else {
                        $errors[] = 'Failed to upload user photo.';
                    }
                }
            }
        } else {
            $errors[] = 'Error occurred during photo upload (code: ' . $fileError . ').';
        }
    }

    // Email uniqueness check
    if (empty($errors)) {
        $emailCheck = mysqli_prepare($conn, "SELECT customerID FROM customer WHERE email = ? AND customerID != ? LIMIT 1");
        if ($emailCheck) {
            mysqli_stmt_bind_param($emailCheck, 'si', $email, $customerID);
            mysqli_stmt_execute($emailCheck);
            mysqli_stmt_store_result($emailCheck);
            if (mysqli_stmt_num_rows($emailCheck) > 0) {
                $errors[] = 'This email address is already associated with another account.';
            }
            mysqli_stmt_close($emailCheck);
        }
    }

    // Execute atomic update
    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $dbLastName  = ($lastName !== '') ? $lastName : null;
            $dbBirthDate = ($birthDate !== '') ? $birthDate : null;
            $oldPhoto    = $customer['photo'] ?? null;

            if ($shouldUpdatePhoto) {
                // Update profile with new photo
                $updateCustomer = mysqli_prepare($conn, "UPDATE customer SET firstName = ?, lastName = ?, birthDate = ?, email = ?, address = ?, photo = ? WHERE customerID = ?");
                mysqli_stmt_bind_param($updateCustomer, 'ssssssi', $firstName, $dbLastName, $dbBirthDate, $email, $address, $uploadedPhotoFilename, $customerID);
            } else {
                // Update profile keeping existing photo
                $updateCustomer = mysqli_prepare($conn, "UPDATE customer SET firstName = ?, lastName = ?, birthDate = ?, email = ?, address = ? WHERE customerID = ?");
                mysqli_stmt_bind_param($updateCustomer, 'sssssi', $firstName, $dbLastName, $dbBirthDate, $email, $address, $customerID);
            }

            mysqli_stmt_execute($updateCustomer);
            mysqli_stmt_close($updateCustomer);

            // Optionally update password
            if ($password !== '') {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $updateAccount = mysqli_prepare($conn, "UPDATE customerAccount SET password = ? WHERE customerID = ?");
                mysqli_stmt_bind_param($updateAccount, 'si', $hashed, $customerID);
                mysqli_stmt_execute($updateAccount);
                mysqli_stmt_close($updateAccount);
            }

            mysqli_commit($conn);

            // If a new photo was uploaded and an old photo exists, delete the old file safely
            if ($shouldUpdatePhoto && $oldPhoto) {
                $oldPath = __DIR__ . '/../assets/images/uploads/users/' . $oldPhoto;
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            // Update session cache
            $_SESSION['firstName'] = $firstName;
            $_SESSION['email']     = $email;
            if ($shouldUpdatePhoto) {
                $_SESSION['photo'] = $uploadedPhotoFilename;
            }
            $_SESSION['success'] = 'Profile changes have been saved.';

            header('Location: profile.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            if ($uploadedPhotoFilename) {
                $cleanPath = __DIR__ . '/../assets/images/uploads/users/' . $uploadedPhotoFilename;
                if (file_exists($cleanPath)) {
                    unlink($cleanPath);
                }
            }
            $errors[] = 'Failed to update profile: ' . $e->getMessage();
        }
    } else {
        if ($uploadedPhotoFilename) {
            $cleanPath = __DIR__ . '/../assets/images/uploads/users/' . $uploadedPhotoFilename;
            if (file_exists($cleanPath)) {
                unlink($cleanPath);
            }
        }
    }
}

// Compute clean initials for fallback avatar
$initials = strtoupper(substr($customer['firstName'], 0, 1) . substr($customer['lastName'] ?? '', 0, 1));
if (trim($initials) === '') {
    $initials = strtoupper(substr($customer['username'] ?? 'U', 0, 1));
}

include('../includes/header.php');
?>

<div class="container py-5" style="max-width: 1040px;">

    <!-- Minimal Header Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 mb-4 border-bottom border-secondary border-opacity-25">
        <div>
            <h2 class="text-white fw-bold mb-1" style="font-size: 1.5rem; letter-spacing: -0.02em;">Account Settings</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0" style="font-size: 0.8rem;">
                    <li class="breadcrumb-item"><a href="../index.php" class="text-secondary text-decoration-none">Home</a></li>
                    <li class="breadcrumb-item active text-white-50" aria-current="page">Profile</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="orders.php" class="pw-btn-glass-sm py-1 px-3">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> My Orders
            </a>
            <a href="../cart.php" class="pw-btn-glass-sm py-1 px-3">
                <i class="fa-solid fa-cart-shopping me-1"></i> Cart
            </a>
            <a href="../index.php" class="btn btn-sm btn-outline-secondary text-white-50 px-3 py-1" style="border-radius: 6px; font-size: 0.825rem;">
                <i class="fa-solid fa-arrow-left me-1"></i> Store
            </a>
        </div>
    </div>

    <?php include('../includes/alert.php'); ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger pw-glass-alert alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="fa-solid fa-circle-exclamation text-warning fs-5"></i>
                <strong class="text-white small">Action Needed</strong>
            </div>
            <ul class="mb-0 ps-3 small text-white-50">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close btn-close-white ms-auto position-absolute top-0 end-0 mt-3 me-3" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Profile Identity Card -->
        <div class="col-lg-4 col-md-5">
            <div class="pw-auth-card p-4 text-center">
                
                <!-- Avatar: Uploaded Photo or Clean Initials Fallback -->
                <div class="mx-auto mb-3 position-relative" style="width: 88px; height: 88px;">
                    <?php if (!empty($customer['photo']) && file_exists(__DIR__ . '/../assets/images/uploads/users/' . $customer['photo'])): ?>
                        <img src="/project/PedalWorks-Dynamics/assets/images/uploads/users/<?php echo htmlspecialchars($customer['photo']); ?>" 
                             alt="<?php echo htmlspecialchars($customer['firstName']); ?>" 
                             class="rounded-circle shadow-sm" 
                             style="width: 88px; height: 88px; object-fit: cover; border: 2px solid rgba(255,255,255,0.15);">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center text-white fw-bold rounded-circle" 
                             style="width: 88px; height: 88px; background: #1e293b; border: 2px solid rgba(255,255,255,0.12); font-size: 1.5rem; letter-spacing: 0.05em;">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h4 class="text-white fw-semibold mb-1" style="font-size: 1.15rem;">
                    <?php echo htmlspecialchars($customer['firstName'] . ' ' . ($customer['lastName'] ?? '')); ?>
                </h4>
                <p class="text-secondary small mb-3" style="font-family: monospace;">@<?php echo htmlspecialchars($customer['username'] ?? ''); ?></p>
                
                <div class="mb-4">
                    <span class="badge <?php echo ($customer['status'] === 'Active') ? 'bg-success bg-opacity-25 text-success' : 'bg-secondary bg-opacity-25 text-secondary'; ?> px-2 py-1" style="font-size: 0.725rem; font-weight: 500; border-radius: 4px; border: 1px solid currentColor;">
                        <?php echo htmlspecialchars($customer['status'] ?? 'Active'); ?>
                    </span>
                </div>

                <div class="pt-3 border-top border-secondary border-opacity-25 text-start" style="font-size: 0.85rem;">
                    <div class="d-flex align-items-start gap-2 text-secondary mb-2">
                        <i class="fa-regular fa-envelope mt-1 text-secondary" style="width: 14px;"></i>
                        <span class="text-white-50 text-break"><?php echo htmlspecialchars($customer['email']); ?></span>
                    </div>
                    <?php if (!empty($customer['birthDate'])): ?>
                        <div class="d-flex align-items-center gap-2 text-secondary mb-2">
                            <i class="fa-regular fa-calendar mt-0 text-secondary" style="width: 14px;"></i>
                            <span class="text-white-50"><?php echo date('M d, Y', strtotime($customer['birthDate'])); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex align-items-start gap-2 text-secondary">
                        <i class="fa-regular fa-compass mt-1 text-secondary" style="width: 14px;"></i>
                        <span class="text-white-50"><?php echo htmlspecialchars($customer['address']); ?></span>
                    </div>
                </div>

            </div>
        </div>

        <!-- Editable Details Card -->
        <div class="col-lg-8 col-md-7">
            <div class="pw-auth-card p-4 p-md-4">
                
                <h5 class="text-white fw-semibold mb-3 pb-2 border-bottom border-secondary border-opacity-25" style="font-size: 1rem;">
                    Personal Information
                </h5>

                <!-- MP5: Form with novalidate to disable browser HTML5 popups and enforce pure PHP server validation -->
                <form action="profile.php" method="POST" enctype="multipart/form-data" autocomplete="on" novalidate>
                    <!-- Names -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="firstName" class="form-label text-secondary small fw-medium mb-1">
                                First Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control pw-form-control" id="firstName" name="firstName" 
                                   value="<?php echo htmlspecialchars($_POST['firstName'] ?? $customer['firstName']); ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="lastName" class="form-label text-secondary small fw-medium mb-1">
                                Last Name <span class="text-white-50" style="font-size: 0.75rem;">(Optional)</span>
                            </label>
                            <input type="text" class="form-control pw-form-control" id="lastName" name="lastName" 
                                   value="<?php echo htmlspecialchars($_POST['lastName'] ?? $customer['lastName'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Email & Birthdate -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-7">
                            <label for="email" class="form-label text-secondary small fw-medium mb-1">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control pw-form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? $customer['email']); ?>" required>
                        </div>
                        <div class="col-sm-5">
                            <label for="birthDate" class="form-label text-secondary small fw-medium mb-1">
                                Birth Date <span class="text-white-50" style="font-size: 0.75rem;">(Optional)</span>
                            </label>
                            <input type="date" class="form-control pw-form-control" id="birthDate" name="birthDate" 
                                   value="<?php echo htmlspecialchars($_POST['birthDate'] ?? $customer['birthDate'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Photo Upload (MP2 Requirement) -->
                    <div class="mb-3">
                        <label for="photo" class="form-label text-secondary small fw-medium mb-1">
                            Update Profile Photo <span class="text-white-50" style="font-size: 0.75rem;">(Optional - JPG, PNG, WEBP, max 5MB)</span>
                        </label>
                        <input type="file" class="form-control pw-form-control" id="photo" name="photo" accept="image/*">
                        <?php if (!empty($customer['photo'])): ?>
                            <small class="text-secondary d-block mt-1" style="font-size: 0.75rem;">
                                Current photo: <code><?php echo htmlspecialchars($customer['photo']); ?></code>. Uploading a new file will replace it.
                            </small>
                        <?php endif; ?>
                    </div>

                    <!-- Address -->
                    <div class="mb-4">
                        <label for="address" class="form-label text-secondary small fw-medium mb-1">
                            Address <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control pw-form-control" id="address" name="address" rows="2" 
                                  placeholder="Street, City, Province, Postal Code" required><?php echo htmlspecialchars($_POST['address'] ?? $customer['address']); ?></textarea>
                    </div>

                    <!-- Account Security Section -->
                    <h5 class="text-white fw-semibold mb-3 pt-3 pb-2 border-top border-bottom border-secondary border-opacity-25" style="font-size: 1rem;">
                        Security &amp; Credentials
                    </h5>

                    <!-- Read-only Username -->
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-medium mb-1">Username</label>
                        <input type="text" class="form-control pw-form-control text-secondary" 
                               value="<?php echo htmlspecialchars($customer['username'] ?? ''); ?>" readonly disabled 
                               style="background: #090d12; border-color: rgba(255,255,255,0.06); cursor: not-allowed;">
                    </div>

                    <!-- Password Inputs -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label for="password" class="form-label text-secondary small fw-medium mb-1">
                                New Password <span class="text-white-50" style="font-size: 0.75rem;">(Optional)</span>
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control pw-form-control border-end-0" id="password" name="password" 
                                       placeholder="Leave blank to keep unchanged">
                                <button type="button" class="btn pw-password-toggle" id="togglePasswordBtn" title="Toggle visibility" tabindex="-1">
                                    <i class="fa-regular fa-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label for="confirmPassword" class="form-label text-secondary small fw-medium mb-1">
                                Confirm New Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control pw-form-control border-end-0" id="confirmPassword" name="confirmPassword" 
                                       placeholder="Re-type new password">
                                <button type="button" class="btn pw-password-toggle" id="toggleConfirmPasswordBtn" title="Toggle visibility" tabindex="-1">
                                    <i class="fa-regular fa-eye" id="toggleConfirmPasswordIcon"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top border-secondary border-opacity-25">
                        <button type="submit" name="submit" class="pw-btn-trail px-4 py-2" style="font-size: 0.9rem;">
                            Save Changes
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function setupPasswordToggle(btnId, inputId, iconId) {
        const btn = document.getElementById(btnId);
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);

        if (btn && input && icon) {
            btn.addEventListener('click', function() {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }
    }

    setupPasswordToggle('togglePasswordBtn', 'password', 'togglePasswordIcon');
    setupPasswordToggle('toggleConfirmPasswordBtn', 'confirmPassword', 'toggleConfirmPasswordIcon');
});
</script>

<?php include('../includes/footer.php'); ?>
