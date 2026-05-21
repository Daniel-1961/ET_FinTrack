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

// 1. HANDLE NEW TRANSACTION POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'add_transaction') {
    $type = $_POST['type']; // sale or expense
    $desc = trim($_POST['desc']);
    $amount = floatval($_POST['amount']);
    $category = trim($_POST['category']);
    $date = $_POST['date'];
    
    $status = 'paid';
    $customerId = null;

    if ($type === 'sale') {
        $status = $_POST['payment_status']; // paid or credit
        if ($status === 'credit' && !empty($_POST['customer_id'])) {
            $customerId = intval($_POST['customer_id']);
        }
    }

    try {
        $pdo->beginTransaction();

        // Safe Insert into transactions
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, customer_id, date, description, type, amount, category, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $customerId, $date, $desc, $type, $amount, $category, $status]);

        // If it's a credit sale, increment customer's debt balance
        if ($type === 'sale' && $status === 'credit' && $customerId) {
            $upd = $pdo->prepare("UPDATE customers SET debt_balance = debt_balance + ?, last_active = ? WHERE id = ? AND user_id = ?");
            $upd->execute([$amount, $date, $customerId, $userId]);
        }

        $pdo->commit();
        $successMsg = "Transaction successfully recorded!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Failed to record transaction: " . $e->getMessage();
    }
}

// 2. FETCH CORE METRICS CALCULATIONS
// Total Sales
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'sale'");
$stmt->execute([$userId]);
$totalSales = floatval($stmt->fetchColumn() ?: 0);

// Total Expenses
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'expense'");
$stmt->execute([$userId]);
$totalExpenses = floatval($stmt->fetchColumn() ?: 0);

// Total Outstanding Debts
$stmt = $pdo->prepare("SELECT SUM(debt_balance) FROM customers WHERE user_id = ?");
$stmt->execute([$userId]);
$totalDebts = floatval($stmt->fetchColumn() ?: 0);

$netProfit = $totalSales - $totalExpenses;

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
    </div>
</div>

<!-- Dynamic Success/Error messages -->
<?php if (!empty($successMsg)): ?>
    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); border-radius: 12px; padding: 12px 16px; margin-bottom: 25px; font-weight: 500;">
        <i class="fas fa-check-circle" style="margin-right: 8px;"></i> <?= htmlspecialchars($successMsg) ?>
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
                        } elseif ($t['status'] === 'credit') {
                            $badgeClass = 'badge-warning';
                            $statusKey = 'text_credit';
                        }
                    ?>
                        <tr>
                            <td><?= $t['date'] ?></td>
                            <td><?= htmlspecialchars($t['description']) ?></td>
                            <td><?= ($t['type'] === 'sale') ? 'Sale' : 'Expense' ?></td>
                            <td style="font-weight:700; color:<?= ($t['type'] === 'sale') ? 'var(--success)' : 'var(--danger)' ?>">
                                <?= ($t['type'] === 'sale') ? '+' : '-' ?><?= number_format($t['amount']) ?> ETB
                            </td>
                            <td><span class="badge <?= $badgeClass ?>" data-localize="<?= $statusKey ?>"><?= ($t['type'] === 'expense') ? 'Expense' : (($t['status'] === 'credit') ? 'Unpaid Debt' : 'Paid') ?></span></td>
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

                <!-- Sales specific filters -->
                <div id="form-sales-only-fields">
                    <div class="form-group">
                        <label class="form-label" data-localize="form_payment_status">Payment Status</label>
                        <select name="payment_status" class="form-control" id="form-tx-payment-status" onchange="toggleCreditCustomerField()">
                            <option value="paid" data-localize="status_paid">Paid (Cash / Telebirr)</option>
                            <option value="credit" data-localize="status_credit">On Credit (ዕዳ)</option>
                        </select>
                    </div>

                    <div class="form-group" id="form-credit-customer-group" style="display: none;">
                        <label class="form-label" data-localize="form_customer_select">Link to Customer Account</label>
                        <select name="customer_id" class="form-control" id="form-tx-customer-id">
                            <?php foreach ($customersList as $cust): ?>
                                <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['name']) ?> (<?= htmlspecialchars($cust['shop_name'] ?: 'No Shop') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 5px;" data-localize="form_customer_helper">
                            Customer not registered? Go to the Debtors CRM tab to register them.
                        </div>
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;" data-localize="btn_save_tx">Save Transaction</button>
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

    function toggleCreditCustomerField() {
        const status = document.getElementById('form-tx-payment-status').value;
        const group = document.getElementById('form-credit-customer-group');
        group.style.display = (status === 'credit') ? 'block' : 'none';
    }
</script>

<?php
// Attach shared footer layout
require_once 'footer.php';
?>
