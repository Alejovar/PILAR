<?php
// =====================================================
// EXECUTE_CANCEL.PHP - Cancela productos en order_details (MySQLi)
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

$response = ['success' => false, 'message' => ''];

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

// 1. Obtener datos
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['order_id']) || !isset($data['items_to_cancel']) || !isset($data['reason'])) {
    http_response_code(400);
    $response['message'] = 'Faltan datos (order_id, ítems o razón de cancelación).';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$order_id = intval($data['order_id']);
$items_to_cancel = $data['items_to_cancel']; // Array de detail_ids (enteros)
$reason = $data['reason'];

if ($order_id <= 0 || empty($items_to_cancel) || strlen($reason) < 5) {
    http_response_code(400);
    $response['message'] = 'Datos de cancelación inválidos o razón muy corta.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================================
// 2. Lógica de Transacción
// =====================================================
$conn->begin_transaction();

try {
    // Definimos el precio de ajuste a cero
    $zero_price = 0.00;
    
    // Crear un string de placeholders para la lista de detail_ids (ej. '?,?,?')
    $item_count = count($items_to_cancel);
    $placeholders = implode(',', array_fill(0, $item_count, '?'));
    $types = str_repeat('i', $item_count); // Tipos: 'iii...' (enteros)

    // Consulta para actualizar el estado, la razón Y EL PRECIO a 0.00
    $sql_update = "
        UPDATE order_details 
        SET is_cancelled = 1, 
            cancellation_reason = ?, 
            price_at_order = ? 
        WHERE detail_id IN ($placeholders) AND order_id = ? AND is_cancelled = 0
    ";
    
    $stmt = $conn->prepare($sql_update);
    
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    // 1. Tipos: s (razón) + d (cero_price) + i*N (detail_ids) + i (order_id)
    $bind_types = "sd" . $types . "i";

    // 2. Parámetros: razón, cero_price, [items...], order_id
    $bind_params = array_merge([$reason, $zero_price], $items_to_cancel, [$order_id]);
    
    // 3. Usamos call_user_func_array para bindear todos los parámetros
    // El primer argumento es $stmt, el segundo es el tipo de string, y el resto son los parámetros.
    array_unshift($bind_params, $bind_types);
    call_user_func_array([$stmt, 'bind_param'], $bind_params);
    
    $stmt->execute();
    
    $canceled_rows = $stmt->affected_rows;
    $stmt->close();
    
    if ($canceled_rows === 0) {
        $conn->rollback();
        http_response_code(400);
        $response['message'] = "No se pudo cancelar ningún ítem. Verifique si los productos ya estaban cancelados o la orden no existe.";
    } else {
        $conn->commit();
        $response['success'] = true;
        $response['message'] = "Se cancelaron {$canceled_rows} ítems de la orden {$order_id}.";
    }
    
} catch (Throwable $e) {
    if ($conn->in_transaction) { $conn->rollback(); }
    http_response_code(500);
    $response['message'] = 'Error en la transacción: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>
