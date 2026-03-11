<?php
// /src/php/reports_api.php - API de Reportes Gerenciales
// VERSIÓN FINAL Y SEGURA.

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'Error desconocido.'];

// 1. Seguridad: Solo Gerente (rol_id = 1)
if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo gerentes.'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // 2. Conexión y Charset
    require $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';
    if (!$conn) throw new Exception('Error de conexión a la base de datos.');
    $conn->set_charset("utf8mb4");

    // 3. Manejo de JSON de Entrada
    $rawData = trim(file_get_contents('php://input'));
    
    // CORRECCIÓN: Prevenir "syntax error" si el cuerpo está vacío
    if (empty($rawData)) {
        $data = [];
    } else {
        $data = json_decode($rawData, true, 512, JSON_THROW_ON_ERROR);
    }
    
    $action = $data['action'] ?? '';
    
    // Extracción común de filtros
    $start_date = $data['start_date'] ?? null;
    $end_date = $data['end_date'] ?? null;
    $server_id = $data['server_id'] ?? null;
    
    // Validar fechas
    $date_required_actions = ['get_product_mix', 'get_service_metrics', 'get_table_rotation', 'get_cancellation_report', 'get_reservation_metrics'];
    
    if (in_array($action, $date_required_actions) && (!$start_date || !$end_date)) {
        throw new Exception('Fechas requeridas para el reporte.');
    }

    // 4. Lógica de Reportes
    switch ($action) {
        
        case 'get_product_mix':
            $sql = "
                SELECT
                    shd.product_name,
                    SUM(shd.quantity) AS total_quantity,
                    SUM(shd.quantity * shd.price_at_order) AS total_bruto
                FROM sales_history_details shd
                JOIN sales_history sh ON shd.sale_id = sh.sale_id
                WHERE shd.was_cancelled = 0 
                    AND sh.payment_time >= ? 
                    AND sh.payment_time < DATE_ADD(?, INTERVAL 1 DAY)
                GROUP BY shd.product_name
                ORDER BY total_quantity DESC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $response = ['success' => true, 'data' => $report_data];
            break;

        case 'get_service_metrics':
             $sql = "
                SELECT 
                    sh.server_name,
                    COALESCE(SUM(sh.client_count), 0) AS served_people
                FROM sales_history sh
                LEFT JOIN users u ON sh.server_name = u.name 
                WHERE
                    sh.payment_time >= ? 
                    AND sh.payment_time < DATE_ADD(?, INTERVAL 1 DAY)
            ";

            $types = "ss";
            $params = [$start_date, $end_date];

            if ($server_id) {
                $sql .= " AND u.id = ?";
                $types .= "i";
                $params[] = $server_id;
            }

            $sql .= " GROUP BY sh.server_name ORDER BY served_people DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params); 
            $stmt->execute();
            $metrics_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            $total_served = array_sum(array_column($metrics_data, 'served_people'));

            $response = [
                'success' => true, 
                'data' => [
                    'metrics' => $metrics_data, 
                    'total_served' => (int)$total_served
                ]
            ];
            break;
            
        case 'get_reservation_metrics':
            $sql = "
                SELECT 
                    COUNT(sh.sale_id) as total_closed_tables,
                    COALESCE(SUM(sh.client_count), 0) as total_people
                FROM sales_history sh
                WHERE sh.payment_time >= ? 
                    AND sh.payment_time < DATE_ADD(?, INTERVAL 1 DAY)
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $metrics = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $total_closed_tables = (int)($metrics['total_closed_tables'] ?? 0);
            $total_people = (int)($metrics['total_people'] ?? 0);
            
            $avg = ($total_closed_tables > 0) ? round($total_people / $total_closed_tables, 1) : 0;
            
            $response = ['success' => true, 'data' => [
                'total_closed_tables' => $total_closed_tables,
                'total_people' => $total_people,
                'average_per_table' => $avg
            ]];
            break;

        case 'get_table_rotation':
             $sql = "
                SELECT 
                    AVG(TIMESTAMPDIFF(MINUTE, time_occupied, payment_time)) AS avg_minutes_occupied,
                    COUNT(sale_id) AS total_tables_closed
                FROM sales_history
                WHERE payment_time >= ? 
                    AND payment_time < DATE_ADD(?, INTERVAL 1 DAY)
             ";
             $stmt = $conn->prepare($sql);
             $stmt->bind_param("ss", $start_date, $end_date);
             $stmt->execute();
             $res = $stmt->get_result()->fetch_assoc();
             $stmt->close();
             
             $response = [
                 'success' => true,
                 'data' => [
                     'avg_minutes_occupied' => round($res['avg_minutes_occupied'] ?? 0),
                     'total_tables_closed' => (int)($res['total_tables_closed'] ?? 0)
                 ]
             ];
             break;
             
        case 'get_cancellation_report':
            $sql = "
                SELECT 
                    shd.product_name,
                    'No especificado' as cancellation_reason,
                    COUNT(shd.sale_detail_id) AS total_canceled_qty,
                    SUM(shd.price_at_order * shd.quantity) AS lost_revenue
                FROM sales_history_details shd
                JOIN sales_history sh ON shd.sale_id = sh.sale_id
                WHERE shd.was_cancelled = 1
                    AND sh.payment_time >= ? 
                    AND sh.payment_time < DATE_ADD(?, INTERVAL 1 DAY)
                GROUP BY shd.product_name
                ORDER BY total_canceled_qty DESC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            $response = ['success' => true, 'data' => $report_data];
            break;

        case 'get_servers':
            $sql = "SELECT id as user_id, name as user_name FROM users WHERE rol_id = 2 AND status = 'ACTIVO' ORDER BY name";
            $result = $conn->query($sql);
            $response = ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)];
            break;
            
        default:
            http_response_code(400);
            $response['message'] = 'Acción de reporte no válida.';
            break;
    }

} catch (Throwable $e) {
    // 5. Manejo de Errores Global
    http_response_code(200);
    $response = ['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()];
} finally {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
