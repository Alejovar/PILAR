<?php
// =====================================================
// VERIFY_MANAGER.PHP - Versión FINAL y ESTABLE
// =====================================================

// 1. Incluimos el archivo de sesión/conexión
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
// Limpieza de buffer y headers
ob_clean();
header('Content-Type: application/json; charset=utf-8');
// Solo usa este header si es estrictamente necesario y estás en testing
// header("Access-Control-Allow-Origin: *"); 

// NOTA: session_start() y ini_set() ya están en check_session_api.php

define('MANAGER_ROLE_ID', 1);

// Estructura base de respuesta
$response = [
    'success' => false,
    'message' => 'Contraseña no válida.'
];

// =====================================================
// 1. Obtener la contraseña enviada por JS
// =====================================================
$data = json_decode(file_get_contents('php://input'), true);
$password = $data['password'] ?? '';

if (empty($password)) {
    $response['message'] = 'Por favor, ingrese una contraseña.';
    echo json_encode($response);
    exit();
}

// =====================================================
// 2. Verificación de Conexión (Usamos la conexión global $conn)
// =====================================================
try {
    if (!isset($conn) || !$conn || $conn->connect_errno) {
        throw new Exception("Error al conectar con la base de datos.");
    }
} catch (Throwable $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'Error de conexión con el servidor.',
        'error_details' => $e->getMessage()
    ];
    echo json_encode($response);
    exit();
}

// =====================================================
// 3. Verificación de la contraseña
// =====================================================
try {
    // Usamos LIMIT 1 porque solo necesitamos la contraseña del gerente (rol_id=1)
    $sql = "SELECT password FROM users WHERE rol_id = ? LIMIT 1"; 
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta SQL: " . $conn->error);
    }

    $roleId = MANAGER_ROLE_ID;
    $stmt->bind_param("i", $roleId);

    $stmt->execute();
    $result = $stmt->get_result();

    $is_verified = false;

    // Solo necesitamos verificar la primera (y única) contraseña del gerente.
    if ($manager = $result->fetch_assoc()) {
        if (password_verify($password, $manager['password'])) {
            $is_verified = true;
        }
    }

    if ($is_verified) {
        $response['success'] = true;
        $response['message'] = 'Verificación exitosa.';
    } else {
        $response['message'] = 'Contraseña no válida.';
    }

    $stmt->close();

} catch (Throwable $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'Error en el servidor durante la ejecución de la consulta.',
        'error_details' => $e->getMessage()
    ];
    echo json_encode($response);
    exit();
}

// =====================================================
// 4. Responder
// =====================================================
// ⚠️ NO CERRAMOS LA CONEXIÓN. Ya eliminamos el bloque de cierre antes.
echo json_encode($response);
exit();
?>
