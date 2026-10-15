<?php
$db_host = "localhost";
$db_username = "root";
$db_passwd = "";
$db_name = "pedalworks_db";

try {
    $conn = @mysqli_connect($db_host, $db_username, $db_passwd, $db_name);
    if (!$conn) {
        $conn = @mysqli_connect($db_host, $db_username, $db_passwd);
        if ($conn) {
            @mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$db_name`");
            @mysqli_select_db($conn, $db_name);
        }
    }
    if ($conn) {
        // Ensure VIEWS and schema for Order Management exist
        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `customerCart` (
            `customerCartID` INT NOT NULL AUTO_INCREMENT,
            `customerAccountID` INT NOT NULL,
            `productID` INT NOT NULL,
            `quantity` INT NOT NULL,
            PRIMARY KEY (`customerCartID`),
            UNIQUE INDEX `uq_cart_account_product` (`customerAccountID` ASC, `productID` ASC),
            CONSTRAINT `chk_cart_quantity` CHECK (`quantity` > 0)
        ) ENGINE = InnoDB");

        @mysqli_query($conn, "ALTER TABLE `productSale` MODIFY COLUMN `status` 
            ENUM('Pending', 'Received', 'In Progress', 'Ready for Pickup', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Received'");

        @mysqli_query($conn, "CREATE OR REPLACE VIEW `vw_order_transaction_details` AS
            SELECT 
                ps.productSaleID,
                ps.orderDate,
                ps.status AS orderStatus,
                ps.paymentMethod,
                ps.pickupMethod,
                ps.deliveryAddress,
                ca.customerAccountID,
                ca.username AS customerUsername,
                c.customerID,
                CONCAT(c.firstName, IF(c.lastName IS NOT NULL AND c.lastName <> '', CONCAT(' ', c.lastName), '')) AS customerName,
                c.email AS customerEmail,
                c.address AS customerRegisteredAddress,
                olp.orderLineProductID,
                olp.productID,
                olp.quantity,
                p.sku,
                p.name AS productName,
                p.image AS productImage,
                p.price AS unitPrice,
                (olp.quantity * p.price) AS lineSubtotal,
                pc.name AS categoryName
            FROM `productSale` ps
            JOIN `customerAccount` ca ON ps.customerAccountID = ca.customerAccountID
            JOIN `customer` c ON ca.customerID = c.customerID
            JOIN `orderLineProduct` olp ON ps.productSaleID = olp.productSaleID
            JOIN `product` p ON olp.productID = p.productID
            LEFT JOIN `productCategory` pc ON p.productCategoryID = pc.productCategoryID");

        @mysqli_query($conn, "CREATE OR REPLACE VIEW `vw_order_summary` AS
            SELECT 
                ps.productSaleID,
                ps.orderDate,
                ps.status AS orderStatus,
                ps.paymentMethod,
                ps.pickupMethod,
                ps.deliveryAddress,
                ca.customerAccountID,
                ca.username AS customerUsername,
                c.customerID,
                CONCAT(c.firstName, IF(c.lastName IS NOT NULL AND c.lastName <> '', CONCAT(' ', c.lastName), '')) AS customerName,
                c.email AS customerEmail,
                COUNT(olp.orderLineProductID) AS totalDistinctItems,
                COALESCE(SUM(olp.quantity), 0) AS totalItemCount,
                COALESCE(SUM(olp.quantity * p.price), 0.00) AS grandTotal
            FROM `productSale` ps
            JOIN `customerAccount` ca ON ps.customerAccountID = ca.customerAccountID
            JOIN `customer` c ON ca.customerID = c.customerID
            LEFT JOIN `orderLineProduct` olp ON ps.productSaleID = olp.productSaleID
            LEFT JOIN `product` p ON olp.productID = p.productID
            GROUP BY 
                ps.productSaleID,
                ps.orderDate,
                ps.status,
                ps.paymentMethod,
                ps.pickupMethod,
                ps.deliveryAddress,
                ca.customerAccountID,
                ca.username,
                c.customerID,
                customerName,
                c.email");
    }
} catch (mysqli_sql_exception $e) {
    $conn = false;
}

