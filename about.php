<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('./includes/config.php');
include('./includes/header.php');
?>

<div class="container py-5">

    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small text-white-50">
            <li class="breadcrumb-item"><a href="index.php" class="text-white-50 text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active text-white" aria-current="page">About Us</li>
        </ol>
    </nav>

    <div class="row align-items-center mb-5 g-4">
        <div class="col-lg-7">
            <div class="pw-section-eyebrow mb-2">
                <i class="fa-solid fa-mountain-sun text-warning"></i> Born for the Mountain &amp; Trail
            </div>
            <h1 class="pw-hero-title mb-3">
                Crafting Reliable Rides for <span class="pw-hero-title-accent">Every Elevation</span>
            </h1>
            <p class="pw-hero-desc mb-4" style="line-height: 1.8; font-size: 1.05rem;">
                At <strong>PedalWorks Dynamics</strong>, cycling isn't just transportation — it's an exploration. From steep gravel switchbacks to bustling urban commutes, we provide riders with genuine, trail-proven bicycles, high-precision replacement components, and certified workshop care.
            </p>
            <div class="d-flex flex-wrap gap-3">
                <a href="products.php" class="pw-btn-trail py-3 px-4">
                    <i class="fa-solid fa-bicycle me-2"></i> Explore Our Fleet
                </a>
                <a href="services.php" class="pw-btn-glass py-3 px-4">
                    <i class="fa-solid fa-wrench me-2"></i> Workshop Services
                </a>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="pw-cart-card p-4 text-center">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" 
                     style="width: 72px; height: 72px; background: rgba(209, 102, 41, 0.15); border: 1px solid rgba(209, 102, 41, 0.35); color: var(--pw-trail-amber); font-size: 1.8rem;">
                    <i class="fa-solid fa-award"></i>
                </div>
                <h4 class="fw-bold text-white mb-2">Our Mission</h4>
                <p class="text-white-50 small mb-0" style="line-height: 1.7;">
                    To deliver uncompromised safety, performance, and transparency in bicycle retail and mechanical maintenance, empowering every rider with confidence on any terrain.
                </p>
            </div>
        </div>
    </div>

    <div class="mb-5">
        <div class="text-center mb-4">
            <h2 class="pw-section-title">The PedalWorks Standard</h2>
            <p class="pw-section-sub mx-auto" style="max-width: 580px;">
                Engineered with lessons from real-world riders, premier retail brands, and certified workshop mechanics.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="pw-feature-card h-100 p-4">
                    <div class="pw-feature-icon mb-3">
                        <i class="fa-solid fa-shield-halved text-success"></i>
                    </div>
                    <h5 class="pw-feature-title">Authentic Gear</h5>
                    <p class="pw-feature-text mb-0">Every bicycle, component, and accessory in our catalog is 100% genuine and verified against manufacturer standards.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="pw-feature-card h-100 p-4">
                    <div class="pw-feature-icon mb-3">
                        <i class="fa-solid fa-screwdriver-wrench text-warning"></i>
                    </div>
                    <h5 class="pw-feature-title">Certified Bay</h5>
                    <p class="pw-feature-text mb-0">Our Taguig service bay features professional hydraulic bleed kits, wheel truing stands, and ultrasonic drivetrains wash.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="pw-feature-card h-100 p-4">
                    <div class="pw-feature-icon mb-3">
                        <i class="fa-solid fa-clock-rotate-left text-info"></i>
                    </div>
                    <h5 class="pw-feature-title">Live Tracking</h5>
                    <p class="pw-feature-text mb-0">Stay in total control with real-time status updates from diagnostic intake to final bench-tested completion.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="pw-feature-card h-100 p-4">
                    <div class="pw-feature-icon mb-3">
                        <i class="fa-solid fa-truck-fast text-primary"></i>
                    </div>
                    <h5 class="pw-feature-title">Dual Fulfillment</h5>
                    <p class="pw-feature-text mb-0">Choose free same-day in-store pickup at our Taguig basecamp or fast doorstep courier delivery nationwide.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="pw-workshop-banner p-4 p-md-5 mb-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="pw-section-eyebrow mb-2">
                    <i class="fa-solid fa-map-location-dot"></i> Visit Our Flagship Basecamp
                </div>
                <h3 class="text-white fw-bold mb-3">Where Precision Meets Community</h3>
                <p class="text-white-50 mb-4" style="line-height: 1.7;">
                    Whether you need a quick tire pressure check, complete group-set overhaul, or advice on frame sizing, our friendly staff and master mechanics are always ready to assist.
                </p>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-location-dot text-warning mt-1"></i>
                            <div>
                                <strong class="text-white d-block small">Service Bay Address</strong>
                                <span class="text-white-50 small">Km. 14 East Service Road, Western Bicutan, Taguig City</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-clock text-success mt-1"></i>
                            <div>
                                <strong class="text-white d-block small">Operating Hours</strong>
                                <span class="text-white-50 small">Mon – Sat: 8:00 AM – 6:30 PM<br>Sunday: 9:00 AM – 3:00 PM</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 text-center">
                <div class="p-4 rounded" style="background: rgba(14, 19, 25, 0.7); border: 1px solid var(--pw-glass-border-light);">
                    <i class="fa-solid fa-headset fa-2x text-warning mb-2"></i>
                    <h5 class="text-white fw-bold mb-1">Direct Rider Dispatch</h5>
                    <p class="text-white-50 small mb-3">Have questions regarding stock availability, special builds, or scheduling?</p>
                    <a href="tel:+63288232457" class="pw-btn-trail-sm py-2 px-3 text-decoration-none d-inline-block">
                        <i class="fa-solid fa-phone me-1"></i> (02) 8823-2457
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('./includes/footer.php'); ?>
