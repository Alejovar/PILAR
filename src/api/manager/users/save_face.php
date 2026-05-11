<?php
// /src/api/manager/users/save_face.php
// Guarda (o borra) el descriptor facial de un usuario.
// Solo puede ejecutarlo el Gerente (rol 1).

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id    = intval($data['user_id'] ?? 0);
$descriptor = $data['descriptor'] ?? null; // array de 128 floats o null para borrar

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario inválido']); exit;
}

try {
    if ($descriptor === null) {
        // Borrar el descriptor facial
        $stmt = $conn->prepare("UPDATE users SET face_descriptor = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Rostro eliminado correctamente']);
    } else {
        // Validar que sea un array de 128 números
        if (!is_array($descriptor) || count($descriptor) !== 128) {
            echo json_encode(['success' => false, 'message' => 'Descriptor facial inválido (se esperan 128 valores)']); exit;
        }
        // Convertir a JSON compacto para guardar
        $json = json_encode($descriptor);
        $stmt = $conn->prepare("UPDATE users SET face_descriptor = ? WHERE id = ?");
        $stmt->bind_param("si", $json, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']); exit;
        }
        echo json_encode(['success' => true, 'message' => 'Rostro registrado correctamente']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
