<?php
session_start();
include('./includes/header.php');
include('./includes/config.php');
?>

<div class="container mt-3">
    <?php include('./includes/alert.php'); ?>
</div>

<!-- ==========================================================================
     HERO SECTION — Adventure Mountain & Trail Lab
     ========================================================================== -->
<section class="pw-hero-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="pw-hero-badge">
                    <i class="fa-solid fa-mountain"></i>
                    <span>EXPEDITION &amp; TRAIL READY • BIKE LAB</span>
                </div>
                <h1 class="pw-hero-title">
                    Engineered for <span class="pw-hero-title-accent">Adventure.</span><br>
                    Built for Every Trail.
                </h1>
                <p class="pw-hero-desc">
                    Explore high-performance bicycles, expedition components, attachments, and certified safety gear. From rugged mountain descents to daily city rides, our Taguig workshop keeps your journey running smoothly.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="#products" class="pw-btn-trail">
                        <i class="fa-solid fa-compass"></i> Explore Trail Catalog
                    </a>
                    <a href="#services" class="pw-btn-glass">
                        <i class="fa-solid fa-wrench"></i> Workshop Services
                    </a>
                </div>

                <div class="pw-hero-pills">
                    <div class="pw-stat-pill">
                        <i class="fa-solid fa-check-double"></i>
                        <span><strong>100%</strong> Trail-Tested</span>
                    </div>
                    <div class="pw-stat-pill">
                        <i class="fa-solid fa-certificate"></i>
                        <span><strong>Certified</strong> Master Technicians</span>
                    </div>
                    <div class="pw-stat-pill">
                        <i class="fa-solid fa-stopwatch"></i>
                        <span><strong>Fast</strong> Workshop Turnaround</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <!-- Hero Visual Glass Card with Nature Contours and Image Placeholder -->
                <div class="pw-hero-visual-card">
                    <div class="pw-hero-placeholder-inner">
                        <!-- Topographic vector contour lines (nature feel) -->
                        <svg class="pw-topographic-overlay" viewBox="0 0 500 350" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M-50,80 Q100,20 250,90 T550,60" stroke="#72a88d" stroke-width="1.5" />
                            <path d="M-50,140 Q120,70 280,160 T550,120" stroke="#72a88d" stroke-width="1.5" />
                            <path d="M-50,200 Q150,130 300,230 T550,180" stroke="#72a88d" stroke-width="1.5" />
                            <path d="M-50,260 Q180,190 320,290 T550,240" stroke="#72a88d" stroke-width="1.5" />
                            <circle cx="380" cy="100" r="45" stroke="#d16629" stroke-width="1" stroke-dasharray="4 4" />
                        </svg>

                        <div class="pw-hero-placeholder-icon">
                            <i class="fa-solid fa-bicycle"></i>
                        </div>
                        <h4 class="pw-hero-placeholder-label">Hero Banner Placeholder</h4>
                        <p class="pw-hero-placeholder-meta">
                            <i class="fa-solid fa-image me-1"></i> 1200 &times; 800 &bull; Adventure Trail Preview
                        </p>
                        <span class="text-white-50 small mt-2">Replaceable via Admin Media Upload</span>

                        <div class="pw-hero-badge-float">
                            <div class="pw-badge-float-icon">
                                <i class="fa-solid fa-gauge-high"></i>
                            </div>
                            <div>
                                <div class="text-white fw-bold small">Expedition Grade</div>
                                <div class="pw-dim-text small" style="color: var(--pw-sage-light); font-size: 0.75rem;">Tested on Philippine Trails</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     CORE CAPABILITIES / VALUE PROPOSITIONS
     ========================================================================== -->
