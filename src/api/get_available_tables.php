<?php
// Preparamos para responder en formato JSON.
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
// Incluimos la conexión a la base de datos.

// Ponemos la zona horaria correcta para que no haya broncas con las horas.
date_default_timezone_set('America/Mexico_City');

// Jalamos la fecha y hora que nos mandaron por la URL (GET).
$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';

// Si no nos mandaron fecha u hora, devolvemos un array vacío y terminamos.
if (empty($date) || empty($time)) {
    echo json_encode([]);
    exit();
}

$today = date('Y-m-d');
$full_datetime_string = $date . ' ' . $time;

// --- LÓGICA PARA ENCONTRAR MESAS DISPONIBLES ---

// 1. Consulta base: Empezamos buscando todas las mesas...
//    ...y le quitamos las que ya tienen una reservación justo a esa misma fecha y hora.
$sql = "SELECT id, table_name
        FROM tables
        WHERE id NOT IN (
            SELECT table_id
            FROM reservation_tables rt
            JOIN reservations r ON rt.reservation_id = r.id
            WHERE r.reservation_date = ? AND r.reservation_time = ?
        )";

// 2. Lógica condicional: El truco está aquí.
//    La disponibilidad cambia si la reservación es para hoy o para el futuro.
if ($date === $today) {
    // CASO A: SI LA RESERVA ES PARA HOY
    // A la consulta anterior, le agregamos una condición extra:
    // La mesa debe estar 'disponible' O, si está 'ocupado', calculamos si ya se va a liberar
    // para la hora solicitada (suponiendo que una mesa se ocupa máximo 6 horas).
    $sql .= " AND (status = 'disponible' OR (status = 'ocupado' AND ADDTIME(status_changed_at, '06:00:00') <= ?))";

    $stmt = $conn->prepare($sql);
    // Necesitamos 3 parámetros para esta consulta.
    $stmt->bind_param("sss", $date, $time, $full_datetime_string);

} else {
    // CASO B: SI LA RESERVA ES PARA UN DÍA FUTURO
    // El estado actual ('ocupado' o 'disponible') no importa, porque para mañana ya estará libre.
    // Así que usamos la consulta base sin añadirle nada más.
    $stmt = $conn->prepare($sql);
    // Y solo necesitamos 2 parámetros.
    $stmt->bind_param("ss", $date, $time);
}

// Ejecutamos la consulta que se haya preparado.
$stmt->execute();
$result = $stmt->get_result();

// Guardamos los resultados en un array.
$tables = [];
while($row = $result->fetch_assoc()) {
    $tables[] = $row;
}

// Devolvemos la lista de mesas disponibles en formato JSON.
echo json_encode($tables);

// Cerramos todo para liberar recursos.
$stmt->close();
$conn->close();
?>
