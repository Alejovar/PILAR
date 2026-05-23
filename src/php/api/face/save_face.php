<?php
// /src/php/api/face/save_face.php
// Guarda o actualiza el descriptor facial de un empleado.
// Solo accesible por admins (desde el panel de empleados).
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido.']); exit();
}

$data        = json_decode(file_get_contents('php://input'), true);
$empleado_id = intval($data['empleado_id'] ?? 0);
$descriptor  = $data['descriptor'] ?? null;   // array de 128 floats de face-api.js

if (!$empleado_id) {
    echo json_encode(['ok'=>false,'msg'=>'empleado_id requerido.']); exit();
}

if ($descriptor === null || $descriptor === '') {
    // Borrar descriptor (reset facial)
    $stmt = $conn->prepare("UPDATE empleados SET face_descriptor = NULL WHERE id = ?");
    $stmt->bind_param('i', $empleado_id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['ok'=>$ok, 'msg'=>$ok ? 'Descriptor eliminado.' : 'Error.']);
    exit();
}

// Validar que es un array de números
if (!is_array($descriptor) || count($descriptor) < 64) {
    echo json_encode(['ok'=>false,'msg'=>'Descriptor facial inválido.']); exit();
}

$json = json_encode($descriptor);
$stmt = $conn->prepare("UPDATE empleados SET face_descriptor = ? WHERE id = ?");
$stmt->bind_param('si', $json, $empleado_id);
$ok  = $stmt->execute();
$stmt->close();

echo json_encode(['ok'=>$ok, 'msg'=>$ok ? 'Rostro guardado correctamente.' : 'Error al guardar.']);
