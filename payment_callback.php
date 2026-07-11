<?php
// payment_callback.php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = $_POST['transaction_id'] ?? '';
    $order_id = $_POST['order_id'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if ($status === 'SUCCESS') {
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid', transaction_id = ?, status = 'processing' WHERE id = ?");
        $stmt->execute([$transaction_id, $order_id]);
        header('Location: order_success.php?order=' . $order_id);
    } else {
        header('Location: order_failed.php?order=' . $order_id);
    }
    exit;
}
?>
