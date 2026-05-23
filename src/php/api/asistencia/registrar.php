<?php
// /src/php/api/asistencia/registrar.php
// Registra una checada (entrada / salida_comida / regreso_comida / salida).
// Endpoint público — valida secuencia antes de guardar.
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$empleado_id = intval($input['empleado_id'] ?? 0);
$planta_id   = intval($input['planta_id']   ?? 0);
$tipo        = trim($input['tipo_evento']   ?? '');
$face_score  = isset($input['face_score']) ? floatval($input['face_score']) : null;

$tiposValidos = ['entrada','salida_comida','regreso_comida','salida'];
if (!$empleado_id || !in_array($tipo, $tiposValidos)) {
    echo json_encode(['ok'=>false,'msg'=>'Parámetros inválidos.']);
    exit();
}

// Verificar que el empleado exista y esté activo
$s = $conn->prepare("SELECT id, planta_id FROM empleados WHERE id = ? AND activo = 1 LIMIT 1");
$s->bind_param('i', $empleado_id);
$s->execute();
$emp = $s->get_result()->fetch_assoc();
$s->close();
if (!$emp) {
    echo json_encode(['ok'=>false,'msg'=>'Empleado no encontrado o inactivo.']);
    exit();
}

// Planta: usar la del empleado si no se especifica
if (!$planta_id) $planta_id = $emp['planta_id'];

// Estado de hoy
$hoy = date('Y-m-d');
$s2  = $conn->prepare("
    SELECT tipo_evento FROM registros_asistencia
    WHERE  empleado_id = ? AND DATE(fecha_hora) = ?
    ORDER  BY fecha_hora ASC
");
$s2->bind_param('is', $empleado_id, $hoy);
$s2->execute();
$registrados = array_column($s2->get_result()->fetch_all(MYSQLI_ASSOC), 'tipo_evento');
$s2->close();

// Validar secuencia
$secuencia = ['entrada','salida_comida','regreso_comida','salida'];
if (in_array($tipo, $registrados)) {
    echo json_encode(['ok'=>false,'msg'=>"Ya registraste '{$tipo}' hoy."]);
    exit();
}
$idx = array_search($tipo, $secuencia);
if ($idx > 0) {
    $previo = $secuencia[$idx - 1];
    if (!in_array($previo, $registrados)) {
        echo json_encode(['ok'=>false,'msg'=>"Debes registrar '{$previo}' primero."]);
        exit();
    }
}

// Insertar
$ahora = date('Y-m-d H:i:s');
$ins = $conn->prepare("
    INSERT INTO registros_asistencia (empleado_id, planta_id, tipo_evento, fecha_hora, face_score)
    VALUES (?, ?, ?, ?, ?)
");
$ins->bind_param('iissd', $empleado_id, $planta_id, $tipo, $ahora, $face_score);
$ok = $ins->execute();
$ins->close();

if ($ok) {
    echo json_encode(['ok'=>true,'msg'=>'Registrado correctamente.','hora'=>$ahora]);
} else {
    echo json_encode(['ok'=>false,'msg'=>'Error al guardar el registro.']);
}
