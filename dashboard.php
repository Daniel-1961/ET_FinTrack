<?php
/**
 * FinTrack ET - Main Business Dashboard
 * Coordinates SQL metric calculations, records active collection warnings, and serves dynamic SVG charts.
 */
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$successMsg = "";
$errorMsg = "";

// 1. HANDLE NEW TRANSACTION POST (Sale / Expense)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'add_transaction') {
    $type = $_POST['type']; // sale or expense
    $desc = trim($_POST['desc']);
    $amount = floatval($_POST['amount']);
    $category = trim($_POST['category']);
    $date = $_POST['date'];
    
    $status = 'paid';
    $customerId = null;
    $productId = null;
    $dueDate = null;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : null;
    $amountPaid = isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $costPrice = null;

    try {
        $pdo->beginTransaction();

        if ($type === 'sale') {
            $status = $_POST['payment_status']; // paid or credit
            if ($status === 'credit') {
                if (isset($_POST['create_new_customer']) && $_POST['create_new_customer'] === '1') {
                    $newCustName = trim($_POST['new_cust_name']);
                    $newCustPhone = trim($_POST['new_cust_phone']);
                    $newCustShop = trim($_POST['new_cust_shop']);
                    $newCustLocation = trim($_POST['new_cust_location']);
                    
                    $insCust = $pdo->prepare("INSERT INTO customers (user_id, name, phone, shop_name, location) VALUES (?, ?, ?, ?, ?)");
                    $insCust->execute([$userId, $newCustName, $newCustPhone, $newCustShop, $newCustLocation]);
                    $customerId = $pdo->lastInsertId();
                } elseif (!empty($_POST['customer_id'])) {
                    $customerId = intval($_POST['customer_id']);
                }
                $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
            }
            if (!empty($_POST['product_id'])) {
                $productId = intval($_POST['product_id']);
                // Look up cost price from the product table
                $cpStmt = $pdo->prepare("SELECT price FROM products WHERE id = ? AND user_id = ?");
                $cpStmt->execute([$productId, $userId]);
                $costPrice = floatval($cpStmt->fetchColumn() ?: 0);
            }
        }

        // Insert into transactions with cost_price and quantity
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, customer_id, product_id, date, description, type, amount, category, status, due_date, comment, cost_price, quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $customerId, $productId, $date, $desc, $type, $amount, $category, $status, $dueDate, $comment, $costPrice, $quantity]);

        // If it's a credit sale, increment customer's debt balance
        if ($type === 'sale' && $status === 'credit' && $customerId) {
            $remainingAmount = max(0, $amount - $amountPaid);
            if ($remainingAmount > 0) {
                $upd = $pdo->prepare("UPDATE customers SET debt_balance = debt_balance + ?, last_active = ? WHERE id = ? AND user_id = ?");
                $upd->execute([$remainingAmount, $date, $customerId, $userId]);
            }
            
            if ($amountPaid > 0) {
                $payStmt = $pdo->prepare("INSERT INTO payments (user_id, customer_id, amount, method, date) VALUES (?, ?, ?, 'cash', ?)");
                $payStmt->execute([$userId, $customerId, $amountPaid, $date]);
                
                $downpayStmt = $pdo->prepare("INSERT INTO transactions (user_id, customer_id, date, description, type, amount, category, status) VALUES (?, ?, ?, ?, 'sale', ?, 'Debt Repayment', 'paid')");
                $downpayStmt->execute([$userId, $customerId, $date, 'Down payment for: ' . $desc, $amountPaid]);
            }
        }
        
        // If linked to a product, deduct exact quantity from stock
        if ($type === 'sale' && $productId) {
            $updProd = $pdo->prepare("UPDATE products SET quantity = GREATEST(0, quantity - ?) WHERE id = ? AND user_id = ?");
            $updProd->execute([$quantity, $productId, $userId]);
        }

        $pdo->commit();
        $successMsg = "Transaction successfully recorded!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Failed to record transaction: " . $e->getMessage();
    }
}

