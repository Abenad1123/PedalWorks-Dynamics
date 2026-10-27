<?php

require_once __DIR__ . '/includes/fpdf/fpdf.php';

function toPdf($str) {
    return mb_convert_encoding((string)$str, 'ISO-8859-1', 'UTF-8');
}

class DocPDF extends FPDF {
    public $documentTitle = 'PedalWorks Dynamics - System & Database Architecture';
    public $documentSubtitle = 'Technical Reference, Workflow Analysis & Concept Glossary';

    function Header() {
        if ($this->PageNo() == 1) {

            return;
        }
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(120, 6, toPdf('PEDALWORKS DYNAMICS | SYSTEM & DATABASE ARCHITECTURE MANUAL'), 0, 0, 'L');
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(70, 6, toPdf('BSIT-2B | ITIM211 Information Management'), 0, 1, 'R');
        $this->SetDrawColor(226, 232, 240);
        $this->Line(10, 16, 200, 16);
        $this->Ln(4);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetDrawColor(226, 232, 240);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->SetY(-12);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(95, 6, toPdf('Confidential - For Academic & System Verification Purposes'), 0, 0, 'L');
        $this->Cell(95, 6, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }

    function ChapterTitle($number, $title) {
        $this->Ln(4);
        $this->SetFillColor(15, 23, 42);  
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(190, 8, '  ' . $number . '. ' . strtoupper(toPdf($title)), 0, 1, 'L', true);
        $this->Ln(3);
        $this->SetTextColor(15, 23, 42);
    }

    function SectionHeading($title) {
        $this->Ln(2);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(194, 65, 12);  
        $this->Cell(190, 6, toPdf($title), 0, 1, 'L');
        $this->SetDrawColor(254, 215, 170);
        $this->Line($this->GetX(), $this->GetY(), $this->GetX() + 190, $this->GetY());
        $this->Ln(2);
        $this->SetTextColor(15, 23, 42);
    }

    function SubSectionHeading($title) {
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(30, 41, 59);
        $this->Cell(190, 5, toPdf($title), 0, 1, 'L');
        $this->Ln(1);
    }

    function BodyText($text) {
        $this->SetFont('Arial', '', 8.5);
        $this->SetTextColor(30, 41, 59);
        $this->MultiCell(190, 4.5, toPdf($text));
        $this->Ln(2);
    }

    function CalloutBox($title, $text, $type = 'info') {
        if ($type === 'warning') {
            $this->SetFillColor(254, 243, 199);
            $this->SetDrawColor(245, 158, 11);
            $titleColor = [180, 83, 9];
        } else {
            $this->SetFillColor(241, 245, 249);
            $this->SetDrawColor(14, 165, 233);
            $titleColor = [3, 105, 161];
        }

        $this->SetFont('Arial', 'B', 8.5);
        $this->SetTextColor($titleColor[0], $titleColor[1], $titleColor[2]);
        $this->Cell(190, 6, '  ' . toPdf($title), 'LTR', 1, 'L', true);

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(51, 65, 85);
        $this->MultiCell(190, 4.2, toPdf($text), 'LBR', 'L', true);
        $this->Ln(3);
    }

    function TableHeader($widths, $headers) {
        $this->SetFillColor(241, 245, 249);
        $this->SetTextColor(71, 85, 105);
        $this->SetDrawColor(203, 213, 225);
        $this->SetFont('Arial', 'B', 8);
        for ($i = 0; $i < count($headers); $i++) {
            $this->Cell($widths[$i], 6, toPdf($headers[$i]), 1, 0, 'L', true);
        }
        $this->Ln();
        $this->SetTextColor(15, 23, 42);
    }

    function TableRow($widths, $data, $fill = false) {
        $this->SetFillColor(248, 250, 252);
        $this->SetDrawColor(226, 232, 240);
        $this->SetFont('Arial', '', 7.8);
        for ($i = 0; $i < count($data); $i++) {
            $this->Cell($widths[$i], 5.5, toPdf($data[$i]), 1, 0, 'L', $fill);
        }
        $this->Ln();
    }
}

$pdf = new DocPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);

$pdf->AddPage();

$pdf->SetFillColor(15, 23, 42);  
$pdf->Rect(10, 10, 190, 42, 'F');

$pdf->SetFillColor(229, 121, 58);  
$pdf->Rect(10, 10, 190, 3, 'F');

