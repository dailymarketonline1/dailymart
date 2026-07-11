<?php
// vendor_dashboard.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'vendor') {
    header('Location: vendor_login.php');
    exit;
}

$vendor_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT vp.*, u.* FROM vendor_profiles vp JOIN users u ON vp.user_id = u.id WHERE vp.user_id = ?");
$stmt->execute([$vendor_id]);
$vendor = $stmt->fetch();

$stats = [];
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE vendor_id = ?");
$stmt->execute([$vendor_id]);
$stats['total_products'] = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE vendor_id = ?");
$stmt->execute([$vendor_id]);
$stats['total_orders'] = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as revenue FROM orders WHERE vendor_id = ? AND status = 'delivered'");
$stmt->execute([$vendor_id]);
$stats['total_revenue'] = $stmt->fetch()['revenue'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE vendor_id = ? AND status = 'pending'");
$stmt->execute([$vendor_id]);
$stats['pending_orders'] = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(payout_amount), 0) as payouts FROM vendor_payouts WHERE vendor_id = ? AND status = 'paid'");
$stmt->execute([$vendor_id]);
$stats['total_payouts'] = $stmt->fetch()['payouts'];

$stmt = $pdo->prepare("SELECT o.*, u.full_name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.vendor_id = ? ORDER BY o.created_at DESC LIMIT 10");
$stmt->execute([$vendor_id]);
$recent_orders = $stmt->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_product') {
        $name = trim($_POST['name'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $short_description = trim($_POST['short_description'] ?? '');
        $discount_price = floatval($_POST['discount_price'] ?? 0);
        $weight = floatval($_POST['weight'] ?? 0);
        $weight_unit = $_POST['weight_unit'] ?? 'kg';
        $color = trim($_POST['color'] ?? '');
        $material = trim($_POST['material'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        
        if (empty($name) || $category_id == 0 || $price <= 0) {
            $error = 'Please fill all required fields';
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            $stmt = $pdo->prepare("INSERT INTO products (vendor_id, category_id, name, slug, description, short_description, price, discount_price, quantity, weight, weight_unit, color, material, brand) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$vendor_id, $category_id, $name, $slug, $description, $short_description, $price, $discount_price, $quantity, $weight, $weight_unit, $color, $material, $brand])) {
                $product_id = $pdo->lastInsertId();
                $message = 'Product added successfully!';
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
                    $upload_dir = 'uploads/products/';
                    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                    $ext = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                    $filename = 'product_' . $product_id . '_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_dir . $filename);
                    $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?, ?, 1)");
                    $stmt->execute([$product_id, $upload_dir . $filename]);
                }
            } else {
                $error = 'Failed to add product';
            }
        }
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - DailyMarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root { --primary: #ff6a00; --secondary: #0f1463; }
        body { background: #f1f3f6; padding-top: 80px; }
        .sidebar { position: fixed; left: 0; top: 80px; height: calc(100vh - 80px); width: 250px; background: #fff; box-shadow: 2px 0 10px rgba(0,0,0,0.05); overflow-y: auto; padding: 20px 0; }
        .sidebar .nav-item { display: block; padding: 12px 20px; color: #333; text-decoration: none; border-left: 3px solid transparent; }
        .sidebar .nav-item:hover { background: #f8f9fa; color: var(--primary); }
        .sidebar .nav-item.active { border-left-color: var(--primary); color: var(--primary); font-weight: 600; }
        .main-content { margin-left: 250px; padding: 20px; }
        .stat-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; }
        .stat-card .number { font-size: 2rem; font-weight: 700; color: var(--secondary); }
        .stat-card .label { color: #888; font-size: 0.9rem; }
        .table-container { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.processing { background: #cce5ff; color: #004085; }
        .status-badge.shipped { background: #cce5ff; color: #004085; }
        .status-badge.delivered { background: #d4edda; color: #155724; }
        .status-badge.cancelled { background: #f8d7da; color: #721c24; }
        .btn-primary { background: var(--primary); border: none; }
        .btn-primary:hover { background: var(--secondary); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
        .toggle-sidebar { display: none; background: none; border: none; font-size: 1.5rem; }
        @media (max-width: 768px) { .toggle-sidebar { display: block; } }
    </style>
</head>
<body>
    <div class="top-bar" style="background:var(--secondary);color:#fff;padding:8px 0;font-size:0.9rem;">
        <div class="container">
            <div class="row">
                <div class="col-md-6"><i class="fas fa-store"></i> Vendor Dashboard</div>
                <div class="col-md-6 text-end"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Vendor'); ?> <a href="logout.php" class="text-white ms-3"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
            </div>
        </div>
    </div>
    <header class="main-header" style="background:#fff;border-bottom:2px solid var(--primary);position:fixed;top:0;left:0;right:0;z-index:1000;">
        <div class="container">
            <div class="row align-items-center py-2">
                <div class="col-md-3"><h2 style="color:var(--primary);font-weight:700;">Daily<span style="color:var(--secondary);">Market</span></h2></div>
                <div class="col-md-6"></div>
                <div class="col-md-3 text-end">
                    <button class="toggle-sidebar" onclick="document.getElementById('sidebar').classList.toggle('open')"><i class="fas fa-bars"></i></button>
                    <span class="ms-2"><?php echo htmlspecialchars($vendor['store_name'] ?? 'My Store'); ?></span>
                </div>
            </div>
        </div>
    </header>
    <div class="sidebar" id="sidebar">
        <nav>
            <a href="#" class="nav-item active" data-page="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="#" class="nav-item" data-page="products"><i class="fas fa-box"></i> Products</a>
            <a href="#" class="nav-item" data-page="add-product"><i class="fas fa-plus-circle"></i> Add Product</a>
            <a href="#" class="nav-item" data-page="orders"><i class="fas fa-shopping-cart"></i> Orders</a>
            <a href="#" class="nav-item" data-page="payouts"><i class="fas fa-wallet"></i> Payouts</a>
            <a href="#" class="nav-item" data-page="profile"><i class="fas fa-store"></i> Store Profile</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>
    <div class="main-content" id="mainContent">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div id="page-dashboard">
            <div class="row g-4 mb-4">
                <div class="col-md-3"><div class="stat-card"><div class="number"><?php echo $stats['total_products']; ?></div><div class="label">Total Products</div></div></div>
                <div class="col-md-3"><div class="stat-card"><div class="number"><?php echo $stats['total_orders']; ?></div><div class="label">Total Orders</div></div></div>
                <div class="col-md-3"><div class="stat-card"><div class="number"><?php echo $stats['pending_orders']; ?></div><div class="label">Pending Orders</div></div></div>
                <div class="col-md-3"><div class="stat-card"><div class="number">Rs. <?php echo number_format($stats['total_revenue']); ?></div><div class="label">Total Revenue</div></div></div>
            </div>
            <div class="table-container">
                <h5>Recent Orders</h5>
                <?php if (empty($recent_orders)): ?>
                    <p class="text-muted">No orders yet</p>
                <?php else: ?>
                    <table class="table">
                        <thead><tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr><td>#<?php echo htmlspecialchars($order['order_number']); ?></td><td><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></td><td>Rs. <?php echo number_format($order['net_amount']); ?></td><td><span class="status-badge <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td><td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <div id="page-products" style="display:none;">
            <h5>My Products</h5>
            <div class="table-container">
                <?php $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_url FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.vendor_id = ? ORDER BY p.created_at DESC");
                $stmt->execute([$vendor_id]);
                $products = $stmt->fetchAll(); ?>
                <?php if (empty($products)): ?>
                    <p class="text-muted">No products yet. <a href="#" onclick="showPage('add-product')">Add your first product</a></p>
                <?php else: ?>
                    <table class="table">
                        <thead><tr><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Weight</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr><td><img src="<?php echo htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/50'); ?>" style="width:50px;height:50px;object-fit:cover;border-radius:5px;"></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                <td>Rs. <?php echo number_format($product['price']); ?></td>
                                <td><?php echo $product['quantity']; ?></td>
                                <td><?php echo $product['weight'] ? $product['weight'] . ' ' . $product['weight_unit'] : 'N/A'; ?></td>
                                <td><button class="btn btn-sm btn-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)"><i class="fas fa-trash"></i></button></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <div id="page-add-product" style="display:none;">
            <h5>Add New Product</h5>
            <div class="bg-white p-4 rounded-3">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_product">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3"><label>Product Name *</label><input type="text" class="form-control" name="name" required></div>
                            <div class="mb-3"><label>Category *</label><select class="form-control" name="category_id" required><option value="">Select Category</option><?php foreach ($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
                            <div class="mb-3"><label>Brand</label><input type="text" class="form-control" name="brand"></div>
                            <div class="row"><div class="col-md-6"><label>Price (Rs.) *</label><input type="number" class="form-control" name="price" step="0.01" required></div><div class="col-md-6"><label>Discount Price</label><input type="number" class="form-control" name="discount_price" step="0.01"></div></div>
                            <div class="mb-3"><label>Quantity *</label><input type="number" class="form-control" name="quantity" required></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3"><label>Product Image</label><input type="file" class="form-control" name="product_image" accept="image/*"></div>
                            <div class="row"><div class="col-md-6"><label>Weight</label><input type="number" class="form-control" name="weight" step="0.01"></div><div class="col-md-6"><label>Unit</label><select class="form-control" name="weight_unit"><option value="kg">Kg</option><option value="g">Gram</option></select></div></div>
                            <div class="row mt-2"><div class="col-md-6"><label>Color</label><input type="text" class="form-control" name="color"></div><div class="col-md-6"><label>Material</label><input type="text" class="form-control" name="material"></div></div>
                        </div>
                    </div>
                    <div class="mb-3"><label>Short Description</label><input type="text" class="form-control" name="short_description"></div>
                    <div class="mb-3"><label>Full Description</label><textarea class="form-control" name="description" rows="4"></textarea></div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Product</button>
                </form>
            </div>
        </div>
        <div id="page-orders" style="display:none;">
            <h5>Orders</h5>
            <div class="table-container">
                <?php $stmt = $pdo->prepare("SELECT o.*, u.full_name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.vendor_id = ? ORDER BY o.created_at DESC");
                $stmt->execute([$vendor_id]);
                $orders = $stmt->fetchAll(); ?>
                <?php if (empty($orders)): ?>
                    <p class="text-muted">No orders yet</p>
                <?php else: ?>
                    <table class="table">
                        <thead><tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Status</th><th>Tracking</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr><td>#<?php echo htmlspecialchars($order['order_number']); ?></td><td><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></td><td>Rs. <?php echo number_format($order['net_amount']); ?></td><td><span class="status-badge <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td><td><?php echo htmlspecialchars($order['tracking_number'] ?? 'N/A'); ?></td>
                                <td><select class="form-select form-select-sm" onchange="updateOrderStatus(<?php echo $order['id']; ?>, this.value)"><option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option><option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option><option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option><option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option></select></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <div id="page-payouts" style="display:none;">
            <h5>Payout History</h5>
            <div class="table-container">
                <?php $stmt = $pdo->prepare("SELECT * FROM vendor_payouts WHERE vendor_id = ? ORDER BY created_at DESC");
                $stmt->execute([$vendor_id]);
                $payouts = $stmt->fetchAll(); ?>
                <?php if (empty($payouts)): ?>
                    <p class="text-muted">No payouts yet</p>
                <?php else: ?>
                    <table class="table"><thead><tr><th>Order</th><th>Commission</th><th>Payout Amount</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($payouts as $payout): ?>
                                <tr><td>#<?php echo $payout['order_id']; ?></td><td>Rs. <?php echo number_format($payout['commission_amount']); ?></td><td><strong>Rs. <?php echo number_format($payout['payout_amount']); ?></strong></td><td><span class="status-badge <?php echo $payout['status']; ?>"><?php echo ucfirst($payout['status']); ?></span></td><td><?php echo date('M d, Y', strtotime($payout['created_at'])); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        function showPage(page) {
            document.querySelectorAll('[id^="page-"]').forEach(el => el.style.display = 'none');
            document.getElementById('page-' + page).style.display = 'block';
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            document.querySelector(`.nav-item[data-page="${page}"]`)?.classList.add('active');
            document.getElementById('sidebar').classList.remove('open');
        }
        function updateOrderStatus(orderId, status) {
            if (confirm('Update order status?')) {
                fetch('vendor_api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=update_order_status&order_id=' + orderId + '&status=' + status })
                .then(r => r.json()).then(data => { if (data.success) location.reload(); else alert('Failed'); });
            }
        }
        function deleteProduct(productId) {
            if (confirm('Delete this product?')) {
                fetch('vendor_api.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=delete_product&product_id=' + productId })
                .then(r => r.json()).then(data => { if (data.success) location.reload(); else alert('Failed'); });
            }
        }
        document.querySelectorAll('.nav-item[data-page]').forEach(el => {
            el.addEventListener('click', function(e) { e.preventDefault(); showPage(this.dataset.page); });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
