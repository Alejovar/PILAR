<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

// Solo Gerente
if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}

try {
    // 💡 CAMBIO: Seleccionamos 'u.status' en lugar de 'u.esta_activo'
    $sql = "SELECT u.id, u.name, u.user, u.rol_id, u.status, r.rol_name 
            FROM users u
            LEFT JOIN roles r ON u.rol_id = r.id
            ORDER BY u.id ASC";
            
    $result = $conn->query($sql);
    $users = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'users' => $users]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
