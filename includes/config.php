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
            ENUM('Pending', 'Received', 'In Progress', 'Ready for Pickup', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending'");

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

        @mysqli_query($conn, "ALTER TABLE `customerService` MODIFY COLUMN `status` 
            ENUM('Pending', 'Received', 'In Progress', 'Ready for Pickup', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending'");

        @mysqli_query($conn, "CREATE OR REPLACE VIEW `vw_service_request_details` AS
            SELECT 
                cs.customerServiceID,
                cs.requestDate,
                cs.scheduledDate,
                cs.notes,
                cs.status AS serviceStatus,
                cs.productSaleID,
                s.serviceID,
                s.sku AS serviceSku,
                s.name AS serviceName,
                s.description AS serviceDescription,
                s.price AS servicePrice,
                sc.name AS serviceCategoryName,
                ca.customerAccountID,
                ca.username AS customerUsername,
                c.customerID,
                CONCAT(c.firstName, IF(c.lastName IS NOT NULL AND c.lastName <> '', CONCAT(' ', c.lastName), '')) AS customerName,
                c.email AS customerEmail,
                c.address AS customerAddress,
                adm.adminAccountID,
                adm.username AS technicianUsername
            FROM `customerService` cs
            JOIN `service` s ON cs.serviceID = s.serviceID
            LEFT JOIN `serviceCategory` sc ON s.serviceCategoryID = sc.serviceCategoryID
            JOIN `customerAccount` ca ON cs.customerAccountID = ca.customerAccountID
            JOIN `customer` c ON ca.customerID = c.customerID
            LEFT JOIN `adminAccount` adm ON cs.adminAccountID = adm.adminAccountID");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `review` (
            `reviewID` INT NOT NULL AUTO_INCREMENT,
            `customerAccountID` INT NOT NULL,
            `productID` INT NULL,
            `serviceID` INT NULL,
            `rating` INT NOT NULL DEFAULT 5,
            `reviewText` TEXT NOT NULL,
            `cleanText` TEXT NOT NULL,
            `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`reviewID`),
            INDEX `idx_review_customer` (`customerAccountID` ASC),
            INDEX `idx_review_product` (`productID` ASC),
            INDEX `idx_review_service` (`serviceID` ASC),
            CONSTRAINT `fk_review_customerAccount`
                FOREIGN KEY (`customerAccountID`) REFERENCES `customerAccount` (`customerAccountID`)
                ON DELETE CASCADE ON UPDATE NO ACTION,
            CONSTRAINT `fk_review_product`
                FOREIGN KEY (`productID`) REFERENCES `product` (`productID`)
                ON DELETE CASCADE ON UPDATE NO ACTION,
            CONSTRAINT `fk_review_service`
                FOREIGN KEY (`serviceID`) REFERENCES `service` (`serviceID`)
                ON DELETE CASCADE ON UPDATE NO ACTION,
            CONSTRAINT `chk_review_target`
                CHECK ((`productID` IS NOT NULL AND `serviceID` IS NULL) OR (`productID` IS NULL AND `serviceID` IS NOT NULL)),
            CONSTRAINT `chk_review_rating`
                CHECK (`rating` >= 1 AND `rating` <= 5)
        ) ENGINE = InnoDB");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `productImage` (
            `productImageID` INT NOT NULL AUTO_INCREMENT,
            `productID` INT NOT NULL,
            `imagePath` VARCHAR(255) NOT NULL,
            `isPrimary` TINYINT(1) NOT NULL DEFAULT 0,
            `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`productImageID`),
            INDEX `idx_productImage_product` (`productID` ASC),
            CONSTRAINT `fk_productImage_product`
                FOREIGN KEY (`productID`) REFERENCES `product` (`productID`)
                ON DELETE CASCADE ON UPDATE NO ACTION
        ) ENGINE = InnoDB");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `serviceImage` (
            `serviceImageID` INT NOT NULL AUTO_INCREMENT,
            `serviceID` INT NOT NULL,
            `imagePath` VARCHAR(255) NOT NULL,
            `isPrimary` TINYINT(1) NOT NULL DEFAULT 0,
            `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`serviceImageID`),
            INDEX `idx_serviceImage_service` (`serviceID` ASC),
            CONSTRAINT `fk_serviceImage_service`
                FOREIGN KEY (`serviceID`) REFERENCES `service` (`serviceID`)
                ON DELETE CASCADE ON UPDATE NO ACTION
        ) ENGINE = InnoDB");

        @mysqli_query($conn, "INSERT INTO `productImage` (`productID`, `imagePath`, `isPrimary`)
            SELECT p.productID, p.image, 1 
            FROM `product` p
            LEFT JOIN `productImage` pi ON p.productID = pi.productID
            WHERE p.image IS NOT NULL AND p.image != '' AND pi.productImageID IS NULL");

        @mysqli_query($conn, "INSERT INTO `serviceImage` (`serviceID`, `imagePath`, `isPrimary`)
            SELECT s.serviceID, s.image, 1 
            FROM `service` s
            LEFT JOIN `serviceImage` si ON s.serviceID = si.serviceID
            WHERE s.image IS NOT NULL AND s.image != '' AND si.serviceImageID IS NULL");
    }
} catch (mysqli_sql_exception $e) {
    $conn = false;
}

