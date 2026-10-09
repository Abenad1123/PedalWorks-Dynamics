<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('./includes/config.php');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Fetch service categories for filter dropdown
$categories = [];
if ($conn) {
    $catQuery = @mysqli_query($conn, "SELECT * FROM serviceCategory ORDER BY name ASC");
    if ($catQuery) {
        while ($row = mysqli_fetch_assoc($catQuery)) {
            $categories[] = $row;
        }
    }
}

// Build service search and filter query
$services = [];
if ($conn) {
    $where = ["s.endDate IS NULL"];
    $params = [];
    $types = "";

    if ($search !== '') {
        $where[] = "(s.name LIKE ? OR s.description LIKE ? OR s.sku LIKE ?)";
        $searchWildcard = "%" . $search . "%";
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $types .= "sss";
    }

    if ($category !== '') {
        $where[] = "sc.name = ?";
        $params[] = $category;
        $types .= "s";
    }

    $sql = "SELECT s.*, sc.name AS categoryName 
            FROM service s 
            LEFT JOIN serviceCategory sc ON s.serviceCategoryID = sc.serviceCategoryID 
            WHERE " . implode(" AND ", $where) . " 
            ORDER BY s.name ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $services[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Services - PedalWorks Dynamics</title>
</head>
<body>
    <header>
        <nav>
            <a href="index.php">Home</a> | 
            <a href="index.php#about">About</a> | 
            <a href="products.php">Products</a> | 
            <a href="services.php"><strong>Services</strong></a>
        </nav>
        <h1>Workshop &amp; Repair Services Catalog</h1>
    </header>

    <main>
        <section>
            <h2>Search and Filter</h2>
            <form method="GET" action="services.php">
                <label for="search">Search:</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search services...">

                <label for="category">Category:</label>
                <select id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['name']); ?>" <?php echo ($category === $cat['name']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Filter</button>
                <?php if ($search !== '' || $category !== ''): ?>
                    <a href="services.php"><button type="button">Reset</button></a>
                <?php endif; ?>
            </form>
        </section>

        <hr>

        <section>
            <h2>Services List (<?php echo count($services); ?> found)</h2>

            <?php if (empty($services)): ?>
                <p>No services found matching your criteria.</p>
            <?php else: ?>
                <table border="1" cellpadding="8" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>SKU</th>
                            <th>Service Name</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Labor Fee / Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $s): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($s['image']) && file_exists(__DIR__ . '/' . $s['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($s['image']); ?>" alt="<?php echo htmlspecialchars($s['name']); ?>" width="100">
                                    <?php else: ?>
                                        No Image
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($s['sku']); ?></td>
                                <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($s['categoryName'] ?? 'Uncategorized'); ?></td>
                                <td><?php echo htmlspecialchars($s['description'] ?? ''); ?></td>
                                <td>&#8369;<?php echo number_format($s['price'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <br>
        <hr>
        <p><a href="index.php">Back to Homepage</a></p>
    </footer>
</body>
</html>
