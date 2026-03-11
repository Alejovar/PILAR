<?php
// src/api/seat_client.php
// Preparamos la respuesta en formato JSON.
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
// Incluimos la conexión a la BD.

// Leemos los datos que nos mandan por JSON (ID del cliente y IDs de las mesas).
$data = json_decode(file_get_contents('php://input'), true);
$clientId = filter_var($data['client_id'] ?? 0, FILTER_VALIDATE_INT);
$tableIds = $data['table_ids'] ?? [];

// Validamos que nos hayan enviado los datos necesarios.
if (!$clientId || empty($tableIds) || !is_array($tableIds)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
    exit();
}

// Iniciar transacción. "Sentar un cliente" implica varios pasos.
// Con una transacción nos aseguramos de que todos los pasos se completen,
// o si algo falla, no se hace ninguno. ¡O todo o nada!
$conn->begin_transaction();

try {
    // 1. Buscamos al cliente en la lista de espera para tener todos sus datos.
    $stmt_get = $conn->prepare("SELECT * FROM waiting_list WHERE id = ?");
    $stmt_get->bind_param("i", $clientId);
    $stmt_get->execute();
    $client = $stmt_get->get_result()->fetch_assoc();

    if (!$client) {
        throw new Exception("Cliente no encontrado.", 404);
    }

    // 2. Obtenemos los nombres de las mesas que nos pasaron.
    // Este truco crea los placeholders (?,?,?) para la consulta IN() de forma dinámica.
    $placeholders = implode(',', array_fill(0, count($tableIds), '?'));
    $stmt_tables = $conn->prepare("SELECT table_name FROM tables WHERE id IN ($placeholders)");
    // El 'spread operator' (...) pasa los IDs de las mesas como argumentos individuales.
    $stmt_tables->bind_param(str_repeat('i', count($tableIds)), ...$tableIds);
    $stmt_tables->execute();
    $tablesResult = $stmt_tables->get_result()->fetch_all(MYSQLI_ASSOC);
    // Convertimos el resultado en un texto simple como "Mesa 5, Terraza 2".
    $tableNames = array_column($tablesResult, 'table_name');
    $tableNamesStr = implode(', ', $tableNames);

    // 3. Movemos al cliente al historial, ahora con estado 'seated' y las mesas que ocupó.
    $stmt_insert = $conn->prepare(
        "INSERT INTO waiting_list_history (original_id, customer_name, number_of_people, customer_phone, created_at, status, tables_assigned) VALUES (?, ?, ?, ?, ?, 'seated', ?)"
    );
    $stmt_insert->bind_param(
        "isssss",
        $client['id'],
        $client['customer_name'],
        $client['number_of_people'],
        $client['customer_phone'],
        $client['created_at'],
        $tableNamesStr
    );
    $stmt_insert->execute();

    // 4. Actualizamos el estado de las mesas a 'ocupado' y registramos la hora.
    $stmt_update = $conn->prepare("UPDATE tables SET status = 'ocupado', status_changed_at = NOW() WHERE id IN ($placeholders)");
    $stmt_update->bind_param(str_repeat('i', count($tableIds)), ...$tableIds);
    $stmt_update->execute();

    // 5. Por último, eliminamos al cliente de la lista de espera activa.
    $stmt_delete = $conn->prepare("DELETE FROM waiting_list WHERE id = ?");
    $stmt_delete->bind_param("i", $clientId);
    $stmt_delete->execute();

    // Si todos los pasos anteriores funcionaron, guardamos los cambios.
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Cliente sentado con éxito.']);

} catch (Exception $e) {
    // Si algo falló en cualquier punto del 'try', revertimos todos los cambios.
    $conn->rollback();
    // Devolvemos el código de error adecuado (404 si no se encontró, 500 para lo demás).
    $code = $e->getCode() === 404 ? 404 : 500;
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Cerramos la conexión.
$conn->close();
?>
