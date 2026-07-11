<?php
// wishlist.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);

    switch ($action) {
        case 'add':
            if ($product_id > 0) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $product_id]);
                $_SESSION['wishlist_message'] = 'Added to wishlist!';
            }
            break;
        case 'remove':
            if ($product_id > 0) {
                $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                $_SESSION['wishlist_message'] = 'Removed from wishlist!';
            }
            break;
        case 'clear':
            $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['wishlist_message'] = 'Wishlist cleared!';
            break;
    }
    header('Location: wishlist.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, pi.image_url, u.full_name as vendor_name,
           (SELECT COUNT(*) FROM cart_items WHERE user_id = ? AND product_id = p.id) as in_cart
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    LEFT JOIN users u ON p.vendor_id = u.id
    WHERE w.user_id = ?
    ORDER BY w.added_at DESC
");
$stmt->execute([$user_id, $user_id]);
$wishlist_items = $stmt->fetchAll();

$message = $_SESSION['wishlist_message'] ?? '';
unset($_SESSION['wishlist_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - DailyMarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f8f9fa; padding-top: 80px; }
        .wishlist-item {
            background: #fff; border-radius: 10px; padding: 15px;
            margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex; align-items: center;
        }
        .wishlist-item img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; }
        .wishlist-item .details { flex: 1; padding: 0 20px; }
        .wishlist-item .details .name { font-weight: 600; color: #333; text-decoration: none; }
        .wishlist-item .details .name:hover { color: #ff6a00; }
        .wishlist-item .details .vendor { font-size: 0.85rem; color: #888; }
        .wishlist-item .price { font-weight: 700; color: #ff6a00; font-size: 1.1rem; }
        .wishlist-item .actions { display: flex; gap: 10px; }
        .wishlist-item .actions .btn-cart {
            background: #ff6a00; color: #fff; border: none;
            padding: 8px 15px; border-radius: 5px; font-weight: 600;
        }
        .wishlist-item .actions .btn-cart:hover { background: #0f1463; }
        .wishlist-item .actions .btn-remove {
            background: transparent; color: #dc3545; border: none; padding: 8px 12px;
        }
        .wishlist-item .actions .btn-remove:hover { color: #c82333; }
        .empty-wishlist { text-align: center; padding: 60px 20px; }
        .empty-wishlist i { font-size: 4rem; color: #ddd; }
        .empty-wishlist h3 { margin-top: 20px; color: #333; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>My Wishlist</h4>
            <?php if (!empty($wishlist_items)): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Clear all items from wishlist?')">
                        <i class="fas fa-trash"></i> Clear All
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if (empty($wishlist_items)): ?>
            <div class="empty-wishlist">
                <i class="fas fa-heart"></i>
                <h3>Your wishlist is empty</h3>
                <p class="text-muted">Start adding items to your wishlist</p>
                <a href="index.php" class="btn btn-primary mt-3">Explore Products</a>
            </div>
        <?php else: ?>
            <?php foreach ($wishlist_items as $item): 
                $price = $item['discount_price'] ?? $item['price'];
            ?>
                <div class="wishlist-item">
                    <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'https://via.placeholder.com/80'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <div class="details">
                        <a href="product.php?slug=<?php echo $item['slug']; ?>" class="name"><?php echo htmlspecialchars($item['name']); ?></a>
                        <div class="vendor"><i class="fas fa-store"></i> By <?php echo htmlspecialchars($item['vendor_name'] ?? 'Unknown Vendor'); ?></div>
                        <div class="mt-1">
                            <?php if ($item['discount_price']): ?>
                                <span class="text-muted text-decoration-line-through me-2">Rs. <?php echo number_format($item['price']); ?></span>
                                <span class="price">Rs. <?php echo number_format($item['discount_price']); ?></span>
                            <?php else: ?>
                                <span class="price">Rs. <?php echo number_format($item['price']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="actions">
                        <?php if ($item['in_cart'] > 0): ?>
                            <a href="cart.php" class="btn-cart">Already in Cart</a>
                        <?php else: ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="btn-cart"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                            <button type="submit" class="btn-remove" title="Remove from wishlist"><i class="fas fa-times"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
