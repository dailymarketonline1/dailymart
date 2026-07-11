<?php
// health.php
require_once 'config/database.php';

try {
    $stmt = $pdo->query("SELECT 1");
    $db_status = 'connected';
} catch (Exception $e) {
    $db_status = 'disconnected';
}

echo json_encode([
    'status' => $db_status === 'connected' ? 'healthy' : 'unhealthy',
    'database' => $db_status,
    'php_version' => PHP_VERSION,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
