<?php
// vendor_api.php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'vendor') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$vendor_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'update_order_status':
        $order_id = intval($_POST['order_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if ($order_id <= 0 || empty($status)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            break;
        }
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ? AND vendor_id = ?");
        $success = $stmt->execute([$status, $order_id, $vendor_id]);
        echo json_encode(['success' => $success]);
        break;
    case 'delete_product':
        $product_id = intval($_POST['product_id'] ?? 0);
        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product']);
            break;
        }
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND vendor_id = ?");
        $success = $stmt->execute([$product_id, $vendor_id]);
        echo json_encode(['success' => $success]);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
