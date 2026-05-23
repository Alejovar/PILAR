<?php
// /src/php/api/empleados/get_empleados.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

// Detectar si existe columna area_id en puestos
$hasPuestoArea = false;
$r = $conn->query("SHOW COLUMNS FROM puestos LIKE 'area_id'");
if ($r && $r->num_rows) $hasPuestoArea = true;

// Detectar columnas extras en empleados (rfc, curp)
$empCols = $conn->query("SHOW COLUMNS FROM empleados")->fetch_all(MYSQLI_ASSOC);
$colNames = array_column($empCols, 'Field');
$hasRFC   = in_array('rfc',  $colNames);
$hasCURP  = in_array('curp', $colNames);

$extraSel = '';
if ($hasRFC)  $extraSel .= ', e.rfc';
if ($hasCURP) $extraSel .= ', e.curp';

$areaJoin = $hasPuestoArea
    ? "LEFT JOIN areas a ON a.id = p.area_id"
    : "";
$areaSel = $hasPuestoArea
    ? "a.id AS area_id, a.nombre AS area_nombre,"
    : "NULL AS area_id, NULL AS area_nombre,";

$sql = "
    SELECT e.id, e.numero_empleado, e.nombre, e.apellido_paterno, e.apellido_materno,
           e.email, e.activo, e.planta_id, e.puesto_id{$extraSel},
           p.nombre AS puesto_nombre,
           {$areaSel}
           pl.nombre AS planta_nombre
    FROM   empleados e
    LEFT JOIN puestos  p  ON p.id  = e.puesto_id
    {$areaJoin}
    LEFT JOIN plantas  pl ON pl.id = e.planta_id
    ORDER  BY e.apellido_paterno, e.nombre ASC
";

$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
echo json_encode(['ok'=>true,'empleados'=>$rows]);
