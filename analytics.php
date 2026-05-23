<?php
/**
 * FinTrack ET - Financial Overview Dashboard
 * Provides year/month-based analytics with key financial metrics
 */
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$successMsg = "";
$errorMsg = "";

// Get current year and month as defaults
$currentYear = date('Y');
$currentMonth = date('m');

// Get filter values from GET parameters
$filterYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;
$filterMonth = isset($_GET['month']) ? intval($_GET['month']) : null;

// Validate year range (prevent extreme values)
if ($filterYear < 1990 || $filterYear > 2100) {
    $filterYear = $currentYear;
}

// Build date filter condition
$dateCondition = "DATE_FORMAT(date, '%Y') = ?";
$dateParams = [$filterYear];

if ($filterMonth !== null && $filterMonth > 0 && $filterMonth <= 12) {
    $dateCondition = "DATE_FORMAT(date, '%Y-%m') = ?";
    $dateParams = [date('Y-m', strtotime("$filterYear-" . str_pad($filterMonth, 2, '0', STR_PAD_LEFT) . "-01"))];
}

// 1. FETCH CORE METRICS FOR THE SELECTED PERIOD

// Total Income (Sales)
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'sale' AND $dateCondition");
$stmt->execute(array_merge([$userId], $dateParams));
$totalIncome = floatval($stmt->fetchColumn() ?: 0);

// Total Expenses
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'expense' AND $dateCondition");
$stmt->execute(array_merge([$userId], $dateParams));
$totalExpenses = floatval($stmt->fetchColumn() ?: 0);

// Receivables (Outstanding Customer Debts)
// For monthly view: only consider debts created in that month
// For yearly view: show all outstanding debts
$receivablesSql = "SELECT SUM(debt_balance) FROM customers WHERE user_id = ? AND debt_balance > 0";
if ($filterMonth !== null && $filterMonth > 0) {
    $receivablesSql .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
    $stmt = $pdo->prepare($receivablesSql);
    $stmt->execute(array_merge([$userId], $dateParams));
} else {
    $stmt = $pdo->prepare($receivablesSql);
    $stmt->execute([$userId]);
}
$receivables = floatval($stmt->fetchColumn() ?: 0);

// Payables (Outstanding Supplier Debts)
// Similarly for payables
$payablesSql = "SELECT SUM(debt_balance) FROM suppliers WHERE user_id = ? AND debt_balance > 0";
if ($filterMonth !== null && $filterMonth > 0) {
    $payablesSql .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
    $stmt = $pdo->prepare($payablesSql);
    $stmt->execute(array_merge([$userId], $dateParams));
} else {
    $stmt = $pdo->prepare($payablesSql);
    $stmt->execute([$userId]);
}
$payables = floatval($stmt->fetchColumn() ?: 0);

// Savings (can be calculated as Income - Expenses, or as a sum of explicit savings transactions)
// For now, we'll calculate it as a subset of income marked as savings or use income - expenses
$savings = max(0, $totalIncome - $totalExpenses - $payables);

// Net Position (Income - Expenses + Savings adjustments)
$netPosition = $totalIncome - $totalExpenses;

// 2. Gross Profit with cost tracking
$stmt = $pdo->prepare("SELECT SUM((amount - COALESCE(cost_price, 0) * COALESCE(quantity, 1))) FROM transactions WHERE user_id = ? AND type = 'sale' AND cost_price IS NOT NULL AND $dateCondition");
$stmt->execute(array_merge([$userId], $dateParams));
$grossProfit = floatval($stmt->fetchColumn() ?: 0);

// For sales without cost tracking
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'sale' AND cost_price IS NULL AND $dateCondition");
$stmt->execute(array_merge([$userId], $dateParams));
$customSalesProfit = floatval($stmt->fetchColumn() ?: 0);

$netProfit = $grossProfit + $customSalesProfit - $totalExpenses;

// 3. Get available years for the dropdown
$stmt = $pdo->prepare("SELECT DISTINCT YEAR(date) as year FROM transactions WHERE user_id = ? UNION SELECT DISTINCT YEAR(created_at) as year FROM customers WHERE user_id = ? UNION SELECT DISTINCT YEAR(created_at) as year FROM suppliers WHERE user_id = ? ORDER BY year DESC");
$stmt->execute([$userId, $userId, $userId]);
$availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($availableYears)) {
    $availableYears = [$currentYear];
}

// 4. Ensure current year is in the list
if (!in_array($currentYear, $availableYears)) {
    $availableYears[] = $currentYear;
    sort($availableYears);
    rsort($availableYears);
}

