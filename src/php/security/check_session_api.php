<?php
// File: /security/check_session_api.php - VERSIÓN CORREGIDA Y SEGURA

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Si no hay datos de sesión en el cliente, es inválida.
if (!isset($_SESSION['user_id'], $_SESSION['session_token'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'invalid', 'reason' => 'No client session']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

// 2. Si la conexión a la DB falla, es un error del servidor.
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'reason' => 'Database connection failed']);
    exit;
}

try {
    // 3. Obtenemos el token actual de la base de datos (solo lectura).
    $stmt = $conn->prepare("SELECT session_token FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_row = $result->fetch_assoc();
    $stmt->close();

    // 4. Comparamos el token de la sesión del cliente con el de la DB.
    // ¡IMPORTANTE! HEMOS QUITADO TODA LA LÓGICA DE BORRADO.
    if (!$user_row || $user_row['session_token'] !== $_SESSION['session_token']) {
        // La sesión no es válida. Simplemente informamos con un 401.
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'invalid', 'reason' => 'Token mismatch']);
    } else {
        // La sesión es válida. Informamos con un 200 OK.
        http_response_code(200); // OK
        echo json_encode(['status' => 'active']);
    }

} catch (Exception $e) {
    // Capturamos cualquier otro error de la base de datos.
    http_response_code(500);
    echo json_encode(['status' => 'error', 'reason' => 'An exception occurred']);
}

exit;
?>
