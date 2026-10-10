<?php
include('./includes/config.php');

if (!$conn) {
    die("Database connection failed. Please ensure MySQL is running in XAMPP.\n");
}

echo "=========================================================\n";
echo "   PedalWorks Dynamics: Clean Catalog Seeder\n";
echo "   (Strictly using provided products & services)\n";
echo "=========================================================\n\n";

// Disable foreign key checks for clean wipe of catalog tables
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
mysqli_query($conn, "DELETE FROM customerCart");
mysqli_query($conn, "DELETE FROM orderLineProduct");
mysqli_query($conn, "DELETE FROM customerService");
mysqli_query($conn, "DELETE FROM product");
mysqli_query($conn, "DELETE FROM service");
mysqli_query($conn, "ALTER TABLE product AUTO_INCREMENT = 1");
mysqli_query($conn, "ALTER TABLE service AUTO_INCREMENT = 1");
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

// Ensure required categories exist
$productCategories = [
    'Road Bikes',
    'Mountain Bikes',
    'Gravel Bikes',
    'Mamachari Bikes',
    'Folding Bikes',
    'Bikes for Kids',
    'Fixies',
    'BMX',
    'Frames'
];

foreach ($productCategories as $pc) {
    $cStmt = mysqli_prepare($conn, "INSERT IGNORE INTO productCategory (name) VALUES (?)");
    mysqli_stmt_bind_param($cStmt, 's', $pc);
    mysqli_stmt_execute($cStmt);
    mysqli_stmt_close($cStmt);
}

$serviceCategories = [
    'Bicycle Repair',
    'Cleaning & Detailing',
    'Parts Installation',
    'Custom Bike Building',
    'Frame Painting'
];

foreach ($serviceCategories as $sc) {
    $scStmt = mysqli_prepare($conn, "INSERT IGNORE INTO serviceCategory (name) VALUES (?)");
    mysqli_stmt_bind_param($scStmt, 's', $sc);
    mysqli_stmt_execute($scStmt);
    mysqli_stmt_close($scStmt);
}

// Fetch category maps
$catMap = [];
$res = mysqli_query($conn, "SELECT productCategoryID, name FROM productCategory");
while ($r = mysqli_fetch_assoc($res)) {
    $catMap[$r['name']] = (int)$r['productCategoryID'];
}

$servCatMap = [];
$res = mysqli_query($conn, "SELECT serviceCategoryID, name FROM serviceCategory");
while ($r = mysqli_fetch_assoc($res)) {
    $servCatMap[$r['name']] = (int)$r['serviceCategoryID'];
}

