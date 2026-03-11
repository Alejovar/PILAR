<?php
// Indicamos que nuestra respuesta será un JSON.
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
// Conectamos a la base de datos.

// Ponemos todo en un 'try' por si la conexión o la consulta fallan.
try {
    // Preparamos la consulta para traer la lista de espera completa.
    // La ordenamos por 'created_at' para que los primeros en llegar sean los primeros en la lista.
    $stmt = $conn->prepare("SELECT id, customer_name, number_of_people, customer_phone FROM waiting_list ORDER BY created_at ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    // Usamos fetch_all(MYSQLI_ASSOC) como un atajo para jalar todas las filas de golpe
    // y meterlas en el array $clients. ¡Más limpio y rápido!
    $clients = $result->fetch_all(MYSQLI_ASSOC);

    // Mandamos la lista de clientes en espera como respuesta.
    echo json_encode($clients);

} catch (Exception $e) {
    // Si algo sale mal, mandamos un error 500.
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}

// Y al final, cerramos la conexión.
$conn->close();
?>
