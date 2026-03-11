<?php
// /src/api/cashier/history_reports/close_shift.php
// 💡 VERSIÓN CORREGIDA FINAL
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'Error al cerrar el turno.'];
$input = json_decode(file_get_contents('php://input'), true);

// 1. Seguridad
$user_id_closing = $_SESSION['user_id'];
$user_name_closing = $_SESSION['user_name'];
if (!isset($_SESSION['rol_id']) || !in_array($_SESSION['rol_id'], [1, 6])) {
    http_response_code(403);
    $response['message'] = 'Acceso no autorizado.';
    echo json_encode($response);
    exit;
}

// 2. Obtener el conteo manual del JS
$manual_cash_total = $input['manual_cash_total'] ?? null;
if ($manual_cash_total === null || !is_numeric($manual_cash_total)) {
    http_response_code(400);
    $response['message'] = 'Debe proporcionar un conteo de efectivo válido.';
    echo json_encode($response);
    exit;
}

// Envolvemos todo en un try/catch
try {
    
    // 3. VERIFICACIÓN CRÍTICA: ¿Hay mesas sin pagar?
    $sql_check = "SELECT 1 FROM restaurant_tables LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);
    if ($stmt_check === false) throw new Exception("Error al preparar 'sql_check': " . $conn->error);
    $stmt_check->execute();
    $open_tables = $stmt_check->get_result();

    if ($open_tables->num_rows > 0) {
        $stmt_check->close();
        throw new Exception("No se puede cerrar el turno. Aún hay " . $open_tables->num_rows . " cuenta(s) abierta(s) sin cobrar.", 409);
    }
    $stmt_check->close();

    // 4. Si no hay mesas abiertas, continuamos con el cierre...
    $conn->begin_transaction();

    // 5. Encontrar el turno abierto
    $sql_shift = "SELECT * FROM cash_shifts WHERE status = 'OPEN' ORDER BY start_time DESC LIMIT 1 FOR UPDATE";
    $stmt_shift = $conn->prepare($sql_shift);
    if ($stmt_shift === false) throw new Exception("Error al preparar 'sql_shift': " . $conn->error);
    $stmt_shift->execute();
    $shift_data = $stmt_shift->get_result()->fetch_assoc();
    $stmt_shift->close();

    if (!$shift_data) {
        throw new Exception("No se encontró un turno abierto para cerrar.", 404);
    }
    
    $shift_id = $shift_data['shift_id'];
    $start_time = $shift_data['start_time'];
    $starting_cash = (float)$shift_data['starting_cash'];

    // 6. Obtener el nombre del usuario que abrió
    $sql_user = "SELECT name FROM users WHERE id = ?";
    $stmt_user = $conn->prepare($sql_user);
    if ($stmt_user === false) throw new Exception("Error al preparar 'sql_user': " . $conn->error);
    $stmt_user->bind_param("i", $shift_data['user_id_opened']);
    $stmt_user->execute();
    $user_opened_name = $stmt_user->get_result()->fetch_assoc()['name'] ?? 'Desconocido';
    $stmt_user->close();

    // 7. Recalcular TODOS los totales
    $sql_sales = "SELECT subtotal, discount_amount, tax_amount, grand_total, tip_amount_card, payment_methods 
                  FROM sales_history WHERE payment_time >= ?";
    $stmt_sales = $conn->prepare($sql_sales);
    if ($stmt_sales === false) throw new Exception("Error al preparar 'sql_sales': " . $conn->error);
    $stmt_sales->bind_param("s", $start_time);
    $stmt_sales->execute();
    $sales_result = $stmt_sales->get_result();
    
    $totals = [
        'sales_count' => 0, 'subtotal' => 0.00, 'discount' => 0.00,
        'tax' => 0.00, 'grand_total' => 0.00, 'card_tips' => 0.00
    ];
    $payments_breakdown = [];
    $total_cash_sales = 0.00;

    while ($sale = $sales_result->fetch_assoc()) {
        $totals['sales_count']++;
        $totals['subtotal'] += (float)$sale['subtotal'];
        $totals['discount'] += (float)$sale['discount_amount'];
        $totals['tax'] += (float)$sale['tax_amount'];
        $totals['grand_total'] += (float)$sale['grand_total'];
        $totals['card_tips'] += (float)$sale['tip_amount_card'];
        
        $methods = json_decode($sale['payment_methods'], true);
        if (is_array($methods)) {
            foreach ($methods as $payment) {
                $method_name = $payment['method'] ?? 'Desconocido';
                $amount = (float)($payment['amount'] ?? 0);
                if (!isset($payments_breakdown[$method_name])) $payments_breakdown[$method_name] = 0.00;
                $payments_breakdown[$method_name] += $amount;
                if ($method_name === 'Efectivo') $total_cash_sales += $amount;
            }
        }
    }
    $stmt_sales->close();

    // 8. Calcular el arqueo final
    $expected_cash_total = $starting_cash + $total_cash_sales;
    $difference = (float)$manual_cash_total - $expected_cash_total;

    // 9. 💥 ¡CERRAR EL TURNO! 💥
    $sql_close = "UPDATE cash_shifts SET 
                    end_time = CURRENT_TIMESTAMP, 
                    status = 'CLOSED', 
                    ending_cash = ? 
                  WHERE shift_id = ?";
    $stmt_close = $conn->prepare($sql_close);
    if ($stmt_close === false) throw new Exception("Error al preparar 'sql_close': " . $conn->error);
    $stmt_close->bind_param("di", $manual_cash_total, $shift_id);
    $stmt_close->execute();
    
    if ($stmt_close->affected_rows === 0) {
        throw new Exception("Fallo al actualizar el turno. No se cerró.", 500);
    }
    $stmt_close->close();

    // 10. Preparar la respuesta JSON para el ticket
    $response['success'] = true;
    $response['message'] = "Turno $shift_id cerrado exitosamente.";
    $response['shift_id'] = $shift_id;
    $response['user_opened_name'] = $user_opened_name;
    $response['user_closed_name'] = $user_name_closing;
    $response['totals'] = $totals;
    $response['payments'] = $payments_breakdown;
    $response['cash_report'] = [
        'starting_cash' => $starting_cash,
        'total_cash_sales' => $total_cash_sales,
        'expected_cash_total' => $expected_cash_total,
        'manual_cash_total' => (float)$manual_cash_total,
        'difference' => $difference
    ];

    // 11. Confirmar transacción
    $conn->commit();

} catch (Throwable $e) {
    // 💡 CAMBIO: Reemplazamos inTransaction() por una versión compatible
    // Simplemente llamamos a rollback(); si no hay transacción,
    // el @ suprimirá el warning de PHP.
    if (isset($conn)) {
        @$conn->rollback();
    }
    
    http_response_code(500); 
    $response['message'] = "Error Fatal en close_shift.php: " . $e->getMessage() . " en la línea " . $e->getLine();
}

if(isset($conn)) $conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
