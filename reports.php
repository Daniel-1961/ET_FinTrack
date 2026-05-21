<?php
/**
 * FinTrack ET - Reports & Intelligent Advisor Panel
 * Computes business math ratios (profit margins, debt exposure index) and presents Addis Ababa market insights.
 */
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$userId = $_SESSION['user_id'];

// 1. CALCULATE CORE PROFIT MARGINS
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'sale'");
$stmt->execute([$userId]);
$totalSales = floatval($stmt->fetchColumn() ?: 0);

$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'expense'");
$stmt->execute([$userId]);
$totalExpenses = floatval($stmt->fetchColumn() ?: 0);

$stmt = $pdo->prepare("SELECT SUM(debt_balance) FROM customers WHERE user_id = ?");
$stmt->execute([$userId]);
$totalDebts = floatval($stmt->fetchColumn() ?: 0);

$netProfit = $totalSales - $totalExpenses;
$profitMargin = $totalSales > 0 ? ($netProfit / $totalSales) * 100 : 0;
$debtRatio = $totalSales > 0 ? ($totalDebts / $totalSales) : 0;

// 2. FETCH EXPENSES CATEGORIZATION SHARE
$stmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'expense' GROUP BY category ORDER BY total DESC");
$stmt->execute([$userId]);
$expensesCategories = $stmt->fetchAll();

// 3. FETCH TOP OUTSTANDING DEBTORS (Up to 4)
$stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ? AND debt_balance > 0 ORDER BY debt_balance DESC LIMIT 4");
$stmt->execute([$userId]);
$topDebtors = $stmt->fetchAll();

require_once 'header.php';
?>

<!-- Title Area -->
<div class="dashboard-header">
    <div class="welcome-section">
        <h1 data-localize="reports_title">Profit Reports & Business Intelligence</h1>
        <p data-localize="reports_subtitle">Custom advice curated for micro and small businesses operating in Addis Ababa markets.</p>
    </div>
    <!-- Clean window.print triggers print stylesheet styles -->
    <button class="btn btn-secondary" onclick="window.print()">
        <i class="fas fa-print"></i> <span data-localize="btn_print">Print Summary</span>
    </button>
</div>

<!-- Addis Ababa Intelligent BI Advisor Card -->
<div class="bi-card">
    <h3 class="bi-title">
        <i class="fas fa-brain"></i> <span data-localize="bi_advisor">FinTrack Intelligent Market Advisor</span>
    </h3>
    <div id="php-bi-container">
        <!-- Renders dynamically depending on language switcher locale loaded in footer -->
    </div>
</div>

<!-- Layout reports split panels -->
<div class="dashboard-panels">
    
    <!-- Expenses categorization -->
    <div class="panel">
        <div class="panel-header">
            <h3 class="panel-title">
                <i class="fas fa-chart-pie"></i> <span data-localize="panel_expense_share">Expense Categorization</span>
            </h3>
        </div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th data-localize="table_category">Category</th>
                        <th data-localize="table_amount">Total Expended</th>
                        <th data-localize="table_percentage">Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expensesCategories)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: var(--text-secondary);" data-localize="no_expenses">No expenses recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expensesCategories as $cat): 
                            $pct = ($totalExpenses > 0) ? round(($cat['total'] / $totalExpenses) * 100) : 0;
                        ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($cat['category']) ?></td>
                                <td style="font-weight: 700; color: var(--danger);"><?= number_format($cat['total']) ?> ETB</td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="flex-grow: 1; background: var(--bg-input); height: 8px; border-radius: 4px; overflow: hidden;">
                                            <div style="background: var(--danger); width: <?= $pct ?>%; height: 100%;"></div>
                                        </div>
                                        <span style="font-weight: 700; font-size: 0.85rem;"><?= $pct ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Debtors list -->
    <div class="panel">
        <div class="panel-header">
            <h3 class="panel-title">
                <i class="fas fa-users"></i> <span data-localize="panel_top_debtors">Top Outstanding Debtors</span>
            </h3>
        </div>
        <div class="debts-warning-list">
            <?php if (empty($topDebtors)): ?>
                <div style="text-align: center; padding: 20px; color: var(--text-secondary);">No outstanding credit debtors!</div>
            <?php else: ?>
                <?php foreach ($topDebtors as $cust): ?>
                    <div class="debt-warning-item" style="border-left: 4px solid var(--danger); background: rgba(239, 68, 68, 0.04); border-radius: 0 12px 12px 0;">
                        <div class="debt-info">
                            <h5><?= htmlspecialchars($cust['name']) ?></h5>
                            <p><?= htmlspecialchars($cust['phone']) ?> | <?= htmlspecialchars($cust['location'] ?: 'Addis Ababa') ?></p>
                        </div>
                        <div class="debt-amount">
                            <span class="value" style="color: var(--danger); font-size: 1.1rem;"><?= number_format($cust['debt_balance']) ?> ETB</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Financial Summary overview for printing layouts -->
