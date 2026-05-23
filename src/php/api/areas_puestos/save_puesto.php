<?php
// /src/php/api/areas_puestos/save_puesto.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido.']); exit();
}

$data    = json_decode(file_get_contents('php://input'), true);
$nombre  = trim($data['nombre']  ?? '');
$area_id = intval($data['area_id'] ?? 0);

if (!$nombre) { echo json_encode(['ok'=>false,'msg'=>'Nombre requerido.']); exit(); }

// Aseguramos columna area_id en puestos
$hasCols = $conn->query("SHOW COLUMNS FROM puestos LIKE 'area_id'");
if ($hasCols && !$hasCols->num_rows) {
    $conn->query("ALTER TABLE puestos ADD COLUMN area_id INT UNSIGNED AFTER nombre");
    $conn->query("ALTER TABLE puestos DROP INDEX nombre");  // quitar unique si existe
}

if ($area_id) {
    $stmt = $conn->prepare("INSERT INTO puestos (nombre, area_id) VALUES (?,?)");
    $stmt->bind_param('si', $nombre, $area_id);
} else {
    $stmt = $conn->prepare("INSERT INTO puestos (nombre) VALUES (?)");
    $stmt->bind_param('s', $nombre);
}

$ok  = $stmt->execute();
$err = $stmt->error;
$stmt->close();

if ($ok) {
    echo json_encode(['ok'=>true,'id'=>$conn->insert_id]);
} else {
    echo json_encode(['ok'=>false,'msg'=>str_contains($err,'Duplicate') ? 'El puesto ya existe.' : $err]);
}
