<?php
/**
 * PedalWorks Dynamics: Catalog Seeder
 *
 * Source: PWD_products-compressed.pdf. Every product uses the exact title
 * and price printed in the document, plus its own image from it
 * (assets/images/products/<sku>.jpg).
 *
 * Categories are the four shop groups: Full Build Bikes, Bicycle Parts,
 * Attachments and Safety Gear. Each product is in exactly one, so filtering
 * by a category shows everything that belongs to it.
 *
 * WARNING: this wipes and rebuilds products, services and both category
 * tables. If any sales exist it refuses to run unless you pass --force
 * (CLI) or ?force=1 (browser); with --force, sales and service requests are
 * deleted as well.
 *
 * Run:  php seed_catalog.php        (add --force to wipe existing sales)
 */
include('./includes/config.php');

if (!$conn) {
    die("Database connection failed. Please ensure MySQL is running in XAMPP.\n");
}

$force = (PHP_SAPI === 'cli' && in_array('--force', $argv ?? [], true))
      || (PHP_SAPI !== 'cli' && ($_GET['force'] ?? '') === '1');

echo "=========================================================\n";
echo "   PedalWorks Dynamics: Catalog Seeder\n";
echo "   (exact titles, prices and images from the PDF)\n";
echo "=========================================================\n\n";

$salesRes = mysqli_query($conn, "SELECT COUNT(*) AS n FROM productSale");
$salesCount = $salesRes ? (int)mysqli_fetch_assoc($salesRes)['n'] : 0;
if ($salesCount > 0 && !$force) {
    die("Aborted: {$salesCount} sale(s) already exist and re-seeding would delete them. Re-run with --force to wipe everything.\n");
}

// Clean wipe (foreign keys off so the order does not matter)
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
foreach (['customerCart', 'orderLineProduct', 'customerService', 'productSale',
          'product', 'service', 'productCategory', 'serviceCategory'] as $t) {
    mysqli_query($conn, "DELETE FROM `$t`");
    mysqli_query($conn, "ALTER TABLE `$t` AUTO_INCREMENT = 1");
}
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

// --------------------------------------------------------
// Categories
// --------------------------------------------------------
$productCategories = [
    'Full Build Bikes',
    'Bicycle Parts',
    'Attachments',
    'Safety Gear'
];

$cStmt = mysqli_prepare($conn, "INSERT INTO productCategory (name) VALUES (?)");
foreach ($productCategories as $pc) {
    mysqli_stmt_bind_param($cStmt, 's', $pc);
    mysqli_stmt_execute($cStmt);
}
mysqli_stmt_close($cStmt);

$serviceCategories = [
    'Bicycle Repair',
    'Cleaning & Detailing',
    'Parts Installation',
    'Custom Bike Building',
    'Frame Painting'
];
$scStmt = mysqli_prepare($conn, "INSERT INTO serviceCategory (name) VALUES (?)");
foreach ($serviceCategories as $sc) {
    mysqli_stmt_bind_param($scStmt, 's', $sc);
    mysqli_stmt_execute($scStmt);
}
mysqli_stmt_close($scStmt);

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

