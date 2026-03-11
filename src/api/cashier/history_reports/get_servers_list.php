<?php
// /src/api/cashier/history_reports/get_servers_list.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => []];

// 1. Seguridad: Solo personal autorizado
if (!isset($_SESSION['rol_id']) || !in_array($_SESSION['rol_id'], [1, 6])) {
    http_response_code(403);
    $response['message'] = 'Acceso no autorizado.';
    echo json_encode($response);
    exit;
}

try {
    // 2. Consulta para obtener meseros (rol_id = 2)
    // Asumimos que "mesero" es el rol_id 2, basado en tu script de BD
    // También filtramos por `esta_activo = 1` (Baja Lógica que discutimos)
    // Si aún no implementas la baja lógica, puedes quitar "AND esta_activo = 1"
    
    // 💡 NOTA: Añade esta columna a tu tabla 'users' cuando puedas:
    // ALTER TABLE users ADD esta_activo BOOLEAN NOT NULL DEFAULT TRUE;

    $sql = "SELECT id, name FROM users WHERE rol_id = 2 ORDER BY name ASC";
    
    // Si YA tienes la columna 'esta_activo':
    // $sql = "SELECT id, name FROM users WHERE rol_id = 2 AND esta_activo = 1 ORDER BY name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $servers = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $response['success'] = true;
    $response['data'] = $servers;

} catch (Throwable $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
