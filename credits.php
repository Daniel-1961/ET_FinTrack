<?php
/**
 * FinTrack ET - Customer Credit & Debt CRM Manager
 * Coordinates customer profiling, debt balance updates, and logs repayment transactions in the ledger.
 */
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$successMsg = "";
$errorMsg = "";

// 1. HANDLE NEW CUSTOMER POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'add_customer') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $shop = trim($_POST['shop_name']);
    $location = trim($_POST['location']);

    if (empty($name) || empty($phone)) {
        $errorMsg = "Name and Phone fields are required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO customers (user_id, name, phone, shop_name, location) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $phone, $shop, $location]);
            $successMsg = "Customer registered successfully!";
        } catch (Exception $e) {
            $errorMsg = "Failed to register customer: " . $e->getMessage();
        }
    }
}

// 1.5 HANDLE EDIT CUSTOMER POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'edit_customer') {
    $customerId = intval($_POST['customer_id']);
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $shop = trim($_POST['shop_name']);
    $location = trim($_POST['location']);

    if (empty($name) || empty($phone)) {
        $errorMsg = "Name and Phone fields are required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, shop_name = ?, location = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $phone, $shop, $location, $customerId, $userId]);
            $successMsg = "Customer details updated successfully!";
        } catch (Exception $e) {
            $errorMsg = "Failed to update customer: " . $e->getMessage();
        }
    }
}

// 2. HANDLE DEBT REPAYMENT POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'log_repayment') {
    $customerId = intval($_POST['customer_id']);
    $repayAmount = floatval($_POST['repay_amount']);
    $method = $_POST['repay_method'];
    $date = $_POST['repay_date'];

    try {
        $pdo->beginTransaction();

        // Retrieve customer details first
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND user_id = ?");
        $stmt->execute([$customerId, $userId]);
        $cust = $stmt->fetch();

        if ($cust) {
            if ($repayAmount > $cust['debt_balance']) {
                $errorMsg = "Repayment amount cannot exceed current outstanding debt!";
                $pdo->rollBack();
            } else {
                // Deduct debt from customer balance
                $upd = $pdo->prepare("UPDATE customers SET debt_balance = debt_balance - ?, last_active = ? WHERE id = ? AND user_id = ?");
                $upd->execute([$repayAmount, $date, $customerId, $userId]);

                // Insert into payments history table
                $insPay = $pdo->prepare("INSERT INTO payments (user_id, customer_id, amount, method, date) VALUES (?, ?, ?, ?, ?)");
                $insPay->execute([$userId, $customerId, $repayAmount, $method, $date]);

                // Insert corresponding sale transaction into ledger
                $descEn = "Debt repayment from " . $cust['name'] . " (" . strtoupper($method) . ")";
                $insTx = $pdo->prepare("INSERT INTO transactions (user_id, customer_id, date, description, type, amount, category, status) VALUES (?, NULL, ?, ?, 'sale', ?, 'Debt Repayment', 'paid')");
                $insTx->execute([$userId, $date, $descEn, $repayAmount]);

                $pdo->commit();
                $successMsg = "Repayment of " . number_format($repayAmount) . " ETB successfully logged!";
            }
        } else {
            $pdo->rollBack();
            $errorMsg = "Customer profile not found.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Repayment transaction failed: " . $e->getMessage();
    }
}

// 3. HANDLE CUSTOMER DELETION (GET request)
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ? AND user_id = ?");
        $stmt->execute([$deleteId, $userId]);
        $successMsg = "Customer record deleted successfully.";
    } catch (Exception $e) {
        $errorMsg = "Failed to delete customer: " . $e->getMessage();
    }
}

