<?php
// /src/php/api/asistencia/validar_geolocalizacion.php
// Valida si el empleado está dentro del radio permitido de su planta.
// POST: { empleado_id, latitud, longitud }
// Responde: { ok, dentro, distancia_m, radio_permitido, msg }

header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

/**
 * Fórmula de Haversine
 * Retorna distancia en metros entre dos puntos geográficos.
 * Se declara primero para evitar errores de ámbito.
 */
function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R = 6371000;

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$empleado_id = intval($input['empleado_id'] ?? 0);
$lat_emp     = isset($input['latitud'])   ? floatval($input['latitud'])  : null;
$lng_emp     = isset($input['longitud'])  ? floatval($input['longitud']) : null;

// Validar datos de entrada
if (!$empleado_id || $lat_emp === null || $lng_emp === null) {
    echo json_encode(['ok' => false, 'msg' => 'Parámetros incompletos.']);
    exit();
}

if ($lat_emp < -90 || $lat_emp > 90 || $lng_emp < -180 || $lng_emp > 180) {
    echo json_encode(['ok' => false, 'msg' => 'Coordenadas fuera de rango.']);
    exit();
}

// Obtener la planta del empleado con sus coordenadas y radio
$stmt = $conn->prepare("
    SELECT p.id        AS planta_id,
           p.nombre    AS planta_nombre,
           p.latitud   AS planta_lat,
           p.longitud  AS planta_lng,
           p.radio_permitido
    FROM   empleados e
    JOIN   plantas   p ON p.id = e.planta_id
    WHERE  e.id = ? AND e.activo = 1
    LIMIT  1
");
$stmt->bind_param('i', $empleado_id);
$stmt->execute();
$planta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$planta) {
    echo json_encode(['ok' => false, 'msg' => 'Empleado no encontrado, inactivo o sin planta asignada.']);
    exit();
}

if ($planta['planta_lat'] === null || $planta['planta_lng'] === null) {
    // Planta sin coordenadas → permitir sin validación geográfica
    echo json_encode([
        'ok'             => true,
        'dentro'         => true,
        'distancia_m'    => null,
        'radio_permitido'=> $planta['radio_permitido'],
        'msg'            => 'Planta sin coordenadas configuradas; geolocalización omitida.',
        'planta_id'      => $planta['planta_id'],
        'sin_coords'     => true,
    ]);
    exit();
}

$distancia_m   = haversine($lat_emp, $lng_emp, (float)$planta['planta_lat'], (float)$planta['planta_lng']);
$radio         = intval($planta['radio_permitido']) ?: 100;
$dentro        = $distancia_m <= $radio;

if ($dentro) {
    echo json_encode([
        'ok'             => true,
        'dentro'         => true,
        'distancia_m'    => round($distancia_m, 1),
        'radio_permitido'=> $radio,
        'planta_id'      => $planta['planta_id'],
        'msg'            => sprintf('Ubicación válida (%.0f m de la planta).', $distancia_m),
    ]);
} else {
    echo json_encode([
        'ok'             => true,
        'dentro'         => false,
        'distancia_m'    => round($distancia_m, 1),
        'radio_permitido'=> $radio,
        'planta_id'      => $planta['planta_id'],
        'msg'            => sprintf(
            'Estás a %.0f m de la planta "%s". El límite es %d m.',
            $distancia_m,
            $planta['planta_nombre'],
            $radio
        ),
    ]);
}
?>