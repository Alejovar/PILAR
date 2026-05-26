<?php
// /src/php/api/empleados/get_empleados.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

$hasPuestoArea = (bool)$conn->query("SHOW COLUMNS FROM puestos LIKE 'area_id'")->num_rows;
$empCols       = array_column($conn->query("SHOW COLUMNS FROM empleados")->fetch_all(MYSQLI_ASSOC), 'Field');
$hasRFC        = in_array('rfc',  $empCols);
$hasCURP       = in_array('curp', $empCols);
$hasPivot      = (bool)$conn->query("SHOW TABLES LIKE 'empleado_plantas'")->num_rows;

$extraSel  = ($hasRFC  ? ', e.rfc'  : '') . ($hasCURP ? ', e.curp' : '');
$areaJoin  = $hasPuestoArea ? "LEFT JOIN areas a ON a.id = p.area_id" : "";
$areaSel   = $hasPuestoArea
    ? "a.id AS area_id, a.nombre AS area_nombre,"
    : "NULL AS area_id, NULL AS area_nombre,";

$rows = $conn->query("
    SELECT e.id, e.numero_empleado, e.nombre, e.apellido_paterno, e.apellido_materno,
           e.email, e.activo, e.planta_id, e.puesto_id {$extraSel},
           p.nombre  AS puesto_nombre,
           {$areaSel}
           pl.nombre AS planta_nombre
    FROM   empleados e
    LEFT JOIN puestos  p  ON p.id  = e.puesto_id
    {$areaJoin}
    LEFT JOIN plantas  pl ON pl.id = e.planta_id
    ORDER  BY e.apellido_paterno, e.nombre ASC
")->fetch_all(MYSQLI_ASSOC);

// Adjuntar todas las plantas de cada empleado (via pivote si existe)
if ($hasPivot && count($rows)) {
    $ids      = implode(',', array_column($rows, 'id'));
    $pivotRows = $conn->query("
        SELECT ep.empleado_id, ep.planta_id, ep.es_principal, pl.nombre AS planta_nombre
        FROM   empleado_plantas ep
        JOIN   plantas pl ON pl.id = ep.planta_id
        WHERE  ep.empleado_id IN ({$ids})
        ORDER  BY ep.es_principal DESC, pl.nombre ASC
    ")->fetch_all(MYSQLI_ASSOC);

    // Indexar por empleado_id
    $plantasPorEmp = [];
    foreach ($pivotRows as $pr) {
        $plantasPorEmp[$pr['empleado_id']][] = [
            'id'           => intval($pr['planta_id']),
            'nombre'       => $pr['planta_nombre'],
            'es_principal' => (bool)$pr['es_principal'],
        ];
    }
    foreach ($rows as &$row) {
        $row['plantas'] = $plantasPorEmp[$row['id']] ?? [];
        $row['activo']  = (bool)$row['activo'];
    }
    unset($row);
} else {
    // Sin pivote: usar planta_id único actual
    foreach ($rows as &$row) {
        $row['plantas'] = $row['planta_id'] ? [[
            'id'           => intval($row['planta_id']),
            'nombre'       => $row['planta_nombre'],
            'es_principal' => true,
        ]] : [];
        $row['activo'] = (bool)$row['activo'];
    }
    unset($row);
}

echo json_encode(['ok'=>true,'empleados'=>$rows]);