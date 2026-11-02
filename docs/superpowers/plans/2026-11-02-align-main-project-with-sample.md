# Align PedalWorks Dynamics with Sample Project (ITIM211) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align the architectural level, directory organization, database conventions, and code structure of PedalWorks Dynamics with the ITIM211 sample project (`itim211-2b-shop`) so the codebase matches exact course rubric expectations while preserving its pro-shop capabilities.

**Architecture:** 
1. **Directory & Resource Structure:** Adopt the sample's clear resource-oriented pattern (`<resource>/index.php`, `create.php`, `store.php`, `edit.php`, `update.php`, `delete.php`) with Post/Redirect/Get (PRG) flow.
2. **Database & Views Compatibility:** Expose course-standard views (`salesperorder`, `orderdetails`) and compatibility table aliases (`item`, `stock`, `orderinfo`, `orderline`).
3. **Cart & Session Architecture:** Mirror cart mutations directly into `$_SESSION['cart_products']` with sample-identical associative keys (`item_id`, `item_name`, `item_price`, `item_qty`).
4. **Session Flash & Error Bags:** Standardize form validation error propagation using session bags (`$_SESSION['<field>Error']` and sticky input keys) with `<small class="text-danger">` tags under form controls.
5. **Transactional Mutation Pattern:** Standardize multi-step writes (checkout, inventory updates, order status) with procedural `mysqli_begin_transaction`, `mysqli_commit`, and `mysqli_rollback`.

**Tech Stack:** PHP 8.2+, MySQLi (Procedural Prepared Statements & ACID Transactions), MySQL Views, Bootstrap 5.3.2, FontAwesome 6.4.2.

**Spec:** Comparative analysis between reference repository `itim211-2b-shop` and main repository `PedalWorks-Dynamics`.

---

## Global Constraints
- Target workspace directory: `C:\Users\abena\OneDrive\Desktop\Abenadi\xampp_folders\xampp_lite_8.3.13_2\www\project\PedalWorks-Dynamics`.
- Never commit or push without explicit user command (`git commit` and `git push` only on command).
- Zero third-party vendor modification (`PHPMailer`, `fpdf`, `chart.min.js`, `anime.min.js` remain intact).
- Retain PHP 8.2 compatibility with zero syntax errors (`php -l` on all touched files).
- Keep changes lean, maintainable, and directly understandable by a course reviewer (Ponytail principle: minimal code, zero bloat).

---

## Code Structure Comparison Matrix

| Structural Layer | Sample Project (`itim211-2b-shop`) | Current PedalWorks (`PedalWorks-Dynamics`) | Target Aligned Code Structure |
| :--- | :--- | :--- | :--- |
| **Directory & Action Separation** | Explicit CRUD scripts per resource:<br>`item/create.php` (form)<br>`item/store.php` (action)<br>`item/edit.php` (form)<br>`item/update.php` (action)<br>`item/delete.php` (action) | Self-submitting monolithic scripts (`addProduct.php`, `editProduct.php`) mixing HTML view rendering and POST mutation in one file | Introduce clean action scripts / PRG endpoints (`store.php`, `update.php`, `delete.php`) or compatibility routes matching the sample's resource model |
| **Cart Manipulation** | Standalone controller: `cart_update.php` handling: `type=add`, `product_qty[]`, `remove_code[]` | Handled via API/AJAX action script `cart_action.php` writing solely to database `customerCart` | Extend cart controller to process standard `cart_update.php` POST inputs and mirror into `$_SESSION['cart_products']` |
| **Form Validation Pattern** | Post/Redirect/Get with Session Error Bags:<br>`$_SESSION['descError'] = '...'`<br>`$_SESSION['desc'] = $_POST['description']`<br>Redirects back to form; rendered with `<small>` tags | In-memory `$errors = []` array rendered in alert boxes upon self-post | Standardize on Session Error Bags for form field error feedback under input controls |
| **Database Schema Abstraction** | Separate `item` (catalog metadata) and `stock` (inventory quantities), joined via `INNER JOIN stock s USING (item_id)` | Unified `product` table containing both pricing and stock count | Expose course views & SQL compatibility aliases so queries targeting `item` and `stock` resolve accurately |
| **Views & Reporting** | `salesperorder` view (`orderinfo_id`, `status`, `total`)<br>`orderdetails` view (customer details + item lines) | `vw_order_summary`<br>`vw_order_transaction_details` | Add `salesperorder` and `orderdetails` views directly in `includes/config.php` and `db.sql` |
| **Session & Auth Keys** | Single unified session:<br>`$_SESSION['user_id']`<br>`$_SESSION['role']` ('user' or 'admin')<br>`$_SESSION['email']`<br>`$_SESSION['cart_products']` | Split role sessions:<br>`$_SESSION['customerAccountID']`<br>`$_SESSION['adminAccountID']`<br>`$_SESSION['adminRole']` | Maintain dual-compatibility session bridge so `$_SESSION['user_id']` and `$_SESSION['role']` are always populated |
| **Order Processing & Routing** | `admin/orders.php` &rarr; `admin/orderDetails.php?id=X` &rarr; `admin/updateOrder.php` (POST) | `admin/orders/index.php` &rarr; `admin/orders/orderDetails.php` | Bridge admin order routes so both `/admin/orders.php` and `/admin/orders/index.php` function identically |

