<?php
session_start();
include('./includes/header.php');
include('./includes/config.php');
?>

<div class="container mt-3">
    <?php include('./includes/alert.php'); ?>
</div>

<!-- ============ HERO SECTION ============ -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h1>Quality Bikes, Parts &amp; Expert Service</h1>
                <p class="lead">Browse our full catalog of bicycles, components, and safety gear — or book a repair and let our technicians handle the rest.</p>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="#products" class="btn btn-pw">Browse Products</a>
                    <a href="#services" class="btn btn-pw-outline">Our Services</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-placeholder-img">
                    <span><i class="fa-solid fa-image me-2"></i>Hero image placeholder</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ FEATURED PRODUCTS ============ -->
<section id="products" class="py-5">
    <div class="container">
        <div class="text-center">
            <h2 class="section-title">Featured Products</h2>
            <p class="section-subtitle">Bicycles, parts, attachments, and safety gear for every rider.</p>
        </div>

        <div class="row g-4">
            <?php
            // Static placeholder products for the base homepage
            // These will later be replaced with database queries
            $placeholderProducts = [
                ['name' => 'Mountain Bike Pro',     'category' => 'Full Build Bikes', 'price' => 18500],
                ['name' => 'Road Bike Elite',       'category' => 'Full Build Bikes', 'price' => 22000],
                ['name' => 'Shimano Brake Set',     'category' => 'Bicycle Parts',    'price' => 2850],
                ['name' => 'LED Headlight Kit',     'category' => 'Attachments',      'price' => 750],
                ['name' => 'Full-Face Helmet',      'category' => 'Safety Gear',      'price' => 3200],
                ['name' => 'Chain Lubricant Pack',   'category' => 'Bicycle Parts',    'price' => 350],
                ['name' => 'Rear Cargo Rack',       'category' => 'Attachments',      'price' => 1450],
                ['name' => 'Cycling Gloves',        'category' => 'Safety Gear',      'price' => 580],
            ];

            foreach ($placeholderProducts as $product):
            ?>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="product-card">
                    <div class="card-img-placeholder">
                        <span><i class="fa-solid fa-image me-1"></i> Product image</span>
                    </div>
                    <div class="card-body">
                        <p class="card-category"><?php echo $product['category']; ?></p>
                        <h5 class="card-title"><?php echo $product['name']; ?></h5>
                        <p class="card-price">&#8369;<?php echo number_format($product['price'], 2); ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ SERVICES SECTION ============ -->
<section id="services" class="py-5" style="background-color: var(--pw-gray-light);">
    <div class="container">
        <div class="text-center">
            <h2 class="section-title">Repair &amp; Labor Services</h2>
            <p class="section-subtitle">Professional maintenance and installation by experienced technicians.</p>
        </div>

        <div class="row g-4">
            <?php
            $services = [
                ['icon' => 'fa-wrench',         'name' => 'General Tune-Up',         'desc' => 'Full inspection, brake and gear adjustments, chain cleaning, and tire pressure check.',          'fee' => 500],
                ['icon' => 'fa-gear',            'name' => 'Brake Adjustment',        'desc' => 'Pad replacement, cable tensioning, and disc alignment for reliable stopping power.',            'fee' => 300],
                ['icon' => 'fa-link',            'name' => 'Chain Replacement',       'desc' => 'Old chain removal, new chain sizing and installation, and drivetrain compatibility check.',     'fee' => 250],
                ['icon' => 'fa-circle-dot',      'name' => 'Wheel Truing',            'desc' => 'Spoke tension correction and rim alignment to eliminate wobble and improve ride quality.',      'fee' => 400],
                ['icon' => 'fa-screwdriver-wrench','name' => 'Parts Installation',    'desc' => 'Professional mounting of handlebars, seats, pedals, racks, lights, and other accessories.',     'fee' => 200],
                ['icon' => 'fa-shield-halved',   'name' => 'Full Overhaul',           'desc' => 'Complete disassembly, deep clean, bearing service, cable replacement, and reassembly.',          'fee' => 1500],
            ];

            foreach ($services as $service):
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fa-solid <?php echo $service['icon']; ?>"></i>
                    </div>
                    <h5><?php echo $service['name']; ?></h5>
                    <p><?php echo $service['desc']; ?></p>
                    <p class="fw-bold" style="color: var(--pw-blue);">Base Fee: &#8369;<?php echo number_format($service['fee'], 2); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include('./includes/footer.php'); ?>