<section class="py-4" id="about">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-sm-6">
                <div class="pw-feature-card">
                    <div class="pw-feature-icon">
                        <i class="fa-solid fa-mountain-sun"></i>
                    </div>
                    <h5 class="pw-feature-title">All-Terrain Proven</h5>
                    <p class="pw-feature-text">Curated full builds and durable components hand-picked for gravel, trails, and rough roads.</p>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="pw-feature-card">
                    <div class="pw-feature-icon">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                    </div>
                    <h5 class="pw-feature-title">Precision Workshop</h5>
                    <p class="pw-feature-text">Professional service lab offering hydraulic brake bleeds, wheel truing, and full drivetrain overhauls.</p>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="pw-feature-card">
                    <div class="pw-feature-icon">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                    <h5 class="pw-feature-title">Pickup &amp; Delivery</h5>
                    <p class="pw-feature-text">Convenient in-store collection at our Taguig branch or direct courier delivery straight to your doorstep.</p>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="pw-feature-card">
                    <div class="pw-feature-icon">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                    <h5 class="pw-feature-title">Transparent Pricing</h5>
                    <p class="pw-feature-text">Clear base rates on all labor and genuine retail parts with no hidden technician fees.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================================================
     FEATURED PRODUCTS / TRAIL CATALOG (FR3 & FR4)
     ========================================================================== -->
<section id="products" class="pw-section">
    <div class="container">
        <div class="pw-section-head">
            <div class="pw-section-eyebrow">
                <i class="fa-solid fa-compass"></i> Expedition Catalog
            </div>
            <h2 class="pw-section-title">Adventure Bikes &amp; Essential Gear</h2>
            <p class="pw-section-sub">
                Browse our curated selection of trail-ready rigs, precision replacement parts, rugged racks, and rider protection.
            </p>
        </div>

        <!-- Interactive Category Filter Pills -->
        <div class="pw-category-strip">
            <button class="pw-filter-pill active" data-category="all">
                <i class="fa-solid fa-layer-group"></i> All Gear
            </button>
            <button class="pw-filter-pill" data-category="Full Build Bikes">
                <i class="fa-solid fa-bicycle"></i> Full Build Bikes
            </button>
            <button class="pw-filter-pill" data-category="Bicycle Parts">
                <i class="fa-solid fa-gears"></i> Bicycle Parts
            </button>
            <button class="pw-filter-pill" data-category="Attachments">
                <i class="fa-solid fa-toolbox"></i> Attachments
            </button>
            <button class="pw-filter-pill" data-category="Safety Gear">
                <i class="fa-solid fa-shield-halved"></i> Safety Gear
            </button>
        </div>

        <!-- Product Cards Grid with Placeholder Images -->
        <div class="row g-4" id="productList">
            <?php
            // Structured sample products based on Project Proposal BSIT-2B-T (FR3, FR4)
            // Uses only placeholder images per user instructions
            $placeholderProducts = [
                [
                    'name'     => 'Apex Trail Mountain Rig Pro',
                    'category' => 'Full Build Bikes',
                    'spec'     => '1x12 Drivetrain • Air Suspension Fork • Tubeless Ready',
                    'price'    => 18500,
                    'icon'     => 'fa-bicycle',
                    'stock'    => 'In Stock'
                ],
                [
                    'name'     => 'GravelQuest Overland Elite',
                    'category' => 'Full Build Bikes',
                    'spec'     => 'Carbon Fork • Shimano GRX Group • Wide Clearance 45c',
                    'price'    => 22500,
                    'icon'     => 'fa-bicycle',
                    'stock'    => 'In Stock'
                ],
                [
                    'name'     => 'Shimano Deore Hydraulic Disc Brakes',
                    'category' => 'Bicycle Parts',
                    'spec'     => 'Dual-Piston Caliper • 2-Finger Ergonomic Lever',
                    'price'    => 3450,
                    'icon'     => 'fa-compact-disc',
                    'stock'    => 'In Stock'
                ],
                [
                    'name'     => 'RockShox Judy Trail Air Fork 120mm',
                    'category' => 'Bicycle Parts',
                    'spec'     => 'Solo Air Spring • TurnKey Lockout • Boost 110mm',
                    'price'    => 7800,
                    'icon'     => 'fa-wrench',
                    'stock'    => 'In Stock'
                ],
                [
                    'name'     => 'TrailBeam 1200 Rechargeable Light',
                    'category' => 'Attachments',
                    'spec'     => '1200 Lumens • USB-C Quick Charge • IPX6 Waterproof',
                    'price'    => 950,
                    'icon'     => 'fa-lightbulb',
                    'stock'    => 'In Stock'
                ],
                [
                    'name'     => 'Overland Heavy-Duty Cargo Rack',
                    'category' => 'Attachments',
                    'spec'     => '6061 Alloy Frame • 30kg Capacity • Pannier Mounts',
                    'price'    => 1650,
                    'icon'     => 'fa-cart-flatbed',
                    'stock'    => 'In Stock'
                ],
                [
                    'name'     => 'TrailGuard Carbon MIPS Enduro Helmet',
                    'category' => 'Safety Gear',
                    'spec'     => 'Integrated MIPS Protection • 18 Vents • Extended Visor',
                    'price'    => 3800,
                    'icon'     => 'fa-helmet-safety',
                    'stock'    => 'In Stock'
                ],
                [
                    'name'     => 'ProEnduro Gel Shock Gloves',
                    'category' => 'Safety Gear',
                    'spec'     => 'Vibration Dampening • Touchscreen Compatible • Breathable',
                    'price'    => 680,
                    'icon'     => 'fa-mitten',
                    'stock'    => 'In Stock'
                ],
            ];

            foreach ($placeholderProducts as $index => $product):
            ?>
            <div class="col-xl-3 col-lg-4 col-md-6 pw-product-item" data-category="<?php echo htmlspecialchars($product['category']); ?>">
                <div class="pw-product-card">
                    <!-- Placeholder Image Container -->
                    <div class="pw-product-img-box">
                        <span class="pw-category-tag"><?php echo htmlspecialchars($product['category']); ?></span>
                        <span class="pw-stock-indicator">
                            <span class="pw-dot"></span> <?php echo htmlspecialchars($product['stock']); ?>
                        </span>
                        
                        <div class="pw-product-img-icon">
                            <i class="fa-solid <?php echo htmlspecialchars($product['icon']); ?>"></i>
                        </div>
                        <div class="pw-product-img-text">Placeholder Image</div>
                        <div class="pw-product-dim">600 &times; 450 &bull; Item #<?php echo $index + 101; ?></div>
                    </div>

                    <div class="pw-product-body">
                        <h5 class="pw-product-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <p class="pw-product-spec"><?php echo htmlspecialchars($product['spec']); ?></p>

                        <div class="pw-product-footer">
                            <div class="pw-product-price">
                                <span class="pw-price-label">Price</span>
                                <span class="pw-price-amount">&#8369;<?php echo number_format($product['price'], 2); ?></span>
                            </div>
                            <a href="#services" class="pw-btn-product-add" title="View details">
                                <i class="fa-solid fa-cart-plus"></i>
                                <span>Details</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==========================================================================
     REPAIR & LABOR SERVICES (FR5 & FR15)
     ========================================================================== -->
