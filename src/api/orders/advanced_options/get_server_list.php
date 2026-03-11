<?php
// =====================================================
// GET_SERVER_LIST.PHP - Obtiene la lista de meseros (MySQLi)
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

try {
    // Asumo que el rol ID para Mesero es 2
    $sql = "SELECT id, name FROM users WHERE rol_id = 2 ORDER BY name";
    $result = $conn->query($sql);
    
    $servers = [];
    while($row = $result->fetch_assoc()) {
        $servers[] = ['id' => (int)$row['id'], 'name' => $row['name']];
    }
    
    $response['success'] = true;
    $response['servers'] = $servers;

} catch (Throwable $e) {
    http_response_code(500);
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>
