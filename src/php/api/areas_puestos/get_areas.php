<?php
// /src/php/api/areas_puestos/get_areas.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

// Áreas y sus puestos anidados
// El schema tiene tabla 'areas' (si existe) o se toma de puestos sin área
// Schema extendido: áreas tabla separada; schema_final original solo tiene puestos.
// Detectamos dinámicamente.

$hasAreas = false;
$res = $conn->query("SHOW TABLES LIKE 'areas'");
if ($res && $res->num_rows) $hasAreas = true;

if ($hasAreas) {
    // Schema extendido con tabla areas
    $areas = $conn->query("SELECT id, nombre FROM areas ORDER BY nombre ASC")->fetch_all(MYSQLI_ASSOC);
    foreach ($areas as &$a) {
        $s = $conn->prepare("SELECT id, nombre FROM puestos WHERE area_id = ? ORDER BY nombre ASC");
        $s->bind_param('i', $a['id']);
        $s->execute();
        $a['puestos'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s->close();
    }
    echo json_encode(['ok'=>true,'areas'=>$areas]);
} else {
    // Schema original: puestos sin área — devolvemos un "área" genérica
    $puestos = $conn->query("SELECT id, nombre FROM puestos ORDER BY nombre ASC")->fetch_all(MYSQLI_ASSOC);
    $areas = [['id'=>1,'nombre'=>'General','puestos'=>$puestos]];
    echo json_encode(['ok'=>true,'areas'=>$areas,'_nota'=>'sin_tabla_areas']);
}
