<?php
session_start();
include('./includes/header.php');
include('./includes/config.php');
?>

<link rel="stylesheet" href="/project/PedalWorks-Dynamics/includes/style/homepage-animations.css">

<div class="pw-mountain-parallax" aria-hidden="true">
    <div class="pw-mountain-layer pw-mountain-layer-0" style="background-image: url('/project/PedalWorks-Dynamics/includes/images/mountain/layer_0.png');"></div>
    <div class="pw-mountain-layer pw-mountain-layer-1" style="background-image: url('/project/PedalWorks-Dynamics/includes/images/mountain/layer_1.png');"></div>
    <div class="pw-mountain-layer pw-mountain-layer-2" style="background-image: url('/project/PedalWorks-Dynamics/includes/images/mountain/layer_2.png');"></div>
    <div class="pw-mountain-mist pw-mountain-mist-far"></div>
    <div class="pw-mountain-layer pw-mountain-layer-3" style="background-image: url('/project/PedalWorks-Dynamics/includes/images/mountain/layer_3.png');"></div>
    <div class="pw-mountain-layer pw-mountain-layer-4" style="background-image: url('/project/PedalWorks-Dynamics/includes/images/mountain/layer_4.png');"></div>
    <div class="pw-mountain-mist pw-mountain-mist-near"></div>
    <div class="pw-mountain-layer pw-mountain-layer-5" style="background-image: url('/project/PedalWorks-Dynamics/includes/images/mountain/layer_5.png');"></div>
    <div class="pw-mountain-layer pw-mountain-layer-6" style="background-image: url('/project/PedalWorks-Dynamics/includes/images/mountain/layer_6.png');"></div>
    
    <div class="pw-mountain-tint"></div>

    <svg class="pw-parallax-contours" viewBox="0 0 1200 800" fill="none" preserveAspectRatio="xMidYMid slice">
        <path class="pw-contour-1" d="M-100,120 C200,60 400,240 800,150 C1000,90 1200,300 1400,200" stroke="#72a88d" stroke-width="1.2" stroke-dasharray="6 6"/>
        <path class="pw-contour-2" d="M-100,350 C300,280 500,480 900,380 C1100,320 1300,500 1400,420" stroke="#72a88d" stroke-width="1.2" stroke-dasharray="8 8"/>
        <path class="pw-contour-3" d="M-100,600 C250,520 600,720 950,590 C1150,510 1350,680 1400,610" stroke="#d16629" stroke-width="1.2" stroke-dasharray="4 6"/>
    </svg>

    <svg class="pw-parallax-compass" viewBox="0 0 200 200" fill="none" stroke="#72a88d" stroke-width="1.5">
        <circle cx="100" cy="100" r="90" stroke-dasharray="4 8"/>
        <circle cx="100" cy="100" r="65" stroke-dasharray="2 4"/>
        <line x1="100" y1="5" x2="100" y2="195"/>
        <line x1="5" y1="100" x2="195" y2="100"/>
        <polygon points="100,25 110,90 100,100 90,90" fill="#d16629" stroke="none"/>
        <polygon points="100,175 110,110 100,100 90,110" fill="#72a88d" stroke="none"/>
    </svg>
</div>

<aside class="pw-trail-progress-rail d-none d-lg-flex" aria-label="Trail Elevation Progress">
    <div class="pw-trail-elevation-pill">
        <i class="fa-solid fa-mountain"></i>
        <span class="pw-elevation-value">1,450m</span>
    </div>
    <div class="pw-trail-meter">
        <div class="pw-trail-track">
            <div class="pw-trail-track-fill"></div>
        </div>
        <div class="pw-trail-dots">
            <div class="pw-trail-dot active" data-target=".pw-hero-section">
                <span class="pw-trail-dot-label">Summit Peak (Hero)</span>
            </div>
            <div class="pw-trail-dot" data-target="#about">
                <span class="pw-trail-dot-label">Ridge Pass (Pillars)</span>
            </div>
            <div class="pw-trail-dot" data-target="#products">
                <span class="pw-trail-dot-label">Gear Stash (Bikes &amp; Parts)</span>
            </div>
            <div class="pw-trail-dot" data-target="#services">
                <span class="pw-trail-dot-label">Workshop Lab (Repairs)</span>
            </div>
            <div class="pw-trail-dot" data-target="#workshop-basecamp">
                <span class="pw-trail-dot-label">Basecamp (Storefront)</span>
            </div>
        </div>
    </div>
</aside>

