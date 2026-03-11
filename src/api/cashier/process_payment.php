<?php
// /src/api/cashier/process_payment.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf8');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$input = json_decode(file_get_contents('php://input'), true);

// Capturamos el ID del cajero desde la sesión
$current_cashier_id = $_SESSION['user_id'] ?? null; 

$conn->begin_transaction();

try {
    // 1. Validación de rol y datos de entrada
    if (!$current_cashier_id || !in_array($_SESSION['rol_id'], [6, 1])) { 
        throw new Exception("Unauthorized access.", 403);
    }
    
    $order_id = $input['order_id'] ?? null;
    $payments = $input['payments'] ?? [];
    $tip_amount_card = $input['tip_amount_card'] ?? 0;
    $discount_amount = $input['discount_amount'] ?? 0;
    $is_courtesy = $input['is_courtesy'] ?? false; 

    if (!$order_id || empty($payments)) {
        throw new Exception("Missing required payment data.", 400);
    }

    // 2. Obtener datos de la orden y la mesa.
    $sql_get_order = "SELECT o.*, rt.table_number, rt.client_count, rt.occupied_at, u.name as server_name 
                      FROM orders o
                      JOIN restaurant_tables rt ON o.table_id = rt.table_id
                      JOIN users u ON o.server_id = u.id
                      WHERE o.order_id = ?";
    $stmt_get = $conn->prepare($sql_get_order);
    $stmt_get->bind_param("i", $order_id);
    $stmt_get->execute();
    $order_data = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if (!$order_data) {
        throw new Exception("Order or table occupation record not found.", 404);
    }
    
    $rt_table_id = $order_data['table_id']; 
    
    // 3. Obtener los detalles (productos) de la orden 
    $sql_get_details = "SELECT od.*, p.name as product_name, m.modifier_name
                        FROM order_details od
                        JOIN products p ON od.product_id = p.product_id
                        LEFT JOIN modifiers m ON od.modifier_id = m.modifier_id
                        WHERE od.order_id = ?";
    $stmt_details = $conn->prepare($sql_get_details);
    $stmt_details->bind_param("i", $order_id);
    $stmt_details->execute();
    $order_details_result = $stmt_details->get_result();
    $order_details_array = $order_details_result->fetch_all(MYSQLI_ASSOC);
    $stmt_details->close();
    
    // Calcular totales
    $subtotal = 0;
    foreach ($order_details_array as $item) {
        if (!$item['is_cancelled']) {
            $subtotal += ($item['quantity'] * $item['price_at_order']);
        }
    }
    $tax_amount = $subtotal * 0.16;
    $grand_total = ($subtotal + $tax_amount - $discount_amount) + $tip_amount_card;

    // 4. ✅ INSERTAR en 'sales_history' 
    // Añadimos 'cashier_id'
    $sql_insert_sale = "INSERT INTO sales_history 
                        (original_order_id, table_number, client_count, server_name, cashier_id, time_occupied, subtotal, tax_amount, discount_amount, tip_amount_card, grand_total, is_courtesy, payment_methods) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_sale = $conn->prepare($sql_insert_sale);
    $payment_methods_json = json_encode($payments);
    
    // <<< ¡AQUÍ ESTABA EL ERROR!
    // El string de tipos original estaba mal (usaba 's' para decimales) y yo lo empeoré.
    // El string correcto es "iiisisdddddis"
    $stmt_sale->bind_param("iiisisdddddis", 
        $order_id,                      // i
        $order_data['table_number'],    // i
        $order_data['client_count'],    // i
        $order_data['server_name'],     // s  <- El nombre del mesero (string)
        $current_cashier_id,            // i  <- El ID del cajero (integer)
        $order_data['occupied_at'],     // s
        $subtotal,                      // d
        $tax_amount,                    // d
        $discount_amount,               // d
        $tip_amount_card,               // d
        $grand_total,                   // d
        $is_courtesy,                   // i
        $payment_methods_json           // s
    );
    $stmt_sale->execute();
    $new_sale_id = $conn->insert_id;
    $stmt_sale->close();

    // 5. INSERTAR en sales_history_details
    $sql_insert_details = "INSERT INTO sales_history_details (sale_id, product_name, modifier_name, quantity, price_at_order, was_cancelled) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_sale_details = $conn->prepare($sql_insert_details);
    foreach ($order_details_array as $item) {
        $stmt_sale_details->bind_param("issidi", $new_sale_id, $item['product_name'], $item['modifier_name'], $item['quantity'], $item['price_at_order'], $item['is_cancelled']);
        $stmt_sale_details->execute();
    }
    $stmt_sale_details->close();

    // 6. ELIMINAR los detalles de la orden
    $sql_delete_details = "DELETE FROM order_details WHERE order_id = ?";
    $stmt_delete_details = $conn->prepare($sql_delete_details);
    $stmt_delete_details->bind_param("i", $order_id);
    $stmt_delete_details->execute();
    $stmt_delete_details->close();

    // 7. ELIMINAR la orden principal
    $sql_delete_order = "DELETE FROM orders WHERE order_id = ?";
    $stmt_delete_order = $conn->prepare($sql_delete_order);
    $stmt_delete_order->bind_param("i", $order_id);
    $stmt_delete_order->execute();
    $stmt_delete_order->close();
    
    // 8. ELIMINAR la ocupación de restaurant_tables
    $sql_delete_table = "DELETE FROM restaurant_tables WHERE table_id = ?";
    $stmt_delete_table = $conn->prepare($sql_delete_table);
    $stmt_delete_table->bind_param("i", $rt_table_id);
    $stmt_delete_table->execute();
    $stmt_delete_table->close();

    // 9. CONFIRMAR la transacción
    $conn->commit();
    $response = ['success' => true, 'message' => 'Account closed and archived successfully.', 'new_sale_id' => $new_sale_id];

} catch (Throwable $e) {
    // 10. REVERTIR todos los cambios
    $conn->rollback();
    http_response_code($e->getCode() ?: 500);
    $response['message'] = 'Transaction failed: ' . $e->getMessage();
} finally {
    if (isset($conn)) $conn->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
