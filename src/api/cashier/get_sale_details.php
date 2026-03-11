<?php
// /src/api/cashier/get_sale_details.php

// 💥 CRÍTICO: Esta línea debe asegurar que $conn esté disponible.
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'data' => null, 'message' => 'An unknown error occurred.'];

// 🚨 Verificación de Conexión: Si check_session_api.php no creó $conn, el script terminará aquí con un JSON de error válido.
if (!isset($conn) || $conn->connect_error) {
    $response['message'] = "Error Fatal: La conexión a la base de datos no está disponible. Verifique db_connection.php.";
    http_response_code(500);
    echo json_encode($response);
    exit;
}

try {
    // 1. Validación de seguridad y entrada
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol_id'], [6, 1])) {
        http_response_code(403);
        throw new Exception("Unauthorized access.");
    }
    
    if (!isset($_GET['sale_id']) || !is_numeric($_GET['sale_id'])) {
        http_response_code(400);
        throw new Exception("Missing or invalid sale_id.");
    }
    
    $sale_id = (int)$_GET['sale_id'];

    // 2. Obtener datos generales de la venta (DE sales_history, incluyendo el JSON de pagos)
    // <<< CAMBIO 1: Añadimos LEFT JOIN y 'u.name AS cashier_name'
    $sql_sale = "SELECT 
                    sh.original_order_id, sh.table_number, sh.server_name, sh.payment_time,
                    sh.subtotal, sh.discount_amount, sh.tax_amount, sh.tip_amount_card, sh.grand_total,
                    sh.payment_methods,
                    u.name AS cashier_name 
                FROM sales_history sh
                LEFT JOIN users u ON sh.cashier_id = u.id
                WHERE sh.sale_id = ?";
    
    $stmt_sale = $conn->prepare($sql_sale);
    $stmt_sale->bind_param("i", $sale_id);
    $stmt_sale->execute();
    $sale_data = $stmt_sale->get_result()->fetch_assoc();
    $stmt_sale->close();

    if (!$sale_data) {
        http_response_code(404);
        throw new Exception("Sale record not found (ID: {$sale_id}).");
    }
    
    $order_id = $sale_data['original_order_id'];

    // 3. Obtener los productos de la venta (DE sales_history_details)
    $sql_items = "SELECT 
                    shd.product_name, 
                    shd.modifier_name,
                    shd.quantity,
                    shd.price_at_order
                  FROM sales_history_details shd
                  WHERE shd.sale_id = ? AND shd.was_cancelled = FALSE
                  ORDER BY shd.sale_detail_id"; 
    
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("i", $sale_id);
    $stmt_items->execute();
    $items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

    // 4. Procesar el campo JSON de pagos
    $payments = json_decode($sale_data['payment_methods'], true); 
    if (json_last_error() !== JSON_ERROR_NONE) {
        // En caso de que el JSON en la DB esté vacío o mal (aunque es improbable)
        $payments = []; 
    }


    // 5. Construir la respuesta final (JSON válido)
    $response = [
        'success' => true,
        'data' => [
            'header' => [
                'restaurant_name' => 'KitchenLink',
                'sale_id' => $sale_id,
                'order_id' => $order_id,
                'date' => date('d/m/Y H:i:s', strtotime($sale_data['payment_time'])),
                'table_number' => $sale_data['table_number'],
                'server_name' => $sale_data['server_name'],
                'cashier_name' => $sale_data['cashier_name'] // <<< CAMBIO 2: Añadido el nombre del cajero
            ],
            'items' => $items,
            'totals' => [
                'subtotal_items' => $sale_data['subtotal'],
                'discount' => $sale_data['discount_amount'],
                'tax' => $sale_data['tax_amount'],
                'grand_total_paid' => $sale_data['grand_total'],
                'tip_card' => $sale_data['tip_amount_card'],
            ],
            'payments' => $payments
        ]
    ];

} catch (Throwable $e) {
    // Si hay un error, capturamos el mensaje y lo devolvemos como JSON
    error_log("Error en get_sale_details.php: " . $e->getMessage());
    if(http_response_code() === 200) http_response_code(500);
    $response['message'] = 'Error de servidor: ' . $e->getMessage();
    
} finally {
    // 💥 CRÍTICO: Aseguramos el cierre de la conexión después de la ejecución
    if (isset($conn) && $conn->ping()) $conn->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