<div class="pw-homepage-wrapper">

    <div class="container mt-3">
        <?php include('./includes/alert.php'); ?>
    </div>

    <section class="pw-hero-section pw-scroll-section" id="hero">
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
                    <div class="d-flex flex-wrap gap-3 pw-hero-actions">
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
                    <div class="pw-hero-visual-card pw-tilt-card">
                        <div class="pw-hero-placeholder-inner">
                            <svg class="pw-topographic-overlay" viewBox="0 0 500 350" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M-50,80 Q100,20 250,90 T550,60" stroke="#72a88d" stroke-width="1.5" />
                                <path d="M-50,140 Q120,70 280,160 T550,120" stroke="#72a88d" stroke-width="1.5" />
                                <path d="M-50,200 Q150,130 300,230 T550,180" stroke="#72a88d" stroke-width="1.5" />
                                <path d="M-50,260 Q180,190 320,290 T550,240" stroke="#72a88d" stroke-width="1.5" />
                                <circle cx="380" cy="100" r="45" stroke="#d16629" stroke-width="1" stroke-dasharray="4 4" />
                            </svg>

                            <h4 class="pw-hero-placeholder-label">Hero Banner Placeholder</h4>
                            <p class="pw-hero-placeholder-meta">
                                <i class="fa-solid fa-image me-1"></i> 1200 &times; 800 &bull; Adventure Trail Rig
                            </p>
                            <span class="text-white-50 small mt-2">Replaceable via Admin Media Upload</span>

                            <div class="pw-hero-badge-float">
                                <div class="pw-badge-float-icon">
                                    <i class="fa-solid fa-gauge-high"></i>
                                </div>
                                <div>
                                    <div class="text-white fw-bold small">Expedition Grade</div>
                                    <div class="pw-dim-text small" style="color: var(--pw-sage-light); font-size: 0.75rem;">Altitude: 1,450m Peak</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <a href="#about" class="pw-scroll-cue" aria-label="Scroll down to explore">
            <span>Descend the Trail</span>
            <div class="pw-scroll-cue-icon">
                <div class="pw-scroll-cue-dot"></div>
            </div>
        </a>
    </section>

    <section class="py-5 pw-scroll-section" id="about">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-3 col-sm-6">
                    <div class="pw-feature-card pw-tilt-card">
                        <div class="pw-feature-icon">
                            <i class="fa-solid fa-mountain-sun"></i>
                        </div>
                        <h5 class="pw-feature-title">All-Terrain Proven</h5>
                        <p class="pw-feature-text">Curated full builds and durable components hand-picked for gravel, trails, and rough roads.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="pw-feature-card pw-tilt-card">
                        <div class="pw-feature-icon">
                            <i class="fa-solid fa-screwdriver-wrench"></i>
                        </div>
                        <h5 class="pw-feature-title">Precision Workshop</h5>
                        <p class="pw-feature-text">Professional service lab offering hydraulic brake bleeds, wheel truing, and full drivetrain overhauls.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="pw-feature-card pw-tilt-card">
                        <div class="pw-feature-icon">
                            <i class="fa-solid fa-truck-fast"></i>
                        </div>
                        <h5 class="pw-feature-title">Pickup &amp; Delivery</h5>
                        <p class="pw-feature-text">Convenient in-store collection at our Taguig branch or direct courier delivery straight to your doorstep.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="pw-feature-card pw-tilt-card">
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

    <section id="products" class="pw-section pw-scroll-section">
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

            <div class="pw-category-strip">
                <button class="pw-filter-pill active" data-category="all">
                    <i class="fa-solid fa-layer-group"></i> All Gear
                </button>
                <?php
                $dbCategories = [];
                if ($conn) {
                    $catRes = @mysqli_query($conn, "SELECT * FROM productCategory ORDER BY productCategoryID ASC");
                    if ($catRes) {
                        while ($catRow = mysqli_fetch_assoc($catRes)) {
                            $dbCategories[] = $catRow;
                        }
                    }
                }
                foreach ($dbCategories as $cat):
                ?>
                <button class="pw-filter-pill" data-category="<?php echo htmlspecialchars($cat['name']); ?>">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="row g-4" id="productList">
                <?php
                $dbProducts = [];
                if ($conn) {
                    $prodRes = @mysqli_query($conn, "SELECT p.*, pc.name AS categoryName 
                                                     FROM product p 
                                                     LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID 
                                                     WHERE p.endDate IS NULL 
                                                     ORDER BY p.productID DESC");
                    if ($prodRes) {
                        while ($prodRow = mysqli_fetch_assoc($prodRes)) {
                            $dbProducts[] = $prodRow;
                        }
                    }
                }

                if (!empty($dbProducts)):
                    foreach ($dbProducts as $product):
                        $catName = $product['categoryName'] ?? 'Gear';
                        $isLowStock = ($product['stock'] <= $product['lowStockThreshold'] && $product['stock'] > 0);
                        $isOutOfStock = ($product['stock'] <= 0);
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6 pw-product-item" data-category="<?php echo htmlspecialchars($catName); ?>">
                    <div class="pw-product-card pw-tilt-card">
                        <div class="pw-product-img-box">
                            <span class="pw-category-tag"><?php echo htmlspecialchars($catName); ?></span>
                            <span class="pw-stock-indicator <?php echo $isOutOfStock ? 'text-danger' : ($isLowStock ? 'text-warning' : ''); ?>">
                                <span class="pw-dot <?php echo $isOutOfStock ? 'bg-danger' : ($isLowStock ? 'bg-warning' : ''); ?>"></span> 
                                <?php 
                                if ($isOutOfStock) {
                                    echo 'Out of Stock';
                                } elseif ($isLowStock) {
                                    echo 'Low Stock (' . (int)$product['stock'] . ')';
                                } else {
                                    echo 'In Stock (' . (int)$product['stock'] . ')';
                                }
                                ?>
                            </span>
                            
                            <?php if (!empty($product['image']) && file_exists(__DIR__ . '/' . $product['image'])): ?>
                                <img src="/project/PedalWorks-Dynamics/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="pw-product-img">
                            <?php else: ?>
                                <div class="pw-product-img-icon">
                                    <i class="fa-solid fa-bicycle"></i>
                                </div>
                                <div class="pw-product-img-text"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="pw-product-dim">SKU: <?php echo htmlspecialchars($product['sku']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="pw-product-body">
                            <h5 class="pw-product-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="pw-product-spec"><?php echo htmlspecialchars($product['description'] ?? ''); ?></p>

                            <div class="pw-product-footer">
                                <div class="pw-product-price">
                                    <span class="pw-price-label">Price</span>
                                    <span class="pw-price-amount">&#8369;<?php echo number_format($product['price'], 2); ?></span>
                                </div>
                                <a href="#" class="pw-btn-product-add" title="View details">
                                    <i class="fa-solid fa-cart-plus"></i>
                                    <span>Details</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php 
                    endforeach;
                else: 
                ?>
                <div class="col-12 text-center py-5">
                    <div class="p-4" style="background: rgba(18, 43, 33, 0.4); border-radius: 12px; border: 1px dashed rgba(114, 168, 141, 0.3);">
                        <i class="fa-solid fa-boxes-stacked fa-2x mb-3 text-secondary"></i>
                        <h5 class="text-white-50">No Active Products in Database</h5>
                        <p class="text-white-50 small mb-0">Products added in the admin dashboard will dynamically appear here.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section id="services" class="pw-section pw-services-bg pw-scroll-section">
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
                $dbServices = [];
                if ($conn) {
                    $servRes = @mysqli_query($conn, "SELECT s.*, sc.name AS categoryName 
                                                     FROM service s 
                                                     LEFT JOIN serviceCategory sc ON s.serviceCategoryID = sc.serviceCategoryID 
                                                     WHERE s.endDate IS NULL 
                                                     ORDER BY s.serviceID ASC");
                    if ($servRes) {
                        while ($servRow = mysqli_fetch_assoc($servRes)) {
                            $dbServices[] = $servRow;
                        }
                    }
                }

                if (!empty($dbServices)):
                    foreach ($dbServices as $service):
                        $catName = $service['categoryName'] ?? 'Workshop';
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="pw-service-card pw-tilt-card">
                        <div class="pw-service-icon-box">
                            <i class="fa-solid fa-wrench"></i>
                        </div>
                        <h5 class="pw-service-title"><?php echo htmlspecialchars($service['name']); ?></h5>
                        <p class="pw-service-desc"><?php echo htmlspecialchars($service['description'] ?? ''); ?></p>

                        <div class="pw-service-meta">
                            <div class="pw-service-fee">
                                <span>Labor Fee</span>
                                &#8369;<?php echo number_format($service['price'], 2); ?>
                            </div>
                            <span class="pw-service-pill">
                                <i class="fa-solid fa-tag me-1"></i><?php echo htmlspecialchars($catName); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php 
                    endforeach;
                else: 
                ?>
                <div class="col-12 text-center py-5">
                    <div class="p-4" style="background: rgba(18, 43, 33, 0.4); border-radius: 12px; border: 1px dashed rgba(114, 168, 141, 0.3);">
                        <i class="fa-solid fa-screwdriver-wrench fa-2x mb-3 text-secondary"></i>
                        <h5 class="text-white-50">No Workshop Services in Database</h5>
                        <p class="text-white-50 small mb-0">Services added in the admin dashboard will dynamically appear here.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="py-5 pw-scroll-section" id="workshop-basecamp">
        <div class="container">
            <div class="pw-workshop-banner pw-tilt-card">
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

</div>

<script src="/project/PedalWorks-Dynamics/includes/js/anime.min.js"></script>

<script src="/project/PedalWorks-Dynamics/includes/js/homepage-animations.js"></script>

<?php include('./includes/footer.php'); ?>
