<?php
// /src/api/attendance/get_employees_list.php
// Lista de empleados activos para el filtro del gerente.
// Requiere sesión de gerente.

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}

try {
    $sql = "SELECT u.id, u.name, u.user, u.nss, u.plant, r.rol_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.rol_id
            WHERE u.status = 'ACTIVO'
            ORDER BY u.name ASC";
    $result = $conn->query($sql);
    $employees = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'employees' => $employees]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