<div class="panel" style="margin-top: 30px;">
    <div class="panel-header">
        <h3 class="panel-title"><i class="fas fa-table-list"></i> Shop Summary Ledger sheet</h3>
    </div>
    <div class="table-responsive">
        <table class="custom-table" style="text-align: left;">
            <tbody>
                <tr>
                    <td style="font-weight: 600; width: 40%;">Total Sales volume</td>
                    <td style="font-weight: 700; color: var(--success);"><?= number_format($totalSales) ?> ETB</td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">Total Expenditures</td>
                    <td style="font-weight: 700; color: var(--danger);"><?= number_format($totalExpenses) ?> ETB</td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">Outstanding Customer Credits</td>
                    <td style="font-weight: 700; color: var(--warning);"><?= number_format($totalDebts) ?> ETB</td>
                </tr>
                <tr style="border-top: 2px solid var(--border-color);">
                    <td style="font-weight: 700; font-size: 1.1rem;">Net Profits</td>
                    <td style="font-weight: 800; color: var(--success); font-size: 1.1rem;"><?= number_format($netProfit) ?> ETB</td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">Net Profit Margin</td>
                    <td style="font-weight: 700; color: var(--info);"><?= number_format($profitMargin, 1) ?>%</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Variables injected directly from PHP to determine ratio metrics inside JS for localized BI advice
    const totalSales = <?= $totalSales ?>;
    const totalExpenses = <?= $totalExpenses ?>;
    const totalDebts = <?= $totalDebts ?>;
    const debtRatio = <?= $debtRatio ?>;

    function renderPHPBIAdvisor(lang) {
        const container = document.getElementById('php-bi-container');
        if (!container) return;

        container.innerHTML = '';
        const insights = [];

        if (lang === 'en') {
            if (debtRatio > 0.4) {
                insights.push({
                    icon: 'fa-exclamation-triangle',
                    text: `<strong>High Debt Exposure Alert:</strong> ${Math.round(debtRatio * 100)}% of your sales are outstanding credits. Highly advise halting credit logs to major debtors until repayments are submitted.`
                });
            } else {
                insights.push({
                    icon: 'fa-check-circle',
                    text: `<strong>Stable Credit Operations:</strong> Outstanding debts are within a safe threshold (${Math.round(debtRatio * 100)}% of sales). Credit management is highly disciplined.`
                });
            }

            if (totalExpenses > totalSales * 0.7) {
                insights.push({
                    icon: 'fa-lightbulb',
                    text: `<strong>Expense Control Advisory:</strong> Your business expenditures absorb ${Math.round((totalExpenses/totalSales)*100)}% of sales. Target transport expenses by consolidating Merkato trips from daily to bi-weekly to save transport cost.`
                });
            } else {
                insights.push({
                    icon: 'fa-arrow-trend-up',
                    text: `<strong>Strong Margin Health:</strong> Net Profit margins are high (${Math.round(totalSales > 0 ? (totalSales-totalExpenses)/totalSales*100 : 0)}%). Consider utilizing CBE Birr or Telebirr merchant savings accounts to earn local yields.`
                });
            }

            insights.push({
                icon: 'fa-location-dot',
                text: `<strong>Merkato Supply Chain Suggestion:</strong> Sugar and wholesale grains are fluctuating in price. Lock in supply costs with pre-purchase agreements at Merkato traders if bulk funds are available.`
            });
        } else {
            // Amharic advice
            if (debtRatio > 0.4) {
                insights.push({
                    icon: 'fa-exclamation-triangle',
                    text: `<strong>ከፍተኛ የብድር አደጋ ማስጠንቀቂያ፡</strong> ከጠቅላላ ሽያጭዎ ውስጥ ${Math.round(debtRatio * 100)}% ያህሉ ያልተሰበሰበ ብድር ነው። ባለዕዳዎች ክፍያ እስኪፈጽሙ ድረስ ተጨማሪ ብድር ባይሰጡ ይመረጣል።`
                });
            } else {
                insights.push({
                    icon: 'fa-check-circle',
                    text: `<strong>መረጋጋት ያለበት የብድር ሁኔታ፡</strong> የደንበኞች ዕዳ ከሽያጭዎ አንፃር ደህንነቱ በተጠበቀ ደረጃ ላይ ይገኛል (${Math.round(debtRatio * 100)}%)። ይህንኑ መልካም የብድር አስተዳደር ይቀጥሉ።`
                });
            }

            if (totalExpenses > totalSales * 0.7) {
                insights.push({
                    icon: 'fa-lightbulb',
                    text: `<strong>የወጪ መቀነሻ ምክር፡</strong> ወጪዎ ከጠቅላላ ሽያጭዎ ${Math.round((totalExpenses/totalSales)*100)}% ያህሉን ወስዷል። የወጪውን ድርሻ ለመቀነስ ወደ መርካቶ የሚደረጉ የዕቃ መግዣ ጉዞዎችን በየቀኑ ከማድረግ በሳምንት ሁለት ጊዜ በማድረግ የትራንስፖርት ወጪዎን ይቆጥቡ።`
                });
            } else {
                insights.push({
                    icon: 'fa-arrow-trend-up',
                    text: `<strong>ጥሩ የትርፍ መጠን፡</strong> በዚህ ወር የተጣራ የትርፍ መጠንዎ በጣም ከፍተኛ ነው (${Math.round(totalSales > 0 ? (totalSales-totalExpenses)/totalSales*100 : 0)}%)። ተጨማሪ ትርፍዎን በቴሌብር (Telebirr) ወይም በንግድ ባንክ ነጋዴ ሂሳብ ላይ በማስቀመጥ ወለድ ቢያገኙ ይመረጣል።`
                });
            }

            insights.push({
                icon: 'fa-location-dot',
                text: `<strong>የመርካቶ የዕቃ አቅርቦት መረጃ፡</strong> የጅምላ ሸቀጣሸቀጥ እና የስኳር ዋጋ በገበያው ላይ እየተዋዥቀ ይገኛል። ቀድመው ከታወቁ የመርካቶ ጅምላ አከፋፋዮች ጋር የረጅም ጊዜ አቅርቦት ስምምነት በማድረግ ወጪዎችን ያረጋጉ።`
            });
        }

        insights.forEach(ins => {
            const div = document.createElement('div');
            div.className = 'bi-advice-item';
            div.innerHTML = `
                <i class="fas ${ins.icon}"></i>
                <div>${ins.text}</div>
            `;
            container.appendChild(div);
        });
    }

    window.addEventListener('DOMContentLoaded', () => {
        const savedLang = localStorage.getItem('fintrack_lang') || 'en';
        renderPHPBIAdvisor(savedLang);

        window.addEventListener('langChanged', (e) => {
            renderPHPBIAdvisor(e.detail);
        });
    });
</script>

<?php
require_once 'footer.php';
?>
