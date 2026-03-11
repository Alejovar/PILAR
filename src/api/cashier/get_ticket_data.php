<?php
// /src/api/cashier/get_ticket_data.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => null, 'message' => 'An unknown error occurred.'];

try {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol_id'], [6, 1])) {
        http_response_code(403);
        throw new Exception("Unauthorized access.");
    }
    
    if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
        http_response_code(400);
        throw new Exception("Missing or invalid order_id.");
    }
    
    $order_id = (int)$_GET['order_id'];

    // 1. Obtener datos generales de la orden (mesa, mesero, etc.)
    $sql_summary = "SELECT o.order_id, rt.table_number, u.name AS server_name
                    FROM orders o
                    JOIN restaurant_tables rt ON o.table_id = rt.table_id
                    JOIN users u ON o.server_id = u.id
                    WHERE o.order_id = ?";
    $stmt_summary = $conn->prepare($sql_summary);
    $stmt_summary->bind_param("i", $order_id);
    $stmt_summary->execute();
    $summary = $stmt_summary->get_result()->fetch_assoc();
    $stmt_summary->close();

    if (!$summary) {
        http_response_code(404);
        throw new Exception("Order not found.");
    }

    // 2. Obtener los productos de la orden (ya agrupados)
    $sql_items = "SELECT 
                    p.name as product_name, 
                    m.modifier_name,
                    SUM(od.quantity) as quantity,
                    od.price_at_order
                  FROM order_details od
                  JOIN products p ON od.product_id = p.product_id
                  LEFT JOIN modifiers m ON od.modifier_id = m.modifier_id
                  WHERE od.order_id = ? AND od.is_cancelled = FALSE
                  GROUP BY p.name, m.modifier_name, od.price_at_order
                  ORDER BY MIN(od.added_at)";
    
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();
    $items = $items_result->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

    // 3. Calcular totales
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['quantity'] * $item['price_at_order'];
    }
    $tax = $subtotal * 0.16;
    $grand_total = $subtotal + $tax; // El descuento se aplicará en el JS

    // 4. Construir la respuesta
    $response = [
        'success' => true,
        'data' => [
            'header' => [
                'restaurant_name' => 'KitchenLink', // Puedes cambiar este nombre
                'order_id' => $summary['order_id'],
                'date' => date('d/m/Y H:i:s'),
                'table_number' => $summary['table_number'],
                'server_name' => $summary['server_name']
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => $subtotal,
                'tax' => $tax,
                // 'discount' => (se pasará desde el JS)
                'grand_total' => $grand_total
            ]
        ]
    ];

} catch (Throwable $e) {
    if(http_response_code() === 200) http_response_code(500);
    $response['message'] = 'Error: ' . $e->getMessage();
} finally {
    if (isset($conn)) $conn->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