---

## Review Focus
1. **Action Handler Isolation:** Mutating requests (create, update, delete, checkout) execute in dedicated procedural handlers that redirect after execution (PRG pattern).
2. **Session Error Persistence:** Validation failures redirect back to the form with sticky inputs and individual field error messages stored in session.
3. **Database View Accuracy:** `SELECT * FROM salesperorder` and `SELECT * FROM orderdetails WHERE orderinfo_id = X` execute without SQL errors.
4. **Session Cart Interoperability:** Adding an item from `index.php` or `products.php` populates both `$_SESSION['cart_products']` and the database cart.
5. **ACID Transaction Rigor:** Multi-table mutations (`checkout.php`, stock deductions) are wrapped in `mysqli_begin_transaction` / `mysqli_commit` / `mysqli_rollback`.

---

## Detailed Task Breakdown

### Task 1: Course Views & Schema Compatibility Layer
**Files:**
- Modify: `includes/config.php`
- Modify: `db.sql`

- [ ] Add `salesperorder` view definition to `includes/config.php`:
  ```sql
  CREATE OR REPLACE VIEW `salesperorder` AS 
  SELECT 
      ps.productSaleID AS orderinfo_id,
      ps.status AS status,
      COALESCE(SUM(olp.quantity * p.price), 0.00) AS total
  FROM productSale ps
  JOIN orderLineProduct olp ON ps.productSaleID = olp.productSaleID
  JOIN product p ON olp.productID = p.productID
  GROUP BY ps.productSaleID, ps.status;
  ```
- [ ] Add `orderdetails` view definition to `includes/config.php`:
  ```sql
  CREATE OR REPLACE VIEW `orderdetails` AS 
  SELECT 
      c.firstName AS fname,
      c.lastName AS lname,
      COALESCE(ps.deliveryAddress, c.address) AS addressline,
      'Taguig' AS town,
      '1630' AS zipcode,
      c.email AS phone,
      ps.productSaleID AS orderinfo_id,
      ps.orderDate AS date_placed,
      ps.status AS status,
      p.name AS description,
      olp.quantity AS quantity,
      p.price AS sell_price
  FROM customer c
  JOIN customerAccount ca ON c.customerID = ca.customerID
  JOIN productSale ps ON ca.customerAccountID = ps.customerAccountID
  JOIN orderLineProduct olp ON ps.productSaleID = olp.productSaleID
  JOIN product p ON olp.productID = p.productID;
  ```
- [ ] Update `db.sql` with these view definitions so clean database setups include them automatically.
- [ ] Run `php -l includes/config.php` to verify syntax.

---

### Task 2: Cart Controller & Session Structure Alignment
**Files:**
- Create/Modify: `cart_update.php`
- Modify: `cart_action.php`
- Modify: `cart.php`
- Modify: `view_cart.php` (bridge or redirect to `cart.php`)

- [ ] Support the sample project's `cart_update.php` endpoint accepting standard parameters:
  - `type=add`, `item_id`, `item_qty`
  - `product_qty[item_id] = qty`
  - `remove_code[] = item_id`
