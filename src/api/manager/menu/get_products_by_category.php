<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false]); exit;
}

$cat_id = intval($_GET['category_id'] ?? 0);

try {
    // Obtenemos producto, su stock y el nombre del grupo de modificadores
    $sql = "SELECT p.product_id, p.name, p.price, p.is_available, p.modifier_group_id, p.stock_quantity,
                   m.group_name as modifier_group_name
            FROM products p
            LEFT JOIN modifier_groups m ON p.modifier_group_id = m.group_id
            WHERE p.category_id = ?
            ORDER BY p.name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cat_id);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'products' => $products]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
