<?php
// /src/php/api/empleados/buscar.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

$q = trim($_GET['buscar'] ?? '');
if (!$q) { echo json_encode(['ok'=>false,'msg'=>'Parámetro requerido.']); exit(); }

$like = "%{$q}%";

$sql = "
    SELECT e.id, e.numero_empleado, e.nombre, e.apellido_paterno, e.apellido_materno,
           p.nombre  AS puesto_nombre,
           pl.nombre AS planta_nombre,
           e.planta_id, e.puesto_id, e.activo
    FROM   empleados e
    LEFT JOIN puestos  p  ON p.id  = e.puesto_id
    LEFT JOIN plantas  pl ON pl.id = e.planta_id
    WHERE  e.numero_empleado LIKE ?
       OR  CONCAT(e.nombre,' ',e.apellido_paterno,' ',COALESCE(e.apellido_materno,'')) LIKE ?
    ORDER  BY e.apellido_paterno, e.nombre
    LIMIT  20
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['ok'=>true,'empleados'=>$rows]);
