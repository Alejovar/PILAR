<?php
// /src/php/api/historial/get_catorcena.php
// Calcula horas trabajadas por catorcena (14 días, 90h normal).
// Horas extra = total - 90 si total > 90.
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

$inicio    = $_GET['inicio']    ?? date('Y-m-01');
$fin       = $_GET['fin']       ?? date('Y-m-14');
$buscar    = trim($_GET['buscar']    ?? '');
$area_id   = intval($_GET['area_id']   ?? 0);
$puesto_id = intval($_GET['puesto_id'] ?? 0);

// Validar fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) {
    echo json_encode(['ok'=>false,'msg'=>'Formato de fecha inválido.']); exit();
}

// Construir WHERE del empleado
$empWhere  = [];
$empParams = [];
$empTypes  = '';

if ($buscar) {
    $like = "%{$buscar}%";
    $empWhere[]  = "(e.numero_empleado LIKE ? OR CONCAT(e.nombre,' ',e.apellido_paterno) LIKE ?)";
    $empParams[] = $like;
    $empParams[] = $like;
    $empTypes   .= 'ss';
}
if ($area_id) {
    $hasPuestoArea = $conn->query("SHOW COLUMNS FROM puestos LIKE 'area_id'")->num_rows > 0;
    if ($hasPuestoArea) {
        $empWhere[]  = 'p.area_id = ?';
        $empParams[] = $area_id;
        $empTypes   .= 'i';
    }
}
if ($puesto_id) {
    $empWhere[]  = 'e.puesto_id = ?';
    $empParams[] = $puesto_id;
    $empTypes   .= 'i';
}

// Detectar tabla areas
$hasAreas = $conn->query("SHOW TABLES LIKE 'areas'")->num_rows > 0;
$hasPuesto= $conn->query("SHOW COLUMNS FROM puestos LIKE 'area_id'")->num_rows > 0;

$areaJoin = $hasAreas && $hasPuesto
    ? "LEFT JOIN areas a ON a.id = p.area_id"
    : "";
$areaSel  = $hasAreas && $hasPuesto
    ? "a.nombre AS area,"
    : "NULL AS area,";

$whereStr = $empWhere ? ('AND ' . implode(' AND ', $empWhere)) : '';

// Obtener todos los empleados que aplican
$empSQL = "
    SELECT e.id, e.numero_empleado,
           CONCAT(e.nombre,' ',e.apellido_paterno,' ',COALESCE(e.apellido_materno,'')) AS nombre_completo,
           pl.nombre AS planta,
           {$areaSel}
           p.nombre  AS puesto
    FROM   empleados e
    LEFT JOIN puestos  p  ON p.id  = e.puesto_id
    {$areaJoin}
    LEFT JOIN plantas  pl ON pl.id = e.planta_id
    WHERE  e.activo = 1 {$whereStr}
    ORDER  BY e.apellido_paterno, e.nombre
";

if ($empTypes) {
    $stmt = $conn->prepare($empSQL);
    $stmt->bind_param($empTypes, ...$empParams);
    $stmt->execute();
    $empleados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $empleados = $conn->query($empSQL)->fetch_all(MYSQLI_ASSOC);
}

// Para cada empleado, calcular horas trabajadas en el período
$LIMITE = 90.0; // horas catorcena normal

$reporte = [];
foreach ($empleados as $emp) {
    // Traer todos los registros del período agrupados por día
    $sql = "
        SELECT DATE(fecha_hora) AS fecha, tipo_evento, fecha_hora
        FROM   registros_asistencia
        WHERE  empleado_id = ?
          AND  DATE(fecha_hora) BETWEEN ? AND ?
        ORDER  BY fecha_hora ASC
    ";
    $s = $conn->prepare($sql);
    $s->bind_param('iss', $emp['id'], $inicio, $fin);
    $s->execute();
    $registros = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    // Agrupar por día
    $dias = [];
    foreach ($registros as $r) {
        $f    = $r['fecha'];
        $tipo = $r['tipo_evento'];
        if (!isset($dias[$f])) $dias[$f] = [];
        $dias[$f][$tipo] = $r['fecha_hora'];
    }

    // Calcular horas por día (entrada→salida_comida) + (regreso_comida→salida)
    $totalMin = 0;
    foreach ($dias as $f => $ev) {
        // Turno mañana: entrada → salida_comida
        if (isset($ev['entrada']) && isset($ev['salida_comida'])) {
            $totalMin += (strtotime($ev['salida_comida']) - strtotime($ev['entrada'])) / 60;
        }
        // Turno tarde: regreso_comida → salida
        if (isset($ev['regreso_comida']) && isset($ev['salida'])) {
            $totalMin += (strtotime($ev['salida']) - strtotime($ev['regreso_comida'])) / 60;
        }
    }

    $horasTrabajadas = round($totalMin / 60, 2);
    $horasExtra      = max(0, round($horasTrabajadas - $LIMITE, 2));

    // Solo incluir si tiene algún registro (o incluir todos — depende del contexto)
    // Roceel quiere ver a todos aunque tengan 0 horas, para detectar ausencias
    $reporte[] = [
        'id'              => $emp['id'],
        'numero_empleado' => $emp['numero_empleado'],
        'nombre_completo' => trim($emp['nombre_completo']),
        'planta'          => $emp['planta'],
        'area'            => $emp['area'],
        'puesto'          => $emp['puesto'],
        'horas_trabajadas'=> $horasTrabajadas,
        'horas_extra'     => $horasExtra,
    ];
}

echo json_encode(['ok'=>true,'reporte'=>$reporte,'inicio'=>$inicio,'fin'=>$fin]);
