<?php
// =====================================================
// CHANGE_TABLE.PHP - Renombramiento de Mesa con MySQLi
// =====================================================

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

// =====================================================
// 1. Conexión a la Base de Datos
// =====================================================
require '../../../php/db_connection.php'; // Tu archivo actual con MySQLi

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión con la base de datos.'
    ]);
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

// =====================================================
// 2. Obtener y validar datos del frontend
// =====================================================
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput);

if (!$data || !isset($data->new_table_number) || !isset($data->current_table_number)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Faltan números de mesa para la reasignación.',
        'raw_input' => $rawInput
    ]);
    exit;
}

$new_table_number = intval($data->new_table_number);
$current_table_number = intval($data->current_table_number);

if ($new_table_number <= 0 || $current_table_number <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Los números de mesa no son válidos.'
    ]);
    exit;
}

// =====================================================
// 3. Lógica de renombramiento con MySQLi
// =====================================================
$conn->begin_transaction();

try {
    // --- A. Validar conflicto ---
    $sql_check_conflict = "
        SELECT table_id 
        FROM restaurant_tables 
        WHERE table_number = ? AND table_number != ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql_check_conflict);
    $stmt->bind_param("ii", $new_table_number, $current_table_number);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($conflict_id);
        $stmt->fetch();
        $conn->rollback();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => "El número {$new_table_number} ya está asignado a otra mesa (ID {$conflict_id})."
        ]);
        exit;
    }
    $stmt->close();

    // --- B. Si es el mismo número ---
    if ($current_table_number === $new_table_number) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El nuevo número de mesa es idéntico al actual.'
        ]);
        exit;
    }

    // --- C. Actualizar número de mesa ---
    $sql_update = "
        UPDATE restaurant_tables 
        SET table_number = ? 
        WHERE table_number = ?
    ";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("ii", $new_table_number, $current_table_number);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró la mesa actual o no se pudo actualizar.'
        ]);
        exit;
    }

    $stmt->close();
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Número de mesa cambiado de {$current_table_number} a {$new_table_number}."
    ]);
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en la transacción de la base de datos.',
        'error_details' => $e->getMessage()
    ]);
    exit;
}
?>
