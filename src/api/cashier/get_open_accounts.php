<?php
// /src/api/cashier/get_open_accounts.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => [], 'message' => 'An unknown error occurred.'];

try {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol_id'], [6, 1])) { // 6: Cajero, 1: Gerente
        http_response_code(403);
        throw new Exception("Unauthorized access.");
    }
    
    // La consulta SQL modificada
    $sql = "SELECT 
                o.order_id,
                rt.table_number,
                rt.pre_bill_status, -- <-- 1. AÑADIR ESTA LÍNEA
                u.name AS server_name,
                o.order_time,
                (SELECT SUM(od2.quantity * od2.price_at_order) FROM order_details od2 WHERE od2.order_id = o.order_id AND od2.is_cancelled = FALSE) AS calculated_total
            FROM orders o
            JOIN restaurant_tables rt ON o.table_id = rt.table_id
            JOIN users u ON o.server_id = u.id
            WHERE o.status = 'PENDING'
            GROUP BY o.order_id
            ORDER BY o.order_time ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $accounts = [];
    while ($row = $result->fetch_assoc()) {
        $total = $row['calculated_total'] ?? 0.00;
        $accounts[] = [
            'order_id' => $row['order_id'],
            'table_number' => $row['table_number'],
            'server_name' => $row['server_name'],
            'order_time' => $row['order_time'],
            'total_amount' => number_format($total, 2, '.', ''),
            'pre_bill_status' => $row['pre_bill_status'] // <-- 2. AÑADIR ESTA CLAVE Y VALOR
        ];
    }

    $response = [
        'success' => true,
        'data' => $accounts
    ];

} catch (Throwable $e) {
    if ($e->getCode() !== 200) http_response_code($e->getCode() ?: 500);
    $response['message'] = 'Error: ' . $e->getMessage();
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
