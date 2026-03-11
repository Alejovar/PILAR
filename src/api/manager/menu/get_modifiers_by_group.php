<?php
// /src/api/manager/menu/get_modifiers_by_group.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) { echo json_encode(['success'=>false]); exit; }

$group_id = intval($_GET['group_id'] ?? 0);

try {
    // 💡 CAMBIO: Añadimos 'stock_quantity' a la selección
    $sql = "SELECT modifier_id, modifier_name, modifier_price, is_active, stock_quantity 
            FROM modifiers 
            WHERE group_id = ? 
            ORDER BY modifier_name ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $mods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success'=>true, 'modifiers'=>$mods]);
} catch (Exception $e) { echo json_encode(['success'=>false]); }
?>
