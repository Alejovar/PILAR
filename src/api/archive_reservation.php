<?php
// Preparamos la respuesta para que sea JSON.
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
// Necesitamos la sesión para saber si el usuario está logueado.
session_start();
// Conectamos a la BD.
require __DIR__ . '/../php/db_connection.php';

// Si no hay un usuario en la sesión, para afuera.
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

// Leemos los datos que nos mandan por JSON.
$data = json_decode(file_get_contents('php://input'), true);
$reservation_id = $data['reservation_id'] ?? 0;
$final_status = $data['status'] ?? '';

// Validamos que los datos que llegaron tengan sentido.
if ($reservation_id <= 0 || !in_array($final_status, ['completada', 'cancelada'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
    exit();
}

// Empezamos una transacción. Esto es para archivar una reservación, lo que implica
// tocar varias tablas. O todo sale bien, o no se hace nada.
$conn->begin_transaction();
try {
    // 1. Buscamos la reservación original para tener todos sus datos.
    $stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$reservation) throw new Exception("Reservación no encontrada.");

    // 2. Buscamos todas las mesas que estaban asociadas a esa reservación.
    $stmt_get_tables = $conn->prepare("SELECT table_id FROM reservation_tables WHERE reservation_id = ?");
    $stmt_get_tables->bind_param("i", $reservation_id);
    $stmt_get_tables->execute();
    $tables_result = $stmt_get_tables->get_result();
    $table_ids = [];
    while($row = $tables_result->fetch_assoc()){
        $table_ids[] = $row['table_id']; // Guardamos los IDs en un array.
    }
    $stmt_get_tables->close();

    // 3. Copiamos la info al historial. Creamos una entrada en el historial por CADA mesa de la reservación.
    $stmt_history = $conn->prepare(
        "INSERT INTO reservations_history (original_reservation_id, hostess_id, table_id, customer_name, customer_phone, reservation_date, reservation_time, number_of_people, special_requests, created_at, final_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($table_ids as $table_id) {
        // En cada vuelta del loop, insertamos un registro con el ID de la mesa correspondiente.
        $stmt_history->bind_param("iiissssisss",
            $reservation['id'], $reservation['hostess_id'], $table_id, $reservation['customer_name'],
            $reservation['customer_phone'], $reservation['reservation_date'], $reservation['reservation_time'],
            $reservation['number_of_people'], $reservation['special_requests'], $reservation['created_at'], $final_status
        );
        $stmt_history->execute();
    }
    $stmt_history->close();

    // 4. LÓGICA EXTRA: Si la reservación fue 'completada', actualizamos el estado de las mesas a 'ocupado'.
    if ($final_status === 'completada' && count($table_ids) > 0) {
        $reservation_start_timestamp = $reservation['reservation_date'] . ' ' . $reservation['reservation_time'];
        // Creamos los placeholders (?) para la consulta IN (...) dinámicamente.
        $ids_placeholder = implode(',', array_fill(0, count($table_ids), '?'));
        $stmt_update = $conn->prepare("UPDATE tables SET status = 'ocupado', status_changed_at = ? WHERE id IN ($ids_placeholder)");

        // Armamos los parámetros para el bind_param dinámicamente.
        $types = 's' . str_repeat('i', count($table_ids));
        $params = array_merge([$reservation_start_timestamp], $table_ids);
        $stmt_update->bind_param($types, ...$params);
        $stmt_update->execute();
        $stmt_update->close();
    }

    // 5. LIMPIEZA: Ahora que todo está en el historial, borramos los registros originales.
    // Primero borramos de la tabla que une reservaciones y mesas.
    $stmt_delete_rt = $conn->prepare("DELETE FROM reservation_tables WHERE reservation_id = ?");
    $stmt_delete_rt->bind_param("i", $reservation_id);
    $stmt_delete_rt->execute();
    $stmt_delete_rt->close();
    // Y luego borramos la reservación principal.
    $stmt_delete_r = $conn->prepare("DELETE FROM reservations WHERE id = ?");
    $stmt_delete_r->bind_param("i", $reservation_id);
    $stmt_delete_r->execute();
    $stmt_delete_r->close();

    // Si llegamos aquí, todo el proceso funcionó. Guardamos los cambios.
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Si algo falló en cualquier punto, deshacemos todo lo que se hizo.
    $conn->rollback();
    http_response_code(500); // Error del servidor
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
// Cerramos la conexión.
$conn->close();
?>
