<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success pw-glass-alert alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
        <i class="fa-solid fa-circle-check text-success fs-5"></i>
        <div><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-danger pw-glass-alert alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
        <i class="fa-solid fa-circle-exclamation text-warning fs-5"></i>
        <div><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
