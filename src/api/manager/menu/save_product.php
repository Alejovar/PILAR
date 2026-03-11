<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id'] ?? null;
$cat_id = intval($data['category_id']);
$name = trim($data['name']);
$price = floatval($data['price']);
$mod_group = !empty($data['modifier_group_id']) ? intval($data['modifier_group_id']) : null;

// Lógica de Stock (85): Convertir string vacío a NULL
$stock_val = $data['stock_quantity']; 
$stock_quantity = ($stock_val === '' || $stock_val === null) ? null : intval($stock_val);

if (empty($name) || $price < 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']); exit;
}

try {
    if (!empty($id)) {
        // UPDATE
        $sql = "UPDATE products SET name=?, price=?, modifier_group_id=?, stock_quantity=? WHERE product_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sddii", $name, $price, $mod_group, $stock_quantity, $id);
    } else {
        // INSERT (Por defecto disponible = 1)
        $sql = "INSERT INTO products (name, price, category_id, modifier_group_id, stock_quantity, is_available) VALUES (?, ?, ?, ?, ?, 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sddii", $name, $price, $cat_id, $mod_group, $stock_quantity);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Error al guardar: " . $conn->error);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