- [ ] Ensure any cart write updates `$_SESSION['cart_products']` using the exact sample associative schema:
  ```php
  $_SESSION['cart_products'][$item_id] = [
      'item_id'    => $productID,
      'item_name'  => $productName,
      'item_price' => $productPrice,
      'item_qty'   => $quantity
  ];
  ```
- [ ] Synchronize `$_SESSION['cart_products']` with database table `customerCart` when a user is signed in.
- [ ] Add a lightweight `view_cart.php` entry point matching the sample project that redirects or renders `cart.php`.
- [ ] Run `php -l` on all modified cart files.

---

### Task 3: Checkout Transaction & Flash Messaging Alignment
**Files:**
- Modify: `checkout.php`

- [ ] Ensure `checkout.php` follows the exact clean transactional flow from the sample:
  1. `mysqli_begin_transaction($conn);`
  2. Resolve customer record by `$_SESSION['customerID']` / `$_SESSION['user_id']`.
  3. Insert `productSale` order record.
  4. Iterate items, inserting `orderLineProduct` and updating product inventory.
  5. `mysqli_commit($conn);`
  6. Unset `$_SESSION['cart_products']` and clear database cart.
  7. Set `$_SESSION['success'] = 'thank you for your order';`
  8. Redirect to `index.php` or order confirmation.
- [ ] Ensure exception catching triggers `mysqli_rollback($conn);` and sets `$_SESSION['message']` (or `$_SESSION['error']`).
- [ ] Verify checkout syntax with `php -l checkout.php`.

---

### Task 4: Form Validation & Error Bag Pattern Alignment
**Files:**
- Modify: `admin/product/addProduct.php`
- Modify: `admin/product/editProduct.php`
- Modify: `includes/alert.php`

- [ ] Update `includes/alert.php` to render both `$_SESSION['message']` and `$_SESSION['error']`, and `$_SESSION['success']`.
- [ ] Refactor product creation/edit validation to support sticky session values (`$_SESSION['desc']`, `$_SESSION['cost']`, `$_SESSION['sell']`, `$_SESSION['qty']`) and error tags (`$_SESSION['descError']`, `$_SESSION['priceError']`, `$_SESSION['stockError']`, `$_SESSION['imageError']`).
- [ ] Display `<small class="text-danger">` directly underneath form inputs in `addProduct.php` and `editProduct.php`.
- [ ] Verify PHP syntax across modified admin forms.

---

### Task 5: Admin Order Processing & Compatibility Routes
**Files:**
- Create/Modify: `admin/orders.php` (compatibility route to `admin/orders/index.php`)
- Modify: `admin/orders/index.php`
- Modify: `admin/orders/orderDetails.php`
- Modify: `admin/orders/updateOrder.php`

- [ ] Add a root-level `admin/orders.php` redirect or inclusion pointing to `admin/orders/index.php` so standard course URLs like `/admin/orders.php` resolve immediately.
- [ ] In `admin/orders/index.php`, query `salesperorder` to populate order totals and statuses.
- [ ] In `admin/orders/orderDetails.php`, query `orderdetails` view to retrieve order header, customer address, line items, and grand total.
- [ ] In `admin/orders/updateOrder.php`, accept status updates and bridge sample statuses (`Processing`, `Delivered`, `Canceled`) to shop statuses (`In Progress`, `Completed`, `Cancelled`).
- [ ] Verify all touched admin files with `php -l`.

---

### Task 6: Session & Authentication Bridge
**Files:**
- Modify: `login.php`
- Modify: `user/profile.php`
- Modify: `includes/header.php`

- [ ] In `login.php`, when a customer logs in, set both:
  - `$_SESSION['customerAccountID']` AND `$_SESSION['user_id'] = $customerAccountID`
  - `$_SESSION['role'] = 'user'`
  - `$_SESSION['email'] = $customerEmail`
- [ ] When an admin logs in, set:
  - `$_SESSION['adminAccountID']` AND `$_SESSION['user_id'] = $adminAccountID`
  - `$_SESSION['role'] = 'admin'`
- [ ] Verify seamless login flow for both customers and administrators.
- [ ] Run full project syntax check (`php -l`).
