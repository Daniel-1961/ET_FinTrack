<?php
/**
 * FinTrack ET - Transaction Ledger Manager
 * Renders complete ledger history, offers dynamic GET filters, and handles safe transaction deletions.
 */
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$successMsg = "";
$errorMsg = "";

// 1. HANDLE LEDGER DELETION (GET request)
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);

    try {
        $pdo->beginTransaction();

        // Check if transaction exists and read details for credit balance recovery
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->execute([$deleteId, $userId]);
        $tx = $stmt->fetch();

        if ($tx) {
            // If deleting a credit sale, decrement customer's debt balance
            if ($tx['type'] === 'sale' && $tx['status'] === 'credit' && !empty($tx['customer_id'])) {
                $upd = $pdo->prepare("UPDATE customers SET debt_balance = GREATEST(0.00, debt_balance - ?) WHERE id = ? AND user_id = ?");
                $upd->execute([$tx['amount'], $tx['customer_id'], $userId]);
            }

            // Perform deletion
            $del = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
            $del->execute([$deleteId, $userId]);

            $pdo->commit();
            $successMsg = "Record deleted successfully!";
        } else {
            $pdo->rollBack();
            $errorMsg = "Transaction not found or insufficient access.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Error deleting record: " . $e->getMessage();
    }
}

// 2. RESOLVE FILTER PARAMS
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$sql = "SELECT t.*, c.name as customer_name FROM transactions t LEFT JOIN customers c ON t.customer_id = c.id WHERE t.user_id = :user_id";

if ($filter === 'sale') {
    $sql .= " AND t.type = 'sale'";
} elseif ($filter === 'expense') {
    $sql .= " AND t.type = 'expense'";
} elseif ($filter === 'credit') {
    $sql .= " AND t.status = 'credit'";
}

$sql .= " ORDER BY t.date DESC, t.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $userId]);
$transactions = $stmt->fetchAll();

require_once 'header.php';
?>

<!-- Title Area -->
<div class="dashboard-header">
    <div class="welcome-section">
        <h1 data-localize="ledger_title">Financial Ledger</h1>
        <p data-localize="ledger_subtitle">Complete record of every transaction in your business.</p>
    </div>
    <div class="header-actions">
        <a href="dashboard.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> <span data-localize="btn_record_sale">Record Sale</span>
        </a>
    </div>
</div>

<!-- Feedback alerts -->
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

<!-- Dynamic Tabs Filter Selection -->
<div class="tab-selector" style="max-width: 600px;">
    <a href="transactions.php?filter=all" class="tab-btn <?= ($filter === 'all') ? 'active' : '' ?>" data-localize="filter_all">All Records</a>
    <a href="transactions.php?filter=sale" class="tab-btn <?= ($filter === 'sale') ? 'active' : '' ?>" data-localize="filter_sales">Sales Only</a>
    <a href="transactions.php?filter=expense" class="tab-btn <?= ($filter === 'expense') ? 'active' : '' ?>" data-localize="filter_expenses">Expenses Only</a>
    <a href="transactions.php?filter=credit" class="tab-btn <?= ($filter === 'credit') ? 'active' : '' ?>" data-localize="filter_credits">Debts & Credits</a>
</div>

<!-- Transactions Table List -->
<div class="panel">
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th data-localize="table_date">Date</th>
                    <th data-localize="table_desc">Description</th>
                    <th data-localize="table_category">Category</th>
                    <th data-localize="table_amount">Amount</th>
                    <th data-localize="table_status">Status</th>
                    <th data-localize="table_actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            No records found under this category.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $t): 
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
                            <td style="font-weight: 500;">
                                <?= htmlspecialchars($t['description']) ?>
                                <?php if (!empty($t['customer_name'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px;">
                                        <i class="fas fa-user-tag" style="color: var(--secondary); margin-right: 4px;"></i> <?= htmlspecialchars($t['customer_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($t['category']) ?></td>
                            <td style="font-weight: 700; color: <?= ($t['type'] === 'sale') ? 'var(--success)' : 'var(--danger)' ?>">
                                <?= ($t['type'] === 'sale') ? '+' : '-' ?><?= number_format($t['amount']) ?> ETB
                            </td>
                            <td><span class="badge <?= $badgeClass ?>" data-localize="<?= $statusKey ?>"><?= ($t['type'] === 'expense') ? 'Expense' : (($t['status'] === 'credit') ? 'Unpaid Debt' : 'Paid') ?></span></td>
                            <td>
                                <a href="transactions.php?filter=<?= $filter ?>&delete_id=<?= $t['id'] ?>" class="btn btn-danger btn-small" onclick="return confirm(localStorage.getItem('fintrack_lang') === 'am' ? 'ይህን መዝገብ መሰረዝ እንደሚፈልጉ እርግጠኛ ነዎት?' : 'Are you sure you want to delete this record?');">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once 'footer.php';
?>
