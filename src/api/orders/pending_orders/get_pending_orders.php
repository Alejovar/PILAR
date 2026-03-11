<?php
// get_pending_orders.php - FINAL Y FUNCIONAL

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

$response = ['success' => false, 'message' => 'Error desconocido.'];

try {
    // 1. Verificación de sesión y conexión
    if (!isset($_SESSION['user_id']) || !isset($conn) || $conn->connect_error) {
        throw new Exception("Error de conexión a la base de datos o sesión inválida.");
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
    
    $server_id = $_SESSION['user_id']; // Recuperamos el filtro de mesero

    // 2. Consulta SQL
    $sql = "
        SELECT 
            o.order_id,
            rt.table_number,
            od.batch_timestamp,
            MIN(od.detail_id) as batch_id, 
            -- Contamos ítems con estatus que NO es COMPLETADO
            SUM(CASE WHEN od.preparation_area = 'COCINA' AND od.item_status != 'COMPLETADO' THEN 1 ELSE 0 END) as total_kitchen_active,
            SUM(CASE WHEN od.preparation_area = 'BARRA' AND od.item_status != 'COMPLETADO' THEN 1 ELSE 0 END) as total_bar_active,
            SUM(CASE WHEN od.preparation_area = 'COCINA' AND od.item_status = 'LISTO' THEN 1 ELSE 0 END) as kitchen_ready,
            SUM(CASE WHEN od.preparation_area = 'BARRA' AND od.item_status = 'LISTO' THEN 1 ELSE 0 END) as bar_ready
        FROM orders o
        JOIN order_details od ON o.order_id = od.order_id
        JOIN restaurant_tables rt ON o.table_id = rt.table_id
        WHERE o.server_id = ? 
          AND o.status NOT IN ('PAGADA', 'CANCELADA', 'CLOSED')
          AND od.is_cancelled = 0
          -- Mantenemos este filtro, el problema estaba en el HAVING
          AND od.item_status != 'COMPLETADO' 
        GROUP BY o.order_id, rt.table_number, od.batch_timestamp
        -- ⚠️ ELIMINA el HAVING para ver si el JS necesita ese dato
        -- HAVING total_kitchen_active > 0 OR total_bar_active > 0 
        ORDER BY od.batch_timestamp DESC;
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error en la preparación de la consulta: " . $conn->error);
    }
    
    // Recuperamos el BIND_PARAM para el mesero
    $stmt->bind_param("i", $server_id); 
    
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $orders_summary = [];
    while ($row = $result->fetch_assoc()) {
        $orders_summary[] = $row;
    }

    $server_time = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

    $response = [
        'success' => true,
        'orders_summary' => $orders_summary,
        'server_time' => $server_time
    ];

} catch (Throwable $e) {
    http_response_code(500); 
    $response = ['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()];
} finally {
    // El 'finally' está vacío
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
