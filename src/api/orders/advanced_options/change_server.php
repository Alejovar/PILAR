<?php
// =====================================================
// CHANGE_SERVER.PHP - Reasigna la mesa a otro mesero (MySQLi)
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

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->table_number) || !isset($data->new_server_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos de mesa o del nuevo mesero.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$table_number = intval($data->table_number);
$new_server_id = intval($data->new_server_id);

if ($table_number <= 0 || $new_server_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valores de mesa o mesero inválidos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================================
// Lógica de Transacción
// =====================================================
$conn->begin_transaction();

try {
    // 1. Verificar que el nuevo mesero exista y tenga el rol correcto (rol_id=2)
    $sql_check_server = "SELECT name FROM users WHERE id = ? AND rol_id = 2";
    $stmt = $conn->prepare($sql_check_server);
    $stmt->bind_param("i", $new_server_id);
    $stmt->execute();
    $server_name = $stmt->get_result()->fetch_assoc()['name'] ?? null;
    $stmt->close();

    if (!$server_name) {
        throw new Exception("El ID del mesero seleccionado no es válido.");
    }
    
    // 2. Actualizar el assigned_server_id en la tabla restaurant_tables
    $sql_update_tables = "
        UPDATE restaurant_tables 
        SET assigned_server_id = ? 
        WHERE table_number = ?
    ";
    
    $stmt = $conn->prepare($sql_update_tables);
    $stmt->bind_param("ii", $new_server_id, $table_number);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception("La Mesa {$table_number} no existe o no se pudo reasignar (verifique si ya tiene ese mesero).");
    }
    $stmt->close();

    // 3. Opcional: Actualizar la orden activa (orders) si existe (para auditoría)
    $sql_update_order = "
        UPDATE orders o
        JOIN restaurant_tables rt ON rt.table_id = o.table_id
        SET o.server_id = ? 
        WHERE rt.table_number = ? AND o.status = 'PENDING'
    ";
    $stmt = $conn->prepare($sql_update_order);
    $stmt->bind_param("ii", $new_server_id, $table_number);
    $stmt->execute();
    $stmt->close(); 


    // Finalizar Transacción
    $conn->commit();
    $response = [
        'success' => true, 
        'message' => "Mesa {$table_number} reasignada a {$server_name}."
    ];

} catch (Throwable $e) {
    if ($conn->in_transaction) { $conn->rollback(); }
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Error en la transacción: ' . $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>
