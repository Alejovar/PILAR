<?php
// /src/api/cashier/history_reports/get_server_report.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'Error al generar reporte.'];

// 1. Seguridad
if (!isset($_SESSION['rol_id']) || !in_array($_SESSION['rol_id'], [1, 6])) {
    http_response_code(403);
    $response['message'] = 'Acceso no autorizado.';
    echo json_encode($response);
    exit;
}

// 2. Obtener IDs
$server_id = $_GET['server_id'] ?? null;
// --- 👇 NUEVO: Obtener el porcentaje de deducción ---
$deduction_rate = (float)($_GET['deduction_rate'] ?? 0);

if (empty($server_id) || !is_numeric($server_id)) {
    http_response_code(400);
    $response['message'] = 'Debe seleccionar un mesero válido.';
    echo json_encode($response);
    exit;
}

try {
    // 3. Encontrar el turno abierto
    $sql_shift = "SELECT shift_id, start_time FROM cash_shifts WHERE status = 'OPEN' ORDER BY start_time DESC LIMIT 1";
    $stmt_shift = $conn->prepare($sql_shift);
    $stmt_shift->execute();
    $shift_data = $stmt_shift->get_result()->fetch_assoc();
    $stmt_shift->close();

    if (!$shift_data) {
        throw new Exception("No se encontró un turno abierto.", 404);
    }
    
    $shift_id = $shift_data['shift_id'];
    $start_time = $shift_data['start_time'];

    // 4. Obtener el nombre del mesero
    $sql_user = "SELECT name FROM users WHERE id = ?";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bind_param("i", $server_id);
    $stmt_user->execute();
    $server_name = $stmt_user->get_result()->fetch_assoc()['name'] ?? 'Mesero Desconocido';
    $stmt_user->close();

    // 5. Calcular los totales para ESE mesero y ESE turno
    $sql_sales = "SELECT 
                    COUNT(sale_id) as sales_count,
                    SUM(subtotal) as subtotal,
                    SUM(discount_amount) as discount,
                    SUM(grand_total) as grand_total,
                    SUM(tip_amount_card) as card_tips
                  FROM sales_history 
                  WHERE 
                    payment_time >= ? 
                    AND server_name = ?";
    
    $stmt_sales = $conn->prepare($sql_sales);
    $stmt_sales->bind_param("ss", $start_time, $server_name);
    $stmt_sales->execute();
    $totals = $stmt_sales->get_result()->fetch_assoc();
    $stmt_sales->close();
    
    // --- 👇 NUEVO: Calcular la deducción y el pago final ---
    $grand_total = $totals['grand_total'] ?? 0.00;
    $card_tips = $totals['card_tips'] ?? 0.00;
    
    // El "punto" o comisión sobre la venta
    $deduction_amount = $grand_total * $deduction_rate; 
    
    // El pago final es la propina MENOS la comisión
    $final_payout = $card_tips - $deduction_amount;

    // 6. Preparar la respuesta
    $response['success'] = true;
    $response['message'] = 'Reporte de mesero generado.';
    $response['shift_id'] = $shift_id;
    $response['server_name'] = $server_name;
    $response['sales_count'] = $totals['sales_count'] ?? 0;
    $response['subtotal'] = $totals['subtotal'] ?? 0.00;
    $response['discount'] = $totals['discount'] ?? 0.00;
    $response['grand_total'] = $grand_total;
    $response['card_tips'] = $card_tips;

    // --- 👇 NUEVO: Añadir los nuevos cálculos al JSON de respuesta ---
    $response['deduction_rate'] = $deduction_rate;       // Ej: 0.10
    $response['deduction_amount'] = $deduction_amount;   // Ej: $100.00
    $response['final_payout'] = $final_payout;         // Ej: $80 (propina) - $100 (deducción) = -$20.00

} catch (Throwable $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

if(isset($conn)) $conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
