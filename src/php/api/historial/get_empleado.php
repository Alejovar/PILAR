<?php
// /src/php/api/historial/get_empleado.php
// Historial detallado de un empleado: día a día con entradas/salidas y horas.
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

$empleado_id = intval($_GET['empleado_id'] ?? 0);
$inicio      = $_GET['inicio'] ?? date('Y-m-d', strtotime('-13 days'));
$fin         = $_GET['fin']    ?? date('Y-m-d');

if (!$empleado_id) {
    echo json_encode(['ok'=>false,'msg'=>'empleado_id requerido.']); exit();
}

// Traer registros del período
$sql = "
    SELECT DATE(fecha_hora) AS fecha, tipo_evento,
           TIME_FORMAT(TIME(fecha_hora), '%H:%i') AS hora
    FROM   registros_asistencia
    WHERE  empleado_id = ?
      AND  DATE(fecha_hora) BETWEEN ? AND ?
    ORDER  BY fecha_hora ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $empleado_id, $inicio, $fin);
$stmt->execute();
$registros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Agrupar por día
$diasMap = [];
foreach ($registros as $r) {
    $f    = $r['fecha'];
    $tipo = $r['tipo_evento'];
    if (!isset($diasMap[$f])) {
        $diasMap[$f] = [
            'fecha'         => $f,
            'entrada'       => null,
            'salida_comida' => null,
            'regreso_comida'=> null,
            'salida'        => null,
            'horas_dia'     => null,
        ];
    }
    $diasMap[$f][$tipo] = $r['hora'];
}

// Calcular horas por día
$LIMITE    = 90.0;
$totalMin  = 0;
$diasTrab  = 0;

foreach ($diasMap as &$dia) {
    $minDia = 0;
    // Turno 1: entrada → salida_comida
    if ($dia['entrada'] && $dia['salida_comida']) {
        $minDia += (strtotime("2000-01-01 {$dia['salida_comida']}") - strtotime("2000-01-01 {$dia['entrada']}")) / 60;
    }
    // Turno 2: regreso_comida → salida
    if ($dia['regreso_comida'] && $dia['salida']) {
        $minDia += (strtotime("2000-01-01 {$dia['salida']}") - strtotime("2000-01-01 {$dia['regreso_comida']}")) / 60;
    }
    if ($minDia > 0) {
        $dia['horas_dia'] = round($minDia / 60, 2);
        $totalMin        += $minDia;
        $diasTrab++;
    }
}

$totalHoras = round($totalMin / 60, 2);
$horasExtra = max(0, round($totalHoras - $LIMITE, 2));

$dias = array_values($diasMap);

echo json_encode([
    'ok'             => true,
    'empleado_id'    => $empleado_id,
    'inicio'         => $inicio,
    'fin'            => $fin,
    'dias_trabajados'=> $diasTrab,
    'total_horas'    => $totalHoras,
    'horas_extra'    => $horasExtra,
    'dias'           => $dias,
]);
