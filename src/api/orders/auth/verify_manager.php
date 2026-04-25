<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

header('Content-Type: application/json; charset=utf-8');

define('MANAGER_ROLE_ID', 1);

/**
 * Helper para devolver respuestas JSON consistentes.
 */
function respondJson(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

try {
    // 1) Solo aceptamos POST para este endpoint.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        respondJson([
            'success' => false,
            'message' => 'Método no permitido.'
        ], 405);
    }

    // 2) Validación defensiva de conexión compartida.
    if (!isset($conn) || !$conn || $conn->connect_error) {
        respondJson([
            'success' => false,
            'message' => 'Error de conexión con el servidor.'
        ], 500);
    }

    // 3) Parseo del body JSON.
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        respondJson([
            'success' => false,
            'message' => 'Formato JSON inválido.'
        ], 400);
    }

    // 4) Validación del dato recibido.
    $password = trim($data['password'] ?? '');

    if ($password === '') {
        respondJson([
            'success' => false,
            'message' => 'Por favor, ingrese una contraseña.'
        ]);
    }

    // 5) Obtenemos hash del gerente principal (rol_id = 1).
    $stmt = $conn->prepare('SELECT password FROM users WHERE rol_id = ? LIMIT 1');
    if ($stmt === false) {
        throw new Exception('No se pudo preparar la consulta de verificación.');
    }

    $stmt->bind_param('i', MANAGER_ROLE_ID);
    $stmt->execute();
    $result = $stmt->get_result();

    $isVerified = false;
    if ($manager = $result->fetch_assoc()) {
        $isVerified = password_verify($password, $manager['password']);
    }

    $stmt->close();

    // 6) Respuesta final del endpoint.
    respondJson([
        'success' => $isVerified,
        'message' => $isVerified ? 'Verificación exitosa.' : 'Contraseña no válida.'
    ]);
} catch (Throwable $e) {
    respondJson([
        'success' => false,
        'message' => 'Error interno al verificar credenciales.'
    ], 500);
}
?>