<section id="services" class="pw-section pw-services-bg">
    <div class="container">
        <div class="pw-section-head">
            <div class="pw-section-eyebrow">
                <i class="fa-solid fa-screwdriver-wrench"></i> Workshop Laboratory
            </div>
            <h2 class="pw-section-title">Bicycle Repair &amp; Maintenance Services</h2>
            <p class="pw-section-sub">
                Professional maintenance performed by experienced technicians using precision tools and premium lubricants.
            </p>
        </div>

        <div class="row g-4">
            <?php
            // Defined services based on proposal FR5 & FR15
            $services = [
                [
                    'icon'  => 'fa-wrench',
                    'name'  => 'Comprehensive Trail Tune-Up',
                    'desc'  => 'Multi-point safety inspection, brake calibration, gear shifting adjustment, chain lubrication, and spoke tension assessment.',
                    'fee'   => 500,
                    'turn'  => 'Same-Day'
                ],
                [
                    'icon'  => 'fa-compact-disc',
                    'name'  => 'Hydraulic Brake Bleed & Alignment',
                    'desc'  => 'Mineral oil or DOT fluid system flush, bubble purge, caliper re-centering, and organic/metallic pad wear inspection.',
                    'fee'   => 350,
                    'turn'  => '2–3 Hours'
                ],
                [
                    'icon'  => 'fa-link',
                    'name'  => 'Drivetrain Ultrasonic Clean & Chain Service',
                    'desc'  => 'Deep ultrasonic degreasing of cassette, chainrings, and pulleys with wear measurement and master-link chain installation.',
                    'fee'   => 250,
                    'turn'  => 'Same-Day'
                ],
                [
                    'icon'  => 'fa-circle-notch',
                    'name'  => 'Precision Wheel Truing & Balancing',
                    'desc'  => 'Tensiometer calibrated spoke adjustment to eliminate lateral wobble and radial hop for smooth high-speed rolling.',
                    'fee'   => 400,
                    'turn'  => '1 Day'
                ],
                [
                    'icon'  => 'fa-gears',
                    'name'  => 'Component & Accessory Mounting',
                    'desc'  => 'Professional torque-spec installation of aftermarket handlebars, racks, lighting harnesses, tubeless setups, and pedals.',
                    'fee'   => 200,
                    'turn'  => '1–2 Hours'
                ],
                [
                    'icon'  => 'fa-shield-halved',
                    'name'  => 'Complete Expedition Rig Overhaul',
                    'desc'  => 'Full bike strip-down, headset and bottom bracket re-packing with marine grease, all cable renewals, and race-ready rebuild.',
                    'fee'   => 1500,
                    'turn'  => '2–3 Days'
                ],
            ];

            foreach ($services as $service):
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="pw-service-card">
                    <div class="pw-service-icon-box">
                        <i class="fa-solid <?php echo htmlspecialchars($service['icon']); ?>"></i>
                    </div>
                    <h5 class="pw-service-title"><?php echo htmlspecialchars($service['name']); ?></h5>
                    <p class="pw-service-desc"><?php echo htmlspecialchars($service['desc']); ?></p>

                    <div class="pw-service-meta">
                        <div class="pw-service-fee">
                            <span>Base Labor Fee</span>
                            &#8369;<?php echo number_format($service['fee'], 2); ?>
                        </div>
                        <span class="pw-service-pill">
                            <i class="fa-regular fa-clock me-1"></i><?php echo htmlspecialchars($service['turn']); ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==========================================================================
     WORKSHOP BASECAMP BANNER / LOCATION INFO (FR8, FR15)
     ========================================================================== -->
