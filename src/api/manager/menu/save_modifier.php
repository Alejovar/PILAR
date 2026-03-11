<?php
// /src/api/manager/menu/save_modifier.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) { echo json_encode(['success'=>false]); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$group_id = intval($data['group_id']);
$name = trim($data['name']);
$price = floatval($data['price']);

// Lógica de Stock (85): Convertir string vacío a NULL
$stock_val = $data['stock_quantity']; 
$stock_quantity = ($stock_val === '' || $stock_val === null) ? null : intval($stock_val);

if (empty($name)) { echo json_encode(['success'=>false, 'message'=>'Nombre de opción requerido']); exit; }

try {
    if ($id) {
        // UPDATE: Añadimos la columna stock_quantity
        $stmt = $conn->prepare("UPDATE modifiers SET modifier_name=?, modifier_price=?, stock_quantity=? WHERE modifier_id=?");
        
        // El bind_param debe usar 'dii' para DECIMAL, INT, INT
        $stmt->bind_param("sdii", $name, $price, $stock_quantity, $id);
    } else {
        // INSERT: Añadimos la columna stock_quantity
        $stmt = $conn->prepare("INSERT INTO modifiers (group_id, modifier_name, modifier_price, stock_quantity, is_active) VALUES (?, ?, ?, ?, 1)");
        // 'isdi' para INT(group_id), STRING(name), DECIMAL(price), INT(stock)
        $stmt->bind_param("isdi", $group_id, $name, $price, $stock_quantity);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success'=>true]);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) { echo json_encode(['success'=>false, 'message'=>$e->getMessage()]); }
?>
