<?php
// profile.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $address, $user_id]);

        if (!empty($current_password) && !empty($new_password)) {
            if (!password_verify($current_password, $user['password_hash'])) {
                $error = 'Current password is incorrect';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match';
            } elseif (strlen($new_password) < 6) {
                $error = 'Password must be at least 6 characters';
            } else {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$new_hash, $user_id]);
                $message = 'Password updated successfully!';
            }
        }

        if (empty($error)) {
            $message = 'Profile updated successfully!';
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            $_SESSION['full_name'] = $user['full_name'];
        }
    } catch (Exception $e) {
        $error = 'Failed to update profile';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - DailyMarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f8f9fa; padding-top: 80px; }
        .profile-container { max-width: 800px; margin: 0 auto; }
        .profile-card {
            background: #fff; border-radius: 10px; padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px;
        }
        .profile-avatar {
            width: 100px; height: 100px; border-radius: 50%;
            background: #ff6a00; color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; font-weight: 700; margin: 0 auto 15px;
        }
        .profile-card .form-control:focus { border-color: #ff6a00; box-shadow: 0 0 0 0.2rem rgba(255, 106, 0, 0.25); }
        .profile-card .btn-primary { background: #ff6a00; border: none; padding: 10px 30px; font-weight: 600; }
        .profile-card .btn-primary:hover { background: #0f1463; }
        .stats-row {
            display: flex; justify-content: space-around; text-align: center;
            padding: 20px 0; border-top: 1px solid #f0f0f0; margin-top: 20px;
        }
        .stats-row .stat-item .number { font-size: 1.5rem; font-weight: 700; color: #333; }
        .stats-row .stat-item .label { font-size: 0.85rem; color: #888; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container profile-container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="profile-card">
            <div class="text-center">
                <div class="profile-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>
                <hr>
                <h6 class="mb-3">Change Password (Optional)</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-control" name="current_password">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="confirm_password">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
            <div class="stats-row">
                <div class="stat-item">
                    <div class="number"><?php 
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        echo $stmt->fetchColumn();
                    ?></div>
                    <div class="label">Orders</div>
                </div>
                <div class="stat-item">
                    <div class="number"><?php 
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        echo $stmt->fetchColumn();
                    ?></div>
                    <div class="label">Wishlist</div>
                </div>
                <div class="stat-item">
                    <div class="number"><?php 
                        $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM orders WHERE user_id = ? AND status = 'delivered'");
                        $stmt->execute([$user_id]);
                        echo 'Rs. ' . number_format($stmt->fetchColumn());
                    ?></div>
                    <div class="label">Total Spent</div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
