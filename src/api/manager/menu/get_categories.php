<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

// Solo Gerente
if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}

try {
    $sql = "SELECT category_id, category_name, preparation_area FROM menu_categories ORDER BY display_order ASC, category_name ASC";
    $result = $conn->query($sql);
    $categories = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'categories' => $categories]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
