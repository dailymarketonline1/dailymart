<?php
// order_failed.php
session_start();
?>
<!DOCTYPE html>
<html>
<head><title>Order Failed - DailyMarket</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container text-center py-5">
    <i class="fas fa-times-circle text-danger" style="font-size:4rem;"></i>
    <h2>Payment Failed</h2>
    <p>Please try again or use another payment method.</p>
    <a href="cart.php" class="btn btn-primary">Go to Cart</a>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
