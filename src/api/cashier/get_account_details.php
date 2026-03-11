<?php
// /src/api/cashier/get_account_details.php (VERSIÓN CORREGIDA)

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => null, 'message' => 'An unknown error occurred.'];

try {
    // 1. Validación de rol y de entrada
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol_id'], [6, 1])) {
        http_response_code(403);
        throw new Exception("Unauthorized access.");
    }
    
    if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
        http_response_code(400);
        throw new Exception("Missing or invalid order_id.");
    }
    
    $order_id = (int)$_GET['order_id'];

    // 2. ✨ CORRECCIÓN: Primero, buscar y validar el resumen de la orden
    $sql_summary = "SELECT o.order_id, rt.table_number, u.name AS server_name, o.order_time
                    FROM orders o
                    JOIN restaurant_tables rt ON o.table_id = rt.table_id
                    JOIN users u ON o.server_id = u.id
                    WHERE o.order_id = ? AND o.status = 'PENDING'";
    
    $stmt_summary = $conn->prepare($sql_summary);
    $stmt_summary->bind_param("i", $order_id);
    $stmt_summary->execute();
    $order_summary = $stmt_summary->get_result()->fetch_assoc();
    $stmt_summary->close();

    // Si no se encuentra un resumen de orden válido, la orden no existe o ya no está activa.
    if (!$order_summary) {
        http_response_code(404); // Not Found
        throw new Exception("Order not found or is no longer active.");
    }

    // 3. Si la orden es válida, AHORA sí buscamos sus productos
    $sql_items = "SELECT 
                    od.detail_id, p.name AS product_name, od.quantity, od.price_at_order,
                    od.special_notes, od.is_cancelled AS was_cancelled, -- Se renombra para evitar conflictos en JS
                    m.modifier_name, mc.preparation_area
                FROM order_details od
                JOIN products p ON od.product_id = p.product_id
                JOIN menu_categories mc ON p.category_id = mc.category_id
                LEFT JOIN modifiers m ON od.modifier_id = m.modifier_id
                WHERE od.order_id = ? ORDER BY od.added_at ASC";
    
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $order_items = $result_items->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

    // 4. Calcular los totales
    $subtotal = 0;
    foreach ($order_items as $item) {
        if (!$item['was_cancelled']) { // Usamos el nuevo nombre 'was_cancelled'
            $subtotal += ($item['quantity'] * $item['price_at_order']);
        }
    }
    $tax_rate = 0.16; // 16% IVA
    $tax_amount = $subtotal * $tax_rate;
    $grand_total = $subtotal + $tax_amount;

    // 5. Construir la respuesta final
    $response = [
        'success' => true,
        'data' => [
            'order_id' => $order_summary['order_id'],
            'table_number' => $order_summary['table_number'],
            'server_name' => $order_summary['server_name'],
            'order_time' => $order_summary['order_time'],
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'tax_amount' => number_format($tax_amount, 2, '.', ''),
            'grand_total' => number_format($grand_total, 2, '.', ''),
            'items' => $order_items
        ]
    ];

} catch (Throwable $e) {
    // Si el código de error no fue establecido, usamos 500 por defecto
    if (http_response_code() === 200) {
        http_response_code(500);
    }
    $response['message'] = 'Error: ' . $e->getMessage();
} finally {
    if (isset($conn)) $conn->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
