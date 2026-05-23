<?php
// /src/php/api/empleados/save_empleado.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido.']); exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$id               = intval($data['id'] ?? 0);
$numero_empleado  = trim($data['numero_empleado']  ?? '');
$nombre           = trim($data['nombre']            ?? '');
$apellido_pat     = trim($data['apellido_paterno']  ?? '');
$apellido_mat     = trim($data['apellido_materno']  ?? '');
$email            = trim($data['email']             ?? '');
$rfc              = strtoupper(trim($data['rfc']    ?? ''));
$curp             = strtoupper(trim($data['curp']   ?? ''));
$puesto_id        = intval($data['puesto_id']       ?? 0);
$planta_id        = intval($data['planta_id']       ?? 0);
$activo           = isset($data['activo']) ? (int)(bool)$data['activo'] : 1;

if (!$numero_empleado || !$nombre || !$apellido_pat || !$puesto_id || !$planta_id) {
    echo json_encode(['ok'=>false,'msg'=>'Campos obligatorios: NSS, nombre, apellido paterno, puesto, planta.']);
    exit();
}

// Agregar columnas extra si no existen
$conn->query("ALTER TABLE empleados ADD COLUMN IF NOT EXISTS rfc  VARCHAR(15) AFTER email");
$conn->query("ALTER TABLE empleados ADD COLUMN IF NOT EXISTS curp VARCHAR(20) AFTER rfc");

if ($id) {
    $stmt = $conn->prepare("
        UPDATE empleados
        SET    numero_empleado=?, nombre=?, apellido_paterno=?, apellido_materno=?,
               email=?, rfc=?, curp=?, puesto_id=?, planta_id=?, activo=?
        WHERE  id=?
    ");
    $stmt->bind_param('sssssssiiis',
        $numero_empleado, $nombre, $apellido_pat, $apellido_mat,
        $email, $rfc, $curp, $puesto_id, $planta_id, $activo, $id
    );
} else {
    $stmt = $conn->prepare("
        INSERT INTO empleados
            (numero_empleado, nombre, apellido_paterno, apellido_materno,
             email, rfc, curp, puesto_id, planta_id, activo)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param('sssssssiis',
        $numero_empleado, $nombre, $apellido_pat, $apellido_mat,
        $email, $rfc, $curp, $puesto_id, $planta_id, $activo
    );
}

$ok  = $stmt->execute();
$err = $stmt->error;
$stmt->close();

if ($ok) {
    echo json_encode(['ok'=>true]);
} else {
    if (str_contains($err, 'Duplicate')) {
        echo json_encode(['ok'=>false,'msg'=>'El NSS ya está registrado.']);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'Error: '.$err]);
    }
}
