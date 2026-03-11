<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false]); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
$status = intval($data['status']); // 1 o 0

try {
    $stmt = $conn->prepare("UPDATE products SET is_available = ? WHERE product_id = ?");
    $stmt->bind_param("ii", $status, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Error al actualizar");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
?>
