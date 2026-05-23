<?php
// /src/php/api/areas_puestos/save_area.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido.']); exit();
}

$data   = json_decode(file_get_contents('php://input'), true);
$nombre = trim($data['nombre'] ?? '');
if (!$nombre) { echo json_encode(['ok'=>false,'msg'=>'Nombre requerido.']); exit(); }

// Aseguramos tabla areas existe
$conn->query("
    CREATE TABLE IF NOT EXISTS areas (
        id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL UNIQUE
    )
");

$stmt = $conn->prepare("INSERT INTO areas (nombre) VALUES (?)");
$stmt->bind_param('s', $nombre);
$ok  = $stmt->execute();
$err = $stmt->error;
$stmt->close();

if ($ok) {
    echo json_encode(['ok'=>true,'id'=>$conn->insert_id]);
} else {
    echo json_encode(['ok'=>false,'msg'=>str_contains($err,'Duplicate') ? 'El área ya existe.' : $err]);
}
