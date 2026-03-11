<?php
// Preparamos la respuesta para que sea un JSON.
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
// Nos conectamos a la base de datos.

// Leemos el ID de la mesa que nos mandaron por JSON.
$data = json_decode(file_get_contents('php://input'), true);
$table_id = $data['table_id'] ?? 0;

// Validamos que el ID sea un número válido.
if ($table_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de mesa inválido.']);
    exit();
}

// Usamos una transacción para asegurar que la operación de leer y luego escribir
// sea atómica. Esto previene problemas si dos usuarios hacen clic al mismo tiempo.
$conn->begin_transaction();
try {
    // 1. Buscamos la mesa y la bloqueamos con "FOR UPDATE".
    // Esto es súper importante: evita que otra persona pueda modificar esta misma mesa
    // hasta que nuestra transacción termine.
    $stmt = $conn->prepare("SELECT status FROM tables WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $table_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) throw new Exception("La mesa no fue encontrada.");

    $current_status = $result->fetch_assoc()['status'];
    $stmt->close();

    // 2. Invertimos el estado actual.
    // Es un simple 'if-else': si está 'disponible', lo cambiamos a 'ocupado', y viceversa.
    $new_status = ($current_status === 'disponible') ? 'ocupado' : 'disponible';

    // 3. Actualizamos la mesa en la base de datos con el nuevo estado.
    // También guardamos la hora del cambio. Esto es útil para el cron job de limpieza.
    $stmt = $conn->prepare("UPDATE tables SET status = ?, status_changed_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $new_status, $table_id);

    if (!$stmt->execute()) throw new Exception("No se pudo actualizar el estado.");
    $stmt->close();

    // Si todo salió bien, guardamos los cambios.
    $conn->commit();
    // Devolvemos el nuevo estado para que la interfaz se pueda actualizar.
    echo json_encode(['success' => true, 'new_status' => $new_status]);

} catch (Exception $e) {
    // Si algo falla, deshacemos todo.
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Cerramos la conexión.
$conn->close();
?>