// 1b. HANDLE RECEIVE STOCK POST (Purchase from Supplier)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'receive_stock') {
    $date = $_POST['date'];
    $quantity = intval($_POST['quantity']);
    $costPrice = floatval($_POST['cost_price']);
    $totalAmount = $costPrice * $quantity;
    $amountPaidSupplier = floatval($_POST['amount_paid_supplier'] ?? 0);
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : null;
    $supplierId = null;
    $productId = null;

    try {
        $pdo->beginTransaction();

        // Handle Supplier: create new or use existing
        if (isset($_POST['create_new_supplier']) && $_POST['create_new_supplier'] === '1') {
            $supName = trim($_POST['new_supplier_name']);
            $supPhone = trim($_POST['new_supplier_phone']);
            $insSup = $pdo->prepare("INSERT INTO suppliers (user_id, name, phone) VALUES (?, ?, ?)");
            $insSup->execute([$userId, $supName, $supPhone]);
            $supplierId = $pdo->lastInsertId();
        } elseif (!empty($_POST['supplier_id'])) {
            $supplierId = intval($_POST['supplier_id']);
        }

        // Handle Product: create new or use existing
        if (isset($_POST['create_new_product']) && $_POST['create_new_product'] === '1') {
            $prodName = trim($_POST['new_product_name']);
            $prodCategory = trim($_POST['new_product_category']);
            $insProd = $pdo->prepare("INSERT INTO products (user_id, name, category, price, quantity) VALUES (?, ?, ?, ?, ?)");
            $insProd->execute([$userId, $prodName, $prodCategory, $costPrice, $quantity]);
            $productId = $pdo->lastInsertId();
            $desc = 'Received stock: ' . $prodName;
            $category = $prodCategory;
        } elseif (!empty($_POST['product_id'])) {
            $productId = intval($_POST['product_id']);
            // Update existing product stock and cost price
            $updProd = $pdo->prepare("UPDATE products SET quantity = quantity + ?, price = ? WHERE id = ? AND user_id = ?");
            $updProd->execute([$quantity, $costPrice, $productId, $userId]);
            // Get product info for description
            $pInfo = $pdo->prepare("SELECT name, category FROM products WHERE id = ?");
            $pInfo->execute([$productId]);
            $prodInfo = $pInfo->fetch();
            $desc = 'Received stock: ' . ($prodInfo['name'] ?? 'Product');
            $category = $prodInfo['category'] ?? 'Stock Purchase';
        }

        // Log the purchase transaction
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, product_id, supplier_id, date, description, type, amount, category, status, comment, cost_price, quantity) VALUES (?, ?, ?, ?, ?, 'purchase', ?, ?, 'paid', ?, ?, ?)");
        $stmt->execute([$userId, $productId, $supplierId, $date, $desc, $totalAmount, $category, $comment, $costPrice, $quantity]);

        // Update supplier debt if not fully paid
        if ($supplierId) {
            $remainingSupplier = max(0, $totalAmount - $amountPaidSupplier);
            if ($remainingSupplier > 0) {
                $updSup = $pdo->prepare("UPDATE suppliers SET debt_balance = debt_balance + ? WHERE id = ? AND user_id = ?");
                $updSup->execute([$remainingSupplier, $supplierId, $userId]);
            }
        }

        $pdo->commit();
        $successMsg = "Stock received and inventory updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Failed to record stock purchase: " . $e->getMessage();
    }
}

// 2. FETCH CORE METRICS CALCULATIONS
// Total Sales Revenue
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'sale'");
$stmt->execute([$userId]);
$totalSales = floatval($stmt->fetchColumn() ?: 0);

// Total Operational Expenses (rent, transport, etc. — NOT stock purchases)
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'expense'");
$stmt->execute([$userId]);
$totalExpenses = floatval($stmt->fetchColumn() ?: 0);

// Total Outstanding Customer Debts
$stmt = $pdo->prepare("SELECT SUM(debt_balance) FROM customers WHERE user_id = ?");
$stmt->execute([$userId]);
$totalDebts = floatval($stmt->fetchColumn() ?: 0);

// Gross Profit = SUM of (selling_price - cost_price) * quantity for sales with cost tracking
$stmt = $pdo->prepare("SELECT SUM((amount - COALESCE(cost_price, 0) * COALESCE(quantity, 1))) FROM transactions WHERE user_id = ? AND type = 'sale' AND cost_price IS NOT NULL");
$stmt->execute([$userId]);
$grossProfit = floatval($stmt->fetchColumn() ?: 0);

