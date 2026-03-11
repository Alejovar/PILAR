<?php
// update_reservation.php - Endpoint API para actualizar una reservación existente.
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

// 1. OBTENER DATOS (Se asume que vienen de FormData)
$reservation_id = $_POST['reservation_id'] ?? null;
$customer_name = $_POST['customer_name'] ?? '';
$customer_phone = $_POST['customer_phone'] ?? '';
$reservation_date = $_POST['reservation_date'] ?? '';
$reservation_time = $_POST['reservation_time'] ?? '';
$number_of_people = $_POST['number_of_people'] ?? null;
$special_requests = $_POST['special_requests'] ?? null;

// Validación básica
if (!$reservation_id || empty($customer_name) || empty($reservation_date) || empty($reservation_time) || empty($number_of_people)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios para actualizar.']);
    exit();
}

// 2. EJECUTAR CONSULTA UPDATE
// NOTA: Esta consulta es básica. En un sistema real, necesitas una lógica robusta
// para actualizar las mesas asignadas a la reservación.
$sql = "UPDATE reservations SET 
            customer_name = ?, 
            customer_phone = ?, 
            reservation_date = ?, 
            reservation_time = ?,
            number_of_people = ?, 
            special_requests = ?
        WHERE id = ?";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssisi", // s=string, i=integer
        $customer_name,
        $customer_phone,
        $reservation_date,
        $reservation_time,
        $number_of_people,
        $special_requests,
        $reservation_id
    );
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Reservación actualizada con éxito.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se realizaron cambios o la reservación no existe.']);
    }

    $stmt->close();
} catch (\Exception $e) {
    error_log("DB Error updating reservation: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos al actualizar la reservación.']);
}