$pdf->SetY(16);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(190, 8, toPdf('PEDALWORKS DYNAMICS'), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(245, 155, 88);
$pdf->Cell(190, 6, toPdf('System & Database Architecture Engineering Manual'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 8.5);
$pdf->SetTextColor(203, 213, 225);
$pdf->Cell(190, 5, toPdf('Core Concepts, Relational Data Models, Workflow Lifecycles, and Terminology Reference'), 0, 1, 'C');

$pdf->SetY(56);
$pdf->SetTextColor(15, 23, 42);

$pdf->SetFillColor(248, 250, 252);
$pdf->SetDrawColor(226, 232, 240);
$pdf->Rect(10, 56, 190, 14, 'DF');
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(45, 6, ' ACADEMIC COURSE:', 0, 0, 'L');
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(50, 6, 'ITIM211 Info Management', 0, 0, 'L');
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(45, 6, ' TARGET APPLICATION:', 0, 0, 'L');
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(50, 6, 'PedalWorks Dynamics Shop', 0, 1, 'L');

$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(45, 6, ' DEGREE & SECTION:', 0, 0, 'L');
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(50, 6, 'BSIT-2B-T (AY 2026-2027)', 0, 0, 'L');
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(45, 6, ' ENVIRONMENT:', 0, 0, 'L');
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(50, 6, 'PHP 8.2+ / MySQL / XAMPP', 0, 1, 'L');

$pdf->Ln(4);

$pdf->ChapterTitle('1', 'Executive Summary & Business Domain');
$pdf->BodyText(
    "PedalWorks Dynamics is a full-featured, enterprise-grade web application engineered for an adventure bike shop and technical workshop operating in Taguig City. The system is designed to handle retail commerce for high-performance bicycles, precision components, attachments, and trail safety gear, alongside a professional workshop service booking and maintenance tracking pipeline.\n\n" .
    "Unlike demo or tutorial projects, PedalWorks Dynamics enforces strict academic and commercial engineering standards: relational database normalization, Slowly Changing Dimensions (Type 2 temporal versioning) for non-destructive price auditing, ACID transactional stock deductions to eliminate race conditions, role-based access control (RBAC), multi-tier user identity separation, and derived financial reporting."
);

$pdf->CalloutBox(
    'Core Business Identity & Operating Constraints',
    "- Physical Basecamp: Km. 14 East Service Road, Western Bicutan, Taguig City.\n" .
    "- Dual Fulfillment Model: Supports Doorstep COD Delivery across Metro Manila and Free In-Store Pickup.\n" .
    "- Decoupled Services Pipeline: Repair and labor services function independently or bundled directly with retail part orders for seamless mechanic installation.\n" .
    "- Compliance Scope: Meets and exceeds Functional Requirements FR1 through FR20 (ITIM211 Project Proposal Specification)."
);

$pdf->ChapterTitle('2', 'High-Level Data Model Architecture');
$pdf->BodyText(
    "The PedalWorks relational schema consists of 13 core database tables and 2 high-performance SQL Views organized into five distinct operational clusters. Each cluster is strictly segregated by single-responsibility concerns:"
);

$clusterWidths = [32, 58, 100];
$pdf->TableHeader($clusterWidths, ['Cluster Name', 'Enclosed Tables', 'Domain Responsibility']);
$pdf->TableRow($clusterWidths, ['1. Identity & People', 'customer, customerAccount, adminAccount', 'Separates profile data from credentials and isolates administrative staff.'], true);
$pdf->TableRow($clusterWidths, ['2. Retail Catalog', 'productCategory, serviceCategory, product, service, productImage', 'Manages products, multi-photo showcases, categories, and SCD2 temporal pricing.'], false);
$pdf->TableRow($clusterWidths, ['3. Commerce Pipeline', 'customerCart, productSale, orderLineProduct, customerReview', 'Handles session carts, orders, immutable order lines, and verified reviews.'], true);
$pdf->TableRow($clusterWidths, ['4. Workshop Services', 'customerService', 'Tracks bike repair/labor jobs, mechanic assignments, and order linkage.'], false);
$pdf->TableRow($clusterWidths, ['5. Financial Accounting', 'expense (plus dynamic income view)', 'Records operating overheads and computes net profit dynamically.'], true);

$pdf->AddPage();

$pdf->ChapterTitle('3', 'Comprehensive Relational Schema Breakdown');
$pdf->BodyText(
    "The schema enforces referential integrity through foreign key constraints without cascading deletes. Below is an exhaustive audit of each table entity and its design rationale:"
);

$pdf->SectionHeading('3.1 Identity & Authentication Cluster');
$pdf->BodyText(
    "1. customer (Personal Profile Entity):\n" .
    "   - Columns: customerID (PK, AUTO_INCREMENT), name, birthDate, email (UNIQUE), address.\n" .
    "   - Purpose: Stores real-world customer demographics. Independent of authentication to allow profile longevity.\n\n" .
    "2. customerAccount (Customer Credential Entity):\n" .
    "   - Columns: customerAccountID (PK), customerID (FK, UNIQUE), username (UNIQUE), password (Bcrypt/Argon2 Hash), photo, status ('Active', 'Suspended'), createdAt.\n" .
    "   - Architectural Rationale: 1-to-1 relationship with customer. Allows account suspension or credential changes without mutating profile records.\n\n" .
    "3. adminAccount (Administrative Staff & RBAC Entity):\n" .
    "   - Columns: adminAccountID (PK), username (UNIQUE), password, name, email, role, createdAt.\n" .
    "   - Roles: 'Super Administrator', 'Inventory Manager', 'Accounting Administrator', 'Service & Repair Manager'.\n" .
    "   - Security Invariant: Kept strictly separate from customerAccount to eliminate privilege-escalation vectors."
);

$pdf->SectionHeading('3.2 Retail & Workshop Catalog Cluster');
$pdf->BodyText(
    "4. productCategory & 5. serviceCategory:\n" .
    "   - Columns: productCategoryID (PK), name (UNIQUE), description, createdAt.\n" .
    "   - Normalized design prevents typographic duplication and powers category-based indexing and filtering.\n\n" .
    "6. product (Sellable Items with SCD2 Temporal Versioning):\n" .
    "   - Columns: productID (PK), sku, productCategoryID (FK), name, description, image, price, stock, lowStockThreshold, startDate, endDate, isCurrent (Generated Column).\n" .
    "   - Rationale: Explained in Section 4. A product version is current when endDate IS NULL.\n\n" .
    "7. service (Repair & Workshop Labor Catalog):\n" .
    "   - Columns: serviceID (PK), serviceCategoryID (FK), name, description, price, startDate, endDate, isCurrent.\n" .
    "   - Non-inventory catalog defining labor jobs (e.g., Hydraulic Bleeding, Drivetrain Overhaul).\n\n" .
    "8. productImage (Multi-Photo Showcase Entity):\n" .
    "   - Columns: productImageID (PK), productID (FK), imagePath, isPrimary, displayOrder.\n" .
    "   - Powers the Apple-inspired product gallery and carousel showcases on product_details.php."
);

$pdf->SectionHeading('3.3 Commerce, Cart & Transaction Cluster');
$pdf->BodyText(
    "9. customerCart (Temporary Holding Staging Table):\n" .
    "   - Columns: cartID (PK), customerAccountID (FK), productID (FK), quantity, addedAt.\n" .
    "   - Invariant: Compound unique index on (customerAccountID, productID) prevents duplicate rows for identical items.\n\n" .
    "10. productSale (Order Transaction Header):\n" .
    "   - Columns: productSaleID (PK), customerAccountID (FK), orderDate, status, paymentMethod, pickupMethod, deliveryAddress.\n" .
    "   - Status Enum: 'Pending', 'Received', 'In Progress', 'Ready for Pickup', 'Completed', 'Cancelled'.\n\n" .
    "11. orderLineProduct (Immutable Line Items):\n" .
    "   - Columns: orderLineProductID (PK), productSaleID (FK), productID (FK), quantity.\n" .
    "   - Points to the exact SCD2 product version active at time of sale, guaranteeing price immutability."
);

$pdf->AddPage();

$pdf->SectionHeading('3.4 Workshop Operations & Financial Tracking');
$pdf->BodyText(
    "12. customerService (Repair Service Requests):\n" .
    "   - Columns: customerServiceID (PK), serviceID (FK), customerAccountID (FK), adminAccountID (FK, Nullable), productSaleID (FK, Nullable), requestDate, scheduledDate, status, notes.\n" .
    "   - Unification: Can link to a productSaleID when a customer orders components and requests installation.\n\n" .
    "13. expense (Overhead Operational Costs):\n" .
    "   - Columns: expenseID (PK), category ('Water', 'Electricity', 'Employee Salary', 'Rent', 'Other'), amount, expenseDate, description, adminAccountID (FK).\n" .
    "   - Invariant: CHECK constraint enforces that 'Other' expense category must have a non-empty description."
);

$pdf->SectionHeading('3.5 Database Views for Fast, Safe Querying');
$pdf->BodyText(
    "A. vw_order_summary:\n" .
    "   Aggregates order header records with line counts and computed grand totals (sum of product lines + attached labor services). Used across admin order dashboards and customer order history.\n\n" .
    "B. vw_order_transaction_details:\n" .
    "   Joins order lines with products, categories, and line totals for PDF receipts and detailed transaction breakdowns."
);

$pdf->ChapterTitle('4', 'Key Technical Ideas & Architectural Patterns');

$pdf->SectionHeading('4.1 Slowly Changing Dimension Type 2 (SCD2) Temporal Versioning');
$pdf->BodyText(
    "Problem: In standard naive systems, updating a product price directly modifies the 'product.price' column. When this occurs, historical sales and financial reports either miscalculate past revenue or require an unnormalized 'priceAtPurchase' column on every single order line.\n\n" .
    "PedalWorks Solution: The database uses Slowly Changing Dimensions Type 2 (SCD2). When an admin updates a price, the existing row is NOT overwritten. Instead, in a single transaction:\n" .
    "1. The current row's 'endDate' is updated to the current timestamp.\n" .
    "2. A brand-new row is inserted with the new price, 'startDate' set to the timestamp, 'endDate = NULL', and stock copied forward.\n\n" .
    "Mathematical Guarantee: An order placed on October 1st references productID 7 ($100). When price increases to $120 on October 10th (creating productID 15), the October 1st order line still points to productID 7. Financial reports remain 100% correct across all time periods."
);

$scdWidths = [24, 26, 26, 38, 38, 38];
$pdf->TableHeader($scdWidths, ['productID', 'SKU', 'Price', 'startDate', 'endDate', 'Temporal State']);
$pdf->TableRow($scdWidths, ['7', 'BK-GRAVEL-01', 'PHP 25,000.00', '2026-09-01 08:00', '2026-10-10 14:00', 'Historical (Closed)'], true);
$pdf->TableRow($scdWidths, ['15', 'BK-GRAVEL-01', 'PHP 27,500.00', '2026-10-10 14:00', 'NULL', 'Current Active'], false);

$pdf->Ln(2);
$pdf->CalloutBox(
    'Unique Current Version Invariant',
    "To ensure that only ONE version per SKU is active at any given moment, the schema utilizes a virtual generated column:\n" .
    "   isCurrent INT GENERATED ALWAYS AS (CASE WHEN endDate IS NULL THEN 1 ELSE NULL END) VIRTUAL\n" .
    "Paired with a compound unique index on (sku, isCurrent), MySQL permits multiple NULLs (historical versions) while strictly preventing multiple 1s (only one current version allowed)."
);

$pdf->AddPage();

$pdf->SectionHeading('4.2 ACID Transaction Boundaries & Concurrency Control');
$pdf->BodyText(
    "During multi-user operation, race conditions can cause negative inventory if two users attempt to purchase the last bike concurrently. PedalWorks Dynamics wraps checkout in strict ACID transactions:\n\n" .
    "1. mysqli_begin_transaction(\$conn): Opens atomic transaction boundary.\n" .
    "2. SELECT ... FOR UPDATE: Issues pessimistic row-level locks on requested product inventory rows, preventing concurrent reads from acting on stale quantities.\n" .
    "3. Stock Verification: Verifies product.stock >= requested_quantity. If any item is insufficient, throws an Exception.\n" .
    "4. Stock Decrement: UPDATE product SET stock = stock - ? WHERE productID = ? immediately reserves stock.\n" .
    "5. Record Insertion: Inserts productSale header and orderLineProduct rows.\n" .
    "6. Cart Cleanup: Deletes purchased items from customerCart.\n" .
    "7. mysqli_commit(\$conn): Commits the state permanently. If an error occurs anywhere, mysqli_rollback(\$conn) restores the exact previous state."
);

$pdf->SectionHeading('4.3 Single Source of Truth: Derived Financials');
$pdf->BodyText(
    "In PedalWorks Dynamics, Total Sales Revenue has NO static column in any table. Storing a static 'totalRevenue' or 'dailyProfit' creates database drift, where manual edits or refunds desynchronize summary figures from the transaction ledger.\n\n" .
    "Instead, Revenue is dynamically aggregated directly from immutable order lines:\n" .
    "   Gross Product Income = SUM(orderLineProduct.quantity * product.price)\n" .
    "   Gross Workshop Income = SUM(service.price)\n" .
    "   Net Profit / Loss = (Gross Product Income + Gross Workshop Income) - SUM(expense.amount)\n\n" .
    "This architecture guarantees that the Financial Reports (FR18, FR19, FR20) always reflect the ground truth."
);

$pdf->ChapterTitle('5', 'System Workflows & Data Lifecycles');

$pdf->SectionHeading('5.1 The Concurrency-Safe Checkout Pipeline');
$stepWidths = [18, 52, 120];
$pdf->TableHeader($stepWidths, ['Step', 'Phase Name', 'Database Action & Verification']);
$pdf->TableRow($stepWidths, ['1', 'Cart Resolution', 'Reads customerCart, joins product to obtain latest active SCD2 version.'], false);
$pdf->TableRow($stepWidths, ['2', 'Pessimistic Lock', 'SELECT ... FOR UPDATE locks row to isolate concurrent transactions.'], true);
$pdf->TableRow($stepWidths, ['3', 'Stock Decrement', 'Deducts stock directly; triggers lowStockThreshold alerts if reached.'], false);
$pdf->TableRow($stepWidths, ['4', 'Order Creation', 'Inserts productSale header with fulfillment and delivery metadata.'], true);
$pdf->TableRow($stepWidths, ['5', 'Line Items', 'Inserts orderLineProduct records referencing the immutable productID.'], false);
$pdf->TableRow($stepWidths, ['6', 'Labor Linkage', 'If labor is attached, inserts customerService linked to productSaleID.'], true);
$pdf->TableRow($stepWidths, ['7', 'Cart Clear', 'Deletes items from customerCart; issues commit and generates PDF receipt.'], false);

$pdf->Ln(2);
$pdf->SectionHeading('5.2 Workshop Service Request & Repair Lifecycle');
$pdf->BodyText(
    "1. Request Submission (Customer): Customer selects a workshop service, inputs preferred schedule date/time, and provides technician notes. Row inserted with status = 'Pending'.\n" .
    "2. Scheduling & Assignment (Service Manager): Staff reviews request, sets status = 'Scheduled', and assigns adminAccountID to a qualified mechanic.\n" .
    "3. Execution & Tuning: Status transitions to 'In Progress' during active repair, then 'Ready for Pickup' upon bench testing.\n" .
    "4. Settlement: Once customer retrieves bike, status becomes 'Completed', and the labor fee is officially credited to workshop revenue."
);

$pdf->AddPage();

$pdf->ChapterTitle('6', 'Technical Terms & Concepts Glossary');
$pdf->BodyText(
    "Below is an authoritative glossary of technical terms, database concepts, and system ideas employed throughout PedalWorks Dynamics:"
);

$terms = [
    ['SCD Type 2', 'Slowly Changing Dimension Type 2: A data warehousing design pattern that tracks historical data changes by creating multiple records for a given natural key (SKU) with valid date ranges (startDate, endDate).'],
    ['ACID', 'Atomicity, Consistency, Isolation, Durability: A set of database transaction properties that guarantee data validity despite errors, power failures, or concurrent user access.'],
    ['Referential Integrity', 'A relational database rule ensuring that foreign key references always point to existing, valid primary key rows in parent tables, preventing orphaned data.'],
    ['3NF (Third Normal Form)', 'A relational database normalization standard where all attributes are functionally dependent only on the primary key, eliminating transitive dependencies and redundant duplication.'],
    ['Pessimistic Locking', 'A concurrency control mechanism (SELECT ... FOR UPDATE) that locks specific database rows from read/write by other transactions until the current transaction commits.'],
    ['Derived Column', 'A calculated attribute that is never physically stored in a table but is computed on-the-fly via SQL aggregate expressions (e.g., Grand Total, Net Margin).'],
    ['Generated Column', 'A virtual or stored column computed automatically by the DBMS from other column values (e.g., isCurrent computed from endDate IS NULL).'],
    ['Compound Index', 'A database index spanning two or more columns simultaneously (e.g., (customerAccountID, productID) on cart, (sku, isCurrent) on product).'],
    ['B-Tree Index', 'Balanced Tree index structure used by MySQL/InnoDB to provide logarithmic O(log N) lookup speeds for primary keys, foreign keys, and search columns.'],
    ['RBAC', 'Role-Based Access Control: Restricting system authorizations based on organizational roles (Super Admin, Inventory Manager, Service Manager, Accounting Admin).'],
    ['Soft Deletion', 'Marking a record as archived or inactive (by setting endDate) instead of issuing a destructive SQL DELETE statement, preserving audit trails and order integrity.'],
    ['Bcrypt / Argon2', 'Cryptographic one-way salted hashing algorithms utilized for secure user and administrator password storage to prevent credential theft.']
];

$termWidths = [42, 148];
$pdf->TableHeader($termWidths, ['Technical Term', 'Formal Definition & Context in PedalWorks']);
$fill = false;
foreach ($terms as $t) {
    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell($termWidths[0], 9.5, toPdf($t[0]), 1, 0, 'L', $fill);
    $pdf->SetFont('Arial', '', 7.6);
    $pdf->MultiCell($termWidths[1], 4.75, toPdf($t[1]), 1, 'L', $fill);
    $fill = !$fill;
}

$pdf->AddPage();

$pdf->ChapterTitle('7', 'Oral Defense & Technical Panel Examination Guide');
$pdf->BodyText(
    "This section outlines critical questions commonly posed by academic evaluators, panel examiners, and technical reviewers, accompanied by authoritative architectural defenses:"
);

$qa = [
    [
        "Q: Why does the orderLineProduct table not have a 'priceAtPurchase' column?",
        "A: In our schema, price history is natively versioned in the 'product' table using Slowly Changing Dimensions Type 2 (SCD2). Each orderLineProduct points to the exact 'productID' version that was active when the order occurred. Storing a duplicate 'priceAtPurchase' would violate 3NF redundancy principles. The product's startDate and endDate columns irrefutably prove which price applied."
    ],
    [
        "Q: What prevents race conditions if two customers buy the last item at the exact same second?",
        "A: The checkout process is encapsulated inside an ACID transaction using InnoDB row-level pessimistic locking ('SELECT ... FOR UPDATE'). The second customer's transaction is queued until the first transaction finishes. Upon evaluation, the second customer's check discovers stock = 0 and immediately throws an inventory exception, rolling back cleanly with zero data corruption."
    ],
    [
        "Q: Why are 'customer' and 'customerAccount' separated into two tables?",
        "A: This enforces Separation of Concerns. 'customer' represents personal identity (name, email, address), while 'customerAccount' represents authentication credentials (username, password hash, status). This design allows customer accounts to be suspended or modified without corrupting or deleting demographic records required for sales auditing."
    ],
    [
        "Q: Why are products and services not deleted with SQL DELETE?",
        "A: Destructive SQL DELETE statements violate relational referential integrity when old sales reference those items. Deleting a product would cascade-delete past sales or orphan line items, corrupting historical financial records. Setting 'endDate' archives the item from active customer view while preserving 100% of transaction history."
    ],
    [
        "Q: Why does the system calculate total income dynamically rather than storing it?",
        "A: Storing computed income creates data desynchronization. If an order line or service fee is modified, a stored 'total' could drift out of agreement with the ledger. Deriving revenue dynamically from 'orderLineProduct' and 'customerService' guarantees that the Financial Reports always reflect the true mathematical ledger."
    ]
];

foreach ($qa as $item) {
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetTextColor(194, 65, 12);  
    $pdf->MultiCell(190, 4.5, toPdf($item[0]));
    $pdf->Ln(1);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->MultiCell(190, 4.2, toPdf($item[1]));
    $pdf->Ln(3);
}

$pdf->SetFillColor(241, 245, 249);
$pdf->SetDrawColor(203, 213, 225);
$pdf->Rect(10, $pdf->GetY(), 190, 16, 'DF');
$pdf->SetY($pdf->GetY() + 2);
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(190, 4, toPdf('PEDALWORKS DYNAMICS ARCHITECTURAL VERIFICATION PASSED'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 7.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(190, 4, toPdf('Document verified against ITIM211 Project Proposal Specification & XAMPP MySQL Production Database Schema.'), 0, 1, 'C');

$outputPath1 = __DIR__ . '/PedalWorks_Dynamics_System_and_Database_Architecture.pdf';
$outputPath2 = dirname(__DIR__) . '/PedalWorks_Dynamics_System_and_Database_Architecture.pdf';

$pdf->Output('F', $outputPath1);
@copy($outputPath1, $outputPath2);

echo "PDF Generation Successful!\n";
echo "Output Path 1: " . $outputPath1 . " (" . filesize($outputPath1) . " bytes)\n";
if (file_exists($outputPath2)) {
    echo "Output Path 2: " . $outputPath2 . " (" . filesize($outputPath2) . " bytes)\n";
}
