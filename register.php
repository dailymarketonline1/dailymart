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

    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->rowCount() > 0) {
                $error = 'Email or username already exists';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, user_type) VALUES (?, ?, ?, ?, ?, 'customer')");
                $stmt->execute([$username, $email, $password_hash, $full_name, $phone]);
                $user_id = $pdo->lastInsertId();
                
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_type'] = 'customer';
                $_SESSION['username'] = $username;
                $_SESSION['full_name'] = $full_name;
                
                $success = 'Registration successful!';
                header('refresh:2;url=index.php');
            }
        } catch (PDOException $e) {
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
    <title>Register - DailyMarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f8f9fa; padding-top: 80px; }
        .register-container {
            max-width: 500px; margin: 0 auto; background: #fff;
            padding: 30px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .register-container h2 { color: #ff6a00; text-align: center; margin-bottom: 30px; }
        .register-container .btn-primary {
            background: #ff6a00; border: none; width: 100%; padding: 12px;
            font-weight: 600;
        }
        .register-container .btn-primary:hover { background: #0f1463; }
        .register-container a { color: #ff6a00; text-decoration: none; }
        .form-control:focus {
            border-color: #ff6a00; box-shadow: 0 0 0 0.2rem rgba(255, 106, 0, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <h2><i class="fas fa-user-plus"></i> Create Account</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" name="full_name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username *</label>
                    <input type="text" class="form-control" name="username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password *</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary">Create Account</button>
            </form>
            <div class="text-center mt-3">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
