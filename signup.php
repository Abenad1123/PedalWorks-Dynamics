<?php
session_start();
include('./includes/config.php');

$errors = [];
$firstName = '';
$lastName  = '';
$birthDate = '';
$email     = '';
$address   = '';
$username  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup_customer'])) {
    $firstName       = trim($_POST['firstName'] ?? '');
    $lastName        = trim($_POST['lastName'] ?? '');
    $birthDate       = trim($_POST['birthDate'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $username        = trim($_POST['username'] ?? '');
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
    if ($username === '') {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$conn) {
        $errors[] = 'Database is offline. Please check that MySQL is running in XAMPP.';
    }

    // Photo Upload Validation (MP2: User Registration upload a photo)
    $uploadedPhotoFilename = null;
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
                // Verify image MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $fileTmpName);
                finfo_close($finfo);

                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array($mimeType, $allowedMimes, true)) {
                    $errors[] = 'Invalid image format uploaded.';
                } else {
                    $uploadDir = __DIR__ . '/assets/images/uploads/users/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $uniqueFilename = 'user_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $fileExt;
                    $destination    = $uploadDir . $uniqueFilename;

                    if (move_uploaded_file($fileTmpName, $destination)) {
                        $uploadedPhotoFilename = $uniqueFilename;
                    } else {
                        $errors[] = 'Failed to upload user photo.';
                    }
                }
            }
        } else {
            $errors[] = 'Error occurred during photo upload (code: ' . $fileError . ').';
        }
    }

    if (empty($errors)) {
        // Check email uniqueness
        $emailCheck = mysqli_prepare($conn, "SELECT customerID FROM customer WHERE email = ? LIMIT 1");
        if ($emailCheck) {
            mysqli_stmt_bind_param($emailCheck, 's', $email);
            mysqli_stmt_execute($emailCheck);
            mysqli_stmt_store_result($emailCheck);
            if (mysqli_stmt_num_rows($emailCheck) > 0) {
                $errors[] = 'This email address is already registered.';
            }
            mysqli_stmt_close($emailCheck);
        }

        // Check username uniqueness
        $userCheck = mysqli_prepare($conn, "SELECT customerAccountID FROM customerAccount WHERE username = ? LIMIT 1");
        if ($userCheck) {
            mysqli_stmt_bind_param($userCheck, 's', $username);
            mysqli_stmt_execute($userCheck);
            mysqli_stmt_store_result($userCheck);
            if (mysqli_stmt_num_rows($userCheck) > 0) {
                $errors[] = 'This username is already taken. Please choose another.';
            }
            mysqli_stmt_close($userCheck);
        }
    }

    // Insert customer & account inside a transaction
    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $dbLastName  = ($lastName !== '') ? $lastName : null;
            $dbBirthDate = ($birthDate !== '') ? $birthDate : null;

            $insertCustomer = mysqli_prepare($conn, "INSERT INTO customer (firstName, lastName, birthDate, email, address, photo) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($insertCustomer, 'ssssss', $firstName, $dbLastName, $dbBirthDate, $email, $address, $uploadedPhotoFilename);
            mysqli_stmt_execute($insertCustomer);
            $newCustomerID = mysqli_insert_id($conn);
            mysqli_stmt_close($insertCustomer);

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $status = 'Active';

            $insertAccount = mysqli_prepare($conn, "INSERT INTO customerAccount (customerID, username, password, status) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($insertAccount, 'isss', $newCustomerID, $username, $hashedPassword, $status);
            mysqli_stmt_execute($insertAccount);
            mysqli_stmt_close($insertAccount);

            mysqli_commit($conn);

            $_SESSION['success'] = "Account successfully created for {$username}! You can now log in using your email or username.";
            header('Location: login.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            if ($uploadedPhotoFilename && file_exists(__DIR__ . '/assets/images/uploads/users/' . $uploadedPhotoFilename)) {
                unlink(__DIR__ . '/assets/images/uploads/users/' . $uploadedPhotoFilename);
            }
            $errors[] = 'Failed to create account: ' . $e->getMessage();
        }
    } else {
        // If validation errors occurred, remove uploaded photo to prevent orphaned files
        if ($uploadedPhotoFilename && file_exists(__DIR__ . '/assets/images/uploads/users/' . $uploadedPhotoFilename)) {
            unlink(__DIR__ . '/assets/images/uploads/users/' . $uploadedPhotoFilename);
        }
    }
}

include('./includes/header.php');
?>

