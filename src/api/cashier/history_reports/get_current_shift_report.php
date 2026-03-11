<?php
// /src/api/cashier/history_reports/get_current_shift_report.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => false,
    'message' => 'No se encontró un turno abierto.',
    'shift_id' => null,
    'start_time' => null,
    'starting_cash' => 0.00,
    'totals' => [
        'sales_count' => 0,
        'subtotal' => 0.00,
        'discount' => 0.00,
        'tax' => 0.00,
        'grand_total' => 0.00,
        'card_tips' => 0.00
    ],
    'payments' => [], // Desglose por método de pago
    'cash_report' => [
        'starting_cash' => 0.00,
        'total_cash_sales' => 0.00,
        'expected_cash_total' => 0.00
    ]
];

// 1. Seguridad: Solo personal autorizado
if (!isset($_SESSION['rol_id']) || !in_array($_SESSION['rol_id'], [1, 6])) {
    http_response_code(403);
    $response['message'] = 'Acceso no autorizado.';
    echo json_encode($response);
    exit;
}

try {
    // 2. Encontrar el turno abierto
    $sql_shift = "SELECT shift_id, start_time, starting_cash FROM cash_shifts WHERE status = 'OPEN' ORDER BY start_time DESC LIMIT 1";
    $stmt_shift = $conn->prepare($sql_shift);
    $stmt_shift->execute();
    $shift_data = $stmt_shift->get_result()->fetch_assoc();
    $stmt_shift->close();

    if (!$shift_data) {
        // No hay turno abierto, devolvemos la respuesta por defecto
        echo json_encode($response);
        exit;
    }

    $start_time = $shift_data['start_time'];
    $starting_cash = (float)$shift_data['starting_cash'];
    
    $response['shift_id'] = $shift_data['shift_id'];
    $response['start_time'] = $start_time;
    $response['starting_cash'] = $starting_cash;
    $response['cash_report']['starting_cash'] = $starting_cash;

    // 3. Obtener TODAS las ventas desde que inició el turno
    $sql_sales = "SELECT 
                    subtotal, 
                    discount_amount, 
                    tax_amount, 
                    grand_total, 
                    tip_amount_card, 
                    payment_methods 
                  FROM sales_history 
                  WHERE payment_time >= ?";
                  
    $stmt_sales = $conn->prepare($sql_sales);
    $stmt_sales->bind_param("s", $start_time);
    $stmt_sales->execute();
    $sales_result = $stmt_sales->get_result();

    $totals = $response['totals'];
    $payments_breakdown = [];
    $total_cash_sales = 0.00;

    // 4. Procesar cada venta una por una
    while ($sale = $sales_result->fetch_assoc()) {
        $totals['sales_count']++;
        $totals['subtotal'] += (float)$sale['subtotal'];
        $totals['discount'] += (float)$sale['discount_amount'];
        $totals['tax'] += (float)$sale['tax_amount'];
        $totals['grand_total'] += (float)$sale['grand_total'];
        $totals['card_tips'] += (float)$sale['tip_amount_card'];

        // 💥 Procesamiento del JSON de pagos
        $methods = json_decode($sale['payment_methods'], true);
        
        if (is_array($methods)) {
            foreach ($methods as $payment) {
                $method_name = $payment['method'] ?? 'Desconocido';
                $amount = (float)($payment['amount'] ?? 0);

                // Inicializar si no existe
                if (!isset($payments_breakdown[$method_name])) {
                    $payments_breakdown[$method_name] = 0.00;
                }
                
                $payments_breakdown[$method_name] += $amount;

                // Sumar al total de efectivo
                if ($method_name === 'Efectivo') {
                    $total_cash_sales += $amount;
                }
            }
        }
    }
    $stmt_sales->close();

    // 5. Finalizar y construir respuesta
    $response['success'] = true;
    $response['message'] = "Reporte de turno actual generado.";
    $response['totals'] = $totals;
    $response['payments'] = $payments_breakdown;
    $response['cash_report']['total_cash_sales'] = $total_cash_sales;
    $response['cash_report']['expected_cash_total'] = $starting_cash + $total_cash_sales;

} catch (Throwable $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
