<?php
// /src/php/api/asistencia/registrar.php
// Registra una checada. Ahora requiere validación geográfica previa exitosa.
// POST: { empleado_id, planta_id, tipo_evento, latitud?, longitud?, face_score? }
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$empleado_id = intval($input['empleado_id'] ?? 0);
$planta_id   = intval($input['planta_id']   ?? 0);
$tipo        = trim($input['tipo_evento']   ?? '');
$face_score  = isset($input['face_score'])  ? floatval($input['face_score']) : null;
$lat_emp     = isset($input['latitud'])     ? floatval($input['latitud'])    : null;
$lng_emp     = isset($input['longitud'])    ? floatval($input['longitud'])   : null;

$tiposValidos = ['entrada', 'salida_comida', 'regreso_comida', 'salida'];
if (!$empleado_id || !in_array($tipo, $tiposValidos)) {
    echo json_encode(['ok' => false, 'msg' => 'Parámetros inválidos.']);
    exit();
}

// Verificar que el empleado exista y esté activo
$s = $conn->prepare("SELECT id, planta_id FROM empleados WHERE id = ? AND activo = 1 LIMIT 1");
$s->bind_param('i', $empleado_id);
$s->execute();
$emp = $s->get_result()->fetch_assoc();
$s->close();

if (!$emp) {
    echo json_encode(['ok' => false, 'msg' => 'Empleado no encontrado o inactivo.']);
    exit();
}

// Planta: usar la del empleado si no se especifica
if (!$planta_id) $planta_id = $emp['planta_id'];

// ── Validación geográfica ───────────────────────────────────
// Si el empleado envió coordenadas, validar contra la planta
if ($lat_emp !== null && $lng_emp !== null) {
    $sp = $conn->prepare("
        SELECT latitud, longitud, radio_permitido, nombre
        FROM   plantas WHERE id = ? LIMIT 1
    ");
    $sp->bind_param('i', $planta_id);
    $sp->execute();
    $planta = $sp->get_result()->fetch_assoc();
    $sp->close();

    if ($planta && $planta['latitud'] !== null && $planta['longitud'] !== null) {
        // Haversine inline
        $dLat = deg2rad((float)$planta['latitud'] - $lat_emp);
        $dLng = deg2rad((float)$planta['longitud'] - $lng_emp);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat_emp)) * cos(deg2rad((float)$planta['latitud'])) * sin($dLng / 2) ** 2;
        $distancia_m = 6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
        $radio = intval($planta['radio_permitido'] ?? 100) ?: 100;

        if ($distancia_m > $radio) {
            echo json_encode([
                'ok'          => false,
                'geo_error'   => true,
                'distancia_m' => round($distancia_m, 1),
                'radio_m'     => $radio,
                'msg'         => sprintf(
                    'Fuera de rango: estás a %.0f m de "%s" (límite %d m).',
                    $distancia_m,
                    $planta['nombre'],
                    $radio
                ),
            ]);
            exit();
        }
    }
} else {
    // Coordenadas no enviadas → rechazar si la planta las tiene configuradas
    $sp = $conn->prepare("SELECT latitud FROM plantas WHERE id = ? LIMIT 1");
    $sp->bind_param('i', $planta_id);
    $sp->execute();
    $row = $sp->get_result()->fetch_assoc();
    $sp->close();

    if ($row && $row['latitud'] !== null) {
        echo json_encode([
            'ok'        => false,
            'geo_error' => true,
            'msg'       => 'Se requiere permiso de ubicación para registrar asistencia.',
        ]);
        exit();
    }
}

// ── Validar secuencia ───────────────────────────────────────
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

$secuencia = ['entrada', 'salida_comida', 'regreso_comida', 'salida'];
if (in_array($tipo, $registrados)) {
    echo json_encode(['ok' => false, 'msg' => "Ya registraste '{$tipo}' hoy."]);
    exit();
}
$idx = array_search($tipo, $secuencia);
if ($idx > 0) {
    $previo = $secuencia[$idx - 1];
    if (!in_array($previo, $registrados)) {
        echo json_encode(['ok' => false, 'msg' => "Debes registrar '{$previo}' primero."]);
        exit();
    }
}

// ── Insertar ────────────────────────────────────────────────
$ahora = date('Y-m-d H:i:s');
$ins = $conn->prepare("
    INSERT INTO registros_asistencia
           (empleado_id, planta_id, tipo_evento, fecha_hora, latitud, longitud, face_score)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$ins->bind_param('iissddd', $empleado_id, $planta_id, $tipo, $ahora, $lat_emp, $lng_emp, $face_score);
$ok = $ins->execute();
$ins->close();

if ($ok) {
    echo json_encode(['ok' => true, 'msg' => 'Registrado correctamente.', 'hora' => $ahora]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Error al guardar el registro.']);
}
