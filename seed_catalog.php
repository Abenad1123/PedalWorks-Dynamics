<?php
include('./includes/config.php');

if (!$conn) {
    die("Database connection failed.\n");
}

echo "Seeding products and services...\n";

// Map category names to IDs
$catMap = [];
$res = mysqli_query($conn, "SELECT productCategoryID, name FROM productCategory");
while ($row = mysqli_fetch_assoc($res)) {
    $catMap[$row['name']] = (int)$row['productCategoryID'];
}

$servCatMap = [];
$res = mysqli_query($conn, "SELECT serviceCategoryID, name FROM serviceCategory");
while ($row = mysqli_fetch_assoc($res)) {
    $servCatMap[$row['name']] = (int)$row['serviceCategoryID'];
}

$products = [
    // Full Build Bikes
    [
        'sku' => 'PROD-BIKE-001',
        'name' => 'PedalWorks Classic City Commuter',
        'description' => 'Comfortable step-through city bicycle equipped with front wire basket, full fenders, and reliable Shimano 7-speed gearing.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image1.png',
        'price' => 8500.00,
        'stock' => 12,
        'lowStockThreshold' => 4
    ],
    [
        'sku' => 'PROD-BIKE-002',
        'name' => 'Black Mamba Mountain Bike 29er',
        'description' => 'Rugged aluminum hardtail frame with front suspension fork, dual mechanical disc brakes, and knobby 29-inch trail tires.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image2.png',
        'price' => 14999.00,
        'stock' => 8,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-BIKE-003',
        'name' => 'FoldMaster Urban 20" Folding Bike',
        'description' => 'Compact folding commuter bike with quick-release latches, integrated headlamp mount, and responsive rim brakes.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image3.png',
        'price' => 11200.00,
        'stock' => 15,
        'lowStockThreshold' => 5
    ],
    [
        'sku' => 'PROD-BIKE-004',
        'name' => 'Rockbros Aero Endurance Road Bike',
        'description' => 'Lightweight aerodynamic road bike with drop handlebars, dual disc brakes, and high-efficiency 2x9 road groupset.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image4.png',
        'price' => 28500.00,
        'stock' => 5,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-BIKE-005',
        'name' => 'Bickerton British Racing Green Folder',
        'description' => 'Heritage-inspired British folding bike with rear luggage rack, leather saddle accents, and 8-speed wide-range gearing.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image5.png',
        'price' => 13500.00,
        'stock' => 7,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-BIKE-006',
        'name' => 'Aenxrd Trail Slayer MTB 27.5"',
        'description' => 'High-traction cross country mountain bike with lockout front suspension, lightweight alloy cockpit, and 24-speed gears.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image8.png',
        'price' => 16800.00,
        'stock' => 9,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-BIKE-007',
        'name' => 'Cube All-Terrain Touring Rig',
        'description' => 'Ready for cross-country bikepacking adventures with front and rear mudguards, integrated cargo rack, and 1x11 drivetrain.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image9.png',
        'price' => 22000.00,
        'stock' => 4,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-BIKE-008',
        'name' => 'Dagger Stealth Gravel Rig',
        'description' => 'Matte black gravel grinder with tan-wall 700x38c multi-surface tires, flared drop bars, and flat-mount disc brakes.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image10.png',
        'price' => 19500.00,
        'stock' => 6,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-BIKE-009',
        'name' => 'Tern Verge Disc Folding Rig',
        'description' => 'Performance folding bicycle engineered with disc brakes, stiff hydroformed aluminum frame, and rapid compact fold.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image11.png',
        'price' => 24000.00,
        'stock' => 3,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-BIKE-010',
        'name' => 'Raleigh Cyan Sprint Aero Racer',
        'description' => 'Striking aero frame with deep-section aerodynamic wheels, integrated cables, and precision road race groupset.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image13.png',
        'price' => 31000.00,
        'stock' => 4,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-BIKE-011',
        'name' => 'Trinx M134 Sport Hardtail MTB',
        'description' => 'Durable trail machine designed for aggressive singletrack climbing and stable downhill control.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image14.png',
        'price' => 9800.00,
        'stock' => 10,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-BIKE-012',
        'name' => 'Rainmo Retro Cream Cruiser',
        'description' => 'Vintage lifestyle bicycle with cream powder-coat finish, woven basket, tan tires, and relaxed upright posture.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image16.png',
        'price' => 7900.00,
        'stock' => 14,
        'lowStockThreshold' => 4
    ],
    [
        'sku' => 'PROD-BIKE-013',
        'name' => 'Garuda Geometric Aero Road Bike',
        'description' => 'Geometric styling on lightweight 6061 alloy frame, drop-bar ergonomics, and high-cadence drivetrain.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image17.png',
        'price' => 15500.00,
        'stock' => 6,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-BIKE-014',
        'name' => 'Toseek Targa Endurance Gravel',
        'description' => 'Long-distance gravel and adventure bicycle with internal cabling, thru-axles, and hydraulic stopping power.',
        'category' => 'Full Build Bikes',
        'image' => 'assets/images/image18.png',
        'price' => 18900.00,
        'stock' => 5,
        'lowStockThreshold' => 2
    ],

    // Bicycle Parts
    [
        'sku' => 'PROD-PART-001',
        'name' => 'Shimano Deore 11-Speed Rear Derailleur',
        'description' => 'Shadow RD+ clutch technology delivers consistent chain retention and quiet drivetrain performance over rough terrain.',
        'category' => 'Bicycle Parts',
        'image' => 'assets/images/categories/category_parts.jpg',
        'price' => 2450.00,
        'stock' => 20,
        'lowStockThreshold' => 5
    ],
    [
        'sku' => 'PROD-PART-002',
        'name' => 'Shimano MT200 Hydraulic Disc Brake Set',
        'description' => 'Front and rear pre-bled hydraulic disc brake set offering consistent, modulated stopping power in all weather conditions.',
        'category' => 'Bicycle Parts',
        'image' => 'assets/images/categories/category_parts.jpg',
        'price' => 2800.00,
        'stock' => 18,
        'lowStockThreshold' => 4
    ],
    [
        'sku' => 'PROD-PART-003',
        'name' => 'SRAM 12-Speed Wide-Range Cassette',
        'description' => '10-52T steel stamped cog range for tackling the steepest mountain gradients with precision shifting ramps.',
        'category' => 'Bicycle Parts',
        'image' => 'assets/images/categories/category_parts.jpg',
        'price' => 4500.00,
        'stock' => 10,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-PART-004',
        'name' => 'CenterLock 160mm Floating Disc Rotor',
        'description' => 'Heat-dissipating alloy carrier with stainless steel braking surface to reduce brake fade on long alpine descents.',
        'category' => 'Bicycle Parts',
        'image' => 'assets/images/categories/category_parts.jpg',
        'price' => 1350.00,
        'stock' => 25,
        'lowStockThreshold' => 6
    ],

    // Attachments
    [
        'sku' => 'PROD-ATT-001',
        'name' => 'Nomad Waterproof Handlebar Bag 4L',
        'description' => 'IPX6 waterproof roll-top handlebar bag with heavy-duty mounting straps, exterior bungee cords, and reflective accents.',
        'category' => 'Attachments',
        'image' => 'assets/images/categories/category_attachments.jpg',
        'price' => 1250.00,
        'stock' => 22,
        'lowStockThreshold' => 5
    ],
    [
        'sku' => 'PROD-ATT-002',
        'name' => 'Explorer RA-3 Heavy Duty Rear Cargo Rack',
        'description' => 'Tubular 6061-T6 aluminum rack supporting up to 25kg load, compatible with standard panniers and rear safety lights.',
        'category' => 'Attachments',
        'image' => 'assets/images/categories/category_attachments.jpg',
        'price' => 1650.00,
        'stock' => 15,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-ATT-003',
        'name' => 'Lumina 800-Lumen USB-C Rechargeable Headlight',
        'description' => 'Ultra-bright trail headlight with IPX5 aluminum housing, multiple light modes, and silicone handlebar strap.',
        'category' => 'Attachments',
        'image' => 'assets/images/categories/category_attachments.jpg',
        'price' => 950.00,
        'stock' => 30,
        'lowStockThreshold' => 6
    ],
    [
        'sku' => 'PROD-ATT-004',
        'name' => 'Matte Trail Water Bottle & Alloy Cage Set',
        'description' => 'BPA-free high-flow squeeze bottle paired with lightweight anodized aluminum retention cage.',
        'category' => 'Attachments',
        'image' => 'assets/images/categories/category_attachments.jpg',
        'price' => 450.00,
        'stock' => 40,
        'lowStockThreshold' => 8
    ],

    // Safety Gear
    [
        'sku' => 'PROD-SAFE-001',
        'name' => 'Kinetic Matte Trail Helmet with Sun Visor',
        'description' => 'In-mold EPS impact foam with adjustable dial fit system, detachable sun visor, and 18 high-flow ventilation channels.',
        'category' => 'Safety Gear',
        'image' => 'assets/images/categories/category_safety_gear.jpg',
        'price' => 2600.00,
        'stock' => 16,
        'lowStockThreshold' => 4
    ],
    [
        'sku' => 'PROD-SAFE-002',
        'name' => 'ArmorGrip Full-Finger Riding Gloves',
        'description' => 'Breathable mesh back with shock-absorbing gel palm pads, touchscreen-compatible fingertips, and TPR knuckle guards.',
        'category' => 'Safety Gear',
        'image' => 'assets/images/categories/category_safety_gear.jpg',
        'price' => 750.00,
        'stock' => 35,
        'lowStockThreshold' => 7
    ],
    [
        'sku' => 'PROD-SAFE-003',
        'name' => 'Titan Hardened Steel U-Lock with Bracket',
        'description' => '14mm hardened steel shackle with anti-pick disc cylinder and quick-release frame mounting bracket.',
        'category' => 'Safety Gear',
        'image' => 'assets/images/categories/category_safety_gear.jpg',
        'price' => 1400.00,
        'stock' => 18,
        'lowStockThreshold' => 4
    ],
    [
        'sku' => 'PROD-SAFE-004',
        'name' => 'AeroVision UV400 Polarized Sport Glasses',
        'description' => 'High-clarity impact-resistant polycarbonate lenses with TR90 flexible frame and anti-fog ventilation slits.',
        'category' => 'Safety Gear',
        'image' => 'assets/images/categories/category_safety_gear.jpg',
        'price' => 890.00,
        'stock' => 24,
        'lowStockThreshold' => 5
    ],
];

