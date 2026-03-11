<?php
// get_reservation_details.php - Endpoint API para cargar datos de una reservación para edición.

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');

// Verificar autenticación (opcional, pero buena práctica)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit();
}

// 1. OBTENER EL ID DE LA RESERVACIÓN
// El ID debe venir en la URL (e.g., ?id=5)
$reservation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reservation_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de reservación inválido.']);
    exit();
}

// 2. CONEXIÓN A BASE DE DATOS (Asegúrate de que este archivo carga tu $conn/MySQLi)

// 3. CONSULTA SQL
// Seleccionar todos los campos necesarios para rellenar el formulario.
$sql = "SELECT 
            customer_name, 
            customer_phone, 
            reservation_date, 
            reservation_time,
            number_of_people, 
            special_requests 
        FROM 
            reservations 
        WHERE 
            id = ?";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservation = $result->fetch_assoc();
    $stmt->close();

    if ($reservation) {
        // 4. ÉXITO: Devolver los datos de la reservación en JSON
        echo json_encode(['success' => true, 'reservation' => $reservation]);
    } else {
        // Reservación no encontrada
        echo json_encode(['success' => false, 'message' => 'Reservación no encontrada.']);
    }

} catch (\Exception $e) {
    error_log("DB Error fetching reservation details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al consultar los detalles de la reservación.']);
}
?>
