<?php
/**
 * FinTrack ET - Shared Responsive Header & Sidebar layout
 * Checks active login session states and renders cohesive global navigation.
 */
require_once 'auth.php';
requireLogin();

// Resolve active sidebar tab depending on file name
$currentPage = basename($_SERVER['PHP_SELF']);

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    require_once 'config.php';
    $newUsername = trim($_POST['username']);
    $newBusinessName = trim($_POST['business_name']);
    $newPassword = $_POST['password'];
    $userId = $_SESSION['user_id'];

    if (empty($newUsername) || empty($newBusinessName)) {
        $_SESSION['profile_error'] = "Username and Business Name cannot be empty.";
    } else {
        try {
            // Check if username is taken by another user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$newUsername, $userId]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['profile_error'] = "Username is already taken by another business.";
            } else {
                if (!empty($newPassword)) {
                    // Update username, business name, and password
                    $hashedPass = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, business_name = ?, password = ? WHERE id = ?");
                    $stmt->execute([$newUsername, $newBusinessName, $hashedPass, $userId]);
                } else {
                    // Update username and business name only
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, business_name = ? WHERE id = ?");
                    $stmt->execute([$newUsername, $newBusinessName, $userId]);
                }

                // Update session
                $_SESSION['username'] = $newUsername;
                $_SESSION['business_name'] = $newBusinessName;
                $_SESSION['profile_success'] = "Profile updated successfully!";

                // Redirect to the same page to prevent form resubmission
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['profile_error'] = "Database error: " . $e->getMessage();
        }
    }
}
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
    <link rel="stylesheet" href="style.css?v=3">
    <!-- Prevent Theme Flash -->
    <script>
        if (localStorage.getItem('fintrack_theme') === 'light') {
            document.documentElement.classList.add('light-theme');
            document.addEventListener('DOMContentLoaded', () => document.body.classList.add('light-theme'));
        }
    </script>
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
                <a href="dashboard.php" class="brand-link" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; flex-grow: 1;">
                    <div class="brand-icon">F</div>
                    <div class="brand-info">
                        <h3 data-localize="app_title">FinTrack ET</h3>
                        <p data-localize="app_subtitle">Addis Ababa Retail</p>
                    </div>
                </a>
                <button class="desktop-nav-toggle" id="desktop-toggle" style="background:none; border:none; color:var(--text-light); font-size:1.1rem; cursor:pointer; margin-left:auto; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <!-- User Info Widget -->
            <div class="user-widget">
                <div class="user-avatar"><i class="fas fa-store"></i></div>
                <div class="user-name">
                    <div style="font-weight: 700; color: var(--text-light);"><?= htmlspecialchars($_SESSION['business_name']) ?></div>
                    <div style="font-size: 0.65rem; color: var(--text-secondary);" data-localize="user_role">Owner Profile</div>
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
                <li class="menu-item">
                    <a href="dashboard.php?drawer=stock"><i class="fas fa-truck-loading"></i> <span data-localize="menu_stock">Receive Stock</span></a>
                </li>
                <li class="menu-item <?= ($currentPage === 'transactions.php') ? 'active' : '' ?>">
                    <a href="transactions.php"><i class="fas fa-exchange-alt"></i> <span data-localize="menu_transactions">Ledger</span></a>
                </li>
                <li class="menu-item <?= ($currentPage === 'credits.php') ? 'active' : '' ?>">
                    <a href="credits.php"><i class="fas fa-users-cog"></i> <span data-localize="menu_credits">Debtors CRM</span></a>
                </li>
                <li class="menu-item <?= ($currentPage === 'suppliers.php') ? 'active' : '' ?>">
                    <a href="suppliers.php"><i class="fas fa-handshake"></i> <span data-localize="menu_suppliers">Suppliers</span></a>
                </li>
                <li class="menu-item <?= ($currentPage === 'reports.php') ? 'active' : '' ?>">
                    <a href="reports.php"><i class="fas fa-chart-line"></i> <span data-localize="menu_reports">Reports & BI</span></a>
                </li>
            </ul>

            <!-- Logout Link inside sidebar -->
            <div class="sidebar-logout-container" style="margin-bottom: 6px; padding: 0 10px;">
                <a href="logout.php" class="btn btn-danger btn-small logout-btn" style="width: 100%; display: flex; gap: 8px; justify-content: center; align-items: center;">
                    <i class="fas fa-arrow-right-from-bracket"></i> <span class="logout-text">Logout</span>
                </a>
            </div>

            <!-- Bilingual Switcher and Theme Toggle in Footer -->
            <div class="sidebar-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 10px 0;">
                <div class="lang-toggle" id="php-lang-switcher">
                    <div class="lang-btn active" id="btn-php-en">EN</div>
                    <div class="lang-btn" id="btn-php-am">አማርኛ</div>
                </div>
                <button id="theme-toggle" class="btn" style="background: none; border: 1px solid var(--border-color); color: var(--text-light); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">

            <!-- Global Profile Notifications -->
            <?php if (isset($_SESSION['profile_success'])): ?>
                <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.25); color: var(--success); border-radius: 12px; padding: 14px 20px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; gap: 10px; font-weight: 500; font-size: 0.95rem; backdrop-filter: blur(8px);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="font-size: 1.1rem;"></i> 
                        <span><?= htmlspecialchars($_SESSION['profile_success']) ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer; font-size:1.4rem; line-height: 1; display: flex; align-items: center; justify-content: center; padding: 0 4px;">&times;</button>
                </div>
                <?php unset($_SESSION['profile_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['profile_error'])): ?>
                <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.25); color: var(--danger); border-radius: 12px; padding: 14px 20px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; gap: 10px; font-weight: 500; font-size: 0.95rem; backdrop-filter: blur(8px);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-exclamation-circle" style="font-size: 1.1rem;"></i> 
                        <span><?= htmlspecialchars($_SESSION['profile_error']) ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer; font-size:1.4rem; line-height: 1; display: flex; align-items: center; justify-content: center; padding: 0 4px;">&times;</button>
                </div>
                <?php unset($_SESSION['profile_error']); ?>
            <?php endif; ?>
