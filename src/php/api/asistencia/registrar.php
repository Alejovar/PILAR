<?php
// /src/php/api/asistencia/registrar.php
// Registra una checada. Valida geo contra CUALQUIERA de las plantas del empleado.
// POST: { empleado_id, tipo_evento, latitud?, longitud?, face_score? }
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']); exit();
}

$input      = json_decode(file_get_contents('php://input'), true);
$empleado_id = intval($input['empleado_id'] ?? 0);
$tipo        = trim($input['tipo_evento']   ?? '');
$face_score  = isset($input['face_score'])  ? floatval($input['face_score']) : null;
$lat_emp     = isset($input['latitud'])     ? floatval($input['latitud'])    : null;
$lng_emp     = isset($input['longitud'])    ? floatval($input['longitud'])   : null;

$tiposValidos = ['entrada', 'salida_comida', 'regreso_comida', 'salida'];
if (!$empleado_id || !in_array($tipo, $tiposValidos)) {
    echo json_encode(['ok' => false, 'msg' => 'Parámetros inválidos.']); exit();
}

// Verificar empleado activo
$s = $conn->prepare("SELECT id, planta_id FROM empleados WHERE id = ? AND activo = 1 LIMIT 1");
$s->bind_param('i', $empleado_id);
$s->execute();
$emp = $s->get_result()->fetch_assoc();
$s->close();

if (!$emp) {
    echo json_encode(['ok' => false, 'msg' => 'Empleado no encontrado o inactivo.']); exit();
}

// ── Obtener todas las plantas del empleado ──────────────────
// Primero intentar desde la tabla pivote, si no existe usar planta_id directo
$hasPivot = $conn->query("SHOW TABLES LIKE 'empleado_plantas'")->num_rows > 0;

if ($hasPivot) {
    $sp = $conn->prepare("
        SELECT p.id, p.nombre, p.latitud, p.longitud, p.radio_permitido, ep.es_principal
        FROM   empleado_plantas ep
        JOIN   plantas p ON p.id = ep.planta_id
        WHERE  ep.empleado_id = ? AND p.activa = 1
        ORDER  BY ep.es_principal DESC
    ");
    $sp->bind_param('i', $empleado_id);
    $sp->execute();
    $plantas = $sp->get_result()->fetch_all(MYSQLI_ASSOC);
    $sp->close();
} else {
    // Sin pivote: usar planta_id único
    $sp = $conn->prepare("
        SELECT p.id, p.nombre, p.latitud, p.longitud, p.radio_permitido, 1 AS es_principal
        FROM   plantas p
        JOIN   empleados e ON e.planta_id = p.id
        WHERE  e.id = ? AND p.activa = 1
        LIMIT  1
    ");
    $sp->bind_param('i', $empleado_id);
    $sp->execute();
    $plantas = $sp->get_result()->fetch_all(MYSQLI_ASSOC);
    $sp->close();
}

if (empty($plantas)) {
    echo json_encode(['ok' => false, 'msg' => 'El empleado no tiene plantas asignadas.']); exit();
}

// ── Validación geográfica contra TODAS las plantas ──────────
// Acepta si está dentro del radio de AL MENOS UNA planta
$plantaChecada = null; // la planta donde se registrará

if ($lat_emp !== null && $lng_emp !== null) {

    $plantasConCoords = array_filter($plantas, fn($p) => $p['latitud'] !== null && $p['longitud'] !== null);

    if (!empty($plantasConCoords)) {
        $dentroDeAlguna  = false;
        $mejorPlanta     = null;
        $menorDistancia  = PHP_INT_MAX;

        foreach ($plantasConCoords as $p) {
            $dLat = deg2rad((float)$p['latitud'] - $lat_emp);
            $dLng = deg2rad((float)$p['longitud'] - $lng_emp);
            $a    = sin($dLat/2)**2 + cos(deg2rad($lat_emp)) * cos(deg2rad((float)$p['latitud'])) * sin($dLng/2)**2;
            $dist = 6371000 * 2 * atan2(sqrt($a), sqrt(1-$a));
            $radio = intval($p['radio_permitido'] ?? 100) ?: 100;

            if ($dist < $menorDistancia) {
                $menorDistancia = $dist;
                $mejorPlanta    = $p;
                $mejorPlanta['distancia_m'] = $dist;
                $mejorPlanta['radio']       = $radio;
            }

            if ($dist <= $radio) {
                $dentroDeAlguna = true;
                $plantaChecada  = $p;
                break; // ya encontró una válida, no seguir
            }
        }

        if (!$dentroDeAlguna) {
            // No está en ninguna — mostrar la más cercana
            echo json_encode([
                'ok'          => false,
                'geo_error'   => true,
                'distancia_m' => round($mejorPlanta['distancia_m'], 1),
                'radio_m'     => $mejorPlanta['radio'],
                'msg'         => sprintf(
                    'No estás en ninguna de tus plantas. La más cercana es "%s" a %.0f m (límite %d m).',
                    $mejorPlanta['nombre'],
                    $mejorPlanta['distancia_m'],
                    $mejorPlanta['radio']
                ),
            ]);
            exit();
        }
    } else {
        // Ninguna planta tiene coords → permitir sin validación geo
        $plantaChecada = $plantas[0]; // usar la principal
    }

} else {
    // Sin coordenadas → rechazar si alguna planta las tiene configuradas
    $algunaTieneCoords = array_filter($plantas, fn($p) => $p['latitud'] !== null);
    if (!empty($algunaTieneCoords)) {
        echo json_encode([
            'ok'        => false,
            'geo_error' => true,
            'msg'       => 'Se requiere permiso de ubicación para registrar asistencia.',
        ]);
        exit();
    }
    $plantaChecada = $plantas[0];
}

// Si no se determinó la planta (sin coords y sin geo) usar la principal
if (!$plantaChecada) $plantaChecada = $plantas[0];
$planta_id = intval($plantaChecada['id']);

// ── Validar secuencia del día ───────────────────────────────
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
    echo json_encode(['ok' => false, 'msg' => "Ya registraste '{$tipo}' hoy."]); exit();
}
$idx = array_search($tipo, $secuencia);
if ($idx > 0 && !in_array($secuencia[$idx-1], $registrados)) {
    echo json_encode(['ok' => false, 'msg' => "Debes registrar '{$secuencia[$idx-1]}' primero."]); exit();
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
    echo json_encode([
        'ok'     => true,
        'msg'    => 'Registrado correctamente.',
        'hora'   => $ahora,
        'planta' => $plantaChecada['nombre'],
    ]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Error al guardar el registro.']);
}