// For sales without cost tracking (custom sales), count their revenue as profit
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'sale' AND cost_price IS NULL");
$stmt->execute([$userId]);
$customSalesProfit = floatval($stmt->fetchColumn() ?: 0);

$netProfit = $grossProfit + $customSalesProfit - $totalExpenses;

// 3. FETCH URGENT DEBT COLLECTIONS (Top 3 outstanding)
$stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ? AND debt_balance > 0 ORDER BY debt_balance DESC LIMIT 3");
$stmt->execute([$userId]);
$urgentDebts = $stmt->fetchAll();

// 4. FETCH RECENT TRANSACTIONS LEDGER (Last 4 entries)
$stmt = $pdo->prepare("SELECT t.*, c.name as customer_name FROM transactions t LEFT JOIN customers c ON t.customer_id = c.id WHERE t.user_id = ? ORDER BY t.date DESC, t.id DESC LIMIT 4");
$stmt->execute([$userId]);
$recentTx = $stmt->fetchAll();

// 5. LOAD REGISTERED CUSTOMERS LIST (for credit sale links dropdown)
$stmt = $pdo->prepare("SELECT id, name, shop_name FROM customers WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$userId]);
$customersList = $stmt->fetchAll();

// 5b. LOAD REGISTERED PRODUCTS LIST (for sales — only in-stock items)
$stmt = $pdo->prepare("SELECT id, name, category, price, quantity FROM products WHERE user_id = ? AND quantity > 0 ORDER BY name ASC");
$stmt->execute([$userId]);
$productsList = $stmt->fetchAll();

// 5c. LOAD ALL PRODUCTS LIST (for receiving stock — includes out-of-stock)
$stmt = $pdo->prepare("SELECT id, name, category, price, quantity FROM products WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$userId]);
$allProductsList = $stmt->fetchAll();

// 5d. LOAD REGISTERED SUPPLIERS LIST
$stmt = $pdo->prepare("SELECT id, name, phone, debt_balance FROM suppliers WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$userId]);
$suppliersList = $stmt->fetchAll();

// 6. COMPILE STATS FOR LAST 7 DAYS SVG CHARTING
$days = [];
$salesData = [];
$expensesData = [];

for ($i = 6; $i >= 0; $i--) {
    $dateVal = date('Y-m-d', strtotime("-$i days"));
    $days[] = date('m-d', strtotime("-$i days"));

    // Sales sum
    $sQuery = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'sale' AND date = ?");
    $sQuery->execute([$userId, $dateVal]);
    $salesData[] = floatval($sQuery->fetchColumn() ?: 0);

    // Expenses sum
    $eQuery = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'expense' AND date = ?");
    $eQuery->execute([$userId, $dateVal]);
    $expensesData[] = floatval($eQuery->fetchColumn() ?: 0);
}

// Attach the unified responsive header
require_once 'header.php';
?>

<!-- Header title area -->
<div class="dashboard-header">
    <div class="welcome-section">
        <h1 data-localize="dash_welcome">እንኳን ደህና መጡ!</h1>
        <p data-localize="dash_desc">Here is your business financial summary for this month.</p>
    </div>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="openDrawer('sale')">
            <i class="fas fa-plus"></i> <span data-localize="btn_record_sale">Record Sale</span>
        </button>
        <button class="btn btn-secondary" onclick="openDrawer('expense')">
            <i class="fas fa-minus"></i> <span data-localize="btn_record_expense">Record Expense</span>
        </button>
        <button class="btn btn-primary" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);" onclick="openStockDrawer()">
            <i class="fas fa-truck-loading"></i> <span>Receive Stock</span>
        </button>
    </div>
</div>

<!-- Dynamic Success/Error messages -->
<?php if (!empty($successMsg)): ?>
    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); border-radius: 12px; padding: 12px 16px; margin-bottom: 25px; font-weight: 500;">
        <i class="fas fa-check-circle" style="margin-right: 8px;"></i> <?= htmlspecialchars($successMsg) ?>
    </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); border-radius: 12px; padding: 12px 16px; margin-bottom: 25px; font-weight: 500;">
        <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<!-- Financial Metrics Cards -->
