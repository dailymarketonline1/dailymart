<?php
// checkout.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = false;

// Get cart items
$cart_items = [];
$total = 0;

$stmt = $pdo->prepare("
    SELECT c.*, p.name as product_name, p.price, p.discount_price, p.vendor_id 
    FROM cart_items c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    header('Location: cart.php');
    exit;
}

foreach ($cart_items as $item) {
    $item_price = $item['discount_price'] ?? $item['price'];
    $total += $item_price * $item['quantity'];
}

$shipping = $total > 2000 ? 0 : 200;
$grand_total = $total + $shipping;

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cod';
    $order_notes = trim($_POST['order_notes'] ?? '');

    if (empty($shipping_address)) {
        $error = 'Please enter shipping address';
    } else {
        try {
            $pdo->beginTransaction();

            foreach ($cart_items as $item) {
                $vendor_id = $item['vendor_id'];
                $item_price = $item['discount_price'] ?? $item['price'];
                $vendor_total = $item_price * $item['quantity'];
                $vendor_shipping = $vendor_total > 2000 ? 0 : 200;
                $vendor_grand_total = $vendor_total + $vendor_shipping;

                $order_number = 'ORD-' . date('Ymd') . '-' . rand(10000, 99999);

                $stmt = $pdo->prepare("
                    INSERT INTO orders (
                        user_id, vendor_id, order_number, total_amount, 
                        shipping_charge, net_amount, status, payment_method, 
                        shipping_address, order_notes
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $vendor_id, $order_number, $vendor_total,
                    $vendor_shipping, $vendor_grand_total, $payment_method,
                    $shipping_address, $order_notes
                ]);

                $order_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, price, total_amount)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order_id, $item['product_id'], $item['quantity'],
                    $item_price, $item_price * $item['quantity']
                ]);

                $stmt = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);

                $commission = $vendor_grand_total * 0.10;
                $payout = $vendor_grand_total - $commission;

                $stmt = $pdo->prepare("
                    INSERT INTO vendor_payouts (vendor_id, order_id, commission_amount, payout_amount, status)
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$vendor_id, $order_id, $commission, $payout]);
            }

            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmt->execute([$user_id]);

            $pdo->commit();
            $success = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Order failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - DailyMarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f8f9fa; padding-top: 80px; }
        .checkout-card {
            background: #fff; border-radius: 10px; padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px;
        }
        .checkout-card h5 { font-weight: 600; color: #333; margin-bottom: 20px; }
        .btn-checkout {
            background: #ff6a00; color: #fff; width: 100%; padding: 14px;
            font-weight: 600; border: none; border-radius: 8px; font-size: 1.1rem;
        }
        .btn-checkout:hover { background: #0f1463; color: #fff; }
        .form-control:focus { border-color: #ff6a00; box-shadow: 0 0 0 0.2rem rgba(255, 106, 0, 0.25); }
        .payment-option {
            padding: 12px 15px; border: 2px solid #e1e1e1; border-radius: 8px;
            cursor: pointer; transition: all 0.3s ease; margin-bottom: 8px;
        }
        .payment-option:hover { border-color: #ff6a00; }
        .payment-option.selected { border-color: #ff6a00; background: rgba(255, 106, 0, 0.05); }
        .payment-option input[type="radio"] { margin-right: 10px; }
        .success-page { text-align: center; padding: 40px 20px; }
        .success-page i { font-size: 4rem; color: #28a745; }
        .success-page h3 { margin-top: 20px; color: #333; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <?php if ($success): ?>
            <div class="checkout-card text-center">
                <div class="success-page">
                    <i class="fas fa-check-circle"></i>
                    <h3>Order Placed Successfully!</h3>
                    <p class="text-muted">Thank you for your order.</p>
                    <div class="mt-3">
                        <a href="orders.php" class="btn btn-primary">View My Orders</a>
                        <a href="index.php" class="btn btn-outline-secondary">Continue Shopping</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="checkout-card">
                        <h5><i class="fas fa-shopping-bag"></i> Order Summary</h5>
                        <?php foreach ($cart_items as $item): 
                            $item_price = $item['discount_price'] ?? $item['price'];
                        ?>
                            <div class="d-flex justify-content-between border-bottom py-2">
                                <span><?php echo htmlspecialchars($item['product_name']); ?> × <?php echo $item['quantity']; ?></span>
                                <span>Rs. <?php echo number_format($item_price * $item['quantity']); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span>Subtotal</span>
                            <span>Rs. <?php echo number_format($total); ?></span>
                        </div>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span>Shipping</span>
                            <span><?php echo $shipping > 0 ? 'Rs. ' . number_format($shipping) : 'Free'; ?></span>
                        </div>
                        <div class="d-flex justify-content-between pt-2 fw-bold">
                            <span>Total</span>
                            <span>Rs. <?php echo number_format($grand_total); ?></span>
                        </div>
                    </div>
                    <div class="checkout-card">
                        <h5><i class="fas fa-map-marker-alt"></i> Shipping Address</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Shipping Address *</label>
                                <textarea class="form-control" name="shipping_address" rows="3" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Order Notes</label>
                                <textarea class="form-control" name="order_notes" rows="2" placeholder="Any special instructions..."></textarea>
                            </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="checkout-card">
                        <h5><i class="fas fa-credit-card"></i> Payment Method</h5>
                        <div class="payment-option selected" onclick="selectPayment('cod')">
                            <input type="radio" name="payment_method" value="cod" checked>
                            <label>Cash on Delivery</label>
                        </div>
                        <div class="payment-option" onclick="selectPayment('jazzcash')">
                            <input type="radio" name="payment_method" value="jazzcash">
                            <label><i class="fas fa-mobile-alt"></i> JazzCash</label>
                        </div>
                        <div class="payment-option" onclick="selectPayment('easypaisa')">
                            <input type="radio" name="payment_method" value="easypaisa">
                            <label><i class="fas fa-mobile-alt"></i> EasyPaisa</label>
                        </div>
                        <div class="payment-option" onclick="selectPayment('bank_transfer')">
                            <input type="radio" name="payment_method" value="bank_transfer">
                            <label><i class="fas fa-university"></i> Bank Transfer</label>
                        </div>
                        <hr>
                        <button type="submit" class="btn btn-checkout">
                            <i class="fas fa-lock"></i> Place Order
                        </button>
                    </div>
                </div>
            </div>
            </form>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>
    <script>
        function selectPayment(method) {
            document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
            document.querySelectorAll('.payment-option input[type="radio"]').forEach(el => {
                if (el.value === method) {
                    el.closest('.payment-option').classList.add('selected');
                    el.checked = true;
                }
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
