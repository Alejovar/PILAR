<?php
// /src/php/ajax_check_notifications.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['rol_id'] != 2 && $_SESSION['rol_id'] != 1)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

header('Content-Type: application/json');

$mesero_id = $_SESSION['user_id'];
$notifications = [];
$notified_ids = [];

// 1. Consulta actualizada para saber si es platillo o bebida
$sql_select = "SELECT 
                    od.detail_id,
                    p.name AS product_name,
                    rt.table_number AS table_number,
                    m.modifier_name AS modifier_name,
                    mc.preparation_area -- ✨ CAMBIO AQUÍ: Traemos el área de preparación
                FROM order_details od
                JOIN products p ON od.product_id = p.product_id
                JOIN menu_categories mc ON p.category_id = mc.category_id -- ✨ CAMBIO AQUÍ: Unimos la tabla de categorías
                JOIN orders o ON od.order_id = o.order_id
                JOIN restaurant_tables rt ON o.table_id = rt.table_id
                LEFT JOIN modifiers m ON od.modifier_id = m.modifier_id
                WHERE 
                    o.server_id = ? AND
                    od.item_status = 'LISTO' AND
                    od.notified_waiter = 0";

$stmt_select = $conn->prepare($sql_select);
$stmt_select->bind_param("i", $mesero_id);
$stmt_select->execute();
$result = $stmt_select->get_result();

while ($row = $result->fetch_assoc()) {
    
    // ✨ CAMBIO AQUÍ: Decidimos qué palabra usar
    $item_type = ($row['preparation_area'] == 'BARRA') ? "La bebida" : "El platillo";

    // Construir el nombre completo del producto (con modificador si existe)
    $product_name = htmlspecialchars($row['product_name']);
    $modifier_name = htmlspecialchars($row['modifier_name'] ?? '');
    $full_product_name = !empty($modifier_name) ? $product_name . " (" . $modifier_name . ")" : $product_name;
    
    // Construir el mensaje final usando la palabra correcta
    $message = $item_type . " <strong>" . $full_product_name . "</strong> de la <strong>Mesa " . htmlspecialchars($row['table_number']) . "</strong> está lista.";
    
    $notifications[] = ['message' => $message];
    $notified_ids[] = $row['detail_id'];
}
$stmt_select->close();

// 2. Si encontramos notificaciones, las marcamos como enviadas (sin cambios aquí)
if (!empty($notified_ids)) {
    $placeholders = implode(',', array_fill(0, count($notified_ids), '?'));
    $types = str_repeat('i', count($notified_ids));

    $sql_update = "UPDATE order_details SET notified_waiter = 1 WHERE detail_id IN ($placeholders)";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param($types, ...$notified_ids);
    $stmt_update->execute();
    $stmt_update->close();
}

// 3. Devolver las notificaciones (sin cambios aquí)
echo json_encode($notifications);
?>