<div class="container">
    <div class="pw-auth-wrapper">
        <div class="pw-auth-card" style="max-width: 640px;">
            <div class="pw-auth-header">
                <div class="pw-auth-icon">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <h2 class="pw-auth-title">Join PedalWorks</h2>
                <p class="pw-auth-subtitle">Create your customer account for streamlined service booking and gear orders</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger pw-glass-alert alert-dismissible fade show mb-4" role="alert">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fa-solid fa-circle-exclamation text-warning fs-5"></i>
                        <strong class="text-white small">Registration Notice</strong>
                    </div>
                    <ul class="mb-0 ps-3 small text-white-50">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close btn-close-white ms-auto position-absolute top-0 end-0 mt-3 me-3" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- MP5: Form with novalidate to disable browser HTML5 popups and enforce pure PHP server validation -->
            <form action="signup.php" method="POST" enctype="multipart/form-data" autocomplete="on" novalidate>
                <!-- Personal Details Section -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="firstName" class="form-label text-white-50 small fw-semibold">
                            First Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control pw-form-control" id="firstName" name="firstName" 
                               value="<?php echo htmlspecialchars($firstName); ?>" 
                               placeholder="First name" required autofocus>
                    </div>
                    <div class="col-md-6">
                        <label for="lastName" class="form-label text-white-50 small fw-semibold">
                            Last Name <span class="text-white-50" style="font-size: 0.75rem;">(Optional)</span>
                        </label>
                        <input type="text" class="form-control pw-form-control" id="lastName" name="lastName" 
                               value="<?php echo htmlspecialchars($lastName); ?>" 
                               placeholder="Last name">
                    </div>
                </div>

                <!-- Contact & DOB Section -->
                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label for="email" class="form-label text-white-50 small fw-semibold">
                            Email Address <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control pw-form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               placeholder="name@example.com" required>
                    </div>
                    <div class="col-md-5">
                        <label for="birthDate" class="form-label text-white-50 small fw-semibold">
                            Birth Date <span class="text-white-50" style="font-size: 0.75rem;">(Optional)</span>
                        </label>
                        <input type="date" class="form-control pw-form-control" id="birthDate" name="birthDate" 
                               value="<?php echo htmlspecialchars($birthDate); ?>">
                    </div>
                </div>

                <!-- Photo Upload Section (MP2 Requirement) -->
                <div class="mb-3">
                    <label for="photo" class="form-label text-white-50 small fw-semibold">
                        Profile Photo <span class="text-white-50" style="font-size: 0.75rem;">(Optional - JPG, PNG, WEBP, max 5MB)</span>
                    </label>
                    <input type="file" class="form-control pw-form-control" id="photo" name="photo" accept="image/*">
                </div>

                <!-- Address Section -->
                <div class="mb-3">
                    <label for="address" class="form-label text-white-50 small fw-semibold">
                        Address <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control pw-form-control" id="address" name="address" rows="2" 
                              placeholder="House/Street, Barangay, City, Postal Code" required><?php echo htmlspecialchars($address); ?></textarea>
                </div>

                <!-- Account Credentials Section -->
                <div class="mb-3">
                    <label for="username" class="form-label text-white-50 small fw-semibold">
                        Account Username <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control pw-form-control" id="username" name="username" 
                           value="<?php echo htmlspecialchars($username); ?>" 
                           placeholder="Choose a username (min 3 chars)" minlength="3" required>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="password" class="form-label text-white-50 small fw-semibold">
                            Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control pw-form-control border-end-0" id="password" name="password" 
                                   placeholder="Min 6 characters" minlength="6" required>
                            <button type="button" class="btn pw-password-toggle" id="togglePasswordBtn" title="Toggle password visibility" tabindex="-1">
                                <i class="fa-regular fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="confirmPassword" class="form-label text-white-50 small fw-semibold">
                            Confirm Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control pw-form-control border-end-0" id="confirmPassword" name="confirmPassword" 
                                   placeholder="Re-enter password" minlength="6" required>
                            <button type="button" class="btn pw-password-toggle" id="toggleConfirmPasswordBtn" title="Toggle password visibility" tabindex="-1">
                                <i class="fa-regular fa-eye" id="toggleConfirmPasswordIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-grid gap-2 mb-3">
                    <button type="submit" name="signup_customer" class="pw-btn-trail py-3 fw-semibold">
                        Create Account
                    </button>
                </div>
            </form>

            <div class="text-center pt-3 border-top border-secondary border-opacity-25">
                <p class="text-white-50 small mb-2">
                    Already registered? 
                    <a href="login.php" class="text-white fw-bold text-decoration-none">
                        Sign In here <i class="fa-solid fa-arrow-right fa-xs ms-1"></i>
                    </a>
                </p>
                <p class="text-white-50 small mb-0">
                    <a href="index.php" class="text-muted text-decoration-none small">
                        <i class="fa-solid fa-house me-1"></i> Return to Homepage
                    </a>
                </p>
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

<?php include('./includes/footer.php'); ?>
