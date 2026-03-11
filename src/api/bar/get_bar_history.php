<?php
// /src/api/bar/get_bar_history.php

// 1. Incluye seguridad. ESTO YA ABRE Y DEFINE $conn (si la sesión es válida)
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');
$response = ['success' => false, 'production_items' => []];

// ⚠️ ELIMINAR: $conn = null;
// ⚠️ ELIMINAR: require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

try {
    // 2. Comprueba que $conn ha sido definida por el archivo de seguridad.
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Error de conexión a la base de datos.");
    }
    
    $selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $selected_date)) {
        throw new Exception("Formato de fecha no válido.");
    }
    
    // El SQL utiliza la conexión $conn abierta.
    $sql = "
        SELECT 
            bph.order_id, bph.table_number, bph.server_name,
            bph.batch_timestamp AS added_at,
            bph.timestamp_added AS order_time,
            bph.service_time, bph.product_name, 
            bph.modifier_name,
            bph.quantity, bph.special_notes,
            'LISTO' as item_status,
            bph.original_detail_id as detail_id
        FROM 
            bar_production_history bph
        WHERE 
            DATE(bph.timestamp_completed) = ?
        ORDER BY 
            bph.batch_timestamp ASC, bph.service_time ASC
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
    $response['error'] = 'Error al obtener el historial de barra.';
    $response['details'] = $e->getMessage();
} finally {
    // ⚠️ ELIMINAR: if ($conn) { $conn->close(); }
    // Dejamos que PHP cierre la conexión al final.
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
