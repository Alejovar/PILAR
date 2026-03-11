<?php
// /src/api/cashier/set_prebill_requested.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf8');
// Asegúrate de que db_connection.php crea $conn
// Si check_session_api.php no incluye la conexión, debes incluirla aquí:
// require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db/db_connection.php'; 

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($conn) || $conn->connect_error) {
    $response['message'] = "Error Fatal: La conexión a la base de datos no está disponible.";
    http_response_code(500);
    echo json_encode($response);
    exit;
}

try {
    // 1. Validación de rol y datos
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol_id'], [6, 1])) {
        throw new Exception("Unauthorized access.", 403);
    }
    
    $order_id = $input['order_id'] ?? null;
    if (!$order_id) {
        throw new Exception("Missing order ID.", 400);
    }

    // 2. Obtener la table_id (ID de la fila en restaurant_tables) asociada a la orden
    $sql_get_table_id = "SELECT table_id FROM orders WHERE order_id = ?";
    $stmt_id = $conn->prepare($sql_get_table_id);
    $stmt_id->bind_param("i", $order_id);
    $stmt_id->execute();
    $result = $stmt_id->get_result();
    $order_data = $result->fetch_assoc();
    $stmt_id->close();

    if (!$order_data) {
        throw new Exception("Order not found or already closed.", 404);
    }
    
    $rt_table_id = $order_data['table_id'];

    // 3. 💥 CRÍTICO: Actualizar el estado de la mesa a REQUESTED en restaurant_tables
    $new_status = 'REQUESTED';
    $sql_update_status = "UPDATE restaurant_tables SET pre_bill_status = ? WHERE table_id = ?";
    $stmt_update = $conn->prepare($sql_update_status);
    $stmt_update->bind_param("si", $new_status, $rt_table_id);
    $stmt_update->execute();
    $rows_affected = $stmt_update->affected_rows;
    $stmt_update->close();

    if ($rows_affected === 0) {
        throw new Exception("Mesa no encontrada en la lista de ocupadas o el estado ya estaba solicitado.", 404);
    }

    $response = ['success' => true, 'message' => "Estado de cuenta solicitado para la mesa {$order_data['table_id']}."];

} catch (Throwable $e) {
    http_response_code($e->getCode() ?: 500);
    $response['message'] = 'Error al establecer el estado de solicitud: ' . $e->getMessage();
} finally {
    if (isset($conn) && $conn->ping()) $conn->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
