<?php
// /src/api/kitchen/get_kitchen_history.php

// 1. Incluye seguridad. ESTO YA ABRE Y DEFINE $conn (si la sesión es válida)
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');
$response = ['success' => false, 'production_items' => []];

// ⚠️ Se eliminó: $conn = null;
// ⚠️ Se eliminó: require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

try {
    // 2. Comprueba que $conn ha sido definida por el archivo de seguridad.
    if (!isset($conn) || $conn->connect_error) {
        // Si hay un error, el archivo de seguridad debió haber hecho exit(500), pero lo verificamos aquí.
        throw new Exception("Error de conexión a la base de datos.");
    }
    
    $selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $selected_date)) {
        throw new Exception("Formato de fecha no válido.");
    }
    
    // El SQL es correcto y usa la conexión $conn abierta.
    $sql = "
        SELECT 
            kph.order_id, kph.table_number, kph.server_name,
            kph.batch_timestamp AS added_at,
            kph.timestamp_added AS order_time,
            kph.service_time, kph.product_name, 
            kph.modifier_name,
            kph.quantity, kph.special_notes,
            'LISTO' as item_status,
            kph.original_detail_id as detail_id
        FROM 
            kitchen_production_history kph
        WHERE 
            DATE(kph.timestamp_completed) = ?
        ORDER BY 
            kph.batch_timestamp ASC, kph.service_time ASC
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error en la preparación de la consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $response['production_items'] = $result->fetch_all(MYSQLI_ASSOC);
    $response['success'] = true;
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = 'Error al obtener el historial de producción.';
    $response['details'] = $e->getMessage();
} finally {
    // ⚠️ Se eliminó el cierre de la conexión ($conn->close()). 
    // Ahora, $conn permanece abierta para la siguiente API o hasta que la solicitud termine.
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
