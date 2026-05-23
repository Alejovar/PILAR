<?php
// /src/php/api/asistencia/estado_hoy.php
// Devuelve las checadas del día de hoy para un empleado.
// Endpoint público.
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

$empleado_id = intval($_GET['empleado_id'] ?? 0);
if (!$empleado_id) {
    echo json_encode(['ok'=>false,'msg'=>'empleado_id requerido.']);
    exit();
}

// Traer los 4 posibles eventos de HOY en zona horaria del servidor
$hoy = date('Y-m-d');

$sql = "
    SELECT tipo_evento, fecha_hora
    FROM   registros_asistencia
    WHERE  empleado_id = ?
      AND  DATE(fecha_hora) = ?
    ORDER  BY fecha_hora ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('is', $empleado_id, $hoy);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Organizar en mapa
$map = [
    'entrada'       => null,
    'salida_comida' => null,
    'regreso_comida'=> null,
    'salida'        => null,
];
foreach ($rows as $r) {
    $tipo = $r['tipo_evento'];
    if (array_key_exists($tipo, $map) && $map[$tipo] === null) {
        $map[$tipo] = $r['fecha_hora'];
    }
}

echo json_encode(['ok'=>true,'registros'=>$map]);
