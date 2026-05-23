<?php
/**
 * FinTrack ET - Product & Inventory Manager
 * Add, update, and organize products, price, and track stock levels.
 */
require_once 'config.php';
require_once 'auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$successMsg = "";
$errorMsg = "";

// 1. HANDLE NEW PRODUCT POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'add_product') {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);

    if (empty($name) || $price <= 0) {
        $errorMsg = "Product name and a valid price are required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (user_id, name, category, price, quantity) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $category, $price, $quantity]);
            $successMsg = "Product registered successfully!";
        } catch (Exception $e) {
            $errorMsg = "Failed to register product: " . $e->getMessage();
        }
    }
}

// 2. HANDLE EDIT PRODUCT POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'edit_product') {
    $productId = intval($_POST['product_id']);
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);

    if (empty($name) || $price <= 0) {
        $errorMsg = "Product name and a valid price are required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $category, $price, $quantity, $productId, $userId]);
            $successMsg = "Product updated successfully!";
        } catch (Exception $e) {
            $errorMsg = "Failed to update product: " . $e->getMessage();
        }
    }
}

// 3. HANDLE PRODUCT DELETION
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
        $stmt->execute([$deleteId, $userId]);
        $successMsg = "Product deleted successfully.";
    } catch (Exception $e) {
        $errorMsg = "Failed to delete product (It may be linked to past transactions): " . $e->getMessage();
    }
}

// 4. FETCH ALL PRODUCTS
$searchQuery = "";
$params = ['user_id' => $userId];

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $searchQuery = trim($_GET['search']);
    $sql = "SELECT * FROM products WHERE user_id = :user_id AND (name LIKE :query OR category LIKE :query) ORDER BY name ASC";
    $params['query'] = '%' . $searchQuery . '%';
} else {
    $sql = "SELECT * FROM products WHERE user_id = :user_id ORDER BY name ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

require_once 'header.php';
?>

<!-- Title Header Area -->
<div class="dashboard-header">
    <div class="welcome-section">
        <h1 data-localize="menu_products">Inventory & Products</h1>
        <p data-localize="products_subtitle">Add, update, and organize your products to easily track stock levels.</p>
    </div>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="openDrawer('add')">
            <i class="fas fa-box-open"></i> <span data-localize="btn_add_product">Add Product</span>
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
<form action="products.php" method="GET" style="max-width: 600px; margin-bottom: 25px; position: relative;">
    <span style="position: absolute; left: 16px; top: 14px; color: var(--text-secondary);">
        <i class="fas fa-search"></i>
    </span>
    <input type="text" name="search" class="form-control" style="padding-left: 45px;" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search products by name or category...">
</form>

<!-- Products Registry Card -->
<div class="panel">
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th data-localize="table_product_name">Product Name</th>
                    <th data-localize="table_category">Category</th>
                    <th data-localize="table_price">Cost Price</th>
                    <th data-localize="table_stock">Stock Level</th>
                    <th data-localize="table_actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                            No products found. Click Add Product to begin.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td style="font-weight:600;">
                                <?= htmlspecialchars($p['name']) ?>
                            </td>
                            <td><?= htmlspecialchars($p['category']) ?></td>
                            <td style="font-weight:700; color: var(--text-primary)">
                                <?= number_format($p['price'], 2) ?> ETB
                            </td>
                            <td>
                                <?php if ($p['quantity'] <= 5): ?>
                                    <span class="badge badge-danger"><?= intval($p['quantity']) ?> <span data-localize="badge_low_stock">Low Stock</span></span>
                                <?php else: ?>
                                    <span class="badge badge-success"><?= intval($p['quantity']) ?> <span data-localize="badge_in_stock">In Stock</span></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <button class="btn btn-secondary btn-small" onclick="openEditDrawer(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>', '<?= addslashes(htmlspecialchars($p['category'])) ?>', <?= $p['price'] ?>, <?= $p['quantity'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <a href="products.php?delete_id=<?= $p['id'] ?>" class="btn btn-danger btn-small" onclick="return confirm(localStorage.getItem('fintrack_lang') === 'am' ? 'እርግጠኛ ነዎት ይህን ምርት መሰረዝ ይፈልጋሉ?' : 'Are you sure you want to delete this product?');">
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

<!-- PRODUCT ADD/EDIT DRAWER -->
<div class="drawer-overlay" id="drawer-product">
    <div class="drawer">
        <div class="drawer-header">
            <h3 id="drawer-product-title" data-localize="drawer_add_product">Add Product</h3>
            <button class="drawer-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="drawer-body">
            <form action="products.php" method="POST">
                <input type="hidden" name="action_type" id="form-product-action" value="add_product">
                <input type="hidden" name="product_id" id="form-product-id">
                
                <div class="form-group">
                    <label class="form-label" data-localize="form_product_name">Product Name</label>
                    <input type="text" name="name" id="form-product-name" class="form-control" required placeholder="e.g. Sugar, Coffee Beans">
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-localize="form_category">Category</label>
                    <input type="text" name="category" id="form-product-category" class="form-control" placeholder="e.g. Groceries">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" data-localize="form_product_price">Cost Price / Buying Price (ETB)</label>
                        <input type="number" name="price" id="form-product-price" class="form-control" step="0.01" min="0" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label" data-localize="form_product_quantity">Initial Quantity</label>
                        <input type="number" name="quantity" id="form-product-quantity" class="form-control" min="0" required placeholder="0">
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;" id="btn-save-product" data-localize="btn_save_product">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openDrawer(type) {
        document.getElementById('form-product-action').value = 'add_product';
        document.getElementById('form-product-id').value = '';
        document.getElementById('form-product-name').value = '';
        document.getElementById('form-product-category').value = '';
        document.getElementById('form-product-price').value = '';
        document.getElementById('form-product-quantity').value = '';
        
        const lang = localStorage.getItem('fintrack_lang') || 'en';
        document.getElementById('drawer-product-title').textContent = (lang === 'en') ? 'Add Product' : 'አዲስ ዕቃ መመዝገቢያ';
        document.getElementById('btn-save-product').textContent = (lang === 'en') ? 'Save Product' : 'ዕቃውን መዝግብ';
        
        document.getElementById('drawer-product').style.display = 'flex';
    }

    function openEditDrawer(id, name, category, price, quantity) {
        document.getElementById('form-product-action').value = 'edit_product';
        document.getElementById('form-product-id').value = id;
        document.getElementById('form-product-name').value = name;
        document.getElementById('form-product-category').value = category;
        document.getElementById('form-product-price').value = price;
        document.getElementById('form-product-quantity').value = quantity;
        
        const lang = localStorage.getItem('fintrack_lang') || 'en';
        document.getElementById('drawer-product-title').textContent = (lang === 'en') ? 'Edit Product' : 'ዕቃውን አስተካክል';
        document.getElementById('btn-save-product').textContent = (lang === 'en') ? 'Update Product' : 'አስተካክል';

        document.getElementById('drawer-product').style.display = 'flex';
    }
</script>

<?php
require_once 'footer.php';
?>
