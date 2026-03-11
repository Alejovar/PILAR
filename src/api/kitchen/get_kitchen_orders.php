<?php
// /src/api/kitchen/get_kitchen_orders.php (VERSIÓN CON MODIFICADORES)

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

$response = ['success' => false, 'message' => 'Error desconocido.'];
$conn = null;

try {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol_id'], [3, 5])) {
        throw new Exception("Acceso no autorizado.");
    }
    
    $area = ($_SESSION['rol_id'] == 3) ? 'COCINA' : 'BARRA';

    require $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

    // Se añade un LEFT JOIN a 'modifiers' y se selecciona 'modifier_name'
    $sql = "
        SELECT 
            od.detail_id, od.order_id,
            p.name AS product_name,
            m.modifier_name, -- SELECCIONA EL NOMBRE DEL MODIFICADOR
            od.quantity, od.special_notes, od.item_status,
            od.batch_timestamp AS added_at,
            od.service_time, o.order_time,
            rt.table_number, u.id AS server_id, u.name AS server_name
        FROM order_details od
        JOIN products p ON od.product_id = p.product_id
        JOIN orders o ON od.order_id = o.order_id
        JOIN restaurant_tables rt ON o.table_id = rt.table_id
        JOIN users u ON o.server_id = u.id
        LEFT JOIN modifiers m ON od.modifier_id = m.modifier_id -- UNE LA TABLA DE MODIFICADORES
        WHERE od.preparation_area = ? 
          AND od.item_status IN ('PENDIENTE', 'EN_PREPARACION')
          AND od.is_cancelled = 0
        ORDER BY od.batch_timestamp ASC, od.service_time ASC, od.detail_id ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $area);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    
    $server_time = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H-i:s');

    $response = [
        'success' => true,
        'production_items' => $items,
        'server_time' => $server_time
    ];

} catch (Throwable $e) {
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()];
} finally {
    if ($conn) $conn->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