// 5. Get transaction breakdown for the period (for detailed analysis)
$stmt = $pdo->prepare("
    SELECT 
        type,
        category,
        SUM(amount) as total,
        COUNT(*) as count
    FROM transactions 
    WHERE user_id = ? AND $dateCondition
    GROUP BY type, category
    ORDER BY type, total DESC
");
$stmt->execute(array_merge([$userId], $dateParams));
$transactionBreakdown = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Overview - FinTrack ET</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .analytics-container {
            min-height: 100vh;
            padding: 40px 20px;
            background: linear-gradient(135deg, #0B0F19 0%, #1a1f2e 100%);
        }

        .analytics-header {
            margin-bottom: 40px;
            color: var(--text-light);
        }

        .analytics-header h1 {
            font-size: 32px;
            margin-bottom: 8px;
            font-weight: 700;
            color: var(--text-heading);
        }

        .analytics-header p {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .filter-section {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 32px;
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
            box-shadow: var(--shadow-main);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 0 1 auto;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-input);
            color: var(--text-primary);
            font-size: 14px;
            font-family: 'Outfit', sans-serif;
            transition: var(--transition);
            min-width: 140px;
        }

        .filter-group select:hover,
        .filter-group select:focus,
        .filter-group input:hover,
        .filter-group input:focus {
            border-color: var(--primary-light);
            background: #2d3748;
            outline: none;
        }

        .filter-buttons {
            display: flex;
            gap: 12px;
        }

        .btn-filter,
        .btn-reset {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-filter {
            background: var(--btn-primary-bg);
            color: var(--btn-primary-text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

        .btn-reset {
            background: var(--bg-input);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-reset:hover {
            background: #374151;
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .metric-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow-main);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            opacity: 0;
            transition: var(--transition);
        }

        .metric-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary-light);
        }

        .metric-card:hover::before {
            opacity: 1;
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 12px;
            flex-shrink: 0;
        }

        .icon-net { background: rgba(15, 81, 50, 0.2); }
        .icon-income { background: rgba(16, 185, 129, 0.2); }
        .icon-expense { background: rgba(239, 68, 68, 0.2); }
        .icon-savings { background: rgba(59, 130, 246, 0.2); }
        .icon-receivables { background: rgba(251, 191, 36, 0.2); }
        .icon-payables { background: rgba(99, 102, 241, 0.2); }

        .metric-label {
            font-size: 14px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .metric-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-light);
            margin-bottom: 8px;
            display: flex;
            align-items: baseline;
            gap: 4px;
        }

        .metric-currency {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .metric-description {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .currency-selector {
            position: absolute;
            top: 20px;
            right: 20px;
        }

        .currency-btn {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .currency-btn:hover {
            background: #2d3748;
            border-color: var(--primary-light);
            color: var(--text-primary);
        }

        .breakdown-section {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow-main);
            margin-top: 32px;
        }

        .breakdown-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 20px;
        }

        .breakdown-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .breakdown-item {
            background: var(--bg-input);
            border-radius: 8px;
            padding: 16px;
            border-left: 3px solid var(--primary-light);
        }

        .breakdown-category {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .breakdown-amount {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-light);
        }

        .breakdown-count {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 6px;
        }

        .period-display {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        @media (max-width: 768px) {
            .analytics-container {
                padding: 20px 12px;
            }

            .analytics-header h1 {
                font-size: 24px;
            }

            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group select,
            .filter-group input {
                min-width: 100%;
            }

            .filter-buttons {
                flex-direction: column;
            }

            .btn-filter,
            .btn-reset {
                width: 100%;
                justify-content: center;
            }

            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .metric-card {
                padding: 20px;
            }

            .metric-value {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="analytics-container">
        <div class="currency-selector">
            <button class="currency-btn" onclick="alert('Currency: USD')">
                💱 $USD
            </button>
        </div>

        <div class="analytics-header">
            <h1>Financial Overview</h1>
            <p>Complete financial summary and reports</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" id="filterForm" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; width: 100%;">
                <div class="filter-group">
                    <label for="year">Year</label>
                    <select name="year" id="year">
                        <?php foreach (array_unique($availableYears) as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $filterYear == $year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="month">Month (Optional)</label>
                    <select name="month" id="month">
                        <option value="">-- All Months --</option>
                        <?php 
                        $months = [
                            '1' => 'January',
                            '2' => 'February',
                            '3' => 'March',
                            '4' => 'April',
                            '5' => 'May',
                            '6' => 'June',
                            '7' => 'July',
                            '8' => 'August',
                            '9' => 'September',
                            '10' => 'October',
                            '11' => 'November',
                            '12' => 'December'
                        ];
                        foreach ($months as $num => $name): 
                        ?>
                            <option value="<?php echo $num; ?>" <?php echo $filterMonth == intval($num) ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn-filter">🔍 Filter</button>
                    <button type="button" class="btn-reset" onclick="resetFilters()">↻ Reset</button>
                </div>
            </form>
        </div>

        <!-- Period Display -->
        <div class="period-display">
            <?php
            if ($filterMonth && $filterMonth > 0) {
                $monthName = $months[$filterMonth] ?? '';
                echo "Showing data for <strong>$monthName $filterYear</strong>";
            } else {
                echo "Showing data for the year <strong>$filterYear</strong>";
            }
            ?>
        </div>

        <!-- Metrics Grid -->
        <div class="metrics-grid">
            <!-- Net Position Card -->
            <div class="metric-card">
                <div class="metric-icon icon-net">📊</div>
                <div class="metric-label">Net Position</div>
                <div class="metric-value">
                    $<span><?php echo number_format($netPosition, 2); ?></span>
                </div>
                <div class="metric-description">Income - Expenses</div>
            </div>

            <!-- Total Income Card -->
            <div class="metric-card">
                <div class="metric-icon icon-income">📈</div>
                <div class="metric-label">Total Income</div>
                <div class="metric-value">
                    $<span><?php echo number_format($totalIncome, 2); ?></span>
                </div>
                <div class="metric-description">All income for <?php echo $filterMonth ? $months[$filterMonth] . ' ' : ''; ?><?php echo $filterYear; ?></div>
            </div>

            <!-- Total Expenses Card -->
            <div class="metric-card">
                <div class="metric-icon icon-expense">📉</div>
                <div class="metric-label">Total Expenses</div>
                <div class="metric-value" style="color: var(--danger);">
                    $<span><?php echo number_format($totalExpenses, 2); ?></span>
                </div>
                <div class="metric-description">All expenses for <?php echo $filterMonth ? $months[$filterMonth] . ' ' : ''; ?><?php echo $filterYear; ?></div>
            </div>

            <!-- Savings Card -->
            <div class="metric-card">
                <div class="metric-icon icon-savings">💰</div>
                <div class="metric-label">Savings</div>
                <div class="metric-value">
                    $<span><?php echo number_format($savings, 2); ?></span>
                </div>
                <div class="metric-description">Total active savings</div>
            </div>

            <!-- Receivables Card -->
            <div class="metric-card">
                <div class="metric-icon icon-receivables">👥</div>
                <div class="metric-label">Receivables</div>
                <div class="metric-value" style="color: var(--warning);">
                    $<span><?php echo number_format($receivables, 2); ?></span>
                </div>
                <div class="metric-description">Outstanding debts to collect</div>
            </div>

            <!-- Payables Card -->
            <div class="metric-card">
                <div class="metric-icon icon-payables">💳</div>
                <div class="metric-label">Payables</div>
                <div class="metric-value" style="color: var(--info);">
                    $<span><?php echo number_format($payables, 2); ?></span>
                </div>
                <div class="metric-description">Outstanding debts to pay</div>
            </div>
        </div>

        <!-- Transaction Breakdown Section -->
        <?php if (!empty($transactionBreakdown)): ?>
        <div class="breakdown-section">
            <div class="breakdown-title">Transaction Breakdown</div>
            <div class="breakdown-grid">
                <?php foreach ($transactionBreakdown as $breakdown): ?>
                    <div class="breakdown-item">
                        <div class="breakdown-category">
                            <?php 
                            echo ucfirst($breakdown['type']) . ' - ' . ucfirst(str_replace('_', ' ', $breakdown['category']));
                            ?>
                        </div>
                        <div class="breakdown-amount">
                            $<?php echo number_format($breakdown['total'], 2); ?>
                        </div>
                        <div class="breakdown-count">
                            <?php echo $breakdown['count']; ?> transaction<?php echo $breakdown['count'] != 1 ? 's' : ''; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        function resetFilters() {
            const year = new Date().getFullYear();
            window.location.href = '?year=' + year + '&month=';
        }

        document.getElementById('month').addEventListener('change', function() {
            if (this.value !== '') {
                document.getElementById('filterForm').submit();
            }
        });
    </script>
</body>
</html>
