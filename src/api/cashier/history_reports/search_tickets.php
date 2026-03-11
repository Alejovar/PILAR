<?php
// /src/api/cashier/history_reports/search_tickets.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => [], 'message' => 'No se proporcionaron filtros válidos.'];

// 1. Seguridad: Solo personal autorizado
if (!isset($_SESSION['rol_id']) || !in_array($_SESSION['rol_id'], [1, 6])) {
    http_response_code(403);
    $response['message'] = 'Acceso no autorizado.';
    echo json_encode($response);
    exit;
}

// 2. Obtener filtros de la URL (GET)
$folio = $_GET['folio'] ?? null;
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

try {
    $params = [];
    $types = "";

    // 3. Construir la consulta SQL dinámicamente
    $sql = "SELECT 
                sh.sale_id, 
                sh.payment_time, 
                sh.table_number, 
                sh.server_name, 
                sh.grand_total, 
                u.name AS cashier_name 
            FROM sales_history sh
            LEFT JOIN users u ON sh.cashier_id = u.id"; // Unimos con 'users' para obtener el nombre del cajero

    if (!empty($folio) && is_numeric($folio)) {
        // --- Búsqueda por Folio (tiene prioridad) ---
        $sql .= " WHERE sh.sale_id = ?";
        $params[] = $folio;
        $types = "i";
        
    } else if (!empty($start_date) && !empty($end_date)) {
        // --- Búsqueda por Rango de Fechas ---
        // Aseguramos que la fecha final incluya todo el día
        $end_date_full = $end_date . ' 23:59:59';
        
        $sql .= " WHERE sh.payment_time BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date_full;
        $types = "ss";
        
    } else {
        // Si no hay filtros válidos, no ejecutamos la consulta
        echo json_encode($response);
        exit;
    }

    $sql .= " ORDER BY sh.sale_id DESC LIMIT 100"; // Limitar resultados a 100

    // 4. Ejecutar la consulta
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $response['success'] = true;
    $response['data'] = $data;
    if (empty($data)) {
        $response['message'] = "No se encontraron tickets con esos filtros.";
    } else {
        $response['message'] = "Tickets encontrados: " . count($data);
    }

} catch (Throwable $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
