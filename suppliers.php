<?php
/**
 * FinTrack ET - Suppliers Manager
 * View, edit, and pay suppliers.
 */
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$successMsg = "";
$errorMsg = "";

// 1. HANDLE NEW SUPPLIER POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'add_supplier') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);

    if (empty($name) || empty($phone)) {
        $errorMsg = "Name and Phone fields are required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO suppliers (user_id, name, phone) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $name, $phone]);
            $successMsg = "Supplier registered successfully!";
        } catch (Exception $e) {
            $errorMsg = "Failed to register supplier: " . $e->getMessage();
        }
    }
}

// 1.5 HANDLE EDIT SUPPLIER POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'edit_supplier') {
    $supplierId = intval($_POST['supplier_id']);
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);

    if (empty($name) || empty($phone)) {
        $errorMsg = "Name and Phone fields are required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, phone = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $phone, $supplierId, $userId]);
            $successMsg = "Supplier details updated successfully!";
        } catch (Exception $e) {
            $errorMsg = "Failed to update supplier: " . $e->getMessage();
        }
    }
}

// 2. HANDLE SUPPLIER PAYMENT POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'log_payment') {
    $supplierId = intval($_POST['supplier_id']);
    $payAmount = floatval($_POST['pay_amount']);
    $date = $_POST['pay_date'];

    try {
        $pdo->beginTransaction();

        // Retrieve supplier details
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND user_id = ?");
        $stmt->execute([$supplierId, $userId]);
        $sup = $stmt->fetch();

        if ($sup) {
            if ($payAmount > $sup['debt_balance']) {
                $errorMsg = "Payment amount cannot exceed current outstanding debt!";
                $pdo->rollBack();
            } else {
                // Deduct debt from supplier balance
                $upd = $pdo->prepare("UPDATE suppliers SET debt_balance = debt_balance - ? WHERE id = ? AND user_id = ?");
                $upd->execute([$payAmount, $supplierId, $userId]);

                // Insert corresponding expense transaction into ledger
                $descEn = "Debt payment to supplier " . $sup['name'];
                $insTx = $pdo->prepare("INSERT INTO transactions (user_id, supplier_id, date, description, type, amount, category, status) VALUES (?, ?, ?, ?, 'expense', ?, 'Supplier Repayment', 'paid')");
                $insTx->execute([$userId, $supplierId, $date, $descEn, $payAmount]);

                $pdo->commit();
                $successMsg = "Payment of " . number_format($payAmount) . " ETB successfully logged!";
            }
        } else {
            $pdo->rollBack();
            $errorMsg = "Supplier profile not found.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Payment transaction failed: " . $e->getMessage();
    }
}

// 3. HANDLE SUPPLIER DELETION (GET request)
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ? AND user_id = ?");
        $stmt->execute([$deleteId, $userId]);
        $successMsg = "Supplier record deleted successfully.";
    } catch (Exception $e) {
        $errorMsg = "Failed to delete supplier (may be linked to past transactions): " . $e->getMessage();
    }
}

