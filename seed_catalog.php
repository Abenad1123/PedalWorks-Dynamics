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
    ['PROD-ROAD-001', 'Wonder Wheels 700C Aluminum Road Bike', 'Aerodynamic 53cm aluminum alloy frame road machine featuring Shimano TX-35 7-speed shifting, 700C alloy black rims, and durable road tires designed for fast asphalt training.', 'Full Build Bikes', 'assets/images/products/wonder-wheels-road-bike-700c-aluminum-frame-shimano-tx-35-7-speed-alloy-black.jpg', 28289.00, 10, 3],
    ['PROD-ROAD-002', 'Rockbros 700C Disc Road Racing Bike', 'Lightweight aluminum alloy racing bicycle equipped with 2x10 speed precision gearing, internal cable routing, and all-weather mechanical disc brakes for adult riders.', 'Full Build Bikes', 'assets/images/products/rockbros-road-bike-700c-2-10-speeds-disc-brake-bike-aluminum-alloy-frame.jpg', 64366.35, 5, 2],
    ['PROD-ROAD-003', 'Garuda Spearow Aero Road Bike', 'Aero-profile aluminum road bike featuring a 3x7 21-speed Shimano drivetrain, integrated internal cabling, and ergonomic drop horn handlebars for sprints.', 'Full Build Bikes', 'assets/images/products/garuda-spearowaero-roadbike-700c-aluminum-frame-3x7-21-speed-shimano-30-direct.jpg', 6499.00, 15, 4],
    ['PROD-ROAD-004', 'Raleigh Ultra-Light Variable Speed Road Bike', 'Classic endurance road bicycle engineered with an ultra-lightweight frame geometry, multi-ratio variable speed gearing, and high-efficiency dual caliper brakes.', 'Full Build Bikes', 'assets/images/products/raleigh-ultra-light-variable-speed-bicycle.jpg', 23687.47, 8, 2],

    // --- Full Build Bikes / Mountain Bikes ---
    ['PROD-MTB-001', 'Aenxrd 21-Speed Trail MTB', 'Rugged cross-country mountain bike with high-carbon steel construction, 21-speed Shimano-compatible derailleurs, and front suspension fork for trail traction.', 'Full Build Bikes', 'assets/images/products/aenxrd-mountain-bike-24-26-inch-21-speed-adult-bicycles-bike-high-carbon-steel.jpg', 4699.00, 18, 5],
    ['PROD-MTB-002', 'Trinx M134 24" Alloy Hardtail MTB', 'Performance youth and adult alloy hardtail mountain bike with front shock absorption, mechanical dual disc brakes, and rugged off-road knobby tires.', 'Full Build Bikes', 'assets/images/products/2026-trinx-m134-24-mechanical-alloy-mountain-bike.jpg', 8900.00, 12, 4],
    ['PROD-MTB-003', 'Black Mamba Steel Trail Mountain Bike', 'Heavy-duty trail rider featuring high-tensile steel frame tubing, 26/27.5-inch alloy wheelset, responsive disc brakes, and quick-shift gear levers.', 'Full Build Bikes', 'assets/images/products/black-mamba-26-27-5-inches-steel-mountain-bike-cycling.jpg', 3679.00, 20, 5],
    ['PROD-MTB-004', 'Sanexrd R12 Hardtail Mountain Bike', '21-speed versatile hardtail mountain bike with front shock-absorbing fork, dual mechanical disc brakes, and high-traction all-terrain tread for singletrack.', 'Full Build Bikes', 'assets/images/products/sanexrd-r12-mountain-bike-24-26-inch-21-speed-adult-bike-with-disc-brakes-gear.jpg', 4199.00, 14, 4],

    // --- Full Build Bikes / Gravel Bikes ---
    ['PROD-GRVL-001', 'Toseek Targa 1.0 CX Gravel Bike', 'All-terrain gravel and cyclocross road bike equipped with lightweight alloy frame, wide-clearance 700C tires, and reliable disc braking for mixed terrain.', 'Full Build Bikes', 'assets/images/products/toseek-targa-1-0-2022-all-new-toseek-road-bike-cx-700c.jpg', 14499.00, 7, 2],
    ['PROD-GRVL-002', 'Steel 700C Adventure Gravel Bike', 'Comfort-oriented steel gravel grinder with 700x28C multi-surface tires, drop handlebars, 7-speed drivetrain, and dependable front and rear disc brakes.', 'Full Build Bikes', 'assets/images/products/700c-gravel-bike-high-carbon-steel-frame-700-x-28c-7-27-front-and-rear-disc.jpg', 19389.00, 9, 3],
    ['PROD-GRVL-003', 'Cube Nuroad Pro FE Adventure Gravel Bike', 'Premium German-engineered gravel machine with full equipment package (FE), hydraulic disc brakes, generous tire clearance, and versatile bikepacking mounts.', 'Full Build Bikes', 'assets/images/products/cube-nuroad-pro-fe-gravel-bike.jpg', 37556.96, 5, 2],
    ['PROD-GRVL-004', 'Dagger Thru-Axle Hydraulic Gravel Bike', 'Modern gravel bike engineered with rigid thru-axles, hydraulic mineral oil disc brakes, wide-range cassette, and responsive carbon-alloy geometry.', 'Full Build Bikes', 'assets/images/products/dagger-oil-brake-through-axle-gravel-bike.jpg', 39088.75, 6, 2],

    // --- Full Build Bikes / Mamachari Bikes ---
    ['PROD-MAMA-001', 'Cyma Punk Rock 27" Japanese Mamachari', 'Classic Japanese commuter bicycle featuring 27-inch wheels, front steel cargo basket, integrated rear luggage carrier, and puncture-resistant tires.', 'Full Build Bikes', 'assets/images/products/bicycle-city-cycle-mama-chari-cyma-punk-rock-27-atm012.jpg', 18631.00, 11, 3],
    ['PROD-MAMA-002', 'City Cycle 26" Step-Through Mamachari', 'Comfortable low-entry step-through city bicycle with woven front basket, sturdy rear cargo rack, chain guard, and wide urban cruiser tires.', 'Full Build Bikes', 'assets/images/products/city-cycle-mamachari-26-inch-ladies-bicycle-with-basket-and-carrier-thick-tires.jpg', 12782.00, 13, 4],
    ['PROD-MAMA-003', 'Vintage City Classic Mamachari with Basket', 'Traditional utility city bicycle with responsive V-brakes, reinforced front utility basket, fenders, and comfortable upright riding posture.', 'Full Build Bikes', 'assets/images/products/city-cycle-mamachari-24-26-inch-bicycle-city-car-women-s-car-v-brake-basket.jpg', 11794.00, 10, 3],
    ['PROD-MAMA-004', '6-Speed Dynamo LED City Mamachari', 'Equipped commuter bike featuring Shimano 6-speed gears, wheel-hub dynamo LED headlight, full-coverage fenders, and built-in rear wheel lock.', 'Full Build Bikes', 'assets/images/products/city-cycle-mamachari-24-26-inch-6-speed-bike-with-basket-with-key-dynamo-led.jpg', 20655.00, 8, 2],

    // --- Full Build Bikes / Folding Bikes ---
    ['PROD-FOLD-001', 'Bickerton Junction 1507 20" Folding Bike', 'British heritage 20-inch portable folding bike featuring an aluminum alloy folding hinge, 7-speed gearing, rear rack, and compact trunk-ready fold.', 'Full Build Bikes', 'assets/images/products/bickerton-junction-1507-20in-folding-bike.jpg', 23500.00, 9, 3],
    ['PROD-FOLD-002', 'Tern Link B8 8-Speed Folding Bike', 'High-performance portable bike from Tern featuring an ultra-stiff FBL 2 Joint, 8-speed wide-ratio cassette, and mechanical disc brakes for city commutes.', 'Full Build Bikes', 'assets/images/products/tern-link-b8.jpg', 29850.00, 7, 2],
    ['PROD-FOLD-003', 'Tilt 120 20" 6-Speed Folding Bike', 'Quick-folding urban bike designed for multimodal commutes. Features a 6-speed derailleur, ergonomic grips, and a 30-second latch folding system.', 'Full Build Bikes', 'assets/images/products/folding-bike-tilt-120-adj-20-inch-6-speed.jpg', 14990.00, 12, 4],
    ['PROD-FOLD-004', 'Shibomei Ultra-Light Portable Folding Bike', 'Ultra-compact folding commuter bicycle crafted with lightweight tubing, multi-speed transmission, and dual hand brakes for transit and car trunks.', 'Full Build Bikes', 'assets/images/products/shibomei-16-inch-20-inch-folding-bicycle-student-adult-light-ultra-light.jpg', 12025.00, 14, 4],

    // --- Full Build Bikes / Bikes for Kids ---
    ['PROD-KIDS-001', 'Maru Minato 4.0 14" Junior Bike', 'Durable children starter bicycle with 14-inch wheels, fully enclosed chain safety guard, removable training wheels, and easy-reach brake levers.', 'Full Build Bikes', 'assets/images/products/maru-minato-4-0-kids-14-junior-series-bike.jpg', 4500.00, 15, 4],
    ['PROD-KIDS-002', 'RoyalBaby Freestyle Kids Bike', 'Freestyle BMX-inspired kids bike featuring wide 2.4-inch tires, dual hand brakes plus coaster brake, water bottle cage, and heavy-duty training wheels.', 'Full Build Bikes', 'assets/images/products/royalbaby-freestyle.jpg', 4500.00, 16, 5],
    ['PROD-KIDS-003', 'Mongoose/Haro 16" BMX Junior Bike', 'Authentic youth BMX bicycle featuring 4-bolt stem, steel freestyle frame, single-speed simplicity, and rugged alloy rims for skateparks and sidewalks.', 'Full Build Bikes', 'assets/images/products/bmx-bicycle-mongoose-haro.jpg', 3733.00, 11, 3],
    ['PROD-KIDS-004', 'Kiumo Foldable Kids Bike with Training Wheels', 'Adjustable junior bicycle with front basket, non-slip rubber tires, ergonomic saddle, and fold-away frame design for easy transport.', 'Full Build Bikes', 'assets/images/products/kiumo-kids-bike-12-14-16-inch-foldable-bicycle-with-training-wheels-adjustable.jpg', 2399.00, 18, 5],

    // --- Full Build Bikes / Fixies ---
    ['PROD-FIX-001', 'Garuda Urban Fixed Gear Track Bike', 'Minimalist track fixie featuring high-tensile steel frame, flip-flop rear hub (fixed gear / freewheel), and high-flange track wheels for pure street riding.', 'Full Build Bikes', 'assets/images/products/garuda-fixie-bike-fixed-gear-steel-bike.jpg', 3899.00, 12, 3],
    ['PROD-FIX-002', 'Garuda Hurricane Steel Fixed Gear Bike', 'Aggressive geometry track bicycle with aero bullhorn bars, sealed bearing bottom bracket, and lightweight deep-V alloy track wheelset.', 'Full Build Bikes', 'assets/images/products/garuda-hurracane-steel-fixed-gear-bicycle.jpg', 5999.00, 10, 3],
    ['PROD-FIX-003', 'Betta Hellboy Aero 700x25C Fixie', 'Aero hydroformed aluminum track frame with 700x25C slick tires, deep-dish aero rims, and dual caliper hand brakes for urban speed.', 'Full Build Bikes', 'assets/images/products/betta-hellboy-fixie-aero-700x25c.jpg', 8999.00, 8, 2],
    ['PROD-FIX-004', 'Promax PF-30 Fixed Gear Bicycle', 'Street-ready fixie bike with stiff track geometry, CNC alloy chainring, platform pedals with toe strap slots, and sealed cartridge bearings.', 'Full Build Bikes', 'assets/images/products/promax-pf-30-fixie.jpg', 6899.00, 9, 3],

    // --- Full Build Bikes / BMX ---
    ['PROD-BMX-001', 'Punisher Ambush 20" Street BMX', 'Street and park BMX bike with 20-inch alloy rims, threadless ahead stem, 25/9 micro-gearing, and heavy-duty dropouts.', 'Full Build Bikes', 'assets/images/products/bmx-20-punisher-ambush-street-design-o-s-threadless-ahead-semi-assembled.jpg', 4338.00, 14, 4],
    ['PROD-BMX-002', 'Maru Seido 20" BMX Version 3.0', 'Freestyle BMX bike featuring 360-degree rotor compatibility, four stunt pegs, 2.3-inch street tires, and reinforced top tube.', 'Full Build Bikes', 'assets/images/products/maru-seido-bmx-20-version-3-0.jpg', 5940.00, 10, 3],
    ['PROD-BMX-003', 'Sushida 26" All-Terrain Fat Bike', 'Monstrous 26x4.0-inch ultra-wide fat tires capable of riding over deep sand, mud, and gravel trails with dual mechanical disc brakes and steel frame.', 'Full Build Bikes', 'assets/images/products/sushida-mtb-26-fatbike-mountain-bike-26x4-0.jpg', 5699.00, 7, 2],
    ['PROD-BMX-004', 'Calum X-Man 20" Alloy BMX with Rotor', 'Lightweight aluminum alloy BMX with 360-degree detangler rotor for handlebar spins, reinforced 48-spoke wheels, and pro-style platform pedals.', 'Full Build Bikes', 'assets/images/products/calum-x-man-alloy-frame-bmx-20-w-rotor.jpg', 8195.00, 6, 2],

    // --- Bicycle Parts / Frames ---
    ['PROD-FRM-001', 'Ryder X9 Thru-Axle MTB Frame', 'Hydroformed aluminum hardtail mountain bike frame featuring 12x142mm rear thru-axle spacing, tapered headtube, and internal dropper routing.', 'Bicycle Parts', 'assets/images/products/ryder-x9-frame-thru-axle-type.jpg', 6400.00, 8, 2],
    ['PROD-FRM-002', 'Maxzone Chromoly 1.0 Steel Bike Frame', 'Resilient 4130 chromoly steel bicycle frame offering superb vibration damping, classic thin-tube aesthetics, and standard threaded bottom bracket shell.', 'Bicycle Parts', 'assets/images/products/maxzone-chromoly-1-0-frame.jpg', 2500.00, 12, 3],
    ['PROD-FRM-003', 'Kemeke All-Mountain / XC Bike Frame', 'Aggressive cross-country and trail frame engineered with lightweight alloy geometry, post-mount disc brake tabs, and reinforced chainstays.', 'Bicycle Parts', 'assets/images/products/kemeke-mountain-all-mountain-cross-country-bike-frame.jpg', 10825.74, 5, 2],
    ['PROD-FRM-004', 'Ryder Carbon 27.5" Thru-Axle Frame', 'Toray carbon fiber XC hardtail frame designed for 27.5-inch wheels with 12x142mm thru-axle, weighing only 1,180 grams with direct derailleur mount.', 'Bicycle Parts', 'assets/images/products/ryder-carbon-frame-27-5in-tires-w-12-x-142mm-ta.jpg', 13000.00, 4, 2],

    // --- Bicycle Parts / Forks ---
    ['PROD-FORK-001', 'Ragusa RB800 700C Rigid Alloy Fork', 'Lightweight aluminum rigid fork designed for road and gravel disc brake setups. Offers crisp steering response and standard 1-1/8 inch steerer tube.', 'Bicycle Parts', 'assets/images/products/bkat-ragusa-rb800-lightweight-alloy-rigid-fork-700c-disc-brake-ready-road-bike.jpg', 600.00, 25, 5],
    ['PROD-FORK-002', 'Suspension MTB Spring Shock Fork', 'Front suspension fork with 100mm travel and mechanical lockout, compatible with 26, 27.5, and 29-inch wheels and disc brake calipers.', 'Bicycle Parts', 'assets/images/products/bike-mtb-fork-shock-absorbing-front-fork-metal-26-27-5-29-inches.jpg', 540.00, 25, 5],
    ['PROD-FORK-003', 'HASSNS Ultra-Light Alloy Rigid MTB Fork', 'CNC-machined aluminum rigid mountain bike fork with post-mount disc brake tabs, providing maximum power transfer for lightweight XC builds.', 'Bicycle Parts', 'assets/images/products/hassns-mountain-bike-fork-bicycle-front-fork-mtb-rigid-fork-ultra-light.jpg', 2300.00, 25, 5],
    ['PROD-FORK-004', 'Rockrider XCT 27.5" 80mm Suspension Fork', 'Suntour XCT suspension fork featuring 80mm travel, pre-load adjustment, 9mm quick-release dropouts, and 1-1/8 inch threadless steerer.', 'Bicycle Parts', 'assets/images/products/rockrider-fork-xct-27-5-80mm-1-1-8-v-brake-qr9mm.jpg', 532.00, 25, 5],

    // --- Bicycle Parts / Handle Bars ---
    ['PROD-HBAR-001', 'Alloy 31.8mm x 720mm Riser Handlebar', 'Durable aluminum riser handlebar with 31.8mm clamp diameter and 720mm width for confident steering control on steep descents.', 'Bicycle Parts', 'assets/images/products/aluminum-alloy-bicycle-handlebar-31-8-720mm-handlebar-stem-31-8mm.jpg', 200.00, 25, 5],
    ['PROD-HBAR-002', 'Sagmit CX Pro 420mm Gravel Drop Bars', 'Flared ergonomic drop handlebar engineered for gravel and adventure bikes, providing superior forearm clearance and stability on rough roads.', 'Bicycle Parts', 'assets/images/products/sagmit-cx-pro-drop-handlebars-420mm-gravel-bars.jpg', 800.00, 25, 5],
    ['PROD-HBAR-003', 'Truvativ HC-AM 420mm Road Drop Bars', 'Compact reach and drop aluminum road handlebar engineered for aerodynamic road cycling and long-distance endurance comfort.', 'Bicycle Parts', 'assets/images/products/truvativ-hc-am-drop-bars-420mm-road.jpg', 600.00, 25, 5],
    ['PROD-HBAR-004', 'UNO 6061-T6 Alloy Matte Handlebar', 'Double-butted 6061 aerospace alloy handlebar featuring a sandblasted matte black finish and laser-etched alignment markings.', 'Bicycle Parts', 'assets/images/products/uno-bicycle-handlebar-aluminium-alloy-6061-t6-silver-matte-black.jpg', 830.87, 25, 5],

    // --- Bicycle Parts / Wheels ---
    ['PROD-WHL-001', 'Kenda 1153 MTB Trail Tire', 'Fast-rolling knobby mountain bike tire with reinforced side treads for cornering grip on hardpack dirt and loose gravel.', 'Bicycle Parts', 'assets/images/products/kenda-original-1153-1pcs-mountain-bike-tires-26-1-95-2-1-27-5-1-95-2-1.jpg', 549.00, 25, 5],
    ['PROD-WHL-002', 'CST All-Terrain Mountain Bike Tire', 'High-mileage all-terrain bicycle tire engineered with puncture-resistant casing and continuous center tread for smooth road rolling.', 'Bicycle Parts', 'assets/images/products/cst-bicycle-tires-for-26-27-5-29-road-mountain-bike-1-95-2-1-mtb-tire.jpg', 599.00, 25, 5],
    ['PROD-WHL-003', 'Maxxis Re-Fuse Folding MaxxShield Tire', 'Legendary puncture-resistant road and endurance tire with diamond knurled tread and MaxxShield bead-to-bead casing protection.', 'Bicycle Parts', 'assets/images/products/maxxis-re-fuse-folding-ms.jpg', 1400.00, 25, 5],
    ['PROD-WHL-004', 'Thickslick 700C Urban Slick Tire', 'Heavy-duty slick urban tire featuring twice the rubber tread depth of standard road tires to resist glass, debris, and urban hazards.', 'Bicycle Parts', 'assets/images/products/thickslick-bicycle-tires-700c.jpg', 695.00, 25, 5],

    // --- Bicycle Parts / Rear Derailleurs ---
    ['PROD-RD-001', 'Shimano Sora R3000 9-Speed Derailleur', 'Precision 9-speed road rear derailleur with wide-link design for crisp, responsive shifting across medium and wide-ratio cassettes.', 'Bicycle Parts', 'assets/images/products/shimano-sora-r3000-rear-derailleur.jpg', 1500.00, 25, 5],
    ['PROD-RD-002', 'Shimano Tourney TY300 6/7-Speed Derailleur', 'Durable SIS index rear derailleur suitable for 6 and 7-speed commuter, hybrid, and mountain bicycles.', 'Bicycle Parts', 'assets/images/products/shimano-tourney-ty300-rear-derailleur.jpg', 900.00, 25, 5],
    ['PROD-RD-003', 'Shimano Tourney RD-TZ500 Direct Mount', 'Upgraded 6/7/8-speed entry-level derailleur with direct mount hanger, reliable index shifting, and zinc-coated steel cage.', 'Bicycle Parts', 'assets/images/products/rd-tourney-rd-tz500-rear-derailleur-6-7-8-speed-direct-mount-upgraded-rd-tz50.jpg', 200.00, 25, 5],
    ['PROD-RD-004', 'Shimano SLX RD-M7100 SGS 12-Speed Derailleur', 'Shadow RD+ 12-speed mountain derailleur with integrated chain stabilizer clutch, reducing chain slap and drops on aggressive singletrack.', 'Bicycle Parts', 'assets/images/products/shimano-sgs-rd-m7100-rear-derailleur.jpg', 1753.99, 25, 5],

    // --- Bicycle Parts / Front Derailleurs ---
    ['PROD-FD-001', 'SRAM Rival 22 Braze-On Front Derailleur', 'Yaw technology front derailleur rotating in line with the chain, eliminating manual trim adjustments across the entire 2x11 gear range.', 'Bicycle Parts', 'assets/images/products/sram-rival-22-braze-on-front-derailleur.jpg', 689.00, 25, 5],
    ['PROD-FD-002', 'Shimano Tourney FD-TY300 Triple Derailleur', 'Top-swing dual-pull front derailleur compatible with 3x6 and 3x7 mountain and city cranksets.', 'Bicycle Parts', 'assets/images/products/shimano-fd-ty300-tourney-6-7-speed-triple-front-derailleur.jpg', 289.00, 25, 5],
    ['PROD-FD-003', 'Sensah Multi-Speed Front Derailleur', 'Precision front derailleur compatible with 2x9, 2x10, and 2x11 setups, featuring reinforced cage plates for crisp upshifts under load.', 'Bicycle Parts', 'assets/images/products/sensah-multi-speed-compatible-front-derailleur.jpg', 1102.00, 25, 5],
    ['PROD-FD-004', 'Shimano Tiagra FD-4703 3x10 Derailleur', 'Dedicated road triple front derailleur with extended link arm for effortless lever throw and smooth transitions across three chainrings.', 'Bicycle Parts', 'assets/images/products/shimano-tiagra-fd-4703-front-derailleur.jpg', 1750.99, 25, 5],

    // --- Bicycle Parts / Cassettes ---
    ['PROD-CASS-001', 'Btwin 7-Speed 14-28T Screw-On Freewheel', 'Standard screw-on multi-gear freewheel offering smooth 14 to 28 tooth progression for 7-speed commuter and hybrid bicycles.', 'Bicycle Parts', 'assets/images/products/decathlon-assurance-sports-btwin-7-speed-screw-on-14-28-freewheel-in-base.jpg', 850.00, 25, 5],
    ['PROD-CASS-002', 'SunRace M2A 7-Speed Freewheel', 'Zinc-plated fluid drive 7-speed freewheel designed for rust resistance and quiet chain engagement on threaded rear hubs.', 'Bicycle Parts', 'assets/images/products/sunrace-mf-m2a-cassette.jpg', 845.63, 25, 5],
    ['PROD-CASS-003', 'Shimano MF-TZ510 7-Speed Multi-Freewheel', 'Genuine Shimano MegaRange freewheel engineered with Hyperglide ramps for clean shifting under pedal pressure.', 'Bicycle Parts', 'assets/images/products/shimano-mf-tz510-7-cp-multi-speed-freewheel.jpg', 499.00, 25, 5],
    ['PROD-CASS-004', 'Shimano Alivio CS-HG200 7-Speed Cassette', 'Splined Hyperglide cassette for freehub wheels, featuring nickel-plated steel cogs and lightweight perforated sprockets.', 'Bicycle Parts', 'assets/images/products/shimano-alivio-cs-hg200-7-speed-cassette.jpg', 505.00, 25, 5],

    // --- Bicycle Parts / Cranksets ---
    ['PROD-CRNK-001', 'Riro Carbon Fiber Road GXP Crankset', 'Ultra-stiff carbon composite road crankset featuring direct-mount 50-34T CNC chainrings, 170mm arm length, and GXP bottom bracket axle.', 'Bicycle Parts', 'assets/images/products/riro-carbon-fiber-crank-road-bike-11-12-speed-50-34-53-39t-gxp-direct-mount.jpg', 1903.00, 25, 5],
    ['PROD-CRNK-002', 'MTB Triple Chainring Steel Crankset', 'Affordable replacement triple crankset with resin-coated steel arms, integrated chainguard ring, and square taper spindle mount.', 'Bicycle Parts', 'assets/images/products/mtb-crank-set-ordinary-plastic-coated-crank-arm-chain-wheel-crank-arm-set.jpg', 196.00, 25, 5],
    ['PROD-CRNK-003', '1x Narrow-Wide 42T Alloy MTB Crankset', 'Modern 1x drivetrain crankset featuring 42T narrow-wide tooth profile to prevent chain drops without needing a chain guide.', 'Bicycle Parts', 'assets/images/products/crankset-42t-8t-160mm-9-11speed-crank-arm-aluminum-alloy-mtb-crankset.jpg', 212.00, 25, 5],
    ['PROD-CRNK-004', 'Shimano FC-MT210-2 Hollowtech II Crankset', '2-piece mountain crankset with external sealed cartridge bearings, rigid alloy spider, and 36-22T double chainrings.', 'Bicycle Parts', 'assets/images/products/shimano-fc-mt210-2-crankset.jpg', 1434.00, 25, 5],

    // --- Bicycle Parts / Brakes ---
    ['PROD-BRK-001', 'Bucklos Hydraulic MTB Disc Brake Set (160mm)', 'Pre-bled front and rear hydraulic mountain disc brake kit with mineral oil, ergonomic 2-finger levers, and two 160mm stainless steel rotors.', 'Bicycle Parts', 'assets/images/products/bucklos-hydrolic-brake-mtb-800-1500mm-bicycle-hydraulic-brake-set-with-160mm.jpg', 889.00, 25, 5],
    ['PROD-BRK-002', 'Shimano MT200 Hydraulic Disc Brake Set', 'The benchmark in hydraulic bicycle brakes: resin pads, Shimano mineral oil, reach adjust lever, and quiet, reliable stopping power.', 'Bicycle Parts', 'assets/images/products/shimano-br-mt200-hydraulic-disc-brake-caliper-set-front-rear-pair.jpg', 2250.00, 25, 5],
    ['PROD-BRK-003', 'Ragusa Pioneer RH300 Hydraulic Disc Brakes', 'High-clamping hydraulic brake calipers with heat-dissipating two-piston design and dual-sided pad contact.', 'Bicycle Parts', 'assets/images/products/ragusa-pioneer-rh300-and-rh500-hydraulic-disc-brake-for-mtb-bicycle-mountain.jpg', 1399.00, 25, 5],
    ['PROD-BRK-004', 'Bucklos Upgraded Disc Brake Set with Rotors', 'Complete MTB hydraulic disc brake assembly featuring 800mm front and 1500mm rear reinforced oil hoses and wave-cut cooling rotors.', 'Bicycle Parts', 'assets/images/products/bucklos-mtb-hydraulic-brake-set-800-1500mm-bike-hydraulic-disc-brake-for.jpg', 1569.00, 25, 5],

    // --- Bicycle Parts / Saddles ---
    ['PROD-SDL-001', 'Rockbros Ergonomic Memory Foam Saddle', 'Breathable center cutout bicycle seat with high-density memory foam padding, reflective rear safety strip, and dual shock-absorbing springs.', 'Bicycle Parts', 'assets/images/products/rockbros-bike-saddle.jpg', 500.00, 25, 5],
    ['PROD-SDL-002', 'S-Works Power Style Short-Nose Saddle', 'Body Geometry performance short-nosed saddle designed for aggressive aero riding positions with reduced soft-tissue pressure.', 'Bicycle Parts', 'assets/images/products/s-work-power-saddle-bike.jpg', 650.00, 25, 5],
    ['PROD-SDL-003', 'ZTTO Ultralight Road Racing Saddle', 'Streamlined racing saddle with hollow chromoly rails, microfiber cover, and lightweight EVA foam for road and gravel racing.', 'Bicycle Parts', 'assets/images/products/ztto-ultralight-road-bike-saddle.jpg', 966.00, 25, 5],
    ['PROD-SDL-004', 'Pnk Ergo Cutout Touring Saddle', 'Long-distance touring saddle engineered with ergonomic perineal relief channel, waterproof synthetic leather, and durable steel rails.', 'Bicycle Parts', 'assets/images/products/pnk-ergo-saddle.jpg', 817.51, 25, 5],

    // --- Bicycle Parts / Pedals ---
    ['PROD-PDL-001', 'Ragusa 710 CNC Aluminum MTB Pedals', 'CNC-machined aluminum alloy flat pedals with replaceable traction pins, sealed cartridge bearings, and 9/16-inch chromoly spindle.', 'Bicycle Parts', 'assets/images/products/mountain-bike-ragusa-710-711-712-cnc-pedal.jpg', 469.00, 25, 5],
    ['PROD-PDL-002', 'Tecnic #630 Heavy-Duty Platform Pedals', 'Wide-platform composite pedals with molded grip studs, large foot support area, and dual reflectors for night visibility.', 'Bicycle Parts', 'assets/images/products/tecnic-bicycle-pedal-9-16-630-heavy-duty-bike-pedals-for-mtb-road-bike.jpg', 850.00, 25, 5],
    ['PROD-PDL-003', 'SPD-Compatible Clipless Road/MTB Pedals', 'Dual-sided clipless pedal system with adjustable release tension, mud-shedding open binding mechanism, and included 2-bolt cleats.', 'Bicycle Parts', 'assets/images/products/mountainous-road-bike-self-lock-pedal-bicycle-locking-pedal-mountainous-bicycle.jpg', 1299.00, 25, 5],
    ['PROD-PDL-004', 'Bucklos SPD-SL Road Cleat Pedal Set', 'Race-proven SPD-SL compatible single-sided road clipless pedals with wide stainless steel body contact plate and 6-degree float cleats.', 'Bicycle Parts', 'assets/images/products/bucklos-bike-pedals-for-spd-sl-road-bike-race-pedals-with-cleats-degree-bicycle.jpg', 1029.00, 25, 5],

    // --- Attachments / Headlights ---
    ['PROD-HLT-001', 'Rockbros USB Rechargeable LED Headlight', 'High-lumen aluminum alloy bicycle headlight with USB rechargeable battery, IPX6 water resistance, and 4 brightness modes.', 'Attachments', 'assets/images/products/rockbros-front-light-usb-rechargeable-led-flashlight-bike-cycling-headlight.jpg', 685.00, 25, 5],
    ['PROD-HLT-002', 'Reelight Nova Frictionless Bike Light', 'Eco-friendly contactless magnetic bicycle light powered by wheel rotation without batteries or mechanical friction.', 'Attachments', 'assets/images/products/reelight-nova-front-light.jpg', 665.00, 25, 5],
    ['PROD-HLT-003', 'HaixnFire BL06 2000LM Dual LED Light', 'Ultra-bright 2000 lumens dual-beam bicycle light with digital battery percentage display, Type-C quick charging, and power bank output.', 'Attachments', 'assets/images/products/haixnfire-bl06-bicycle-light-type-c-rechargeable-led-2000-lumens-high-brightness.jpg', 319.00, 25, 5],
    ['PROD-HLT-004', 'CatEye AMPP200 Compact Headlight', 'Compact 200-lumen safety headlight from CatEye featuring OptiCube lens technology for wide beam distribution and helmet or bar mount.', 'Attachments', 'assets/images/products/cateye-ampp200-headlight.jpg', 820.00, 25, 5],

    // --- Attachments / Rear Lights ---
    ['PROD-RLT-001', 'Rockbros Smart Brake-Sensing Rear Light', 'Intelligent tail light that automatically increases brightness when decelerating. Features vibration auto-sleep, IPX6 waterproofing, and Type-C charging.', 'Attachments', 'assets/images/products/rockbros-bike-tail-light-smart-brake-sensing-ipx6-waterproof-type-c.jpg', 474.00, 25, 5],
    ['PROD-RLT-002', 'Meroca Auto On/Off Smart Tail Light', 'CNC alloy casing safety light with auto light sensor, motion activation, multiple strobe patterns, and seatpost/saddle dual mount.', 'Attachments', 'assets/images/products/meroca-smart-bike-tail-light-ultra-bright-bike-light-rechargeable-auto-on-off.jpg', 369.00, 25, 5],
    ['PROD-RLT-003', 'Acebiker AS1010 USB Rechargeable Rear Light', 'High-visibility safety warning taillight featuring multi-LED highlight array, waterproof silicone button, and USB rechargeable lithium battery.', 'Attachments', 'assets/images/products/acebiker-as1010-rechargeable-led-rear-light-bicycle-highlight-warning-taillight.jpg', 135.00, 25, 5],
    ['PROD-RLT-004', 'Forever Cat-Eye Design Bicycle Tail Light', 'Distinctive cat-eye patterned red safety beacon offering wide-angle 180-degree visibility and tool-free silicone strap mounting.', 'Attachments', 'assets/images/products/forever-cat-eye-design-bike-tail-light-rechargeable.jpg', 686.04, 25, 5],

    // --- Attachments / Fenders ---
    ['PROD-FND-001', 'Ragusa RC-Series Universal Mudguard Set', 'Flexible front and rear polypropylene fender set designed to shield riders from mud, water spray, and gravel debris.', 'Attachments', 'assets/images/products/ragusa-fender-rc50-rc60-mountain-bike-road-rc-50-rc-60-rc-70.jpg', 109.00, 25, 5],
    ['PROD-FND-002', 'Oxford Mudstop Quick-Release Road Mudguard', 'Sleek, aerodynamic road bike mudguard with quick-release rubber mounting straps, providing rain protection without aerodynamic drag.', 'Attachments', 'assets/images/products/oxford-mudstop-road-front-mudguard.jpg', 830.01, 25, 5],
    ['PROD-FND-003', 'Chenjie Stainless Steel 700C Full Fenders', 'Classic polished stainless steel full-coverage fender set with rigid mounting stays, ideal for touring, gravel, and vintage city builds.', 'Attachments', 'assets/images/products/chenjie-dustproof-bike-front-rear-fenders-700c-27-stainless-steel-road-bicycle.jpg', 333.73, 25, 5],
    ['PROD-FND-004', 'Gurpil Velox Clip-On Rear Mudguard', 'Minimalist under-saddle rear ass-saver mudguard that clips directly onto saddle rails to block rear tire spray.', 'Attachments', 'assets/images/products/gurpil-velox-rear-mudguard.jpg', 373.66, 25, 5],

    // --- Attachments / Bottle Holders ---
    ['PROD-BTL-001', 'Rockbros Ultralight Colorful Bottle Cage', 'High-strength polycarbonate water bottle holder featuring dual-color metallic finish, rounded edges, and secure bottle retention on rough trails.', 'Attachments', 'assets/images/products/rockbros-bike-water-bottle-cage-ultralight-colorful-cycling-cup-holder.jpg', 159.00, 25, 5],
    ['PROD-BTL-002', 'Enlee Universal Lightweight Bottle Holder', 'Flexible composite bottle cage weighing only 30 grams, engineered for rapid one-handed bottle insertion and extraction while riding.', 'Attachments', 'assets/images/products/enlee-universal-bicycle-bottle-cage-lightweight-bike-water-bottle-holder.jpg', 170.00, 25, 5],
    ['PROD-BTL-003', 'Minoura AB100-4.5 Dura-Cage Bottle Holder', 'Japanese 2017 duralumin aircraft alloy bottle cage known for featherlight weight, exceptional durability, and classic wire cage profile.', 'Attachments', 'assets/images/products/minoura-ab100-4-5-bottle-cage.jpg', 350.00, 25, 5],
    ['PROD-BTL-004', 'Elite Ala High-Retention Bottle Cage', 'Italian-designed bottle cage with wrap-around retention arms, ensuring your bidon stays locked in place over cobbles and drops.', 'Attachments', 'assets/images/products/elite-ala-bottle-cage.jpg', 500.00, 25, 5],

    // --- Safety Gear / Helmets ---
    ['PROD-HLM-001', 'RNOX Aerodynamic Ultralight Bicycle Helmet', 'Aerodynamically vented road and MTB helmet featuring in-mold polycarbonate shell, shock-absorbing EPS foam, and dial-fit adjustment.', 'Safety Gear', 'assets/images/products/rnox-helmet-aerodynamic-safety-ultralight-mtb-bicycle-helmet.jpg', 539.00, 25, 5],
    ['PROD-HLM-002', 'Giro In-Mold All-Road Bicycle Helmet', 'Versatile recreational and commuter cycling helmet with 22 wind-tunnel vents, removable snap-fit visor, and Roc Loc fit system.', 'Safety Gear', 'assets/images/products/giro-bike-helmet-bicycle-helmet.jpg', 689.00, 25, 5],
    ['PROD-HLM-003', 'Specialized Airnet Aero Vented Helmet', 'Clean, minimalist aero road helmet equipped with merino wool brow pad, reflective rear decals, and internal aramid-reinforced skeleton.', 'Safety Gear', 'assets/images/products/specialized-airnet-bike-helmet.jpg', 656.00, 25, 5],
    ['PROD-HLM-004', 'Rockbros Integrated Magnetic Visor Helmet', 'Modern cycling helmet featuring a magnetic detachable tinted sun visor, integrated rechargeable rear LED safety flasher, and bug mesh lining.', 'Safety Gear', 'assets/images/products/rockbros-bike-helmet-lightweight-safety-integrated-adjustable.jpg', 1737.00, 25, 5],

    // --- Safety Gear / Glasses ---
    ['PROD-GLS-001', 'Rockbros Photochromic UV400 Cycling Glasses', 'Intelligent photochromic sunglasses that dynamically darken under sunlight. Features UV400 eye protection and adjustable rubber nose pads.', 'Safety Gear', 'assets/images/products/rockbros-photochromic-cycling-glasses-full-frame-sunglasses-uv-400-protection.jpg', 750.00, 25, 5],
    ['PROD-GLS-002', 'Scicon Polarized UV400 Cycling Goggles', 'High-definition polarized sports eyewear eliminating glare from asphalt and wet roads with shatterproof polycarbonate optics.', 'Safety Gear', 'assets/images/products/scicon-bike-goggles-polarized-cycling-glasses-uv400.jpg', 937.00, 25, 5],
    ['PROD-GLS-003', 'NPC Polarized Multi-Sport Cycling Glasses', 'Wrap-around aerodynamic shield sunglasses providing panoramic field of view, wind resistance, and interchangeable temple arms.', 'Safety Gear', 'assets/images/products/npc-polarized-cycling-sunglasses.jpg', 384.47, 25, 5],
    ['PROD-GLS-004', 'Kapvoe UV400 Full-Frame Sport Sunglasses', 'Full-frame cycling sunglasses engineered with TR90 memory polymer, anti-scratch mirrored lens coating, and prescription lens insert clip.', 'Safety Gear', 'assets/images/products/kapvoe-uv400-outdoor-cycling-sunglasses.jpg', 305.88, 25, 5],

    // --- Safety Gear / Gloves ---
    ['PROD-GLV-001', 'Rockbros Ice Silk Half-Finger Cycling Gloves', 'Breathable ice silk summer cycling gloves with silicone palm grip pads, thumb sweat wipe, and quick-off finger pulls.', 'Safety Gear', 'assets/images/products/rockbros-ice-silk-cycling-gloves-anti-slip-short-protect-anti-uv-half-finger.jpg', 402.00, 25, 5],
    ['PROD-GLV-002', 'Rockbros Shockproof Gel-Pad Riding Gloves', 'All-season road and MTB gloves equipped with multi-zone liquid gel palm inserts, vibration damping, and conductive touchscreen fingertips.', 'Safety Gear', 'assets/images/products/rockbros-cycling-gloves-touch-screen-summer-autumn-spring-mtb-road-bike-gloves.jpg', 599.00, 25, 5],
    ['PROD-GLV-003', 'Giyo Full-Finger Touchscreen Gel Gloves', 'Full-finger mountain and gravel riding gloves featuring high-density gel palm cushioning, micro-mesh ventilation, and touchscreen thumbs.', 'Safety Gear', 'assets/images/products/giyo-touch-screen-long-full-fingers-gel-sports-cycling-gloves.jpg', 460.00, 25, 5],
    ['PROD-GLV-004', 'West Biking Half-Finger Shockproof Gloves', 'Ergonomic short-finger cycling gloves featuring high-elasticity Lycra backing, thickened SBR palm padding, and hook-and-loop wrist closures.', 'Safety Gear', 'assets/images/products/west-biking-half-finger-cycling-gloves-shockproof-style.jpg', 1016.12, 25, 5],

    // --- Safety Gear / Chain Locks ---
    ['PROD-LCK-001', 'ULAC Resettable Combination Chain Lock', 'Heavy-duty braided steel chain lock encased in a protective nylon sleeve to prevent frame scratches, with a 4-digit keyless combination.', 'Safety Gear', 'assets/images/products/ulac-combination-chain-lock-by-rockbros-blue.jpg', 800.00, 25, 5],
    ['PROD-LCK-002', 'West Biking 5-Digit High-Security Chain Lock', 'Hardened alloy steel chain lock featuring a 100,000-combination 5-digit security cylinder, weatherproof outer jacket, and 100cm length.', 'Safety Gear', 'assets/images/products/west-bike-chain-lock-5-digit-code-lock-65cm-100cm-120cm-150cm-mountain-road.jpg', 779.00, 25, 5],
    ['PROD-LCK-003', 'Kryptonite Keeper 585 Integrated Chain Lock', 'End-link design securing chain directly into deadbolt mechanism, featuring 5mm four-sided manganese steel chain links and pick-resistant disc cylinder.', 'Safety Gear', 'assets/images/products/kryptonite-keeper-585-chain-lock.jpg', 1593.08, 25, 5],
    ['PROD-LCK-004', 'Jinjian Alloy Anti-Theft Bike Chain Lock', 'Reinforced alloy security lock with dual brass keys, dustproof keyway cover, and weather-resistant vinyl protective sheath.', 'Safety Gear', 'assets/images/products/jinjian-bicycle-alloy-anti-theft-chain-lock-durable-and-wear-resistant.jpg', 226.00, 25, 5],
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