<section class="py-5">
    <div class="container">
        <div class="pw-workshop-banner">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <span class="pw-trail-badge mb-3 d-inline-block">
                        <i class="fa-solid fa-location-dot me-1"></i>Taguig Basecamp &amp; Service Bay
                    </span>
                    <h3 class="pw-workshop-title text-white">Need a Trail Diagnostic or Custom Rig Build?</h3>
                    <p class="pw-workshop-desc">
                        Visit our physical service bay at Western Bicutan for walk-in repairs, parts installation, or online order pickups. Our mechanics are ready to prep your bike for whatever the mountain throws at you.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="pw-store-badge">
                            <i class="fa-solid fa-map-pin text-warning fs-5"></i>
                            <div class="text-start">
                                <div class="text-white fw-bold small">Shop Address</div>
                                <div class="small text-white-50">Km. 14 East Service Rd, Taguig City</div>
                            </div>
                        </div>
                        <div class="pw-store-badge">
                            <i class="fa-solid fa-phone text-success fs-5"></i>
                            <div class="text-start">
                                <div class="text-white fw-bold small">Call Dispatch</div>
                                <div class="small text-white-50">(02) 8823-2457 / +63 917 555 BIKE</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end text-center mt-4 mt-lg-0">
                    <a href="#services" class="pw-btn-trail py-3 px-4">
                        <i class="fa-solid fa-calendar-check"></i> Book Workshop Visit
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include('./includes/footer.php'); ?>