// 4. FETCH ALL REGISTERED SUPPLIERS
$searchQuery = "";
$params = ['user_id' => $userId];

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchQuery = trim($_GET['search']);
    $sql = "SELECT * FROM suppliers WHERE user_id = :user_id AND (name LIKE :query OR phone LIKE :query) ORDER BY name ASC";
    $params['query'] = '%' . $searchQuery . '%';
} else {
    $sql = "SELECT * FROM suppliers WHERE user_id = :user_id ORDER BY name ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

require_once 'header.php';
?>

<!-- Title Header Area -->
<div class="dashboard-header">
    <div class="welcome-section">
        <h1>Supplier Management</h1>
        <p>Manage your suppliers, update their contact info, and track outstanding balances.</p>
    </div>
    <div class="header-actions">
        <button class="btn btn-accent" onclick="openSupplierDrawer('add')">
            <i class="fas fa-plus"></i> <span>Add Supplier</span>
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
<form action="suppliers.php" method="GET" style="max-width: 600px; margin-bottom: 25px; position: relative;">
    <span style="position: absolute; left: 16px; top: 14px; color: var(--text-secondary);">
        <i class="fas fa-search"></i>
    </span>
    <input type="text" name="search" class="form-control" style="padding-left: 45px;" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search suppliers by name or phone...">
</form>

<!-- Suppliers Registry Card -->
<div class="panel">
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Supplier Name</th>
                    <th>Phone</th>
                    <th>Amount Owed (Debt)</th>
                    <th>Registered On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            No suppliers found. Click Add Supplier to begin.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td style="font-weight:600;"><?= htmlspecialchars($s['name']) ?></td>
                            <td><?= htmlspecialchars($s['phone']) ?></td>
                            <td style="font-weight:700; color: <?= ($s['debt_balance'] > 0) ? 'var(--danger)' : 'var(--text-primary)' ?>">
                                <?= number_format($s['debt_balance']) ?> ETB
                            </td>
                            <td><?= date('Y-m-d', strtotime($s['created_at'])) ?></td>
                            <td>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <?php if ($s['debt_balance'] > 0): ?>
                                        <button class="btn btn-primary btn-small" onclick="openSupplierPayDrawer(<?= $s['id'] ?>, '<?= addslashes(htmlspecialchars($s['name'])) ?>', <?= $s['debt_balance'] ?>)">
                                            <i class="fas fa-money-bill-wave"></i> <span>Pay</span>
                                        </button>
                                    <?php else: ?>
                                        <span style="font-size:0.8rem; color:var(--text-muted);"><i class="fas fa-check-circle" style="color:var(--success)"></i> Settled</span>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-secondary btn-small" onclick="openSupplierDrawer('edit', <?= $s['id'] ?>, '<?= addslashes(htmlspecialchars($s['name'])) ?>', '<?= addslashes(htmlspecialchars($s['phone'])) ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <a href="suppliers.php?delete_id=<?= $s['id'] ?>" class="btn btn-danger btn-small" onclick="return confirm('Are you sure you want to delete this supplier profile?');">
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

<!-- SUPPLIER ADD/EDIT DRAWER -->
<div class="drawer-overlay" id="drawer-supplier">
    <div class="drawer">
        <div class="drawer-header">
            <h3 id="drawer-supplier-title">Add Supplier</h3>
            <button class="drawer-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="drawer-body">
            <form action="suppliers.php" method="POST">
                <input type="hidden" name="action_type" id="form-supplier-action" value="add_supplier">
                <input type="hidden" name="supplier_id" id="form-supplier-id">
                
                <div class="form-group">
                    <label class="form-label">Supplier Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Merkato Wholesale">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" required placeholder="e.g. 0911223344">
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="submit" id="btn-save-supplier" class="btn btn-accent" style="width: 100%;">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SUPPLIER PAYMENT DRAWER -->
<div class="drawer-overlay" id="drawer-sup-payment">
    <div class="drawer">
        <div class="drawer-header">
            <h3>Record Payment to Supplier</h3>
            <button class="drawer-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="drawer-body">
            <form action="suppliers.php" method="POST">
                <input type="hidden" name="action_type" value="log_payment">
                <input type="hidden" name="supplier_id" id="form-sup-pay-id">
                
                <div class="form-group">
                    <label class="form-label">Supplier</label>
                    <input type="text" class="form-control" id="form-sup-pay-name" readonly style="background: rgba(255,255,255,0.02); cursor: not-allowed;">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Owed Balance (ETB)</label>
                        <input type="text" class="form-control" id="form-sup-pay-balance" readonly style="background: rgba(255,255,255,0.02); cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Amount (ETB)</label>
                        <input type="number" name="pay_amount" class="form-control" min="1" id="form-sup-pay-amount-input" required placeholder="Amount you paid">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Date of Payment</label>
                    <input type="date" name="pay_date" id="form-sup-pay-date" class="form-control" required>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Log Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openSupplierDrawer(mode, id = '', name = '', phone = '') {
        document.getElementById('form-supplier-action').value = (mode === 'add') ? 'add_supplier' : 'edit_supplier';
        document.getElementById('form-supplier-id').value = id;
        document.querySelector('#drawer-supplier input[name="name"]').value = name;
        document.querySelector('#drawer-supplier input[name="phone"]').value = phone;

        if (mode === 'add') {
            document.getElementById('drawer-supplier-title').textContent = 'Add Supplier';
            document.getElementById('btn-save-supplier').textContent = 'Save Supplier';
        } else {
            document.getElementById('drawer-supplier-title').textContent = 'Edit Supplier';
            document.getElementById('btn-save-supplier').textContent = 'Save Changes';
        }

        document.getElementById('drawer-supplier').style.display = 'flex';
    }

    function openSupplierPayDrawer(id, name, balance) {
        document.getElementById('form-sup-pay-id').value = id;
        document.getElementById('form-sup-pay-name').value = name;
        document.getElementById('form-sup-pay-balance').value = balance;
        document.getElementById('form-sup-pay-amount-input').max = balance;
        document.getElementById('form-sup-pay-amount-input').value = '';
        document.getElementById('form-sup-pay-date').value = new Date().toISOString().split('T')[0];

        document.getElementById('drawer-sup-payment').style.display = 'flex';
    }
</script>

<?php
require_once 'footer.php';
?>