// --------------------------------------------------------
// PRODUCTS: [sku, name, description, category, image, price, stock, lowStockThreshold]
// name = the PDF title (cut at 100 characters if longer, as product.name is VARCHAR(100));
// description = the full PDF title, unchanged.
// Stock figures for the parts, attachments and safety gear are placeholders.
// --------------------------------------------------------
$products = [

    // --- Full Build Bikes / Road Bikes ---
    ['PROD-ROAD-001', 'Wonder Wheels Road Bike 700c Aluminum Frame Shimano Tx-35 7 Speed Alloy Black Rims...', 'Wonder Wheels Road Bike 700c Aluminum Frame Shimano Tx-35 7 Speed Alloy Black Rims 700c*1.5*14g*32 Wonder Wheels Road Bike 700C 53Cm Aluminum Frame Matte Black, Shimano Tx-35 7 Speed Alloy Black Rims, Black Spokes 700C*1.5*14G*32H Tire: Black,', 'Full Build Bikes', 'assets/images/products/wonder-wheels-road-bike-700c-aluminum-frame-shimano-tx-35-7-speed-alloy-black.jpg', 28289.00, 10, 3],
    ['PROD-ROAD-002', 'ROCKBROS Road Bike 700C 2*10 Speeds Disc Brake Bike Aluminum Alloy Frame Internal Cable Light...', 'ROCKBROS Road Bike 700C 2*10 Speeds Disc Brake Bike Aluminum Alloy Frame Internal Cable Light Weight Adults Road Bike for 160-190cm He', 'Full Build Bikes', 'assets/images/products/rockbros-road-bike-700c-2-10-speeds-disc-brake-bike-aluminum-alloy-frame.jpg', 64366.35, 5, 2],
    ['PROD-ROAD-003', 'Garuda SpearowAero Roadbike 700c Aluminum Frame 3x7 21 Speed Shimano 30 Direct Dial with...', 'Garuda SpearowAero Roadbike 700c Aluminum Frame 3x7 21 Speed Shimano 30 Direct Dial with Carinside Line and Special Horn Brake', 'Full Build Bikes', 'assets/images/products/garuda-spearowaero-roadbike-700c-aluminum-frame-3x7-21-speed-shimano-30-direct.jpg', 6499.00, 15, 4],
    ['PROD-ROAD-004', 'Raleigh | Ultra-light Variable Speed Bicycle', 'Raleigh | Ultra-light Variable Speed Bicycle', 'Full Build Bikes', 'assets/images/products/raleigh-ultra-light-variable-speed-bicycle.jpg', 23687.47, 8, 2],

    // --- Full Build Bikes / Mountain Bikes ---
    ['PROD-MTB-001', 'AENXRD Mountain Bike 24/26 inch 21 SPEED Adult Bicycles Bike High Carbon Steel Road Bicycle...', 'AENXRD Mountain Bike 24/26 inch 21 SPEED Adult Bicycles Bike High Carbon Steel Road Bicycle Aluminum alloy frame', 'Full Build Bikes', 'assets/images/products/aenxrd-mountain-bike-24-26-inch-21-speed-adult-bicycles-bike-high-carbon-steel.jpg', 4699.00, 18, 5],
    ['PROD-MTB-002', '2026 Trinx M134 24" Mechanical Alloy Mountain Bike', '2026 Trinx M134 24" Mechanical Alloy Mountain Bike', 'Full Build Bikes', 'assets/images/products/2026-trinx-m134-24-mechanical-alloy-mountain-bike.jpg', 8900.00, 12, 4],
    ['PROD-MTB-003', 'Black Mamba 26 & 27.5 inches Steel Mountain Bike Cycling', 'Black Mamba 26 & 27.5 inches Steel Mountain Bike Cycling', 'Full Build Bikes', 'assets/images/products/black-mamba-26-27-5-inches-steel-mountain-bike-cycling.jpg', 3679.00, 20, 5],
    ['PROD-MTB-004', 'SANEXRD R12 Mountain Bike 24/26 INCH 21 SPEED Adult Bike With disc brakes & Gear shifting Kids...', 'SANEXRD R12 Mountain Bike 24/26 INCH 21 SPEED Adult Bike With disc brakes & Gear shifting Kids Bike High Carbon Steel with Fork Shock absorbing front', 'Full Build Bikes', 'assets/images/products/sanexrd-r12-mountain-bike-24-26-inch-21-speed-adult-bike-with-disc-brakes-gear.jpg', 4199.00, 14, 4],

    // --- Full Build Bikes / Gravel Bikes ---
    ['PROD-GRVL-001', 'TOSEEK TARGA 1.0 2022 All New Toseek Road Bike/CX 700c', 'TOSEEK TARGA 1.0 2022 All New Toseek Road Bike/CX 700c', 'Full Build Bikes', 'assets/images/products/toseek-targa-1-0-2022-all-new-toseek-road-bike-cx-700c.jpg', 14499.00, 7, 2],
    ['PROD-GRVL-002', '700c Gravel Bike, High Carbon Steel Frame, 700 x 28c 7:27, Front and Rear Disc Brakes, Drop...', '700c Gravel Bike, High Carbon Steel Frame, 700 x 28c 7:27, Front and Rear Disc Brakes, Drop Handlebars, Ideal for Beginners, Lightweight and Comfortable City Bike 7 speed, Gray', 'Full Build Bikes', 'assets/images/products/700c-gravel-bike-high-carbon-steel-frame-700-x-28c-7-27-front-and-rear-disc.jpg', 19389.00, 9, 3],
    ['PROD-GRVL-003', 'Cube Nuroad Pro FE Gravel Bike', 'Cube Nuroad Pro FE Gravel Bike', 'Full Build Bikes', 'assets/images/products/cube-nuroad-pro-fe-gravel-bike.jpg', 37556.96, 5, 2],
    ['PROD-GRVL-004', 'dagger | Oil Brake Through-Axle Gravel Bike', 'dagger | Oil Brake Through-Axle Gravel Bike', 'Full Build Bikes', 'assets/images/products/dagger-oil-brake-through-axle-gravel-bike.jpg', 39088.75, 6, 2],

    // --- Full Build Bikes / Mamachari Bikes ---
    ['PROD-MAMA-001', 'Bicycle City Cycle Mama-chari cyma Punk Rock 27" ATM012', 'Bicycle City Cycle Mama-chari cyma Punk Rock 27" ATM012', 'Full Build Bikes', 'assets/images/products/bicycle-city-cycle-mama-chari-cyma-punk-rock-27-atm012.jpg', 18631.00, 11, 3],
    ['PROD-MAMA-002', 'City Cycle Mamachari 26-inch Ladies\' Bicycle with Basket and Carrier, Thick Tires, Commuting to...', 'City Cycle Mamachari 26-inch Ladies\' Bicycle with Basket and Carrier, Thick Tires, Commuting to Work or School, Riding in Town, Going out, New Life, School Entrance Celebration, Simple Design', 'Full Build Bikes', 'assets/images/products/city-cycle-mamachari-26-inch-ladies-bicycle-with-basket-and-carrier-thick-tires.jpg', 12782.00, 13, 4],
    ['PROD-MAMA-003', 'City Cycle Mamachari 24/26 inch Bicycle, City Car, Women\'s Car, V-Brake, Basket Included,...', 'City Cycle Mamachari 24/26 inch Bicycle, City Car, Women\'s Car, V-Brake, Basket Included, Basket, Thick Tires, Commuting to Work or School, City Riding, Going, New Life, Popular Gift', 'Full Build Bikes', 'assets/images/products/city-cycle-mamachari-24-26-inch-bicycle-city-car-women-s-car-v-brake-basket.jpg', 11794.00, 10, 3],
    ['PROD-MAMA-004', 'City Cycle Mamachari 24 / 26 Inch 6 Speed Bike with Basket with Key Dynamo LED Light Mud Flaps...', 'City Cycle Mamachari 24 / 26 Inch 6 Speed Bike with Basket with Key Dynamo LED Light Mud Flaps with Carrier Included, Popular, Fashionable, For Commuting to Work or School, City Rides Outings', 'Full Build Bikes', 'assets/images/products/city-cycle-mamachari-24-26-inch-6-speed-bike-with-basket-with-key-dynamo-led.jpg', 20655.00, 8, 2],

    // --- Full Build Bikes / Folding Bikes ---
    ['PROD-FOLD-001', 'Bickerton Junction 1507 (20in Folding Bike)', 'Bickerton Junction 1507 (20in Folding Bike)', 'Full Build Bikes', 'assets/images/products/bickerton-junction-1507-20in-folding-bike.jpg', 23500.00, 9, 3],
    ['PROD-FOLD-002', 'Tern Link B8', 'Tern Link B8', 'Full Build Bikes', 'assets/images/products/tern-link-b8.jpg', 29850.00, 7, 2],
    ['PROD-FOLD-003', 'Folding Bike Tilt 120 ADJ 20 inch 6 speed', 'Folding Bike Tilt 120 ADJ 20 inch 6 speed', 'Full Build Bikes', 'assets/images/products/folding-bike-tilt-120-adj-20-inch-6-speed.jpg', 14990.00, 12, 4],
    ['PROD-FOLD-004', 'Shibomei 16-inch 20-inch folding bicycle student adult light ultra-light portable variable speed...', 'Shibomei 16-inch 20-inch folding bicycle student adult light ultra-light portable variable speed small bicycle for men and women', 'Full Build Bikes', 'assets/images/products/shibomei-16-inch-20-inch-folding-bicycle-student-adult-light-ultra-light.jpg', 12025.00, 14, 4],

    // --- Full Build Bikes / Bikes for Kids ---
    ['PROD-KIDS-001', 'Maru Minato 4.0 Kids 14" Junior Series Bike', 'Maru Minato 4.0 Kids 14" Junior Series Bike', 'Full Build Bikes', 'assets/images/products/maru-minato-4-0-kids-14-junior-series-bike.jpg', 4500.00, 15, 4],
    ['PROD-KIDS-002', 'RoyalBaby Freestyle', 'RoyalBaby Freestyle', 'Full Build Bikes', 'assets/images/products/royalbaby-freestyle.jpg', 4500.00, 16, 5],
    ['PROD-KIDS-003', 'BMX Bicycle Mongoose/HARO', 'BMX Bicycle Mongoose/HARO', 'Full Build Bikes', 'assets/images/products/bmx-bicycle-mongoose-haro.jpg', 3733.00, 11, 3],
    ['PROD-KIDS-004', 'Kiumo Kids Bike 12/14/16 Inch Foldable Bicycle with Training Wheels, Adjustable Seat, Rubber...', 'Kiumo Kids Bike 12/14/16 Inch Foldable Bicycle with Training Wheels, Adjustable Seat, Rubber Tires & Front Basket', 'Full Build Bikes', 'assets/images/products/kiumo-kids-bike-12-14-16-inch-foldable-bicycle-with-training-wheels-adjustable.jpg', 2399.00, 18, 5],

    // --- Full Build Bikes / Fixies ---
    ['PROD-FIX-001', 'Garuda Fixie Bike Fixed Gear steel bike', 'Garuda Fixie Bike Fixed Gear steel bike', 'Full Build Bikes', 'assets/images/products/garuda-fixie-bike-fixed-gear-steel-bike.jpg', 3899.00, 12, 3],
    ['PROD-FIX-002', 'Garuda Hurracane Steel Fixed Gear Bicycle', 'Garuda Hurracane Steel Fixed Gear Bicycle', 'Full Build Bikes', 'assets/images/products/garuda-hurracane-steel-fixed-gear-bicycle.jpg', 5999.00, 10, 3],
    ['PROD-FIX-003', 'BETTA HELLBOY FIXIE AERO 700x25C', 'BETTA HELLBOY FIXIE AERO 700x25C', 'Full Build Bikes', 'assets/images/products/betta-hellboy-fixie-aero-700x25c.jpg', 8999.00, 8, 2],
    ['PROD-FIX-004', 'PROMAX PF-30 FIXIE', 'PROMAX PF-30 FIXIE', 'Full Build Bikes', 'assets/images/products/promax-pf-30-fixie.jpg', 6899.00, 9, 3],

    // --- Full Build Bikes / BMX ---
    ['PROD-BMX-001', 'BMX 20 PUNISHER AMBUSH STREET DESIGN O/S THREADLESS AHEAD SEMI ASSEMBLED', 'BMX 20 PUNISHER AMBUSH STREET DESIGN O/S THREADLESS AHEAD SEMI ASSEMBLED', 'Full Build Bikes', 'assets/images/products/bmx-20-punisher-ambush-street-design-o-s-threadless-ahead-semi-assembled.jpg', 4338.00, 14, 4],
    ['PROD-BMX-002', 'Maru Seido BMX 20" Version 3.0', 'Maru Seido BMX 20" Version 3.0', 'Full Build Bikes', 'assets/images/products/maru-seido-bmx-20-version-3-0.jpg', 5940.00, 10, 3],
    ['PROD-BMX-003', 'Sushida MTB 26" FATBIKE Mountain Bike 26x4.0', 'Sushida MTB 26" FATBIKE Mountain Bike 26x4.0', 'Full Build Bikes', 'assets/images/products/sushida-mtb-26-fatbike-mountain-bike-26x4-0.jpg', 5699.00, 7, 2],
    ['PROD-BMX-004', 'Calum X-Man Alloy Frame Bmx 20" w rotor', 'Calum X-Man Alloy Frame Bmx 20" w rotor', 'Full Build Bikes', 'assets/images/products/calum-x-man-alloy-frame-bmx-20-w-rotor.jpg', 8195.00, 6, 2],

    // --- Bicycle Parts / Frames ---
    ['PROD-FRM-001', 'Ryder X9 Frame (Thru-Axle Type)', 'Ryder X9 Frame (Thru-Axle Type)', 'Bicycle Parts', 'assets/images/products/ryder-x9-frame-thru-axle-type.jpg', 6400.00, 8, 2],
    ['PROD-FRM-002', 'MAXZONE CHROMOLY 1.0 FRAME', 'MAXZONE CHROMOLY 1.0 FRAME', 'Bicycle Parts', 'assets/images/products/maxzone-chromoly-1-0-frame.jpg', 2500.00, 12, 3],
    ['PROD-FRM-003', 'KEMEKE | Mountain All-Mountain/Cross Country Bike Frame', 'KEMEKE | Mountain All-Mountain/Cross Country Bike Frame', 'Bicycle Parts', 'assets/images/products/kemeke-mountain-all-mountain-cross-country-bike-frame.jpg', 10825.74, 5, 2],
    ['PROD-FRM-004', 'Ryder Carbon Frame (27.5in Tires w/ 12 x 142mm TA)', 'Ryder Carbon Frame (27.5in Tires w/ 12 x 142mm TA)', 'Bicycle Parts', 'assets/images/products/ryder-carbon-frame-27-5in-tires-w-12-x-142mm-ta.jpg', 13000.00, 4, 2],

    // --- Bicycle Parts / Forks ---
    ['PROD-FORK-001', 'BKAT Ragusa RB800 Lightweight Alloy Rigid Fork 700c Disc Brake Ready Road Bike Gravel Bike FORK', 'BKAT Ragusa RB800 Lightweight Alloy Rigid Fork 700c Disc Brake Ready Road Bike Gravel Bike FORK', 'Bicycle Parts', 'assets/images/products/bkat-ragusa-rb800-lightweight-alloy-rigid-fork-700c-disc-brake-ready-road-bike.jpg', 600.00, 25, 5],
    ['PROD-FORK-002', 'Bike MTB Fork Shock absorbing front fork metal 26 27.5 29 inches', 'Bike MTB Fork Shock absorbing front fork metal 26 27.5 29 inches', 'Bicycle Parts', 'assets/images/products/bike-mtb-fork-shock-absorbing-front-fork-metal-26-27-5-29-inches.jpg', 540.00, 25, 5],
    ['PROD-FORK-003', 'HASSNS Mountain Bike Fork Bicycle Front Fork MTB Rigid Fork Ultra Light Aluminum Alloy Front Fork', 'HASSNS Mountain Bike Fork Bicycle Front Fork MTB Rigid Fork Ultra Light Aluminum Alloy Front Fork', 'Bicycle Parts', 'assets/images/products/hassns-mountain-bike-fork-bicycle-front-fork-mtb-rigid-fork-ultra-light.jpg', 2300.00, 25, 5],
    ['PROD-FORK-004', 'ROCKRIDER Fork XCT 27.5" 80MM 1"1/8 V-Brake QR9MM', 'ROCKRIDER Fork XCT 27.5" 80MM 1"1/8 V-Brake QR9MM', 'Bicycle Parts', 'assets/images/products/rockrider-fork-xct-27-5-80mm-1-1-8-v-brake-qr9mm.jpg', 532.00, 25, 5],

    // --- Bicycle Parts / Handle Bars ---
    ['PROD-HBAR-001', 'Aluminum Alloy Bicycle Handlebar 31.8*720mm/handlebar stem 31.8mm', 'Aluminum Alloy Bicycle Handlebar 31.8*720mm/handlebar stem 31.8mm', 'Bicycle Parts', 'assets/images/products/aluminum-alloy-bicycle-handlebar-31-8-720mm-handlebar-stem-31-8mm.jpg', 200.00, 25, 5],
    ['PROD-HBAR-002', 'Sagmit CX Pro Drop Handlebars (420mm Gravel Bars)', 'Sagmit CX Pro Drop Handlebars (420mm Gravel Bars)', 'Bicycle Parts', 'assets/images/products/sagmit-cx-pro-drop-handlebars-420mm-gravel-bars.jpg', 800.00, 25, 5],
    ['PROD-HBAR-003', 'Truvativ HC-AM Drop Bars (420mm, Road)', 'Truvativ HC-AM Drop Bars (420mm, Road)', 'Bicycle Parts', 'assets/images/products/truvativ-hc-am-drop-bars-420mm-road.jpg', 600.00, 25, 5],
    ['PROD-HBAR-004', 'UNO Bicycle Handlebar Aluminium Alloy 6061-T6 Silver/Matte Black', 'UNO Bicycle Handlebar Aluminium Alloy 6061-T6 Silver/Matte Black', 'Bicycle Parts', 'assets/images/products/uno-bicycle-handlebar-aluminium-alloy-6061-t6-silver-matte-black.jpg', 830.87, 25, 5],

    // --- Bicycle Parts / Wheels ---
    ['PROD-WHL-001', 'Kenda Original 1153 1PCS Mountain Bike Tires 26*1.95/*2.1 27.5*1.95/2.1', 'Kenda Original 1153 1PCS Mountain Bike Tires 26*1.95/*2.1 27.5*1.95/2.1', 'Bicycle Parts', 'assets/images/products/kenda-original-1153-1pcs-mountain-bike-tires-26-1-95-2-1-27-5-1-95-2-1.jpg', 549.00, 25, 5],
    ['PROD-WHL-002', 'CST Bicycle Tires for 26/27.5/29 Road Mountain Bike 1.95/2.1 MTB Tire', 'CST Bicycle Tires for 26/27.5/29 Road Mountain Bike 1.95/2.1 MTB Tire', 'Bicycle Parts', 'assets/images/products/cst-bicycle-tires-for-26-27-5-29-road-mountain-bike-1-95-2-1-mtb-tire.jpg', 599.00, 25, 5],
    ['PROD-WHL-003', 'Maxxis Re-Fuse Folding MS', 'Maxxis Re-Fuse Folding MS', 'Bicycle Parts', 'assets/images/products/maxxis-re-fuse-folding-ms.jpg', 1400.00, 25, 5],
    ['PROD-WHL-004', 'Thickslick Bicycle Tires 700c', 'Thickslick Bicycle Tires 700c', 'Bicycle Parts', 'assets/images/products/thickslick-bicycle-tires-700c.jpg', 695.00, 25, 5],

    // --- Bicycle Parts / Rear Derailleurs ---
    ['PROD-RD-001', 'Shimano Sora R3000 Rear Derailleur', 'Shimano Sora R3000 Rear Derailleur', 'Bicycle Parts', 'assets/images/products/shimano-sora-r3000-rear-derailleur.jpg', 1500.00, 25, 5],
    ['PROD-RD-002', 'Shimano Tourney TY300 Rear Derailleur', 'Shimano Tourney TY300 Rear Derailleur', 'Bicycle Parts', 'assets/images/products/shimano-tourney-ty300-rear-derailleur.jpg', 900.00, 25, 5],
    ['PROD-RD-003', 'RD Tourney RD-TZ500 Rear Derailleur 6/7/8 Speed Direct Mount Upgraded RD-TZ50', 'RD Tourney RD-TZ500 Rear Derailleur 6/7/8 Speed Direct Mount Upgraded RD-TZ50', 'Bicycle Parts', 'assets/images/products/rd-tourney-rd-tz500-rear-derailleur-6-7-8-speed-direct-mount-upgraded-rd-tz50.jpg', 200.00, 25, 5],
    ['PROD-RD-004', 'Shimano SGS RD-M7100 Rear derailleur', 'Shimano SGS RD-M7100 Rear derailleur', 'Bicycle Parts', 'assets/images/products/shimano-sgs-rd-m7100-rear-derailleur.jpg', 1753.99, 25, 5],

    // --- Bicycle Parts / Front Derailleurs ---
    ['PROD-FD-001', 'SRAM Rival 22 Braze-on Front Derailleur', 'SRAM Rival 22 Braze-on Front Derailleur', 'Bicycle Parts', 'assets/images/products/sram-rival-22-braze-on-front-derailleur.jpg', 689.00, 25, 5],
    ['PROD-FD-002', 'Shimano FD-TY300 Tourney 6/7 Speed triple front derailleur', 'Shimano FD-TY300 Tourney 6/7 Speed triple front derailleur', 'Bicycle Parts', 'assets/images/products/shimano-fd-ty300-tourney-6-7-speed-triple-front-derailleur.jpg', 289.00, 25, 5],
    ['PROD-FD-003', 'SENSAH Multi-speed Compatible Front Derailleur', 'SENSAH Multi-speed Compatible Front Derailleur', 'Bicycle Parts', 'assets/images/products/sensah-multi-speed-compatible-front-derailleur.jpg', 1102.00, 25, 5],
    ['PROD-FD-004', 'Shimano Tiagra Fd-4703 Front Derailleur', 'Shimano Tiagra Fd-4703 Front Derailleur', 'Bicycle Parts', 'assets/images/products/shimano-tiagra-fd-4703-front-derailleur.jpg', 1750.99, 25, 5],

    // --- Bicycle Parts / Cassettes ---
    ['PROD-CASS-001', 'Decathlon Assurance Sports Btwin 7-Speed Screw-On 14-28 Freewheel in Base', 'Decathlon Assurance Sports Btwin 7-Speed Screw-On 14-28 Freewheel in Base', 'Bicycle Parts', 'assets/images/products/decathlon-assurance-sports-btwin-7-speed-screw-on-14-28-freewheel-in-base.jpg', 850.00, 25, 5],
    ['PROD-CASS-002', 'Sunrace Mf M2a Cassette', 'Sunrace Mf M2a Cassette', 'Bicycle Parts', 'assets/images/products/sunrace-mf-m2a-cassette.jpg', 845.63, 25, 5],
    ['PROD-CASS-003', 'Shimano MF-TZ510-7-CP Multi-Speed Freewheel', 'Shimano MF-TZ510-7-CP Multi-Speed Freewheel', 'Bicycle Parts', 'assets/images/products/shimano-mf-tz510-7-cp-multi-speed-freewheel.jpg', 499.00, 25, 5],
    ['PROD-CASS-004', 'Shimano Alivio CS-HG200 7-Speed Cassette', 'Shimano Alivio CS-HG200 7-Speed Cassette', 'Bicycle Parts', 'assets/images/products/shimano-alivio-cs-hg200-7-speed-cassette.jpg', 505.00, 25, 5],

    // --- Bicycle Parts / Cranksets ---
    ['PROD-CRNK-001', 'RIRO Carbon Fiber Crank Road Bike 11/12 Speed 50-34/53-39T GXP Direct Mount Bicycle Crankset...', 'RIRO Carbon Fiber Crank Road Bike 11/12 Speed 50-34/53-39T GXP Direct Mount Bicycle Crankset 170mm 46/48/50/52T', 'Bicycle Parts', 'assets/images/products/riro-carbon-fiber-crank-road-bike-11-12-speed-50-34-53-39t-gxp-direct-mount.jpg', 1903.00, 25, 5],
    ['PROD-CRNK-002', 'MTB Crank Set Ordinary Plastic Coated Crank Arm Chain Wheel & Crank Arm Set', 'MTB Crank Set Ordinary Plastic Coated Crank Arm Chain Wheel & Crank Arm Set', 'Bicycle Parts', 'assets/images/products/mtb-crank-set-ordinary-plastic-coated-crank-arm-chain-wheel-crank-arm-set.jpg', 196.00, 25, 5],
    ['PROD-CRNK-003', 'Crankset 42T 8T 160mm 9-11Speed Crank Arm Aluminum Alloy MTB Crankset', 'Crankset 42T 8T 160mm 9-11Speed Crank Arm Aluminum Alloy MTB Crankset', 'Bicycle Parts', 'assets/images/products/crankset-42t-8t-160mm-9-11speed-crank-arm-aluminum-alloy-mtb-crankset.jpg', 212.00, 25, 5],
    ['PROD-CRNK-004', 'Shimano FC-MT210-2 Crankset', 'Shimano FC-MT210-2 Crankset', 'Bicycle Parts', 'assets/images/products/shimano-fc-mt210-2-crankset.jpg', 1434.00, 25, 5],

    // --- Bicycle Parts / Brakes ---
    ['PROD-BRK-001', 'BUCKLOS Hydrolic Brake MTB 800/1500mm Bicycle Hydraulic Brake Set with 160mm Rotor Hydraulic Brake', 'BUCKLOS Hydrolic Brake MTB 800/1500mm Bicycle Hydraulic Brake Set with 160mm Rotor Hydraulic Brake', 'Bicycle Parts', 'assets/images/products/bucklos-hydrolic-brake-mtb-800-1500mm-bicycle-hydraulic-brake-set-with-160mm.jpg', 889.00, 25, 5],
    ['PROD-BRK-002', 'Shimano BR MT200 Hydraulic Disc Brake Caliper Set Front Rear Pair', 'Shimano BR MT200 Hydraulic Disc Brake Caliper Set Front Rear Pair', 'Bicycle Parts', 'assets/images/products/shimano-br-mt200-hydraulic-disc-brake-caliper-set-front-rear-pair.jpg', 2250.00, 25, 5],
    ['PROD-BRK-003', 'Ragusa Pioneer Rh300 AND RH500 Hydraulic Disc Brake For MTB Bicycle Mountain Bike', 'Ragusa Pioneer Rh300 AND RH500 Hydraulic Disc Brake For MTB Bicycle Mountain Bike', 'Bicycle Parts', 'assets/images/products/ragusa-pioneer-rh300-and-rh500-hydraulic-disc-brake-for-mtb-bicycle-mountain.jpg', 1399.00, 25, 5],
    ['PROD-BRK-004', 'BUCKLOS MTB Hydraulic Brake Set 800/1500mm Bike Hydraulic Disc Brake for Shimano with Rotor', 'BUCKLOS MTB Hydraulic Brake Set 800/1500mm Bike Hydraulic Disc Brake for Shimano with Rotor', 'Bicycle Parts', 'assets/images/products/bucklos-mtb-hydraulic-brake-set-800-1500mm-bike-hydraulic-disc-brake-for.jpg', 1569.00, 25, 5],

    // --- Bicycle Parts / Saddles ---
    ['PROD-SDL-001', 'ROCKBROS Bike Saddle', 'ROCKBROS Bike Saddle', 'Bicycle Parts', 'assets/images/products/rockbros-bike-saddle.jpg', 500.00, 25, 5],
    ['PROD-SDL-002', 'S-WORK POWER saddle bike', 'S-WORK POWER saddle bike', 'Bicycle Parts', 'assets/images/products/s-work-power-saddle-bike.jpg', 650.00, 25, 5],
    ['PROD-SDL-003', 'ZTTO Ultralight Road Bike Saddle', 'ZTTO Ultralight Road Bike Saddle', 'Bicycle Parts', 'assets/images/products/ztto-ultralight-road-bike-saddle.jpg', 966.00, 25, 5],
    ['PROD-SDL-004', 'Pnk Ergo Saddle', 'Pnk Ergo Saddle', 'Bicycle Parts', 'assets/images/products/pnk-ergo-saddle.jpg', 817.51, 25, 5],

    // --- Bicycle Parts / Pedals ---
    ['PROD-PDL-001', 'Mountain Bike Ragusa 710 711 712 Cnc Pedal', 'Mountain Bike Ragusa 710 711 712 Cnc Pedal', 'Bicycle Parts', 'assets/images/products/mountain-bike-ragusa-710-711-712-cnc-pedal.jpg', 469.00, 25, 5],
    ['PROD-PDL-002', 'TECNIC Bicycle Pedal 9/16 #630 Heavy-Duty Bike Pedals for MTB/Road Bike', 'TECNIC Bicycle Pedal 9/16 #630 Heavy-Duty Bike Pedals for MTB/Road Bike', 'Bicycle Parts', 'assets/images/products/tecnic-bicycle-pedal-9-16-630-heavy-duty-bike-pedals-for-mtb-road-bike.jpg', 850.00, 25, 5],
    ['PROD-PDL-003', 'Mountainous Road Bike Self-lock Pedal Bicycle Locking Pedal Mountainous Bicycle Self-locking Pedal', 'Mountainous Road Bike Self-lock Pedal Bicycle Locking Pedal Mountainous Bicycle Self-locking Pedal', 'Bicycle Parts', 'assets/images/products/mountainous-road-bike-self-lock-pedal-bicycle-locking-pedal-mountainous-bicycle.jpg', 1299.00, 25, 5],
    ['PROD-PDL-004', 'BUCKLOS Bike Pedals for SPD-SL Road Bike Race Pedals with Cleats Degree Bicycle Lock Pedal for...', 'BUCKLOS Bike Pedals for SPD-SL Road Bike Race Pedals with Cleats Degree Bicycle Lock Pedal for R8000 Sealed Bearings', 'Bicycle Parts', 'assets/images/products/bucklos-bike-pedals-for-spd-sl-road-bike-race-pedals-with-cleats-degree-bicycle.jpg', 1029.00, 25, 5],

    // --- Attachments / Headlights ---
    ['PROD-HLT-001', 'ROCKBROS Front Light USB Rechargeable LED Flashlight Bike Cycling Headlight', 'ROCKBROS Front Light USB Rechargeable LED Flashlight Bike Cycling Headlight', 'Attachments', 'assets/images/products/rockbros-front-light-usb-rechargeable-led-flashlight-bike-cycling-headlight.jpg', 685.00, 25, 5],
    ['PROD-HLT-002', 'Reelight NOVA Front Light', 'Reelight NOVA Front Light', 'Attachments', 'assets/images/products/reelight-nova-front-light.jpg', 665.00, 25, 5],
    ['PROD-HLT-003', 'HaixnFire BL06 Bicycle Light Type-C Rechargeable LED 2000 Lumens High Brightness', 'HaixnFire BL06 Bicycle Light Type-C Rechargeable LED 2000 Lumens High Brightness', 'Attachments', 'assets/images/products/haixnfire-bl06-bicycle-light-type-c-rechargeable-led-2000-lumens-high-brightness.jpg', 319.00, 25, 5],
    ['PROD-HLT-004', 'CatEye AMPP200 Headlight', 'CatEye AMPP200 Headlight', 'Attachments', 'assets/images/products/cateye-ampp200-headlight.jpg', 820.00, 25, 5],

    // --- Attachments / Rear Lights ---
    ['PROD-RLT-001', 'ROCKBROS Bike Tail Light Smart Brake Sensing IPX6 Waterproof Type-C Rechargeable 22g Rear Light', 'ROCKBROS Bike Tail Light Smart Brake Sensing IPX6 Waterproof Type-C Rechargeable 22g Rear Light', 'Attachments', 'assets/images/products/rockbros-bike-tail-light-smart-brake-sensing-ipx6-waterproof-type-c.jpg', 474.00, 25, 5],
    ['PROD-RLT-002', 'Meroca Smart Bike Tail Light Ultra Bright,-Bike Light Rechargeable Auto On/Off IPX6 Waterproof...', 'Meroca Smart Bike Tail Light Ultra Bright,-Bike Light Rechargeable Auto On/Off IPX6 Waterproof LED Bicycle Lights', 'Attachments', 'assets/images/products/meroca-smart-bike-tail-light-ultra-bright-bike-light-rechargeable-auto-on-off.jpg', 369.00, 25, 5],
    ['PROD-RLT-003', 'Acebiker AS1010 Rechargeable LED Rear Light Bicycle Highlight Warning Taillight With USB Cable Build', 'Acebiker AS1010 Rechargeable LED Rear Light Bicycle Highlight Warning Taillight With USB Cable Build', 'Attachments', 'assets/images/products/acebiker-as1010-rechargeable-led-rear-light-bicycle-highlight-warning-taillight.jpg', 135.00, 25, 5],
    ['PROD-RLT-004', 'FOREVER Cat Eye Design Bike Tail Light Rechargeable', 'FOREVER Cat Eye Design Bike Tail Light Rechargeable', 'Attachments', 'assets/images/products/forever-cat-eye-design-bike-tail-light-rechargeable.jpg', 686.04, 25, 5],

    // --- Attachments / Fenders ---
    ['PROD-FND-001', 'Ragusa Fender rc50 rc60 Mountain Bike Road rc-50 rc-60 rc-70', 'Ragusa Fender rc50 rc60 Mountain Bike Road rc-50 rc-60 rc-70', 'Attachments', 'assets/images/products/ragusa-fender-rc50-rc60-mountain-bike-road-rc-50-rc-60-rc-70.jpg', 109.00, 25, 5],
    ['PROD-FND-002', 'Oxford Mudstop Road Front Mudguard', 'Oxford Mudstop Road Front Mudguard', 'Attachments', 'assets/images/products/oxford-mudstop-road-front-mudguard.jpg', 830.01, 25, 5],
    ['PROD-FND-003', 'CHENJIE Dustproof Bike Front Rear Fenders 700c 27 Stainless Steel Road Bicycle Fender', 'CHENJIE Dustproof Bike Front Rear Fenders 700c 27 Stainless Steel Road Bicycle Fender', 'Attachments', 'assets/images/products/chenjie-dustproof-bike-front-rear-fenders-700c-27-stainless-steel-road-bicycle.jpg', 333.73, 25, 5],
    ['PROD-FND-004', 'Gurpil Velox Rear Mudguard', 'Gurpil Velox Rear Mudguard', 'Attachments', 'assets/images/products/gurpil-velox-rear-mudguard.jpg', 373.66, 25, 5],

    // --- Attachments / Bottle Holders ---
    ['PROD-BTL-001', 'ROCKBROS Bike Water Bottle Cage Ultralight Colorful Cycling Cup Holder', 'ROCKBROS Bike Water Bottle Cage Ultralight Colorful Cycling Cup Holder', 'Attachments', 'assets/images/products/rockbros-bike-water-bottle-cage-ultralight-colorful-cycling-cup-holder.jpg', 159.00, 25, 5],
    ['PROD-BTL-002', 'ENLEE Universal Bicycle Bottle Cage Lightweight Bike Water Bottle Holder', 'ENLEE Universal Bicycle Bottle Cage Lightweight Bike Water Bottle Holder', 'Attachments', 'assets/images/products/enlee-universal-bicycle-bottle-cage-lightweight-bike-water-bottle-holder.jpg', 170.00, 25, 5],
    ['PROD-BTL-003', 'Minoura AB100-4.5 Bottle Cage', 'Minoura AB100-4.5 Bottle Cage', 'Attachments', 'assets/images/products/minoura-ab100-4-5-bottle-cage.jpg', 350.00, 25, 5],
    ['PROD-BTL-004', 'Elite Ala Bottle Cage', 'Elite Ala Bottle Cage', 'Attachments', 'assets/images/products/elite-ala-bottle-cage.jpg', 500.00, 25, 5],

    // --- Safety Gear / Helmets ---
    ['PROD-HLM-001', 'RNOX Helmet Aerodynamic Safety Ultralight MTB Bicycle Helmet', 'RNOX Helmet Aerodynamic Safety Ultralight MTB Bicycle Helmet', 'Safety Gear', 'assets/images/products/rnox-helmet-aerodynamic-safety-ultralight-mtb-bicycle-helmet.jpg', 539.00, 25, 5],
    ['PROD-HLM-002', 'GIRO Bike helmet Bicycle Helmet', 'GIRO Bike helmet Bicycle Helmet', 'Safety Gear', 'assets/images/products/giro-bike-helmet-bicycle-helmet.jpg', 689.00, 25, 5],
    ['PROD-HLM-003', 'SPECIALIZED AIRNET Bike Helmet', 'SPECIALIZED AIRNET Bike Helmet', 'Safety Gear', 'assets/images/products/specialized-airnet-bike-helmet.jpg', 656.00, 25, 5],
    ['PROD-HLM-004', 'ROCKBROS Bike Helmet Lightweight Safety Integrated Adjustable', 'ROCKBROS Bike Helmet Lightweight Safety Integrated Adjustable', 'Safety Gear', 'assets/images/products/rockbros-bike-helmet-lightweight-safety-integrated-adjustable.jpg', 1737.00, 25, 5],

    // --- Safety Gear / Glasses ---
    ['PROD-GLS-001', 'ROCKBROS Photochromic Cycling Glasses Full Frame Sunglasses UV 400 Protection', 'ROCKBROS Photochromic Cycling Glasses Full Frame Sunglasses UV 400 Protection', 'Safety Gear', 'assets/images/products/rockbros-photochromic-cycling-glasses-full-frame-sunglasses-uv-400-protection.jpg', 750.00, 25, 5],
    ['PROD-GLS-002', 'SCICON Bike Goggles Polarized Cycling Glasses UV400', 'SCICON Bike Goggles Polarized Cycling Glasses UV400', 'Safety Gear', 'assets/images/products/scicon-bike-goggles-polarized-cycling-glasses-uv400.jpg', 937.00, 25, 5],
    ['PROD-GLS-003', 'NPC | Polarized Cycling Sunglasses', 'NPC | Polarized Cycling Sunglasses', 'Safety Gear', 'assets/images/products/npc-polarized-cycling-sunglasses.jpg', 384.47, 25, 5],
    ['PROD-GLS-004', 'Kapvoe UV400 Outdoor Cycling Sunglasses', 'Kapvoe UV400 Outdoor Cycling Sunglasses', 'Safety Gear', 'assets/images/products/kapvoe-uv400-outdoor-cycling-sunglasses.jpg', 305.88, 25, 5],

    // --- Safety Gear / Gloves ---
    ['PROD-GLV-001', 'ROCKBROS Ice Silk Cycling Gloves Anti-slip Short Protect Anti-UV Half Finger Sports Gloves', 'ROCKBROS Ice Silk Cycling Gloves Anti-slip Short Protect Anti-UV Half Finger Sports Gloves', 'Safety Gear', 'assets/images/products/rockbros-ice-silk-cycling-gloves-anti-slip-short-protect-anti-uv-half-finger.jpg', 402.00, 25, 5],
    ['PROD-GLV-002', 'ROCKBROS Cycling Gloves Touch Screen Summer Autumn Spring MTB Road Bike Gloves Sports GEL Pad...', 'ROCKBROS Cycling Gloves Touch Screen Summer Autumn Spring MTB Road Bike Gloves Sports GEL Pad Shockproof Half Finger Bicycle Gloves', 'Safety Gear', 'assets/images/products/rockbros-cycling-gloves-touch-screen-summer-autumn-spring-mtb-road-bike-gloves.jpg', 599.00, 25, 5],
    ['PROD-GLV-003', 'GIYO Touch Screen Long Full Fingers Gel Sports Cycling Gloves', 'GIYO Touch Screen Long Full Fingers Gel Sports Cycling Gloves', 'Safety Gear', 'assets/images/products/giyo-touch-screen-long-full-fingers-gel-sports-cycling-gloves.jpg', 460.00, 25, 5],
    ['PROD-GLV-004', 'West BIKING Half Finger Cycling Gloves Shockproof Style', 'West BIKING Half Finger Cycling Gloves Shockproof Style', 'Safety Gear', 'assets/images/products/west-biking-half-finger-cycling-gloves-shockproof-style.jpg', 1016.12, 25, 5],

    // --- Safety Gear / Chain Locks ---
    ['PROD-LCK-001', 'ULAC Combination Chain Lock by RockBros Blue', 'ULAC Combination Chain Lock by RockBros Blue', 'Safety Gear', 'assets/images/products/ulac-combination-chain-lock-by-rockbros-blue.jpg', 800.00, 25, 5],
    ['PROD-LCK-002', 'WEST Bike Chain Lock 5-Digit Code Lock 65cm/100cm/120cm/150cm Mountain Road Bike Portable Code Lock', 'WEST Bike Chain Lock 5-Digit Code Lock 65cm/100cm/120cm/150cm Mountain Road Bike Portable Code Lock', 'Safety Gear', 'assets/images/products/west-bike-chain-lock-5-digit-code-lock-65cm-100cm-120cm-150cm-mountain-road.jpg', 779.00, 25, 5],
    ['PROD-LCK-003', 'Kryptonite Keeper 585 Chain Lock', 'Kryptonite Keeper 585 Chain Lock', 'Safety Gear', 'assets/images/products/kryptonite-keeper-585-chain-lock.jpg', 1593.08, 25, 5],
    ['PROD-LCK-004', 'JINJIAN Bicycle Alloy Anti-Theft Chain Lock Durable And Wear-Resistant', 'JINJIAN Bicycle Alloy Anti-Theft Chain Lock Durable And Wear-Resistant', 'Safety Gear', 'assets/images/products/jinjian-bicycle-alloy-anti-theft-chain-lock-durable-and-wear-resistant.jpg', 226.00, 25, 5],
];

