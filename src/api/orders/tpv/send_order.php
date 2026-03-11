<?php
// ===================================================================
// send_order.php - VERSIÓN CON PUNTO DE DEBUG
// ===================================================================

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0); 
error_reporting(E_ALL);

$response = ['success' => false, 'message' => 'Error desconocido.'];

try {
    // 1. Verificación Inicial y Lectura de Datos
    if (!isset($_SESSION['user_id']) || ($_SESSION['rol_id'] != 2 && $_SESSION['rol_id'] != 1)) {
        http_response_code(403);
        throw new Exception('Acceso denegado: solo meseros y gerentes pueden enviar órdenes.');
    }
    $server_id = $_SESSION['user_id'];

    $rawData = trim(file_get_contents('php://input'));
    if ($rawData === '') throw new Exception('No se recibieron datos (JSON vacío).');
    $data = json_decode($rawData, true, 512, JSON_THROW_ON_ERROR);

    $table_number = intval($data['table_number'] ?? 0);
    $times = $data['times'] ?? [];
    if ($table_number <= 0 || empty($times)) throw new Exception('Datos incompletos.');

    // Conexión y Modelo
    require $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php'; 
    if (!$conn || $conn->connect_errno) throw new Exception('Error de conexión a la base de datos.');
    
    // Verificar turno de caja
    $stmt_shift = $conn->prepare("SELECT 1 FROM cash_shifts WHERE status = 'OPEN' LIMIT 1");
    $stmt_shift->execute();
    $shift_result = $stmt_shift->get_result();   // ← FIX
    if ($shift_result->num_rows === 0) {
        $stmt_shift->close();
        http_response_code(403);
        throw new Exception('ACCIÓN BLOQUEADA: El turno de caja está cerrado.');
    }
    $stmt_shift->close();
    
    require __DIR__ . '/MenuModel.php'; 
    $menuModel = new MenuModel($conn);

    $conn->begin_transaction();

    $now_timestamp = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    
    $table_id = 0;
    $order_id = 0;

    // 2. VERIFICACIÓN Y OBTENCIÓN DE MESA ACTIVA
    $stmt_check_active = $conn->prepare("SELECT table_id, assigned_server_id, pre_bill_status FROM restaurant_tables WHERE table_number = ? FOR UPDATE");
    $stmt_check_active->bind_param("i", $table_number);
    $stmt_check_active->execute();
    $active_table_res = $stmt_check_active->get_result();
    $active_table_row = $active_table_res->fetch_assoc();
    $stmt_check_active->close();
    
    if ($active_table_res->num_rows == 0) {
        $conn->rollback();
        http_response_code(410); 
        throw new Exception('MESA CERRADA: El cajero ya cerró esta cuenta. Por favor, actualiza la lista de mesas.', 410);
    }
    
    $table_id = $active_table_row['table_id'];
    if ($active_table_row['pre_bill_status'] === 'REQUESTED') {
        $conn->rollback(); 
        http_response_code(403); 
        throw new Exception('ACCIÓN BLOQUEADA: La cuenta ya fue solicitada al cajero.', 403);
    }
    
    // 3. BUSCAR O CREAR ORDEN ACTIVA
    $stmt_order = $conn->prepare("SELECT order_id FROM orders WHERE table_id=? AND status NOT IN ('PAID', 'CLOSED') LIMIT 1");
    $stmt_order->bind_param("i", $table_id);
    $stmt_order->execute();
    $res_order = $stmt_order->get_result();

    if ($row_order = $res_order->fetch_assoc()) {
        $order_id = $row_order['order_id'];
    } else {
        $stmt_create_order = $conn->prepare("INSERT INTO orders (table_id, server_id, status, order_time) VALUES (?, ?, 'PENDING', ?)");
        $stmt_create_order->bind_param("iis", $table_id, $server_id, $now_timestamp);
        $stmt_create_order->execute();
        $order_id = $conn->insert_id;
        $stmt_create_order->close();
    }
    $stmt_order->close();
    if (!$order_id) throw new Exception('No se pudo crear o recuperar la orden.');

    // --- 4. VALIDACIÓN DE STOCK ---
    $all_stock_updates = [];

    $stmt_get_prod_trans = $conn->prepare("SELECT price, category_id, stock_quantity, is_available, name FROM products WHERE product_id = ? FOR UPDATE");
    $stmt_get_mod_trans = $conn->prepare("SELECT modifier_price, stock_quantity, is_active, modifier_name FROM modifiers WHERE modifier_id = ? FOR UPDATE");

    $sql_insert_detail = "INSERT INTO order_details (order_id, product_id, quantity, price_at_order, special_notes, modifier_id, preparation_area, batch_timestamp, service_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert_detail = $conn->prepare($sql_insert_detail);

    foreach ($times as $time_block) {
        $service_time = intval($time_block['service_time'] ?? 0);
        $items = $time_block['items'] ?? [];
        if ($service_time <= 0 || empty($items)) continue; 

        foreach ($items as $item) {
            if ($item['sentTimestamp'] ?? false) continue;

            $product_id = intval($item['id'] ?? 0);
            $quantity = intval($item['quantity'] ?? 1);
            $modifier_id = !empty($item['modifier_id']) ? intval($item['modifier_id']) : null;
            $notes = trim($item['comment'] ?? '');

            // Producto principal
            $stmt_get_prod_trans->bind_param("i", $product_id);
            $stmt_get_prod_trans->execute();
            $prod_data = $stmt_get_prod_trans->get_result()->fetch_assoc();
            if (!$prod_data) throw new Exception("Producto ID {$product_id} no encontrado.");
            $price_at_order = $prod_data['price'];

            if ($prod_data['is_available'] == 0 || ($prod_data['stock_quantity'] !== null && $prod_data['stock_quantity'] <= 0)) {
                throw new Exception("El producto está AGOTADO (86).");
            }

            if ($prod_data['stock_quantity'] !== null) {
                $current_stock = intval($prod_data['stock_quantity']);
                if ($current_stock < $quantity) throw new Exception("Stock insuficiente.");
                $all_stock_updates[] = ['table' => 'products', 'id' => $product_id, 'qty' => $quantity, 'current' => $current_stock];
            }
            $preparation_area = $menuModel->getPreparationAreaByProductId($product_id);

            // Modificador
            if ($modifier_id) {
                $stmt_get_mod_trans->bind_param("i", $modifier_id);
                $stmt_get_mod_trans->execute();
                $mod_data = $stmt_get_mod_trans->get_result()->fetch_assoc();
                
                if (!$mod_data) throw new Exception("Modificador ID $modifier_id no encontrado.");

                if ($mod_data['is_active'] == 0 || ($mod_data['stock_quantity'] !== null && $mod_data['stock_quantity'] <= 0)) {
                    throw new Exception("Modificador agotado.");
                }
                
                if ($mod_data['stock_quantity'] !== null) {
                    $current_mod_stock = intval($mod_data['stock_quantity']);
                    if ($current_mod_stock < $quantity) throw new Exception("Stock insuficiente del modificador.");
                    $all_stock_updates[] = ['table' => 'modifiers', 'id' => $modifier_id, 'qty' => $quantity, 'current' => $current_mod_stock];
                }
            }

            // --- FIX PRINCIPAL ---
            if ($modifier_id === null) $modifier_id = 0;

            $stmt_insert_detail->bind_param(
                "iiidsissi",
                $order_id,
                $product_id,
                $quantity,
                $price_at_order,
                $notes,
                $modifier_id,
                $preparation_area,
                $now_timestamp,
                $service_time
            );

            if (!$stmt_insert_detail->execute()) throw new Exception('Fallo al insertar detalle: ' . $stmt_insert_detail->error);
        }
    }

    // 5. Actualización de stock
    foreach ($all_stock_updates as $update) {
        $new_stock = $update['current'] - $update['qty'];
        $is_available = ($new_stock === 0) ? 0 : 1;
        
        $table_name = $update['table'];
        $id_column = ($table_name === 'products') ? 'product_id' : 'modifier_id';
        $available_column = ($table_name === 'products') ? 'is_available' : 'is_active';

        $sql_final = "UPDATE {$table_name} SET stock_quantity=?, {$available_column}=? WHERE {$id_column}=?";
        $stmt_final_update = $conn->prepare($sql_final);
        $stmt_final_update->bind_param("iii", $new_stock, $is_available, $update['id']);
        $stmt_final_update->execute();
        $stmt_final_update->close();
    }

    // 6. Total
    $stmt_update_total = $conn->prepare("
        UPDATE orders o
        SET o.total = (
            SELECT SUM( (od.price_at_order + COALESCE(m.modifier_price, 0)) * od.quantity )
            FROM order_details od
            LEFT JOIN modifiers m ON od.modifier_id = m.modifier_id
            WHERE od.order_id = ? AND od.is_cancelled = 0
        )
        WHERE o.order_id = ?
    ");
    $stmt_update_total->bind_param("ii", $order_id, $order_id);
    $stmt_update_total->execute();
    $stmt_update_total->close();

    $conn->commit();

    $response = ['success' => true, 'message' => "Comanda enviada con éxito."];

} catch (Throwable $e) {
    if (isset($conn) && $conn->ping()) $conn->rollback();
    http_response_code(200); 
    $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}

if ($conn) $conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>
