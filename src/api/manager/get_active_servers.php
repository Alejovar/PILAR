<?php
// /src/api/manager/get_active_servers.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => []];

// 1. Seguridad: Solo Gerente (1) y Cajero (6) deberían ver la lista de empleados
// (Aunque sea carpeta manager, a veces caja necesita verlos para reportes, pero aquí priorizamos tu estructura)
if (!isset($_SESSION['rol_id']) || !in_array($_SESSION['rol_id'], [1, 6])) {
    http_response_code(403);
    $response['message'] = 'Acceso no autorizado.';
    echo json_encode($response);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

try {
    // 2. Obtener usuarios con rol de MESERO (rol_id = 2)
    // (Opcional) Si tienes campo 'active', añádelo al WHERE: AND active = 1
    $sql = "SELECT id, name FROM users WHERE rol_id = 2 ORDER BY name ASC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $servers = $result->fetch_all(MYSQLI_ASSOC);
        $response['success'] = true;
        $response['data'] = $servers;
    } else {
        throw new Exception($conn->error);
    }

} catch (Throwable $e) {
    http_response_code(500);
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

if (isset($conn)) $conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
