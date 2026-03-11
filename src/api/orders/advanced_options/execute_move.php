<?php
// =====================================================
// EXECUTE_MOVE.PHP - Ejecuta la transacción de mover productos (MySQLi)
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

$response = ['success' => false, 'message' => ''];

// 1. Obtener datos (se asume que la validación ya pasó en el frontend)
$data = json_decode(file_get_contents("php://input"), true);
// ... [Obtención de $source_order_id, $destination_table_number, $items_to_move] ...

$source_order_id = intval($data['source_order_id']);
$destination_table_number = intval($data['destination_table_number']);
$items_to_move = $data['items'];

if ($source_order_id <= 0 || $destination_table_number <= 0 || empty($items_to_move)) {
    http_response_code(400);
    $response['message'] = 'Datos de movimiento inválidos.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================================
// 2. Lógica de Transacción
// =====================================================
$conn->begin_transaction();

// Definir estados finales para las consultas (los mismos que en get_move_data.php)
$final_statuses = ['COBRADO', 'CLOSED', 'CANCELADO']; 
$status_placeholders = str_repeat('?,', count($final_statuses) - 1) . '?';
$status_types = str_repeat('s', count($final_statuses));

try {
    // --- A. Encontrar/Crear ORDEN DESTINO ---
    
    // 1. Obtener table_id destino y servidor asignado
    $sql_table_data = "SELECT table_id, assigned_server_id FROM restaurant_tables WHERE table_number = ?";
    $stmt = $conn->prepare($sql_table_data);
    $stmt->bind_param("i", $destination_table_number);
    $stmt->execute();
    $table_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$table_result) {
        throw new Exception("Mesa destino #{$destination_table_number} no existe.");
    }
    $destination_table_id = $table_result['table_id'];
    $destination_server_id = $table_result['assigned_server_id'];
    
    // 2. Buscar si ya existe una orden activa en destino (que no esté en estado final)
    $sql_find_order = "SELECT order_id FROM orders WHERE table_id = ? AND status NOT IN ({$status_placeholders})";
    $stmt = $conn->prepare($sql_find_order);
    $bind_find_params = array_merge([$destination_table_id], $final_statuses);
    $stmt->bind_param("i" . $status_types, ...$bind_find_params);

    $stmt->execute();
    $order_result = $stmt->get_result();
    $destination_order_id = $order_result->fetch_assoc()['order_id'] ?? null;
    $stmt->close();

    // 3. Si no existe, crear una nueva orden
    if (!$destination_order_id) {
        $sql_create_order = "INSERT INTO orders (table_id, server_id, order_time, status) VALUES (?, ?, NOW(), 'PENDING')";
        $stmt = $conn->prepare($sql_create_order);
        $stmt->bind_param("ii", $destination_table_id, $destination_server_id); 
        $stmt->execute();
        $destination_order_id = $conn->insert_id;
        $stmt->close();
    }
    
    // --- B. Mover Iitems (INSERT en destino, DELETE en origen) ---
    $moved_count = 0;
    
    // Preparamos todas las consultas fuera del bucle
    $sql_get_item = "SELECT product_id, quantity, price_at_order, special_notes, modifier_id FROM order_details WHERE detail_id = ? AND order_id = ?";
    $stmt_get = $conn->prepare($sql_get_item);
    $stmt_insert = $conn->prepare("
        INSERT INTO order_details 
        (order_id, product_id, quantity, price_at_order, special_notes, item_status, modifier_id, added_at, batch_timestamp)
        VALUES (?, ?, ?, ?, ?, 'PENDIENTE', ?, NOW(), NOW())
    ");
    $stmt_delete = $conn->prepare("DELETE FROM order_details WHERE detail_id = ?");
    
    // Bind de inserción: iiidsi (order_id, product_id, quantity, price, notes, modifier_id)
    $stmt_insert->bind_param("iiidsi", $destination_order_id_param, $product_id_param, $qty_param, $price_param, $notes_param, $modifier_param);
    
    foreach ($items_to_move as $item) {
        $detail_id = intval($item['detail_id']);

        // 1. Obtener detalles del ítem original
        $stmt_get->bind_param("ii", $detail_id, $source_order_id);
        $stmt_get->execute();
        $item_result = $stmt_get->get_result()->fetch_assoc();
        
        if (!$item_result) continue; 

        // 2. Ejecutar INSERT en la orden destino
        $destination_order_id_param = $destination_order_id;
        $product_id_param = $item_result['product_id'];
        $qty_param = $item_result['quantity'];
        $price_param = $item_result['price_at_order'];
        $notes_param = $item_result['special_notes'];
        $modifier_param = $item_result['modifier_id'];
        
        $stmt_insert->execute();

        // 3. ELIMINAR el ítem de la orden origen
        $stmt_delete->bind_param("i", $detail_id);
        $stmt_delete->execute();

        $moved_count += $qty_param;
    }
    
    // Cierre de sentencias preparadas
    $stmt_get->close();
    $stmt_insert->close();
    $stmt_delete->close();


    // --- C. Verificar si la orden origen quedó vacía (si se vacía, la cerramos) ---
    $sql_check_empty = "SELECT COUNT(*) FROM order_details WHERE order_id = ?";
    $stmt = $conn->prepare($sql_check_empty);
    $stmt->bind_param("i", $source_order_id);
    $stmt->execute();
    $items_left = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    
    if ($items_left == 0) {
        // Cerramos la orden de origen si se vació
        $sql_close_order = "UPDATE orders SET status = 'CLOSED' WHERE order_id = ?";
        $stmt = $conn->prepare($sql_close_order);
        $stmt->bind_param("i", $source_order_id);
        $stmt->execute();
        $stmt->close();
    }


    // --- D. Finalizar Transacción ---
    $conn->commit();
    $response['success'] = true;
    $response['message'] = "Se movieron {$moved_count} productos de la Mesa {$source_table_number} a la Mesa {$destination_table_number}.";

} catch (Throwable $e) {
    if ($conn->in_transaction) { $conn->rollback(); }
    http_response_code(500);
    $response['message'] = 'Error en la transacción: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>
