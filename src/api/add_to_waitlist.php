<?php
// src/api/add_to_waitlist.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
// Le decimos al navegador que la respuesta será un JSON.
header('Content-Type: application/json');
// Conectamos a la base de datos. Si esto falla, el script se detiene.
require '../php/db_connection.php';

// Solo aceptamos solicitudes por POST, porque estamos creando un nuevo registro.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // 405 = Método no permitido
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit();
}

// Recogemos los datos del formulario, limpiando espacios con trim()
// y usando '??' para evitar errores si un campo viene vacío.
$name = trim($_POST['customer_name'] ?? '');
$people = trim($_POST['number_of_people'] ?? '');
$phone = trim($_POST['customer_phone'] ?? '');

// --- VALIDACIÓN DE DATOS DEL LADO DEL SERVIDOR ---
// ¡Importante! Nunca confíes en los datos que vienen del cliente.
// Siempre hay que validarlos aquí para asegurar que todo esté en orden.

// 1. Que el nombre no esté vacío y solo contenga letras y espacios.
if (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
    http_response_code(400); // 400 = Solicitud incorrecta
    echo json_encode(['success' => false, 'message' => 'El nombre del cliente solo puede contener letras y espacios.']);
    exit();
}

// 2. Que el número de personas sea un número válido (entre 1 y 99).
if (!preg_match('/^[0-9]{1,2}$/', $people) || (int)$people == 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El número de personas debe ser un número entre 1 y 99.']);
    exit();
}

// 3. Si pusieron teléfono, que sean puros números (máximo 10).
if (!empty($phone) && !preg_match('/^[0-9]{1,10}$/', $phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El teléfono solo puede contener números y un máximo de 10 dígitos.']);
    exit();
}
// --- FIN DE LA VALIDACIÓN ---

try {
    // Preparamos la consulta para evitar inyecciones SQL. ¡Esto es por seguridad!
    $stmt = $conn->prepare("INSERT INTO waiting_list (customer_name, number_of_people, customer_phone) VALUES (?, ?, ?)");
    // Le pasamos los valores de forma segura. "sis" significa string, integer, string.
    $stmt->bind_param("sis", $name, $people, $phone);

    // Intentamos ejecutar la consulta
    if ($stmt->execute()) {
        // Si todo sale bien, mandamos una respuesta de éxito con el ID del nuevo registro.
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        // Si execute() falla, lanzamos un error para que lo atrape el 'catch'.
        throw new Exception("Error al ejecutar la consulta.");
    }

} catch (Exception $e) {
    // Si algo sale mal con la base de datos, atrapamos el error aquí.
    http_response_code(500); // 500 = Error del servidor
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}

// Cerramos la conexión para liberar recursos. ¡Adiós!
$conn->close();
?>
