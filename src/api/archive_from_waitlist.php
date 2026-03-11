<?php
// Le decimos al cliente que le vamos a responder con JSON.
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
// Incluimos la conexión a la base de datos.
require '../php/db_connection.php';

// Como esta acción modifica datos, nos aseguramos de que sea por POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit();
}

// Ojo, aquí leemos el cuerpo de la petición como JSON.
// Esto es común en APIs modernas en lugar de usar $_POST.
$data = json_decode(file_get_contents('php://input'), true);

// Obtenemos los datos y de paso limpiamos el ID para que sea un número entero seguro.
$id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
$status = $data['status'] ?? '';

// Validamos que el ID sea válido y que el 'status' sea uno de los permitidos.
if (!$id || !in_array($status, ['seated', 'cancelled'])) {
    http_response_code(400); // 400 = Petición incorrecta
    echo json_encode(['success' => false, 'message' => 'ID de cliente o estado inválido.']);
    exit();
}

// Iniciamos una transacción. Esto es para que las 3 operaciones de abajo
// (leer, insertar, borrar) se hagan todas juntas o no se haga ninguna. Así no dejamos datos a medias.
$conn->begin_transaction();

try {
    // 1. Primero, buscamos al cliente en la lista de espera para asegurar que existe.
    $stmt_get = $conn->prepare("SELECT * FROM waiting_list WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    $client = $result->fetch_assoc();

    // Si no lo encontramos, lanzamos un error y cancelamos todo.
    if (!$client) {
        throw new Exception("Cliente no encontrado en la lista de espera.", 404);
    }

    // 2. Ahora, copiamos sus datos a la tabla de historial con su nuevo estado.
    $stmt_insert = $conn->prepare(
        "INSERT INTO waiting_list_history (original_id, customer_name, number_of_people, customer_phone, created_at, status) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt_insert->bind_param(
        "isssss",
        $client['id'],
        $client['customer_name'],
        $client['number_of_people'],
        $client['customer_phone'],
        $client['created_at'],
        $status
    );
    $stmt_insert->execute();

    // 3. Finalmente, lo borramos de la lista de espera activa.
    $stmt_delete = $conn->prepare("DELETE FROM waiting_list WHERE id = ?");
    $stmt_delete->bind_param("i", $id);
    $stmt_delete->execute();

    // Si llegamos hasta aquí, todo salió bien. Guardamos los cambios permanentemente.
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Cliente archivado correctamente.']);

} catch (Exception $e) {
    // Si algo falló en el 'try', revertimos todos los cambios. Es como si nada hubiera pasado.
    $conn->rollback();

    // Checamos si el error fue porque no se encontró al cliente (404) o por otra cosa (500).
    $code = $e->getCode() === 404 ? 404 : 500;
    http_response_code($code);

    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Siempre es bueno cerrar la conexión al final.
$conn->close();
?>
