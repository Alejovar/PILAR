<?php
// /src/api/manager/users/get_all_users.php
// ACTUALIZADO: Devuelve has_face (1/0) sin exponer el descriptor completo.

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

// Solo Gerente
if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}

try {
    $sql = "SELECT u.id, u.name, u.user, u.rol_id, u.status, r.rol_name,
                 u.nss, u.plant, u.tax_rate, u.salary_per_day, u.overtime_rate,
                   IF(u.face_descriptor IS NOT NULL, 1, 0) AS has_face
            FROM users u
            LEFT JOIN roles r ON u.rol_id = r.id
            ORDER BY u.id ASC";
            
    $result = $conn->query($sql);
    $users  = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'users' => $users]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
