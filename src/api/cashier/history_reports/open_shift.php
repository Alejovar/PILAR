<?php
// /src/api/reports/open_shift.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'Error desconocido.'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    // 1. Validar Rol (Solo Cajero o Gerente pueden abrir caja)
    if (!isset($_SESSION['rol_id']) || !in_array($_SESSION['rol_id'], [1, 6])) {
        throw new Exception("Acceso no autorizado.", 403);
    }

    $starting_cash = $input['starting_cash'] ?? null;
    $user_id = $_SESSION['user_id']; // El usuario que está abriendo el turno

    if ($starting_cash === null || !is_numeric($starting_cash) || $starting_cash < 0) {
        throw new Exception("Monto de fondo de caja inválido.", 400);
    }

    // 2. 💥 Verificación CRÍTICA: Asegurarse de que no haya otro turno abierto
    $sql_check = "SELECT 1 FROM cash_shifts WHERE status = 'OPEN' LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute();
    if ($stmt_check->get_result()->fetch_assoc()) {
        $stmt_check->close();
        throw new Exception("Ya existe un turno abierto. Refresque la página.", 409);
    }
    $stmt_check->close();

    // 3. Crear el nuevo turno
    $sql_insert = "INSERT INTO cash_shifts (user_id_opened, starting_cash, status) VALUES (?, ?, 'OPEN')";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("id", $user_id, $starting_cash);
    
    if ($stmt_insert->execute()) {
        $response['success'] = true;
        $response['message'] = "Turno abierto exitosamente.";
        $response['new_shift_id'] = $conn->insert_id;
        $response['starting_cash'] = $starting_cash;
    } else {
        throw new Exception("No se pudo registrar el nuevo turno en la base de datos.");
    }
    $stmt_insert->close();

} catch (Throwable $e) {
    http_response_code($e->getCode() ?: 500);
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
