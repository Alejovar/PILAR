<?php
// /api/orders/get_tables.php - API para obtener las mesas y su tiempo de ocupación

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');

// 1. VERIFICAR AUTENTICACIÓN
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Error: Sesión no válida.']);
    exit();
}

// CRÍTICO: Incluye tu archivo de conexión MySQLi
require $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php'; 

$user_id = $_SESSION['user_id']; 
$rol_id  = $_SESSION['rol_id']; // 🟢 Obtenemos el rol para decidir qué mostrar

try {
    // 2. CONSULTA SQL BASE (Igual para todos)
    $sql_base = "
        SELECT 
            rt.table_id,
            rt.table_number,
            rt.client_count,
            rt.occupied_at,
            TIMESTAMPDIFF(MINUTE, rt.occupied_at, NOW()) AS minutes_occupied,
            rt.pre_bill_status,
            u.name AS mesero_nombre
        FROM 
            restaurant_tables rt
        LEFT JOIN 
            users u ON rt.assigned_server_id = u.id
    ";

    // 3. LÓGICA CONDICIONAL
    if ($rol_id == 1) {
        // --- MODO GERENTE: Ver TODAS las mesas ---
        $sql = $sql_base . " ORDER BY rt.table_number ASC";
        $stmt = $conn->prepare($sql);
    } else {
        // --- MODO MESERO: Ver SOLO sus mesas ---
        $sql = $sql_base . " WHERE rt.assigned_server_id = ? ORDER BY rt.table_number ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
    }

    // Ejecutar
    $stmt->execute();
    $result = $stmt->get_result(); 
    $tables = $result->fetch_all(MYSQLI_ASSOC);
    
    $stmt->close();
    $conn->close();

    // 4. DEVOLVER RESPUESTA
    echo json_encode(['success' => true, 'tables' => $tables]);

} catch (\Exception $e) {
    error_log("Error fetching tables from DB: " . $e->getMessage());
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Error al consultar las mesas en la base de datos.']);
}
?>
