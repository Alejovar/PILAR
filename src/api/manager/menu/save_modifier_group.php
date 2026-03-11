<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if (!isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 1) { echo json_encode(['success'=>false]); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$name = trim($data['name']);

if (empty($name)) { echo json_encode(['success'=>false, 'message'=>'Nombre requerido']); exit; }

try {
    if ($id) {
        $stmt = $conn->prepare("UPDATE modifier_groups SET group_name=? WHERE group_id=?");
        $stmt->bind_param("si", $name, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO modifier_groups (group_name) VALUES (?)");
        $stmt->bind_param("s", $name);
    }
    $stmt->execute();
    echo json_encode(['success'=>true]);
} catch (Exception $e) { echo json_encode(['success'=>false, 'message'=>$e->getMessage()]); }
?>
