<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id'] ?? null;
$name = trim($data['name']);
$area = $data['preparation_area'] ?? 'COCINA';

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']); exit;
}

try {
    if (!empty($id)) {
        // UPDATE
        $sql = "UPDATE menu_categories SET category_name=?, preparation_area=? WHERE category_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $name, $area, $id);
    } else {
        // INSERT
        $sql = "INSERT INTO menu_categories (category_name, preparation_area) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $name, $area);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Error al guardar");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
