<?php
// /api/orders/tpv/get_current_order.php
// =====================================================
// VERSIÓN COMPLETA Y CORREGIDA
// Envía los productos de la orden y el total actual guardado en la base de datos.
// =====================================================

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

$response = ['success' => false, 'message' => 'Error desconocido.'];
$conn = null;

try {
    // 1. Validar que se recibió un order_id
    $order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
    if (!$order_id) {
        throw new Exception("ID de orden no proporcionado o inválido.");
    }

    // 2. Conexión a la base de datos
    require $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';
    if (!$conn || $conn->connect_errno) {
        throw new Exception('Error de conexión a la base de datos.');
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

    // 3. Consulta para obtener el total guardado de la orden
    $total_from_db = 0;
    $stmt_total = $conn->prepare("SELECT total FROM orders WHERE order_id = ?");
    $stmt_total->bind_param("i", $order_id);
    if ($stmt_total->execute()) {
        $result_total = $stmt_total->get_result();
        if ($row_total = $result_total->fetch_assoc()) {
            $total_from_db = $row_total['total'];
        }
    }
    $stmt_total->close();

    // 4. Consulta principal para obtener los productos de la orden
    $sql = "
        SELECT
            od.detail_id,
            od.product_id,
            p.name AS product_name,
            od.quantity,
            od.price_at_order,
            od.special_notes,
            od.modifier_id,
            od.batch_timestamp,
            od.service_time,
            m.modifier_name
        FROM order_details od
        JOIN products p ON od.product_id = p.product_id
        LEFT JOIN modifiers m ON od.modifier_id = m.modifier_id
        WHERE od.order_id = ?
        ORDER BY od.service_time ASC, od.batch_timestamp ASC, od.detail_id ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $items_by_time = [];

    // 5. Procesar y agrupar los resultados
    while ($row = $result->fetch_assoc()) {
        $service_time = $row['service_time'];
        
        if (!isset($items_by_time[$service_time])) {
            $items_by_time[$service_time] = [
                'service_time' => (int)$service_time,
                'items' => []
            ];
        }

        $item_name = $row['product_name'];
        if (!empty($row['modifier_name'])) {
            $item_name .= ' (' . $row['modifier_name'] . ')';
        }

        $items_by_time[$service_time]['items'][] = [
            'id' => (int)$row['product_id'],
            'name' => $item_name,
            'price' => (float)$row['price_at_order'],
            'comment' => $row['special_notes'],
            'modifier_id' => $row['modifier_id'] ? (int)$row['modifier_id'] : null,
            'batch_timestamp' => $row['batch_timestamp'] 
        ];
    }
    $stmt->close();
    
    // 6. Enviar la respuesta JSON completa
    $response = [
        'success' => true,
        'order_id' => $order_id,
        'total' => (float)$total_from_db, // <-- Se incluye el total de la BD
        'times' => array_values($items_by_time)
    ];

} catch (Throwable $e) {
    http_response_code(500); 
    $response = [
        'success' => false, 
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ];
} finally {
    if ($conn) $conn->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
