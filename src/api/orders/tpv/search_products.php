<?php
// =====================================================
// SEARCH_PRODUCTS.PHP - Busca productos (MySQLi)
// =====================================================
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

// Ruta absoluta al archivo de conexión
$absolute_path_to_conn = $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';
require_once $absolute_path_to_conn;

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = $_GET['query'] ?? '';

if (empty($query)) {
    echo json_encode(['success' => true, 'products' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Buscar productos cuyo nombre contenga la consulta
    $search_term = "%" . $conn->real_escape_string($query) . "%";
    
    $sql = "
        SELECT 
            p.product_id, 
            p.name, 
            p.price, 
            p.category_id,
            p.modifier_group_id,
            mc.category_name
        FROM 
            products p
        JOIN
            menu_categories mc ON mc.category_id = p.category_id
        WHERE 
            p.is_available = TRUE 
            AND p.name LIKE ?
        ORDER BY 
            p.name ASC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => (int)$row['product_id'],
            'name' => $row['name'],
            'price' => (float)$row['price'],
            'category_id' => (int)$row['category_id'],
            'category_name' => $row['category_name'],
            'modifier_group_id' => isset($row['modifier_group_id']) ? (int)$row['modifier_group_id'] : null
        ];
    }

    $stmt->close();

    echo json_encode(['success' => true, 'products' => $products], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