<div class="metrics-grid">
    <div class="metric-card sales">
        <div class="metric-info">
            <h4 data-localize="metric_sales">Total Sales</h4>
            <div class="metric-value"><?= number_format($totalSales) ?><span class="metric-currency"> ETB</span></div>
        </div>
        <div class="metric-icon"><i class="fas fa-arrow-trend-up"></i></div>
    </div>
    <div class="metric-card expenses">
        <div class="metric-info">
            <h4 data-localize="metric_expenses">Expenses</h4>
            <div class="metric-value"><?= number_format($totalExpenses) ?><span class="metric-currency"> ETB</span></div>
        </div>
        <div class="metric-icon"><i class="fas fa-arrow-trend-down"></i></div>
    </div>
    <div class="metric-card credits">
        <div class="metric-info">
            <h4 data-localize="metric_credits">Active Debts</h4>
            <div class="metric-value"><?= number_format($totalDebts) ?><span class="metric-currency"> ETB</span></div>
        </div>
        <div class="metric-icon"><i class="fas fa-user-clock"></i></div>
    </div>
    <div class="metric-card profit">
        <div class="metric-info">
            <h4 data-localize="metric_profit">Net Profit</h4>
            <div class="metric-value"><?= number_format($netProfit) ?><span class="metric-currency"> ETB</span></div>
        </div>
        <div class="metric-icon"><i class="fas fa-coins"></i></div>
    </div>
</div>

<!-- Central Analysis & Quick Actions -->
<div class="dashboard-panels">
    <div class="panel">
        <div class="panel-header">
            <h3 class="panel-title"><i class="fas fa-chart-area"></i> <span data-localize="panel_sales_trend">Sales & Expenses Trend</span></h3>
            <div style="font-size: 0.8rem; color: var(--text-secondary);" data-localize="chart_realtime">Real-time dynamic visualization</div>
        </div>
        <div class="chart-container" id="dashboard-chart">
            <!-- Rendered dynamically by SVG Charting Engine -->
        </div>
    </div>

    <!-- Urgent Collections -->
    <div class="panel">
        <div class="panel-header">
            <h3 class="panel-title"><i class="fas fa-bell"></i> <span data-localize="panel_urgent_debts">Urgent Collections</span></h3>
        </div>
        <div class="debts-warning-list">
            <?php if (empty($urgentDebts)): ?>
                <div style="text-align:center; padding:20px; color:var(--text-secondary);" data-localize="no_warnings">No active debtor collections. Great job!</div>
            <?php else: ?>
                <?php foreach ($urgentDebts as $cust): 
                    $isHigh = ($cust['debt_balance'] > 2000);
                ?>
                    <div class="debt-warning-item <?= $isHigh ? 'danger' : 'warning' ?>">
                        <div class="debt-info">
                            <h5><?= htmlspecialchars($cust['name']) ?></h5>
                            <p><?= htmlspecialchars($cust['phone']) ?></p>
                        </div>
                        <div class="debt-amount <?= $isHigh ? 'danger' : 'warning' ?>">
                            <span class="value"><?= number_format($cust['debt_balance']) ?> ETB</span>
                            <span class="days" style="font-size: 0.7rem; font-weight:600; color: <?= $isHigh ? 'var(--danger)' : 'var(--warning)' ?>">
                                <?= $isHigh ? 'Overdue!' : 'Due Soon' ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Activities Table -->
