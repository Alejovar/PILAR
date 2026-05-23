<?php
// /src/php/api/empleados/save_empleado.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

function empleado_save_log(string $message): void {
    error_log('[save_empleado] ' . $message);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    empleado_save_log('Rejected request: method=' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido.']); exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    empleado_save_log('Invalid JSON body received');
    echo json_encode(['ok'=>false,'msg'=>'Petición inválida.']);
    exit();
}

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

empleado_save_log('Incoming save request: id=' . $id . ', numero_empleado=' . $numero_empleado . ', puesto_id=' . $puesto_id . ', planta_id=' . $planta_id . ', activo=' . $activo);

if (!$numero_empleado || !$nombre || !$apellido_pat || !$puesto_id || !$planta_id) {
    empleado_save_log('Rejected request: missing required fields');
    echo json_encode(['ok'=>false,'msg'=>'Campos obligatorios: NSS, nombre, apellido paterno, puesto, planta.']);
    exit();
}

// Agregar columnas extra si no existen
$rfcColumn = $conn->query("SHOW COLUMNS FROM empleados LIKE 'rfc'");
if (!$rfcColumn || $rfcColumn->num_rows === 0) {
    empleado_save_log('Adding missing column empleados.rfc');
    if (!$conn->query("ALTER TABLE empleados ADD COLUMN rfc VARCHAR(15) AFTER email")) {
        empleado_save_log('Failed adding rfc column: ' . $conn->error);
        echo json_encode(['ok'=>false,'msg'=>'No se pudo preparar la tabla de empleados.']);
        exit();
    }
}

$curpColumn = $conn->query("SHOW COLUMNS FROM empleados LIKE 'curp'");
if (!$curpColumn || $curpColumn->num_rows === 0) {
    empleado_save_log('Adding missing column empleados.curp');
    if (!$conn->query("ALTER TABLE empleados ADD COLUMN curp VARCHAR(20) AFTER rfc")) {
        empleado_save_log('Failed adding curp column: ' . $conn->error);
        echo json_encode(['ok'=>false,'msg'=>'No se pudo preparar la tabla de empleados.']);
        exit();
    }
}

if ($id) {
    $stmt = $conn->prepare("
        UPDATE empleados
        SET    numero_empleado=?, nombre=?, apellido_paterno=?, apellido_materno=?,
               email=?, rfc=?, curp=?, puesto_id=?, planta_id=?, activo=?
        WHERE  id=?
    ");
    if (!$stmt) {
        empleado_save_log('Prepare failed (update): ' . $conn->error);
        echo json_encode(['ok'=>false,'msg'=>'No se pudo preparar la actualización.']);
        exit();
    }
    $stmt->bind_param('sssssssiiii',
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
    if (!$stmt) {
        empleado_save_log('Prepare failed (insert): ' . $conn->error);
        echo json_encode(['ok'=>false,'msg'=>'No se pudo preparar el guardado.']);
        exit();
    }
    $stmt->bind_param('sssssssiii',
        $numero_empleado, $nombre, $apellido_pat, $apellido_mat,
        $email, $rfc, $curp, $puesto_id, $planta_id, $activo
    );
}

$ok  = $stmt->execute();
$err = $stmt->error;
$stmt->close();

if (!$ok) {
    empleado_save_log('Execution failed: ' . $err);
}

if ($ok) {
    empleado_save_log('Employee saved successfully');
    echo json_encode(['ok'=>true]);
} else {
    if (str_contains($err, 'Duplicate')) {
        echo json_encode(['ok'=>false,'msg'=>'El NSS ya está registrado.']);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'Error: '.$err]);
    }
}
