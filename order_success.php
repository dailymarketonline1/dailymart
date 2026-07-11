<?php
// order_success.php
session_start();
?>
<!DOCTYPE html>
<html>
<head><title>Order Success - DailyMarket</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container text-center py-5">
    <i class="fas fa-check-circle text-success" style="font-size:4rem;"></i>
    <h2>Order Placed Successfully!</h2>
    <p>Thank you for your order.</p>
    <a href="orders.php" class="btn btn-primary">View Orders</a>
    <a href="index.php" class="btn btn-secondary">Continue Shopping</a>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