foreach ($products as $p) {
    $catId = $catMap[$p['category']] ?? 1;
    $chk = mysqli_prepare($conn, "SELECT productID FROM product WHERE sku = ? AND endDate IS NULL LIMIT 1");
    mysqli_stmt_bind_param($chk, 's', $p['sku']);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);

    if (mysqli_stmt_num_rows($chk) === 0) {
        $ins = mysqli_prepare($conn, "INSERT INTO product (sku, name, description, productCategoryID, image, price, stock, lowStockThreshold, startDate, endDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)");
        mysqli_stmt_bind_param($ins, 'sssissdi', $p['sku'], $p['name'], $p['description'], $catId, $p['image'], $p['price'], $p['stock'], $p['lowStockThreshold']);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
        echo "Inserted product: {$p['name']} ({$p['sku']})\n";
    } else {
        echo "Product exists: {$p['sku']}\n";
    }
    mysqli_stmt_close($chk);
}

// Services
$services = [
    [
        'sku' => 'SERV-TUNE-001',
        'name' => 'Complete Trail Tune-Up & Overhaul',
        'description' => 'Full frame inspection, 100% bearing torque check, derailleur indexing, brake adjustment, and drivetrain clean & lube.',
        'category' => 'Tune-Up & Overhaul',
        'image' => 'assets/images/categories/category_parts.jpg',
        'price' => 1200.00
    ],
    [
        'sku' => 'SERV-BRK-001',
        'name' => 'Hydraulic Brake Bleed & Fluid Flush (Front & Rear)',
        'description' => 'Complete flush with premium mineral oil or DOT fluid, bubble removal, caliper piston cleaning, and bite point calibration.',
        'category' => 'Brake Services',
        'image' => 'assets/images/categories/category_parts.jpg',
        'price' => 650.00
    ],
    [
        'sku' => 'SERV-BRK-002',
        'name' => 'Disc Brake Pad Replacement & Rotor Truing',
        'description' => 'Installation of fresh semi-metallic or ceramic brake pads, surface decontamination, and rotor truing.',
        'category' => 'Brake Services',
        'image' => 'assets/images/categories/category_parts.jpg',
        'price' => 350.00
    ],
    [
        'sku' => 'SERV-DRV-001',
        'name' => 'Drivetrain Ultrasonic Deep Clean & Re-Lube',
        'description' => 'Ultrasonic bath cleaning for cassette, chain, chainrings, and pulleys with high-efficiency chain wax application.',
        'category' => 'Drivetrain & Transmission',
        'image' => 'assets/images/categories/category_parts.jpg',
        'price' => 500.00
    ],
    [
        'sku' => 'SERV-DRV-002',
        'name' => 'Derailleur Hanger Alignment & Shifter Indexing',
        'description' => 'Gauge tool calibration to straighten bent derailleur hanger and cable tension tuning for crisp gear changes.',
        'category' => 'Drivetrain & Transmission',
        'image' => 'assets/images/categories/category_parts.jpg',
        'price' => 400.00
    ],
    [
        'sku' => 'SERV-WHL-001',
        'name' => 'Precision Wheel Truing & Spoke Tensioning',
        'description' => 'Centering and radial/lateral truing on truing stand with spoke tensiometer inspection.',
        'category' => 'Wheel & Tire Services',
        'image' => 'assets/images/categories/category_parts.jpg',
        'price' => 450.00
    ],
    [
        'sku' => 'SERV-WHL-002',
        'name' => 'Tubeless Tire Conversion & Sealant Fill',
        'description' => 'Rim bed tape application, tubeless valve installation, bead seating, and sealant injection.',
        'category' => 'Wheel & Tire Services',
        'image' => 'assets/images/categories/category_parts.jpg',
        'price' => 550.00
    ],
    [
        'sku' => 'SERV-MNT-001',
        'name' => 'Custom Bikepacking Rack & Bag Mount Fitting',
        'description' => 'Custom bracket alignment and secure mounting of front/rear cargo racks, bottle cages, and bikepacking kits.',
        'category' => 'Custom Component Mounts',
        'image' => 'assets/images/categories/category_attachments.jpg',
        'price' => 750.00
    ]
];

foreach ($services as $s) {
    $scId = $servCatMap[$s['category']] ?? 1;
    $chk = mysqli_prepare($conn, "SELECT serviceID FROM service WHERE sku = ? AND endDate IS NULL LIMIT 1");
    mysqli_stmt_bind_param($chk, 's', $s['sku']);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);

    if (mysqli_stmt_num_rows($chk) === 0) {
        $ins = mysqli_prepare($conn, "INSERT INTO service (sku, name, description, serviceCategoryID, image, price, startDate, endDate) VALUES (?, ?, ?, ?, ?, ?, NOW(), NULL)");
        mysqli_stmt_bind_param($ins, 'sssiss', $s['sku'], $s['name'], $s['description'], $scId, $s['image'], $s['price']);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
        echo "Inserted service: {$s['name']} ({$s['sku']})\n";
    } else {
        echo "Service exists: {$s['sku']}\n";
    }
    mysqli_stmt_close($chk);
}

echo "Seeding completed successfully.\n";
