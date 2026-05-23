<?php
// /src/php/api/asistencia/buscar_empleado.php
// Endpoint público — no requiere sesión (checador accesible para todos)
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

$nss    = trim($_GET['nss']    ?? '');
$nombre = trim($_GET['nombre'] ?? '');

if (!$nss && !$nombre) {
    echo json_encode(['ok'=>false,'msg'=>'Parámetros requeridos.']);
    exit();
}

$where  = [];
$params = [];
$types  = '';

if ($nss) {
    $where[]  = 'e.numero_empleado = ?';
    $params[] = $nss;
    $types   .= 's';
}
if ($nombre) {
    $like     = "%{$nombre}%";
    $where[]  = "(CONCAT(e.nombre,' ',e.apellido_paterno,' ',COALESCE(e.apellido_materno,'')) LIKE ?)";
    $params[] = $like;
    $types   .= 's';
}

$whereStr = implode(' OR ', $where);

$sql = "
    SELECT e.id, e.numero_empleado, e.nombre, e.apellido_paterno, e.apellido_materno,
           p.nombre  AS puesto,
           pl.nombre AS planta,
           e.planta_id, e.activo
    FROM   empleados e
    LEFT JOIN puestos  p  ON p.id  = e.puesto_id
    LEFT JOIN plantas  pl ON pl.id = e.planta_id
    WHERE  ({$whereStr}) AND e.activo = 1
    LIMIT  1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) {
    echo json_encode(['ok'=>false,'msg'=>'Empleado no encontrado o inactivo.']);
    exit();
}

echo json_encode(['ok'=>true,'empleado'=>$emp]);
