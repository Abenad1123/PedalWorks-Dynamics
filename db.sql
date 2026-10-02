-- =====================================================================
-- PedalWorks Dynamics: Bicycle Shop Management System
-- Plain schema only (MySQL 8.0.16+ for CHECK constraints).
-- No stored procedures, views, or DELIMITER: logic lives in the app.
--
-- Price history: SCD Type 2 on `product` and `service`.
--   * productID / serviceID identifies ONE VERSION of an item.
--   * sku identifies the item itself across versions.
--   * A version is current when endDate IS NULL.
--   * startDate is inclusive, endDate is exclusive.
--
-- The `status` columns on productSale and customerService are kept from
-- your original script. Replace them with your rewritten versions.
-- =====================================================================

CREATE SCHEMA IF NOT EXISTS `pedalworks_db` DEFAULT CHARACTER SET utf8mb4;
USE `pedalworks_db`;

CREATE TABLE IF NOT EXISTS `customer` (
  `customerID` INT NOT NULL AUTO_INCREMENT,
  `firstName` VARCHAR(45) NOT NULL,
  `lastName` VARCHAR(45) NULL,
  `birthDate` DATE NULL,
  `email` VARCHAR(255) NOT NULL,
  `address` TEXT NOT NULL,
  PRIMARY KEY (`customerID`),
  UNIQUE INDEX `email_UNIQUE` (`email` ASC)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `customerAccount` (
  `customerAccountID` INT NOT NULL AUTO_INCREMENT,
  `customerID` INT NOT NULL,
  `username` VARCHAR(45) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `status` ENUM('Active', 'Inactive', 'Disabled') NOT NULL,
  PRIMARY KEY (`customerAccountID`),
  UNIQUE INDEX `customerID_UNIQUE` (`customerID` ASC),
  UNIQUE INDEX `username_UNIQUE` (`username` ASC),
  CONSTRAINT `fk_customerAccount_customer`
    FOREIGN KEY (`customerID`) REFERENCES `customer` (`customerID`)
    ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `adminAccount` (
  `adminAccountID` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(45) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('Accounting Administrator', 'Super Administrator',
              'Inventory Manager', 'Service & Repair Manager') NOT NULL,
  PRIMARY KEY (`adminAccountID`),
  UNIQUE INDEX `admin_username_UNIQUE` (`username` ASC)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `productCategory` (
  `productCategoryID` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`productCategoryID`),
  UNIQUE INDEX `productCategory_name_UNIQUE` (`name` ASC)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `serviceCategory` (
  `serviceCategoryID` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`serviceCategoryID`),
  UNIQUE INDEX `serviceCategory_name_UNIQUE` (`name` ASC)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `product` (
  `productID` INT NOT NULL AUTO_INCREMENT,
  `sku` VARCHAR(45) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` LONGTEXT NULL,
  `productCategoryID` INT NOT NULL,
  `image` VARCHAR(255) NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `stock` INT NOT NULL,
  `lowStockThreshold` INT NOT NULL DEFAULT 5,
  `startDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `endDate` DATETIME NULL,
  -- 1 while current, NULL when superseded/archived.
  -- Unique (sku, isCurrent) allows at most ONE current version per SKU.
  `isCurrent` TINYINT GENERATED ALWAYS AS (IF(`endDate` IS NULL, 1, NULL)) STORED,
  PRIMARY KEY (`productID`),
  UNIQUE INDEX `uq_product_sku_current` (`sku` ASC, `isCurrent` ASC),
  UNIQUE INDEX `uq_product_sku_start` (`sku` ASC, `startDate` ASC),
  INDEX `idx_product_name` (`name` ASC),
  INDEX `fk_product_category_idx` (`productCategoryID` ASC),
  CONSTRAINT `fk_product_category`
    FOREIGN KEY (`productCategoryID`) REFERENCES `productCategory` (`productCategoryID`)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `chk_product_price` CHECK (`price` >= 0),
  CONSTRAINT `chk_product_stock` CHECK (`stock` >= 0),
  CONSTRAINT `chk_product_dates` CHECK (`endDate` IS NULL OR `endDate` > `startDate`)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `service` (
  `serviceID` INT NOT NULL AUTO_INCREMENT,
  `sku` VARCHAR(45) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `image` VARCHAR(255) NULL,
  `description` LONGTEXT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `serviceCategoryID` INT NOT NULL,
  `startDate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `endDate` DATETIME NULL,
  `isCurrent` TINYINT GENERATED ALWAYS AS (IF(`endDate` IS NULL, 1, NULL)) STORED,
  PRIMARY KEY (`serviceID`),
  UNIQUE INDEX `uq_service_sku_current` (`sku` ASC, `isCurrent` ASC),
  UNIQUE INDEX `uq_service_sku_start` (`sku` ASC, `startDate` ASC),
  INDEX `idx_service_name` (`name` ASC),
  INDEX `fk_service_category_idx` (`serviceCategoryID` ASC),
  CONSTRAINT `fk_service_category`
    FOREIGN KEY (`serviceCategoryID`) REFERENCES `serviceCategory` (`serviceCategoryID`)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `chk_service_price` CHECK (`price` >= 0),
  CONSTRAINT `chk_service_dates` CHECK (`endDate` IS NULL OR `endDate` > `startDate`)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `customerCart` (
  `customerCartID` INT NOT NULL AUTO_INCREMENT,
  `customerAccountID` INT NOT NULL,
  `productID` INT NOT NULL,
  `quantity` INT NOT NULL,
  PRIMARY KEY (`customerCartID`),
  UNIQUE INDEX `uq_cart_account_product` (`customerAccountID` ASC, `productID` ASC),
  INDEX `fk_customerCart_product1_idx` (`productID` ASC),
  CONSTRAINT `fk_customerCart_customerAccount1`
    FOREIGN KEY (`customerAccountID`) REFERENCES `customerAccount` (`customerAccountID`)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_customerCart_product1`
    FOREIGN KEY (`productID`) REFERENCES `product` (`productID`)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `chk_cart_quantity` CHECK (`quantity` > 0)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `productSale` (
  `productSaleID` INT NOT NULL AUTO_INCREMENT,
  `customerAccountID` INT NOT NULL,
  `paymentMethod` ENUM('Cash on Delivery', 'Cash on Pickup') NOT NULL,
  `pickupMethod` ENUM('Delivery', 'In-Store Pickup') NOT NULL,
  `deliveryAddress` TEXT NULL,
  `status` ENUM('Received', 'In Progress', 'Ready for Pickup') NOT NULL,
  `orderDate` DATETIME NOT NULL,
  PRIMARY KEY (`productSaleID`),
  INDEX `fk_productSale_customerAccount1_idx` (`customerAccountID` ASC),
  INDEX `idx_productSale_orderDate` (`orderDate` ASC),
  CONSTRAINT `fk_productSale_customerAccount1`
    FOREIGN KEY (`customerAccountID`) REFERENCES `customerAccount` (`customerAccountID`)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `chk_sale_delivery_address`
    CHECK (`pickupMethod` <> 'Delivery' OR `deliveryAddress` IS NOT NULL)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `orderLineProduct` (
  `orderLineProductID` INT NOT NULL AUTO_INCREMENT,
  `productSaleID` INT NOT NULL,
  `productID` INT NOT NULL,
  `quantity` INT NOT NULL,
  PRIMARY KEY (`orderLineProductID`),
  UNIQUE INDEX `uq_orderLine_sale_product` (`productSaleID` ASC, `productID` ASC),
  INDEX `fk_orderLineProduct_product1_idx` (`productID` ASC),
  CONSTRAINT `fk_orderLineProduct_product1`
    FOREIGN KEY (`productID`) REFERENCES `product` (`productID`)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_orderLineProduct_productSale1`
    FOREIGN KEY (`productSaleID`) REFERENCES `productSale` (`productSaleID`)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `chk_orderLine_quantity` CHECK (`quantity` > 0)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `customerService` (
  `customerServiceID` INT NOT NULL AUTO_INCREMENT,
  `serviceID` INT NOT NULL,
  `customerAccountID` INT NOT NULL,
  `adminAccountID` INT NULL,
  `productSaleID` INT NULL,
  `requestDate` DATETIME NOT NULL,
  `scheduledDate` DATETIME NULL,
  `notes` TEXT NULL,
  `status` ENUM('Completed', 'Cancelled', 'Pending') NOT NULL,
  PRIMARY KEY (`customerServiceID`),
  INDEX `fk_customerService_service1_idx` (`serviceID` ASC),
  INDEX `fk_customerService_customerAccount1_idx` (`customerAccountID` ASC),
  INDEX `fk_customerService_adminAccount1_idx` (`adminAccountID` ASC),
  INDEX `fk_customerService_productSale1_idx` (`productSaleID` ASC),
  CONSTRAINT `fk_customerService_service1`
    FOREIGN KEY (`serviceID`) REFERENCES `service` (`serviceID`)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_customerService_customerAccount1`
    FOREIGN KEY (`customerAccountID`) REFERENCES `customerAccount` (`customerAccountID`)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_customerService_adminAccount1`
    FOREIGN KEY (`adminAccountID`) REFERENCES `adminAccount` (`adminAccountID`)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_customerService_productSale1`
    FOREIGN KEY (`productSaleID`) REFERENCES `productSale` (`productSaleID`)
    ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `expense` (
  `expenseID` INT NOT NULL AUTO_INCREMENT,
  `category` ENUM('Water', 'Electricity', 'Employee Salary', 'Rent', 'Other') NOT NULL,
  `description` VARCHAR(255) NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `expenseDate` DATETIME NOT NULL,
  `recordedBy` INT NOT NULL,
  PRIMARY KEY (`expenseID`),
  INDEX `fk_expense_adminAccount1_idx` (`recordedBy` ASC),
  INDEX `idx_expense_date` (`expenseDate` ASC),
  CONSTRAINT `fk_expense_adminAccount1`
    FOREIGN KEY (`recordedBy`) REFERENCES `adminAccount` (`adminAccountID`)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `chk_expense_amount` CHECK (`amount` > 0),
  CONSTRAINT `chk_expense_other_desc`
    CHECK (`category` <> 'Other' OR `description` IS NOT NULL)
) ENGINE = InnoDB;

-- =====================================================================
-- Notes for the application code (not executed)
--
-- Price change (one transaction, same NOW() for both statements):
--   1. UPDATE product SET endDate = :now WHERE sku = :sku AND endDate IS NULL;
--   2. INSERT a new product row: same sku/name/category/etc., the new price,
--      the same stock, startDate = :now, endDate = NULL.
--   Never UPDATE price in place.
--
-- Checkout (one transaction, one NOW() used as productSale.orderDate):
--   * Resolve each cart item to the current version of its SKU.
--   * Deduct stock on that current version; fail if archived or short.
--   * Insert productSale, insert orderLineProduct rows pointing at the
--     current versions, then clear the customer's cart.
--
-- Price at a given date (by SKU):
--   WHERE sku = :sku AND startDate <= :date
--     AND (endDate IS NULL OR :date < endDate)
--
-- Catalog: WHERE endDate IS NULL.   Low stock: stock < lowStockThreshold.
-- =====================================================================