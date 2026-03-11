<?php
// =====================================================
// CHANGE_GUEST_COUNT.PHP - Actualizar número de comensales (MySQLi)
// =====================================================
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

// --- Cargar conexión y dependencias ---
// Usamos la lógica de ruta absoluta, pero asumimos que define $conn (objeto MySQLi)
$absolute_path_to_conn = $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';
require_once $absolute_path_to_conn;

// Verificación de conexión MySQLi
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

if (!isset($data->table_number) || !isset($data->new_guest_count)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros necesarios (table_number o new_guest_count).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$table_number = intval($data->table_number);
$new_guest_count = intval($data->new_guest_count);

if ($table_number <= 0 || $new_guest_count <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Los valores ingresados no son válidos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================================
// Lógica de Renombramiento con MySQLi
// =====================================================
$conn->begin_transaction();

try {
    // Actualizar el número de comensales en la tabla restaurant_tables
    $sql_update = "
        UPDATE restaurant_tables 
        SET client_count = ? 
        WHERE table_number = ?
    ";
    
    $stmt = $conn->prepare($sql_update);
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    // 'ii' significa dos enteros (integer, integer)
    $stmt->bind_param("ii", $new_guest_count, $table_number);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        $response = ['success' => false, 'message' => "La Mesa {$table_number} no existe o no se pudo actualizar."];
    } else {
        $conn->commit();
        $response = [
            'success' => true, 
            'message' => "El número de comensales en la Mesa {$table_number} fue actualizado a {$new_guest_count}."
        ];
    }
    
    $stmt->close();
    
} catch (Throwable $e) {
    if ($conn->in_transaction) { $conn->rollback(); }
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Error de servidor: ' . $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>
