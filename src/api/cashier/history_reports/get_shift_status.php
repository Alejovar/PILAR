<?php
// /src/api/cashier/history_reports/get_shift_status.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

// 💡 CAMBIO: Añadido rol 2 (Mesero) a la lista de roles permitidos
if (!isset($_SESSION['rol_id']) || !in_array($_SESSION['rol_id'], [1, 6, 2])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$response = [
    'success' => false,
    'status' => 'CLOSED', // Estado por defecto
    'shift_id' => null,
    'starting_cash' => 0.00
];

try {
    // Buscamos un turno que esté 'OPEN'
    $sql = "SELECT shift_id, starting_cash FROM cash_shifts WHERE status = 'OPEN' ORDER BY start_time DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    
    // Si la tabla no existiera, $stmt sería false y fallaría.
    if ($stmt === false) {
        throw new Exception("Error en la consulta. ¿Existe la tabla 'cash_shifts'?", 500);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // ¡Encontramos un turno abierto!
        $response['success'] = true;
        $response['status'] = 'OPEN';
        $response['shift_id'] = $row['shift_id'];
        $response['starting_cash'] = $row['starting_cash'];
    } else {
        // No hay turnos abiertos
        $response['success'] = true;
        $response['status'] = 'CLOSED';
    }
    
    $stmt->close();
    
} catch (Throwable $e) {
    http_response_code($e->getCode() ?: 500);
    $response['message'] = $e->getMessage();
}

if (isset($conn)) $conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
