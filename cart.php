<?php
// cart.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);

    switch ($action) {
        case 'add':
            if ($product_id > 0 && $quantity > 0) {
                $stmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
                $stmt->execute([$user_id, $product_id, $quantity, $quantity]);
                $_SESSION['cart_message'] = 'Product added to cart!';
            }
            break;
        case 'remove':
            $cart_id = intval($_POST['cart_id'] ?? 0);
            if ($cart_id > 0) {
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
                $stmt->execute([$cart_id, $user_id]);
            }
            break;
        case 'clear':
            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmt->execute([$user_id]);
            break;
        case 'update':
            $cart_id = intval($_POST['cart_id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 1);
            if ($cart_id > 0 && $quantity > 0) {
                $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$quantity, $cart_id, $user_id]);
            }
            break;
    }
    header('Location: cart.php');
    exit;
}

// Get cart items
$cart_items = [];
$total = 0;

$stmt = $pdo->prepare("
    SELECT c.*, p.name as product_name, p.slug, p.price, p.discount_price, 
           pi.image_url, u.full_name as vendor_name
    FROM cart_items c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    LEFT JOIN users u ON p.vendor_id = u.id
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

foreach ($cart_items as $item) {
    $item_price = $item['discount_price'] ?? $item['price'];
    $total += $item_price * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - DailyMarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f8f9fa; padding-top: 80px; }
        .cart-item {
            background: #fff; border-radius: 10px; padding: 20px;
            margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .cart-item img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; }
        .cart-item .product-title { font-weight: 600; color: #333; text-decoration: none; }
        .cart-item .product-title:hover { color: #ff6a00; }
        .cart-item .price { font-weight: 700; color: #ff6a00; }
        .cart-summary {
            background: #fff; border-radius: 10px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky; top: 100px;
        }
        .cart-summary .total { font-size: 1.5rem; font-weight: 700; }
        .cart-summary .btn-checkout {
            background: #ff6a00; color: #fff; width: 100%; padding: 12px;
            font-weight: 600; border: none; border-radius: 8px;
        }
        .cart-summary .btn-checkout:hover { background: #0f1463; }
        .empty-cart { text-align: center; padding: 60px 20px; }
        .empty-cart i { font-size: 4rem; color: #ddd; }
        .qty-btn {
            background: #f0f0f0; border: none; width: 30px; height: 30px;
            border-radius: 50%; font-weight: 600; cursor: pointer;
        }
        .qty-btn:hover { background: #ff6a00; color: #fff; }
        .qty-input { width: 50px; text-align: center; border: none; font-weight: 600; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">
                <h4 class="mb-3">Shopping Cart (<?php echo count($cart_items); ?> items)</h4>
                <?php if (isset($_SESSION['cart_message'])): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['cart_message']); unset($_SESSION['cart_message']); ?></div>
                <?php endif; ?>
                <?php if (empty($cart_items)): ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your cart is empty</h3>
                        <a href="index.php" class="btn btn-primary mt-3">Start Shopping</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($cart_items as $item): 
                        $item_price = $item['discount_price'] ?? $item['price'];
                        $subtotal = $item_price * $item['quantity'];
                    ?>
                        <div class="cart-item">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'https://via.placeholder.com/80'); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <a href="product.php?slug=<?php echo $item['slug']; ?>" class="product-title"><?php echo htmlspecialchars($item['product_name']); ?></a>
                                    <div class="text-muted small">By <?php echo htmlspecialchars($item['vendor_name'] ?? 'Vendor'); ?></div>
                                </div>
                                <div class="col-md-2">
                                    <div class="price">Rs. <?php echo number_format($item_price); ?></div>
                                </div>
                                <div class="col-md-2">
                                    <div class="d-flex align-items-center">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="quantity" value="<?php echo $item['quantity'] - 1; ?>" class="qty-btn">-</button>
                                        </form>
                                        <span class="qty-input"><?php echo $item['quantity']; ?></span>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="quantity" value="<?php echo $item['quantity'] + 1; ?>" class="qty-btn">+</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <div class="fw-bold">Rs. <?php echo number_format($subtotal); ?></div>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-link text-danger p-0" onclick="return confirm('Remove this item?')">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Clear all items?')">
                            <i class="fas fa-trash-alt"></i> Clear Cart
                        </button>
                    </form>
                    <a href="index.php" class="btn btn-outline-primary ms-2">Continue Shopping</a>
                <?php endif; ?>
            </div>
            <div class="col-lg-4">
                <div class="cart-summary">
                    <h5 class="mb-3">Order Summary</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span>Rs. <?php echo number_format($total); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping</span>
                        <span><?php echo $total > 2000 ? 'Free' : 'Rs. 200'; ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="fw-bold">Total</span>
                        <span class="total">Rs. <?php echo number_format($total + ($total > 2000 ? 0 : 200)); ?></span>
                    </div>
                    <?php if (!empty($cart_items)): ?>
                        <a href="checkout.php" class="btn btn-checkout"><i class="fas fa-lock"></i> Proceed to Checkout</a>
                    <?php else: ?>
                        <button class="btn btn-checkout" disabled>Cart is empty</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