echo "Inserting Products (" . count($products) . " items)...\n";
$insProd = mysqli_prepare($conn, "INSERT INTO product (sku, name, description, productCategoryID, image, price, stock, lowStockThreshold, startDate, endDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)");

foreach ($products as [$sku, $name, $description, $catName, $image, $price, $stock, $threshold]) {
    $catId = $catMap[$catName] ?? null;
    if ($catId === null) {
        die("Unknown category '{$catName}' for {$sku}\n");
    }
    mysqli_stmt_bind_param($insProd, 'sssisdii', $sku, $name, $description, $catId, $image, $price, $stock, $threshold);
    mysqli_stmt_execute($insProd);
    echo "  [+] {$sku} ({$catName}): PHP {$price}\n";
}
mysqli_stmt_close($insProd);

// ========================================================
// SERVICES (the 6 services listed in the PDF)
// ========================================================
$services = [
    ['SERV-REP-001', 'Bike Repair w/ Cleaning', 'Mechanical repair diagnostics and repair service combined with complete cleaning.', 'Bicycle Repair', 'assets/images/categories/category_parts.jpg', 250.00],
    ['SERV-REP-002', 'Bike Repair', 'Standard mechanical bike repair and adjustment service.', 'Bicycle Repair', 'assets/images/categories/category_parts.jpg', 150.00],
    ['SERV-CLN-001', 'Cleaning', 'Full frame and drivetrain degreasing and bike wash.', 'Cleaning & Detailing', 'assets/images/categories/category_safety_gear.jpg', 100.00],
    ['SERV-INS-001', 'Parts Installment', 'Installation service for individual replacement or upgrade components (PHP 50 per part).', 'Parts Installation', 'assets/images/categories/category_attachments.jpg', 50.00],
    ['SERV-BLD-001', 'Full Bike Building', 'Complete ground-up assembly of full build bikes from boxed or custom parts.', 'Custom Bike Building', 'assets/images/categories/category_bikes.jpg', 300.00],
    ['SERV-PNT-001', 'Re-paint', 'Frame surface preparation and full custom re-painting service.', 'Frame Painting', 'assets/images/categories/category_bikes.jpg', 500.00],
];

echo "\nInserting Services (" . count($services) . " items)...\n";
$insServ = mysqli_prepare($conn, "INSERT INTO service (sku, name, description, serviceCategoryID, image, price, startDate, endDate) VALUES (?, ?, ?, ?, ?, ?, NOW(), NULL)");

foreach ($services as [$sku, $name, $description, $catName, $image, $price]) {
    $scId = $servCatMap[$catName];
    mysqli_stmt_bind_param($insServ, 'sssisd', $sku, $name, $description, $scId, $image, $price);
    mysqli_stmt_execute($insServ);
    echo "  [+] {$sku}: {$name} (PHP {$price})\n";
}
mysqli_stmt_close($insServ);

echo "\n=========================================================\n";
echo "   Catalog seeded.\n";
echo "   Categories: " . count($productCategories) . "\n";
echo "   Total Products: " . count($products) . "\n";
echo "   Total Services: " . count($services) . "\n";
echo "=========================================================\n";