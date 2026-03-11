<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false]); exit;
}

try {
    $sql = "SELECT id, rol_name FROM roles ORDER BY id ASC";
    $result = $conn->query($sql);
    $roles = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'roles' => $roles]);

} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
?>