// ========================================================
// PRODUCTS LIST (Strictly from the provided products document)
// ========================================================
$products = [
    // --- Road Bikes ---
    [
        'sku' => 'PROD-ROAD-001',
        'name' => 'Wonder Wheels Road Bike 700c Aluminum Frame',
        'description' => 'Wonder Wheels Road Bike 700C 53Cm Aluminum Frame Matte Black, Shimano TX-35 7 Speed, Alloy Black Rims, Black Spokes 700C*1.5*14G*32H, Tire: Black.',
        'category' => 'Road Bikes',
        'image' => 'assets/images/image6.png',
        'price' => 28289.00,
        'stock' => 10,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-ROAD-002',
        'name' => 'ROCKBROS Road Bike 700C 2*10 Speeds Disc Brake',
        'description' => 'ROCKBROS Road Bike 700C 2*10 Speeds Disc Brake Bike Aluminum Alloy Frame Internal Cable Light Weight Adults Road Bike for 160-190cm Height.',
        'category' => 'Road Bikes',
        'image' => 'assets/images/image17.png',
        'price' => 64366.35,
        'stock' => 5,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-ROAD-003',
        'name' => 'Garuda SpearowAero Roadbike 700c 21 Speed',
        'description' => 'Garuda SpearowAero Roadbike 700c Aluminum Frame 3x7 21 Speed Shimano 30 Direct Dial with Carinside Line and Special Horn Brake.',
        'category' => 'Road Bikes',
        'image' => 'assets/images/image16.png',
        'price' => 6499.00,
        'stock' => 15,
        'lowStockThreshold' => 4
    ],
    [
        'sku' => 'PROD-ROAD-004',
        'name' => 'Raleigh | Ultra-light Variable Speed Bicycle',
        'description' => 'Raleigh ultra-light variable speed performance road bicycle designed for speed and endurance.',
        'category' => 'Road Bikes',
        'image' => 'assets/images/image28.png',
        'price' => 23687.47,
        'stock' => 8,
        'lowStockThreshold' => 2
    ],

    // --- Mountain Bikes ---
    [
        'sku' => 'PROD-MTB-001',
        'name' => 'AENXRD Mountain Bike 24/26 inch 21 SPEED',
        'description' => 'AENXRD Mountain Bike 24/26 inch 21 SPEED Adult Bicycles Bike High Carbon Steel Road Bicycle Aluminum alloy frame.',
        'category' => 'Mountain Bikes',
        'image' => 'assets/images/image35.png',
        'price' => 4699.00,
        'stock' => 18,
        'lowStockThreshold' => 5
    ],
    [
        'sku' => 'PROD-MTB-002',
        'name' => '2026 Trinx M134 24" Mechanical Alloy Mountain Bike',
        'description' => '2026 Trinx M134 24" Mechanical Alloy Mountain Bike with suspension fork and durable trail tires.',
        'category' => 'Mountain Bikes',
        'image' => 'assets/images/image34.png',
        'price' => 8900.00,
        'stock' => 12,
        'lowStockThreshold' => 4
    ],
    [
        'sku' => 'PROD-MTB-003',
        'name' => 'Black Mamba 26 & 27.5 inches Steel Mountain Bike Cycling',
        'description' => 'Black Mamba 26 & 27.5 inches Steel Mountain Bike Cycling built for reliable all-terrain riding.',
        'category' => 'Mountain Bikes',
        'image' => 'assets/images/image29.png',
        'price' => 3679.00,
        'stock' => 20,
        'lowStockThreshold' => 5
    ],
    [
        'sku' => 'PROD-MTB-004',
        'name' => 'ANEXRD R12 Mountain Bike 24/26 INCH 21 SPEED',
        'description' => 'ANEXRD R12 Mountain Bike 24/26 INCH 21 SPEED Adult Bike With disc brakes & Gear shifting Kids Bike High Carbon Steel with Fork Shock absorbing front.',
        'category' => 'Mountain Bikes',
        'image' => 'assets/images/image25.png',
        'price' => 4199.00,
        'stock' => 14,
        'lowStockThreshold' => 4
    ],

    // --- Gravel Bikes ---
    [
        'sku' => 'PROD-GRVL-001',
        'name' => 'TOSEEK TARGA 1.0 2022 All New Road Bike/CX 700c',
        'description' => 'TOSEEK TARGA 1.0 2022 All New Toseek Road Bike/CX 700c with performance cyclocross and gravel geometry.',
        'category' => 'Gravel Bikes',
        'image' => 'assets/images/image21.png',
        'price' => 14499.00,
        'stock' => 7,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-GRVL-002',
        'name' => '700c Gravel Bike, High Carbon Steel Frame 7 speed',
        'description' => '700c Gravel Bike, High Carbon Steel Frame, 700 x 28c 7:27, Front and Rear Disc Brakes, Drop Handlebars, Ideal for Beginners, Lightweight and Comfortable City Bike 7 speed, Gray.',
        'category' => 'Gravel Bikes',
        'image' => 'assets/images/image13.png',
        'price' => 19389.00,
        'stock' => 9,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-GRVL-003',
        'name' => 'Cube Nuroad Pro FE Gravel Bike',
        'description' => 'Cube Nuroad Pro FE Gravel Bike fully equipped with adventure touring components and disc brakes.',
        'category' => 'Gravel Bikes',
        'image' => 'assets/images/image22.png',
        'price' => 37556.96,
        'stock' => 5,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-GRVL-004',
        'name' => 'dagger | Oil Brake Through-Axle Gravel Bike',
        'description' => 'dagger | Oil Brake Through-Axle Gravel Bike with hydraulic braking and high-stiffness through-axles.',
        'category' => 'Gravel Bikes',
        'image' => 'assets/images/image20.png',
        'price' => 39088.75,
        'stock' => 6,
        'lowStockThreshold' => 2
    ],

    // --- Mamachari Bikes ---
    [
        'sku' => 'PROD-MAMA-001',
        'name' => 'Bicycle City Cycle Mama-chari cyma Punk Rock 27" ATM012',
        'description' => 'Bicycle City Cycle Mama-chari cyma Punk Rock 27" ATM012 with step-through commuter frame and front basket.',
        'category' => 'Mamachari Bikes',
        'image' => 'assets/images/image30.png',
        'price' => 18631.00,
        'stock' => 11,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-MAMA-002',
        'name' => 'City Cycle Mamachari 26-inch Ladies\' Bicycle with Basket',
        'description' => 'City Cycle Mamachari 26-inch Ladies\' Bicycle with Basket and Carrier, Thick Tires, Commuting to Work or School, Riding in Town, Going out, New Life, School Entrance Celebration, Simple Design.',
        'category' => 'Mamachari Bikes',
        'image' => 'assets/images/image36.png',
        'price' => 12782.00,
        'stock' => 13,
        'lowStockThreshold' => 4
    ],
    [
        'sku' => 'PROD-MAMA-003',
        'name' => 'City Cycle Mamachari 24/26 inch Bicycle V-Brake with Basket',
        'description' => 'City Cycle Mamachari 24/26 inch Bicycle, City Car, Women\'s Car, V-Brake, Basket Included, Basket, Thick Tires, Commuting to Work or School, City Riding, Going, New Life, Popular Gift.',
        'category' => 'Mamachari Bikes',
        'image' => 'assets/images/image23.png',
        'price' => 11794.00,
        'stock' => 10,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-MAMA-004',
        'name' => 'City Cycle Mamachari 24 / 26 Inch 6 Speed Bike with Dynamo LED',
        'description' => 'City Cycle Mamachari 24 / 26 Inch 6 Speed Bike with Basket with Key Dynamo LED Light Mud Flaps with Carrier Included, Popular, Fashionable, For Commuting to Work or School, City Rides Outings.',
        'category' => 'Mamachari Bikes',
        'image' => 'assets/images/image7.png',
        'price' => 20655.00,
        'stock' => 8,
        'lowStockThreshold' => 2
    ],

    // --- Folding Bikes ---
    [
        'sku' => 'PROD-FOLD-001',
        'name' => 'Bickerton Junction 1507 (20in Folding Bike)',
        'description' => 'Bickerton Junction 1507 (20in Folding Bike) precision engineered with premium folding latches and luggage rack.',
        'category' => 'Folding Bikes',
        'image' => 'assets/images/image26.png',
        'price' => 23500.00,
        'stock' => 9,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-FOLD-002',
        'name' => 'Tern Link B8',
        'description' => 'Tern Link B8 versatile folding commuter bike with 8-speed wide gearing and disc brakes.',
        'category' => 'Folding Bikes',
        'image' => 'assets/images/image24.png',
        'price' => 29850.00,
        'stock' => 7,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-FOLD-003',
        'name' => 'Folding Bike Tilt 120 ADJ 20 inch 6 speed',
        'description' => 'Folding Bike Tilt 120 ADJ 20 inch 6 speed with fast folding mechanism and compact footprint.',
        'category' => 'Folding Bikes',
        'image' => 'assets/images/image2.png',
        'price' => 14990.00,
        'stock' => 12,
        'lowStockThreshold' => 4
    ],
    [
        'sku' => 'PROD-FOLD-004',
        'name' => 'Shibomei 16-inch 20-inch folding bicycle',
        'description' => 'Shibomei 16-inch 20-inch folding bicycle student adult light ultra-light portable variable speed small bicycle for men and women.',
        'category' => 'Folding Bikes',
        'image' => 'assets/images/image14.png',
        'price' => 12025.00,
        'stock' => 14,
        'lowStockThreshold' => 4
    ],

    // --- Bikes for Kids ---
    [
        'sku' => 'PROD-KIDS-001',
        'name' => 'Maru Minato 4.0 Kids 14" Junior Series Bike',
        'description' => 'Maru Minato 4.0 Kids 14" Junior Series Bike with training wheels and chain protection guard.',
        'category' => 'Bikes for Kids',
        'image' => 'assets/images/image8.png',
        'price' => 4500.00,
        'stock' => 15,
        'lowStockThreshold' => 4
    ],
    [
        'sku' => 'PROD-KIDS-002',
        'name' => 'RoyalBaby Freestyle',
        'description' => 'RoyalBaby Freestyle durable children bicycle with water bottle and sturdy spoked wheels.',
        'category' => 'Bikes for Kids',
        'image' => 'assets/images/image33.png',
        'price' => 4500.00,
        'stock' => 16,
        'lowStockThreshold' => 5
    ],
    [
        'sku' => 'PROD-KIDS-003',
        'name' => 'BMX Bicycle Mongoose/HARO',
        'description' => 'BMX Bicycle Mongoose/HARO style junior bicycle designed for neighborhood cruising and jumps.',
        'category' => 'Bikes for Kids',
        'image' => 'assets/images/image27.png',
        'price' => 3733.00,
        'stock' => 11,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-KIDS-004',
        'name' => 'Kiumo Kids Bike 12/14/16 Inch Foldable Bicycle',
        'description' => 'Kiumo Kids Bike 12/14/16 Inch Foldable Bicycle with Training Wheels, Adjustable Seat, Rubber Tires & Front Basket.',
        'category' => 'Bikes for Kids',
        'image' => 'assets/images/image15.png',
        'price' => 2399.00,
        'stock' => 18,
        'lowStockThreshold' => 5
    ],

    // --- Fixies ---
    [
        'sku' => 'PROD-FIX-001',
        'name' => 'Garuda Fixie Bike Fixed Gear steel bike',
        'description' => 'Garuda Fixie Bike Fixed Gear steel bike with classic track dropouts and minimalist design.',
        'category' => 'Fixies',
        'image' => 'assets/images/image1.png',
        'price' => 3899.00,
        'stock' => 12,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-FIX-002',
        'name' => 'Garuda Hurracane Steel Fixed Gear Bicycle',
        'description' => 'Garuda Hurracane Steel Fixed Gear Bicycle built for fast responsive city riding.',
        'category' => 'Fixies',
        'image' => 'assets/images/image19.png',
        'price' => 5999.00,
        'stock' => 10,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-FIX-003',
        'name' => 'BETTA HELLBOY FIXIE AERO 700x25C',
        'description' => 'BETTA HELLBOY FIXIE AERO 700x25C aerodynamic track bike with aero frame tubing.',
        'category' => 'Fixies',
        'image' => 'assets/images/image18.png',
        'price' => 8999.00,
        'stock' => 8,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-FIX-004',
        'name' => 'PROMAX PF-30 FIXIE',
        'description' => 'PROMAX PF-30 FIXIE lightweight urban fixed gear track bicycle.',
        'category' => 'Fixies',
        'image' => 'assets/images/image31.png',
        'price' => 6899.00,
        'stock' => 9,
        'lowStockThreshold' => 3
    ],

    // --- BMX ---
    [
        'sku' => 'PROD-BMX-001',
        'name' => 'BMX 20 PUNISHER AMBUSH STREET DESIGN O/S THREADLESS AHEAD SEMI ASSEMBLED',
        'description' => 'BMX 20 PUNISHER AMBUSH STREET DESIGN O/S THREADLESS AHEAD SEMI ASSEMBLED stunt bike.',
        'category' => 'BMX',
        'image' => 'assets/images/image4.png',
        'price' => 4338.00,
        'stock' => 14,
        'lowStockThreshold' => 4
    ],
    [
        'sku' => 'PROD-BMX-002',
        'name' => 'Maru Seido BMX 20" Version 3.0',
        'description' => 'Maru Seido BMX 20" Version 3.0 freestyle street and park stunt bicycle.',
        'category' => 'BMX',
        'image' => 'assets/images/image9.png',
        'price' => 5940.00,
        'stock' => 10,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-BMX-003',
        'name' => 'Sushida MTB 26" FATBIKE Mountain Bike 26x4.0',
        'description' => 'Sushida MTB 26" FATBIKE Mountain Bike 26x4.0 with ultra wide all-terrain tires.',
        'category' => 'BMX',
        'image' => 'assets/images/image5.png',
        'price' => 5699.00,
        'stock' => 7,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-BMX-004',
        'name' => 'Calum X-Man Alloy Frame Bmx 20" w rotor',
        'description' => 'Calum X-Man Alloy Frame Bmx 20" w rotor for 360 bar rotation freestyle riding.',
        'category' => 'BMX',
        'image' => 'assets/images/image10.png',
        'price' => 8195.00,
        'stock' => 6,
        'lowStockThreshold' => 2
    ],

    // --- Frames ---
    [
        'sku' => 'PROD-FRM-001',
        'name' => 'Ryder X9 Frame (Thru-Axle Type)',
        'description' => 'Ryder X9 Frame (Thru-Axle Type) hardtail mountain bike frame with boost spacing.',
        'category' => 'Frames',
        'image' => 'assets/images/image12.png',
        'price' => 6400.00,
        'stock' => 8,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-FRM-002',
        'name' => 'MAXZONE CHROMOLY 1.0 FRAME',
        'description' => 'MAXZONE CHROMOLY 1.0 FRAME classic durable steel bicycle frame.',
        'category' => 'Frames',
        'image' => 'assets/images/image32.png',
        'price' => 2500.00,
        'stock' => 12,
        'lowStockThreshold' => 3
    ],
    [
        'sku' => 'PROD-FRM-003',
        'name' => 'KEMEKE | Mountain All-Mountain/Cross Country Bike Frame',
        'description' => 'KEMEKE | Mountain All-Mountain/Cross Country Bike Frame alloy cross country lightweight frame.',
        'category' => 'Frames',
        'image' => 'assets/images/image3.png',
        'price' => 10825.74,
        'stock' => 5,
        'lowStockThreshold' => 2
    ],
    [
        'sku' => 'PROD-FRM-004',
        'name' => 'Ryder Carbon Frame (27.5in Tires w/ 12 x 142mm TA)',
        'description' => 'Ryder Carbon Frame (27.5in Tires w/ 12 x 142mm TA) ultra-lightweight carbon fiber frame.',
        'category' => 'Frames',
        'image' => 'assets/images/image11.png',
        'price' => 13000.00,
        'stock' => 4,
        'lowStockThreshold' => 2
    ]
];

