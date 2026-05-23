<?php
// /src/php/api/plantas/save_planta.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido.']); exit();
}

$data     = json_decode(file_get_contents('php://input'), true);
$id       = intval($data['id']       ?? 0);
$nombre   = trim($data['nombre']     ?? '');
$codigo   = strtoupper(trim($data['codigo'] ?? ''));
$ubicacion= trim($data['ubicacion']  ?? '');
$activa   = isset($data['activa']) ? (bool)$data['activa'] : true;

if (!$nombre || !$codigo) {
    echo json_encode(['ok'=>false,'msg'=>'Nombre y código son requeridos.']); exit();
}

// Verificar si el campo ubicacion existe
$hasUbicacion = false;
$res = $conn->query("SHOW COLUMNS FROM plantas LIKE 'ubicacion'");
if ($res && $res->num_rows) $hasUbicacion = true;

if ($id) {
    // UPDATE
    if ($hasUbicacion) {
        $stmt = $conn->prepare("UPDATE plantas SET nombre=?, codigo=?, ubicacion=?, activa=? WHERE id=?");
        $stmt->bind_param('sssii', $nombre, $codigo, $ubicacion, $activa, $id);
    } else {
        $stmt = $conn->prepare("UPDATE plantas SET nombre=?, codigo=?, activa=? WHERE id=?");
        $stmt->bind_param('ssii', $nombre, $codigo, $activa, $id);
    }
} else {
    // INSERT
    if ($hasUbicacion) {
        $stmt = $conn->prepare("INSERT INTO plantas (nombre, codigo, ubicacion, activa) VALUES (?,?,?,?)");
        $stmt->bind_param('sssi', $nombre, $codigo, $ubicacion, $activa);
    } else {
        $stmt = $conn->prepare("INSERT INTO plantas (nombre, codigo, activa) VALUES (?,?,?)");
        $stmt->bind_param('ssi', $nombre, $codigo, $activa);
    }
}

$ok = $stmt->execute();
$err= $stmt->error;
$stmt->close();

if ($ok) {
    echo json_encode(['ok'=>true]);
} else {
    // Código duplicado
    if (str_contains($err, 'Duplicate')) {
        echo json_encode(['ok'=>false,'msg'=>"El código '{$codigo}' ya existe."]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'Error al guardar: '.$err]);
    }
}
