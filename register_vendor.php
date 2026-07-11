<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $store_name = trim($_POST['store_name'] ?? '');
    $store_description = trim($_POST['store_description'] ?? '');
    $store_address = trim($_POST['store_address'] ?? '');
    $store_phone = trim($_POST['store_phone'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($store_name)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->rowCount() > 0) {
                $error = 'Email or username already exists';
            } else {
                $pdo->beginTransaction();
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, user_type, vendor_status) VALUES (?, ?, ?, ?, ?, 'vendor', 'pending')");
                $stmt->execute([$username, $email, $password_hash, $full_name, $phone]);
                $user_id = $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO vendor_profiles (user_id, store_name, store_description, store_address, store_phone) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $store_name, $store_description, $store_address, $store_phone]);
                $pdo->commit();
                $success = true;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Vendor - DailyMarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f8f9fa; padding-top: 80px; }
        .vendor-register-container {
            max-width: 700px; margin: 0 auto; background: #fff;
            padding: 30px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .vendor-register-container h2 { color: #0f1463; text-align: center; margin-bottom: 10px; }
        .vendor-register-container .btn-primary {
            background: #0f1463; border: none; width: 100%; padding: 12px;
            font-weight: 600;
        }
        .vendor-register-container .btn-primary:hover { background: #ff6a00; }
        .form-control:focus { border-color: #0f1463; box-shadow: 0 0 0 0.2rem rgba(15, 20, 99, 0.25); }
        .success-page { text-align: center; padding: 30px 20px; }
        .success-page i { font-size: 4rem; color: #28a745; }
        .form-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .form-section .section-title { font-weight: 600; color: #333; margin-bottom: 15px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-light bg-white fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <h2 style="color:#ff6a00;font-weight:700;">Daily<span style="color:#0f1463;">Market</span></h2>
            </a>
            <a href="login.php" class="btn btn-outline-primary">Login</a>
        </div>
    </nav>
    <div class="container">
        <div class="vendor-register-container">
            <?php if ($success): ?>
                <div class="success-page">
                    <i class="fas fa-check-circle"></i>
                    <h3>Application Submitted!</h3>
                    <p>Your vendor application has been submitted successfully.</p>
                    <p class="text-muted">Our team will review your application within 24-48 hours.</p>
                    <div class="mt-3">
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                        <a href="index.php" class="btn btn-outline-secondary">Return to Home</a>
                    </div>
                </div>
            <?php else: ?>
                <h2><i class="fas fa-store"></i> Become a Vendor</h2>
                <p class="text-center text-muted">Start selling your products to thousands of customers</p>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-user"></i> Personal Information</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password *</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-store-alt"></i> Store Information</div>
                        <div class="mb-3">
                            <label class="form-label">Store Name *</label>
                            <input type="text" class="form-control" name="store_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Store Description</label>
                            <textarea class="form-control" name="store_description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Store Phone</label>
                                <input type="text" class="form-control" name="store_phone">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Store Address</label>
                                <input type="text" class="form-control" name="store_address">
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Your application will be reviewed. You'll receive an email once approved.
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Application</button>
                </form>
                <div class="text-center mt-3">
                    <p><a href="vendor_login.php">Already a vendor? Login</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
