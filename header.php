<?php
/**
 * FinTrack ET - Shared Responsive Header & Sidebar layout
 * Checks active login session states and renders cohesive global navigation.
 */
require_once 'auth.php';
requireLogin();

// Resolve active sidebar tab depending on file name
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinTrack ET (ፋይናንስ ትራክ) - Business Finance Manager</title>
    <!-- FontAwesome Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Premium Shared CSS Design System -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle" id="mobile-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="app-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon">F</div>
                <div class="brand-info">
                    <h3 data-localize="app_title">FinTrack ET</h3>
                    <p data-localize="app_subtitle">Addis Ababa Retail</p>
                </div>
            </div>

            <!-- User Info Widget -->
            <div class="user-widget">
                <div class="user-avatar"><i class="fas fa-store"></i></div>
                <div class="user-name">
                    <div style="font-weight: 700; color: var(--text-light);"><?= htmlspecialchars($_SESSION['business_name']) ?></div>
                    <div style="font-size: 0.7rem; color: var(--text-secondary);" data-localize="user_role">Owner Profile</div>
                </div>
            </div>

            <!-- Sidebar Menu list -->
            <ul class="sidebar-menu">
                <li class="menu-item <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                    <a href="dashboard.php"><i class="fas fa-th-large"></i> <span data-localize="menu_dashboard">Dashboard</span></a>
                </li>
                <li class="menu-item <?= ($currentPage === 'products.php') ? 'active' : '' ?>">
                    <a href="products.php"><i class="fas fa-box"></i> <span data-localize="menu_products">Inventory</span></a>
                </li>
                <li class="menu-item <?= ($currentPage === 'transactions.php') ? 'active' : '' ?>">
                    <a href="transactions.php"><i class="fas fa-exchange-alt"></i> <span data-localize="menu_transactions">Ledger</span></a>
                </li>
                <li class="menu-item <?= ($currentPage === 'credits.php') ? 'active' : '' ?>">
                    <a href="credits.php"><i class="fas fa-users-cog"></i> <span data-localize="menu_credits">Debtors CRM</span></a>
                </li>
                <li class="menu-item <?= ($currentPage === 'reports.php') ? 'active' : '' ?>">
                    <a href="reports.php"><i class="fas fa-chart-line"></i> <span data-localize="menu_reports">Reports & BI</span></a>
                </li>
            </ul>

            <!-- Logout Link inside sidebar -->
            <div style="margin-bottom: 20px; padding: 0 10px;">
                <a href="logout.php" class="btn btn-danger btn-small" style="width: 100%; display: flex; gap: 8px; justify-content: center;">
                    <i class="fas fa-arrow-right-from-bracket"></i> Logout
                </a>
            </div>

            <!-- Bilingual Switcher in Footer -->
            <div class="sidebar-footer">
                <div class="lang-toggle" id="php-lang-switcher">
                    <div class="lang-btn active" id="btn-php-en">EN</div>
                    <div class="lang-btn" id="btn-php-am">አማርኛ</div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
