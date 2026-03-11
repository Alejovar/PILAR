<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
$new_status = $data['status']; // 💡 CAMBIO: Esperamos 'ACTIVO' o 'INACTIVO'

if ($id <= 0 || !in_array($new_status, ['ACTIVO', 'INACTIVO'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']); exit;
}

// Evitar que el gerente se desactive a sí mismo
if ($id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'No puedes desactivar tu propia cuenta.']); exit;
}

try {
    // 💡 CAMBIO: Actualizamos la columna 'status' con un string ('s')
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Error al actualizar estado.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
