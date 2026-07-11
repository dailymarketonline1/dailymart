<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'vendor') {
    header('Location: vendor_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND user_type = 'vendor'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['vendor_status'] === 'pending') {
                    $error = 'Your vendor application is pending approval.';
                } elseif ($user['vendor_status'] === 'suspended') {
                    $error = 'Your account has been suspended.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = 'vendor';
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    header('Location: vendor_dashboard.php');
                    exit;
                }
            } else {
                $error = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Login - DailyMarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f8f9fa; padding-top: 80px; }
        .login-container {
            max-width: 450px; margin: 0 auto; background: #fff;
            padding: 30px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .login-container h2 { color: #0f1463; text-align: center; margin-bottom: 30px; }
        .login-container .btn-primary {
            background: #0f1463; border: none; width: 100%; padding: 12px;
            font-weight: 600;
        }
        .login-container .btn-primary:hover { background: #ff6a00; }
        .login-container a { color: #0f1463; text-decoration: none; }
        .form-control:focus {
            border-color: #0f1463; box-shadow: 0 0 0 0.2rem rgba(15, 20, 99, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <h2><i class="fas fa-store"></i> Vendor Login</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            <div class="text-center mt-3">
                <p>Not a vendor? <a href="register_vendor.php">Apply to become a vendor</a></p>
                <p><a href="login.php">Customer Login</a></p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