// 4. FETCH ALL REGISTERED CUSTOMERS
$searchQuery = "";
$params = ['user_id' => $userId];

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchQuery = trim($_GET['search']);
    $sql = "SELECT * FROM customers WHERE user_id = :user_id AND (name LIKE :query OR phone LIKE :query OR shop_name LIKE :query OR location LIKE :query) ORDER BY name ASC";
    $params['query'] = '%' . $searchQuery . '%';
} else {
    $sql = "SELECT * FROM customers WHERE user_id = :user_id ORDER BY name ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

require_once 'header.php';
?>

<!-- Title Header Area -->
<div class="dashboard-header">
    <div class="welcome-section">
        <h1 data-localize="crm_title">Customer Debt Tracker</h1>
        <p data-localize="crm_subtitle">Easily manage your regular customers, record outstanding balances, and trace repayments.</p>
    </div>
    <div class="header-actions">
        <button class="btn btn-accent" onclick="openDrawer('customer')">
            <i class="fas fa-user-plus"></i> <span data-localize="btn_add_customer">Add Customer</span>
        </button>
    </div>
</div>

<!-- Alerts Panel -->
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

<!-- Search filter form -->
<form action="credits.php" method="GET" style="max-width: 600px; margin-bottom: 25px; position: relative;">
    <span style="position: absolute; left: 16px; top: 14px; color: var(--text-secondary);">
        <i class="fas fa-search"></i>
    </span>
    <input type="text" name="search" class="form-control" style="padding-left: 45px;" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search customers by name, phone or shop location...">
</form>

<!-- Customers Registry Card -->
<div class="panel">
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th data-localize="table_customer">Customer Name</th>
                    <th data-localize="table_phone">Phone</th>
                    <th data-localize="table_location">Location</th>
                    <th data-localize="table_total_debt">Outstanding Debt</th>
                    <th data-localize="table_last_active">Last Repayment</th>
                    <th data-localize="table_actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            No customers found. Click Register Customer to begin.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td style="font-weight:600;">
                                <div><?= htmlspecialchars($c['name']) ?></div>
                                <?php if (!empty($c['shop_name'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($c['shop_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($c['phone']) ?></td>
                            <td><?= htmlspecialchars($c['location'] ?: 'Addis Ababa') ?></td>
                            <td style="font-weight:700; color: <?= ($c['debt_balance'] > 0) ? 'var(--danger)' : 'var(--text-primary)' ?>">
                                <?= number_format($c['debt_balance']) ?> ETB
                            </td>
                            <td><?= $c['last_active'] ?: 'No activity' ?></td>
                            <td>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <?php if ($c['debt_balance'] > 0): ?>
                                        <button class="btn btn-accent btn-small" onclick="openRepayDrawer(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['name'])) ?>', <?= $c['debt_balance'] ?>)">
                                            <i class="fas fa-hand-holding-dollar"></i> <span data-localize="action_pay">Repay</span>
                                        </button>
                                    <?php else: ?>
                                        <span style="font-size:0.8rem; color:var(--text-muted);"><i class="fas fa-check-circle" style="color:var(--success)"></i> Clear</span>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-secondary btn-small" onclick="openCustomerDrawer('edit', <?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['name'])) ?>', '<?= addslashes(htmlspecialchars($c['phone'])) ?>', '<?= addslashes(htmlspecialchars($c['shop_name'])) ?>', '<?= addslashes(htmlspecialchars($c['location'])) ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <a href="credits.php?delete_id=<?= $c['id'] ?>" class="btn btn-danger btn-small" onclick="return confirm(localStorage.getItem('fintrack_lang') === 'am' ? 'ደንበኛውን መሰረዝ እንደሚፈልጉ እርግጠኛ ነዎት?' : 'Are you sure you want to delete this customer profile?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CUSTOMER PROFILE DRAWER -->
<div class="drawer-overlay" id="drawer-customer">
    <div class="drawer">
        <div class="drawer-header">
            <h3 id="drawer-customer-title" data-localize="drawer_customer_title">Register Customer</h3>
            <button class="drawer-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="drawer-body">
            <form action="credits.php" method="POST">
                <input type="hidden" name="action_type" id="form-customer-action" value="add_customer">
                <input type="hidden" name="customer_id" id="form-customer-id">
                
                <div class="form-group">
                    <label class="form-label" data-localize="form_cust_name">Full Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Abebe Kebede, Almaz Tesfaye">
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-localize="form_cust_phone">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" required placeholder="e.g. 0911223344">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" data-localize="form_cust_shop">Shop/Business Name</label>
                        <input type="text" name="shop_name" class="form-control" placeholder="e.g. Merkato Stall #44">
                    </div>
                    <div class="form-group">
                        <label class="form-label" data-localize="form_cust_location">Location / Addis Subcity</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Bole, Piazza, Kolfe">
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" id="btn-save-customer" class="btn btn-accent" style="width: 100%;" data-localize="btn_save_customer">Register Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- REPAYMENT DRAWER -->
<div class="drawer-overlay" id="drawer-repayment">
    <div class="drawer">
        <div class="drawer-header">
            <h3 data-localize="drawer_repay_title">Record Debt Repayment</h3>
            <button class="drawer-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="drawer-body">
            <form action="credits.php" method="POST">
                <input type="hidden" name="action_type" value="log_repayment">
                <input type="hidden" name="customer_id" id="form-repay-customer-id">
                
                <div class="form-group">
                    <label class="form-label" data-localize="form_repay_customer_name">Debtor Customer</label>
                    <input type="text" class="form-control" id="form-repay-customer-name" readonly style="background: rgba(255,255,255,0.02); cursor: not-allowed;">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" data-localize="form_repay_balance">Outstanding Balance (ETB)</label>
                        <input type="text" class="form-control" id="form-repay-current-balance" readonly style="background: rgba(255,255,255,0.02); cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" data-localize="form_repay_amount">Repayment Amount (ETB)</label>
                        <input type="number" name="repay_amount" class="form-control" min="1" id="form-repay-amount-input" required placeholder="Enter amount received">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" data-localize="form_repay_method">Repayment Method</label>
                    <select name="repay_method" class="form-control">
                        <option value="cash" data-localize="method_cash">Cash (በጥሬ ገንዘብ)</option>
                        <option value="telebirr" data-localize="method_telebirr">Telebirr (በቴሌብር)</option>
                        <option value="bank_transfer">CBE Birr / Bank Transfer</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" data-localize="form_repay_date">Repayment Date</label>
                    <input type="date" name="repay_date" id="form-repay-date" class="form-control" required>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;" data-localize="btn_submit_repay">Log Repayment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openDrawer(type) {
        if (type === 'customer') {
            openCustomerDrawer('add');
        }
    }

    function openCustomerDrawer(mode, id = '', name = '', phone = '', shop = '', location = '') {
        document.getElementById('form-customer-action').value = (mode === 'add') ? 'add_customer' : 'edit_customer';
        document.getElementById('form-customer-id').value = id;
        document.querySelector('#drawer-customer input[name="name"]').value = name;
        document.querySelector('#drawer-customer input[name="phone"]').value = phone;
        document.querySelector('#drawer-customer input[name="shop_name"]').value = shop;
        document.querySelector('#drawer-customer input[name="location"]').value = location;

        const lang = localStorage.getItem('fintrack_lang') || 'en';
        if (mode === 'add') {
            document.getElementById('drawer-customer-title').textContent = (lang === 'en') ? 'Register Customer' : 'አዲስ ደንበኛ መመዝገቢያ';
            document.getElementById('btn-save-customer').textContent = (lang === 'en') ? 'Register Customer' : 'ደንበኛውን መዝግብ';
        } else {
            document.getElementById('drawer-customer-title').textContent = (lang === 'en') ? 'Edit Customer' : 'ደንበኛውን አስተካክል';
            document.getElementById('btn-save-customer').textContent = (lang === 'en') ? 'Save Changes' : 'አስተካክል';
        }

        document.getElementById('drawer-customer').style.display = 'flex';
    }

    function openRepayDrawer(id, name, balance) {
        document.getElementById('form-repay-customer-id').value = id;
        document.getElementById('form-repay-customer-name').value = name;
        document.getElementById('form-repay-current-balance').value = balance;
        document.getElementById('form-repay-amount-input').max = balance;
        document.getElementById('form-repay-amount-input').value = '';
        document.getElementById('form-repay-date').value = new Date().toISOString().split('T')[0];

        document.getElementById('drawer-repayment').style.display = 'flex';
    }
</script>

<?php
require_once 'footer.php';
?>