echo "Inserting Products (" . count($products) . " items)...\n";
$insProd = mysqli_prepare($conn, "INSERT INTO product (sku, name, description, productCategoryID, image, price, stock, lowStockThreshold, startDate, endDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)");

foreach ($products as $p) {
    $catId = $catMap[$p['category']] ?? 1;
    mysqli_stmt_bind_param($insProd, 'sssissdi', $p['sku'], $p['name'], $p['description'], $catId, $p['image'], $p['price'], $p['stock'], $p['lowStockThreshold']);
    mysqli_stmt_execute($insProd);
    echo "  [+] {$p['sku']} — {$p['name']} (PHP {$p['price']})\n";
}
mysqli_stmt_close($insProd);

// ========================================================
// SERVICES LIST (Strictly the 6 services from products.html)
// ========================================================
$services = [
    [
        'sku' => 'SERV-REP-001',
        'name' => 'Bike Repair w/ Cleaning',
        'description' => 'Mechanical repair diagnostics and repair service combined with complete cleaning.',
        'category' => 'Bicycle Repair',
        'image' => 'assets/images/categories/category_parts.jpg',
        'price' => 250.00
    ],
    [
        'sku' => 'SERV-REP-002',
        'name' => 'Bike Repair',
        'description' => 'Standard mechanical bike repair and adjustment service.',
        'category' => 'Bicycle Repair',
        'image' => 'assets/images/categories/category_parts.jpg',
        'price' => 150.00
    ],
    [
        'sku' => 'SERV-CLN-001',
        'name' => 'Cleaning',
        'description' => 'Full frame and drivetrain degreasing and bike wash.',
        'category' => 'Cleaning & Detailing',
        'image' => 'assets/images/categories/category_safety_gear.jpg',
        'price' => 100.00
    ],
    [
        'sku' => 'SERV-INS-001',
        'name' => 'Parts Installment',
        'description' => 'Installation service for individual replacement or upgrade components (PHP 50 per part).',
        'category' => 'Parts Installation',
        'image' => 'assets/images/categories/category_attachments.jpg',
        'price' => 50.00
    ],
    [
        'sku' => 'SERV-BLD-001',
        'name' => 'Full Bike Building',
        'description' => 'Complete ground-up assembly of full build bikes from boxed or custom parts.',
        'category' => 'Custom Bike Building',
        'image' => 'assets/images/categories/category_bikes.jpg',
        'price' => 300.00
    ],
    [
        'sku' => 'SERV-PNT-001',
        'name' => 'Re-paint',
        'description' => 'Frame surface preparation and full custom re-painting service.',
        'category' => 'Frame Painting',
        'image' => 'assets/images/categories/category_bikes.jpg',
        'price' => 500.00
    ]
];

echo "\nInserting Services (" . count($services) . " items)...\n";
$insServ = mysqli_prepare($conn, "INSERT INTO service (sku, name, description, serviceCategoryID, image, price, startDate, endDate) VALUES (?, ?, ?, ?, ?, ?, NOW(), NULL)");

foreach ($services as $s) {
    $scId = $servCatMap[$s['category']] ?? 1;
    mysqli_stmt_bind_param($insServ, 'sssiss', $s['sku'], $s['name'], $s['description'], $scId, $s['image'], $s['price']);
    mysqli_stmt_execute($insServ);
    echo "  [+] {$s['sku']} — {$s['name']} (PHP {$s['price']})\n";
}
mysqli_stmt_close($insServ);

echo "\n=========================================================\n";
echo "   Catalog reset & seeded with exact provided data!\n";
echo "   Total Products: " . count($products) . "\n";
echo "   Total Services: " . count($services) . "\n";
echo "=========================================================\n";
