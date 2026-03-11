<?php
// /src/api/manager/menu/get_all_products_flat.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false]); exit;
}

try {
    // Obtenemos todos los productos activos y sus datos básicos para la búsqueda
    $sql = "SELECT p.product_id, p.name, p.price, p.category_id, p.is_available, p.stock_quantity, m.group_name as modifier_group_name
            FROM products p
            LEFT JOIN modifier_groups m ON p.modifier_group_id = m.group_id
            WHERE p.is_available = 1
            ORDER BY p.name ASC";

    $result = $conn->query($sql);
    $products = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'products' => $products]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