<div class="panel">
    <div class="panel-header">
        <h3 class="panel-title"><i class="fas fa-history"></i> <span data-localize="panel_recent_tx">Recent Ledger Entries</span></h3>
        <a href="transactions.php" class="btn btn-secondary btn-small" data-localize="btn_view_all">View All</a>
    </div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th data-localize="table_date">Date</th>
                    <th data-localize="table_desc">Description</th>
                    <th data-localize="table_type">Type</th>
                    <th data-localize="table_amount">Amount</th>
                    <th data-localize="table_status">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentTx)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-secondary);">No ledger records found yet. Click Record Sale to begin.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentTx as $t): 
                        $badgeClass = 'badge-success';
                        $statusKey = 'text_paid';
                        if ($t['type'] === 'expense') {
                            $badgeClass = 'badge-danger';
                            $statusKey = 'text_expense';
                        } elseif ($t['type'] === 'purchase') {
                            $badgeClass = 'badge-info';
                            $statusKey = 'text_purchase';
                        } elseif ($t['status'] === 'credit') {
                            $badgeClass = 'badge-warning';
                            $statusKey = 'text_credit';
                        }
                        $typeLabel = 'Sale';
                        $typeColor = 'var(--success)';
                        $typeSign = '+';
                        if ($t['type'] === 'expense') { $typeLabel = 'Expense'; $typeColor = 'var(--danger)'; $typeSign = '-'; }
                        elseif ($t['type'] === 'purchase') { $typeLabel = 'Purchase'; $typeColor = 'var(--info, #6366f1)'; $typeSign = '-'; }
                    ?>
                        <tr>
                            <td><?= $t['date'] ?></td>
                            <td><?= htmlspecialchars($t['description']) ?></td>
                            <td><?= $typeLabel ?></td>
                            <td style="font-weight:700; color:<?= $typeColor ?>">
                                <?= $typeSign ?><?= number_format($t['amount']) ?> ETB
                            </td>
                            <td><span class="badge <?= $badgeClass ?>"><?= ($t['type'] === 'purchase') ? 'Stock In' : (($t['type'] === 'expense') ? 'Expense' : (($t['status'] === 'credit') ? 'Unpaid Debt' : 'Paid')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- TRANSACTION DRAWER (SALES & EXPENSES) -->
<div class="drawer-overlay" id="drawer-transaction">
    <div class="drawer">
        <div class="drawer-header">
            <h3 id="drawer-tx-title">Record Sale</h3>
            <button class="drawer-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="drawer-body">
            <form id="form-transaction" action="dashboard.php" method="POST">
                <input type="hidden" name="action_type" value="add_transaction">
                <input type="hidden" name="type" id="form-tx-type" value="sale">
                
                <div class="form-group">
                    <label class="form-label" data-localize="form_desc">Transaction Title / Items purchased</label>
                    <input type="text" name="desc" class="form-control" required placeholder="e.g. Macchiato, Kiosk Goods, Soap box">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" data-localize="form_amount">Amount (ETB)</label>
                        <input type="number" name="amount" class="form-control" min="1" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label" data-localize="form_category">Category</label>
                        <input type="text" name="category" class="form-control" required placeholder="e.g. Stock, Cafe, Rent">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" data-localize="form_date">Transaction Date</label>
                    <input type="date" name="date" id="form-tx-date" class="form-control" required>
                </div>

                <div id="form-sales-only-fields">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" data-localize="form_sale_product">Select Product from Inventory</label>
                        <select name="product_id" class="form-control" id="form-tx-product-id" onchange="autoFillProductDetails()">
                            <option value="">-- Custom Sale (No Product) --</option>
                            <?php foreach ($productsList as $prod): ?>
                                <option value="<?= $prod['id'] ?>" data-name="<?= htmlspecialchars($prod['name']) ?>" data-category="<?= htmlspecialchars($prod['category']) ?>" data-price="<?= $prod['price'] ?>"><?= htmlspecialchars($prod['name']) ?> (<?= number_format($prod['price']) ?> ETB) - Stock: <?= $prod['quantity'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row" id="form-qty-row" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Quantity Sold</label>
                            <input type="number" name="quantity" id="form-tx-quantity" class="form-control" min="1" value="1" oninput="calculateTotalAmount()">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="color: var(--text-secondary);">Cost Price (Reference)</label>
                            <input type="number" id="form-tx-orig-price" class="form-control" disabled style="opacity: 0.7; font-style: italic;">
                            <div style="font-size: 0.7rem; color: var(--text-secondary); margin-top: 3px;"><i class="fas fa-info-circle"></i> This is what you paid for it</div>
                        </div>
                    </div>
                    
                    <div class="form-group" id="form-selling-price-row" style="display: none;">
                        <label class="form-label" style="font-weight: 700;">Selling Price per Unit (ETB)</label>
                        <input type="number" id="form-tx-selling-price" class="form-control" min="0" step="0.01" placeholder="Your selling price for this customer" oninput="calculateFromSellingPrice()">
                        <div style="font-size: 0.7rem; color: var(--text-secondary); margin-top: 3px;"><i class="fas fa-hand-holding-dollar"></i> The price you're charging the buyer</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" data-localize="form_payment_status">Payment Status</label>
                        <select name="payment_status" class="form-control" id="form-tx-payment-status" onchange="toggleCreditCustomerField()">
                            <option value="paid" data-localize="status_paid">In Cash / Paid</option>
                            <option value="credit" data-localize="status_credit">To be collected (Credit)</option>
                        </select>
                    </div>

                    <div id="form-credit-customer-group" style="display: none; background: rgba(0,0,0,0.02); padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 20px;">
                        <div class="form-group">
                            <label class="form-label" data-localize="form_customer_select">Borrower's Info</label>
                            <select name="customer_id" class="form-control" id="form-tx-customer-id" onchange="toggleNewCustomerFields()">
                                <option value="">-- Select Borrower --</option>
                                <option value="NEW" style="font-weight: bold; color: var(--primary);">➕ Create New Borrower</option>
                                <?php foreach ($customersList as $cust): ?>
                                    <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['name']) ?> (<?= htmlspecialchars($cust['shop_name'] ?: 'No Shop') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="form-new-customer-fields" style="display: none; padding-top: 10px; border-top: 1px dashed var(--border-color); margin-top: 10px;">
                            <input type="hidden" name="create_new_customer" id="create-new-customer-flag" value="0">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="new_cust_name" class="form-control" placeholder="Abebe Kebede">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="new_cust_phone" class="form-control" placeholder="09...">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Shop Name</label>
                                    <input type="text" name="new_cust_shop" class="form-control" placeholder="Kebede Kiosk">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="new_cust_location" class="form-control" placeholder="Bole">
                                </div>
                            </div>
                        </div>

                        <div class="form-row" style="margin-top: 15px;">
                            <div class="form-group">
                                <label class="form-label">Amount Paid (Down Payment)</label>
                                <input type="number" name="amount_paid" id="form-tx-amount-paid" class="form-control" min="0" value="0" step="0.01" oninput="calculateRemaining()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Remaining Amount</label>
                                <input type="number" id="form-tx-remaining" class="form-control" disabled value="0">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Comment / Notes (For rehearsal)</label>
                    <textarea name="comment" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;" data-localize="btn_save_tx">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- RECEIVE STOCK DRAWER -->
<div class="drawer-overlay" id="drawer-stock">
    <div class="drawer">
        <div class="drawer-header">
            <h3><i class="fas fa-truck-loading" style="margin-right: 8px;"></i>Receive Stock from Supplier</h3>
            <button class="drawer-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="drawer-body">
            <form action="dashboard.php" method="POST">
                <input type="hidden" name="action_type" value="receive_stock">
                
                <div class="form-group">
                    <label class="form-label" style="font-weight: 700;">Date Received</label>
                    <input type="date" name="date" id="form-stock-date" class="form-control" required>
                </div>

                <!-- Product Selection -->
                <div style="background: rgba(99,102,241,0.04); padding: 15px; border-radius: 8px; border: 1px solid rgba(99,102,241,0.15); margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 700; color: var(--primary);"><i class="fas fa-box-open" style="margin-right: 5px;"></i>Product / Item Details</label>
                    <select name="product_id" class="form-control" id="form-stock-product" onchange="toggleStockProductFields()" style="margin-bottom: 10px;">
                        <option value="">-- Select Existing Product --</option>
                        <option value="NEW" style="font-weight: bold; color: var(--primary);">➕ Add New Product</option>
                        <?php foreach ($allProductsList as $prod): ?>
                            <option value="<?= $prod['id'] ?>" data-name="<?= htmlspecialchars($prod['name']) ?>" data-category="<?= htmlspecialchars($prod['category']) ?>" data-price="<?= $prod['price'] ?>"><?= htmlspecialchars($prod['name']) ?> (<?= htmlspecialchars($prod['category']) ?>) — Current Stock: <?= $prod['quantity'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div id="form-new-product-fields" style="display: none; padding-top: 10px; border-top: 1px dashed var(--border-color);">
                        <input type="hidden" name="create_new_product" id="create-new-product-flag" value="0">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Product Name</label>
                                <input type="text" name="new_product_name" class="form-control" placeholder="e.g. Sugar (1 kg)">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <input type="text" name="new_product_category" class="form-control" placeholder="e.g. Groceries">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing & Quantity -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700;">Quantity Received</label>
                        <input type="number" name="quantity" id="form-stock-qty" class="form-control" min="1" value="1" required oninput="calculateStockTotal()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700;">Cost Price per Unit (ETB)</label>
                        <input type="number" name="cost_price" id="form-stock-cost" class="form-control" min="0" step="0.01" required placeholder="0.00" oninput="calculateStockTotal()">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Total Amount</label>
                    <input type="number" id="form-stock-total" class="form-control" disabled value="0" style="font-weight: 700; font-size: 1.05rem; background: rgba(16,185,129,0.06);">
                </div>

                <!-- Supplier Selection -->
                <div style="background: rgba(245,158,11,0.04); padding: 15px; border-radius: 8px; border: 1px solid rgba(245,158,11,0.15); margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 700; color: var(--warning);"><i class="fas fa-handshake" style="margin-right: 5px;"></i>Supplier Info</label>
                    <select name="supplier_id" class="form-control" id="form-stock-supplier" onchange="toggleNewSupplierFields()" style="margin-bottom: 10px;">
                        <option value="">-- No Supplier (Self-purchased) --</option>
                        <option value="NEW" style="font-weight: bold; color: var(--primary);">➕ Add New Supplier</option>
                        <?php foreach ($suppliersList as $sup): ?>
                            <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?> (<?= htmlspecialchars($sup['phone']) ?>) <?= $sup['debt_balance'] > 0 ? '— Owed: ' . number_format($sup['debt_balance']) . ' ETB' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div id="form-new-supplier-fields" style="display: none; padding-top: 10px; border-top: 1px dashed var(--border-color);">
                        <input type="hidden" name="create_new_supplier" id="create-new-supplier-flag" value="0">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Supplier Name</label>
                                <input type="text" name="new_supplier_name" class="form-control" placeholder="e.g. Merkato Wholesale">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" name="new_supplier_phone" class="form-control" placeholder="09...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment to Supplier -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700;">Amount Paid to Supplier</label>
                        <input type="number" name="amount_paid_supplier" id="form-stock-paid" class="form-control" min="0" value="0" step="0.01" oninput="calculateStockRemaining()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Remaining (Owed to Supplier)</label>
                        <input type="number" id="form-stock-remaining" class="form-control" disabled value="0" style="color: var(--danger); font-weight: 700;">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Comment / Notes</label>
                    <textarea name="comment" class="form-control" rows="2" placeholder="Any notes about this delivery..."></textarea>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; background: linear-gradient(135deg, #6366f1, #8b5cf6);">Save & Update Inventory</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Injected dynamic stats to render the premium SVG Chart
    const chartDays = <?= json_encode($days) ?>;
    const chartSales = <?= json_encode($salesData) ?>;
    const chartExpenses = <?= json_encode($expensesData) ?>;

    window.addEventListener('load', () => {
        // Render Chart
        renderSVGChart('dashboard-chart', chartDays, chartSales, chartExpenses);

        // Lang Switch Listener to redraw chart labels in correct locale if necessary
        window.addEventListener('langChanged', () => {
            renderSVGChart('dashboard-chart', chartDays, chartSales, chartExpenses);
        });
    });

    function openDrawer(type) {
        document.getElementById('form-tx-date').value = new Date().toISOString().split('T')[0];
        document.getElementById('form-tx-type').value = type;

        const title = document.getElementById('drawer-tx-title');
        const salesFields = document.getElementById('form-sales-only-fields');

        const currentLang = localStorage.getItem('fintrack_lang') || 'en';

        if (type === 'sale') {
            title.textContent = (currentLang === 'en') ? 'Record Sale' : 'ሽያጭ መዝግብ';
            salesFields.style.display = 'block';
        } else {
            title.textContent = (currentLang === 'en') ? 'Record Expense' : 'ወጪ መዝግብ';
            salesFields.style.display = 'none';
        }

        document.getElementById('drawer-transaction').style.display = 'flex';
    }

    function autoFillProductDetails() {
        const select = document.getElementById('form-tx-product-id');
        const option = select.options[select.selectedIndex];
        if (option.value) {
            document.querySelector('#form-transaction input[name="desc"]').value = option.getAttribute('data-name');
            document.querySelector('#form-transaction input[name="category"]').value = option.getAttribute('data-category');
            const costPrice = parseFloat(option.getAttribute('data-price')) || 0;
            document.getElementById('form-tx-orig-price').value = costPrice;
            document.getElementById('form-tx-selling-price').value = '';
            
            document.getElementById('form-qty-row').style.display = 'flex';
            document.getElementById('form-selling-price-row').style.display = 'block';
            // Don't auto-calculate total — user must set selling price
            document.querySelector('#form-transaction input[name="amount"]').value = '';
        } else {
            document.getElementById('form-qty-row').style.display = 'none';
            document.getElementById('form-selling-price-row').style.display = 'none';
        }
    }
    
    function calculateFromSellingPrice() {
        const sellingPrice = parseFloat(document.getElementById('form-tx-selling-price').value) || 0;
        const qty = parseFloat(document.getElementById('form-tx-quantity').value) || 1;
        const total = sellingPrice * qty;
        document.querySelector('#form-transaction input[name="amount"]').value = total;
        calculateRemaining();
    }
    
    function calculateTotalAmount() {
        // Recalculate based on selling price if set, otherwise do nothing
        const sellingPrice = parseFloat(document.getElementById('form-tx-selling-price').value) || 0;
        if (sellingPrice > 0) {
            calculateFromSellingPrice();
        }
    }

    function toggleCreditCustomerField() {
        const status = document.getElementById('form-tx-payment-status').value;
        const group = document.getElementById('form-credit-customer-group');
        group.style.display = (status === 'credit') ? 'block' : 'none';
        calculateRemaining();
    }
    
    function calculateRemaining() {
        const total = parseFloat(document.querySelector('#form-transaction input[name="amount"]').value) || 0;
        const paid = parseFloat(document.getElementById('form-tx-amount-paid').value) || 0;
        const remaining = Math.max(0, total - paid);
        document.getElementById('form-tx-remaining').value = remaining;
    }
    
    function toggleNewCustomerFields() {
        const select = document.getElementById('form-tx-customer-id');
        const newFields = document.getElementById('form-new-customer-fields');
        const flag = document.getElementById('create-new-customer-flag');
        
        if (select.value === 'NEW') {
            newFields.style.display = 'block';
            flag.value = '1';
        } else {
            newFields.style.display = 'none';
            flag.value = '0';
        }
    }
    
    // ============ RECEIVE STOCK DRAWER FUNCTIONS ============
    function openStockDrawer() {
        document.getElementById('form-stock-date').value = new Date().toISOString().split('T')[0];
        document.getElementById('drawer-stock').style.display = 'flex';
    }
    
    function toggleStockProductFields() {
        const select = document.getElementById('form-stock-product');
        const newFields = document.getElementById('form-new-product-fields');
        const flag = document.getElementById('create-new-product-flag');
        
        if (select.value === 'NEW') {
            newFields.style.display = 'block';
            flag.value = '1';
            document.getElementById('form-stock-cost').value = '';
        } else {
            newFields.style.display = 'none';
            flag.value = '0';
            // Auto-fill cost price from existing product
            const option = select.options[select.selectedIndex];
            if (option && option.value && option.value !== 'NEW') {
                const price = option.getAttribute('data-price');
                if (price) document.getElementById('form-stock-cost').value = price;
            }
        }
        calculateStockTotal();
    }
    
    function toggleNewSupplierFields() {
        const select = document.getElementById('form-stock-supplier');
        const newFields = document.getElementById('form-new-supplier-fields');
        const flag = document.getElementById('create-new-supplier-flag');
        
        if (select.value === 'NEW') {
            newFields.style.display = 'block';
            flag.value = '1';
        } else {
            newFields.style.display = 'none';
            flag.value = '0';
        }
    }
    
    function calculateStockTotal() {
        const qty = parseFloat(document.getElementById('form-stock-qty').value) || 0;
        const cost = parseFloat(document.getElementById('form-stock-cost').value) || 0;
        const total = qty * cost;
        document.getElementById('form-stock-total').value = total;
        calculateStockRemaining();
    }
    
    function calculateStockRemaining() {
        const total = parseFloat(document.getElementById('form-stock-total').value) || 0;
        const paid = parseFloat(document.getElementById('form-stock-paid').value) || 0;
        const remaining = Math.max(0, total - paid);
        document.getElementById('form-stock-remaining').value = remaining;
    }
</script>

<?php
// Attach shared footer layout
require_once 'footer.php';
?>
