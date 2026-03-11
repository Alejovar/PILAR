<?php
// =====================================================
// GET_CANCEL_DATA.PHP - Carga los productos activos para cancelar (MySQLi)
// =====================================================
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

// --- Cargar conexión ---
$absolute_path_to_conn = $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';
require_once $absolute_path_to_conn;

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión con la base de datos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar que el turno de caja esté abierto
$stmt_shift = $conn->prepare("SELECT 1 FROM cash_shifts WHERE status = 'OPEN' LIMIT 1");
$stmt_shift->execute();
$shift_result = $stmt_shift->get_result();

if ($shift_result->num_rows === 0) {
    // ¡TURNO CERRADO! Rechazamos la acción.
    $stmt_shift->close();
    http_response_code(403); // Prohibido
    echo json_encode(['success' => false, 'message' => 'El turno de caja está cerrado. No se pueden procesar nuevas acciones.']);
    exit;
}
$stmt_shift->close();

$source_table_number = filter_input(INPUT_GET, 'source_table', FILTER_VALIDATE_INT);

if (!$source_table_number || $source_table_number <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Número de mesa de origen inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    $final_statuses = ['COBRADO', 'CLOSED', 'CANCELADO']; 
    $status_placeholders = str_repeat('?,', count($final_statuses) - 1) . '?';
    $status_types = str_repeat('s', count($final_statuses));

    $sql_products = "
        SELECT 
            o.order_id, 
            od.detail_id, 
            od.quantity, 
            od.price_at_order,
            p.name AS product_name,
            m.modifier_name AS modifier_name -- <-- CORRECCIÓN: La columna se llama 'modifier_name'
        FROM 
            orders o
        JOIN 
            restaurant_tables rt ON rt.table_id = o.table_id
        JOIN 
            order_details od ON od.order_id = o.order_id
        JOIN
            products p ON p.product_id = od.product_id
        LEFT JOIN 
            modifiers m ON m.modifier_id = od.modifier_id
        WHERE 
            rt.table_number = ? 
            AND o.status NOT IN ({$status_placeholders}) 
            AND od.is_cancelled = FALSE 
    ";
    
    $stmt = $conn->prepare($sql_products);
    
    $bind_params = array_merge([$source_table_number], $final_statuses);
    $stmt->bind_param("i" . $status_types, ...$bind_params); 
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    $source_order_id = null;

    while ($row = $result->fetch_assoc()) {
        $source_order_id = $row['order_id'];
        $products[] = [
            'detail_id'      => (int)$row['detail_id'],
            'quantity'       => (int)$row['quantity'],
            'price_at_order' => (float)$row['price_at_order'],
            'product_name'   => $row['product_name'],
            'modifier_name'  => $row['modifier_name'] 
        ];
    }
    $stmt->close();
    
    if (!$source_order_id) {
        $response['message'] = "No se encontró una orden activa con productos para la Mesa {$source_table_number}.";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $response['success'] = true;
    $response['source_order_id'] = (int)$source_order_id;
    $response['products'] = $products;

} catch (Throwable $e) {
    http_response_code(500);
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>
