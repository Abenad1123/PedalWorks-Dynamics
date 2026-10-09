<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('./includes/config.php');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Fetch categories for filter dropdown
$categories = [];
if ($conn) {
    $catQuery = @mysqli_query($conn, "SELECT * FROM productCategory ORDER BY name ASC");
    if ($catQuery) {
        while ($row = mysqli_fetch_assoc($catQuery)) {
            $categories[] = $row;
        }
    }
}

// Build product search and filter query
$products = [];
if ($conn) {
    $where = ["p.endDate IS NULL"];
    $params = [];
    $types = "";

    if ($search !== '') {
        $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
        $searchWildcard = "%" . $search . "%";
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $types .= "sss";
    }

    if ($category !== '') {
        $where[] = "pc.name = ?";
        $params[] = $category;
        $types .= "s";
    }

    $sql = "SELECT p.*, pc.name AS categoryName 
            FROM product p 
            LEFT JOIN productCategory pc ON p.productCategoryID = pc.productCategoryID 
            WHERE " . implode(" AND ", $where) . " 
            ORDER BY p.name ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $products[] = $row;
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
    <title>Products - PedalWorks Dynamics</title>
</head>
<body>
    <header>
        <nav>
            <a href="index.php">Home</a> | 
            <a href="index.php#about">About</a> | 
            <a href="products.php"><strong>Products</strong></a> | 
            <a href="services.php">Services</a>
        </nav>
        <h1>Products Catalog</h1>
    </header>

    <main>
        <section>
            <h2>Search and Filter</h2>
            <form method="GET" action="products.php">
                <label for="search">Search:</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search products...">

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
                    <a href="products.php"><button type="button">Reset</button></a>
                <?php endif; ?>
            </form>
        </section>

        <hr>

        <section>
            <h2>Product List (<?php echo count($products); ?> found)</h2>

            <?php if (empty($products)): ?>
                <p>No products found matching your criteria.</p>
            <?php else: ?>
                <table border="1" cellpadding="8" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Price</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($p['image']) && file_exists(__DIR__ . '/' . $p['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" width="100">
                                    <?php else: ?>
                                        No Image
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($p['sku']); ?></td>
                                <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($p['categoryName'] ?? 'Uncategorized'); ?></td>
                                <td><?php echo htmlspecialchars($p['description'] ?? ''); ?></td>
                                <td>&#8369;<?php echo number_format($p['price'], 2); ?></td>
                                <td>
                                    <?php 
                                    if ($p['stock'] <= 0) {
                                        echo "Out of Stock (0)";
                                    } elseif ($p['stock'] <= $p['lowStockThreshold']) {
                                        echo "Low Stock (" . (int)$p['stock'] . ")";
                                    } else {
                                        echo "In Stock (" . (int)$p['stock'] . ")";
                                    }
                                    ?>
                                </td>
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
