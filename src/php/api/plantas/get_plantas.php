<?php
// /src/php/api/plantas/get_plantas.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

$sql = "
    SELECT id, nombre, codigo,
           CONCAT(COALESCE(latitud,''),' ',COALESCE(longitud,'')) AS coords,
           -- En schema_final ubicacion es latitud+longitud; añadimos campo adicional si se agrega
           '' AS ubicacion,
           activa, created_at
    FROM  plantas
    ORDER BY nombre ASC
";

// Soporte campo ubicacion si existe en la BD (se añade en schema extendido)
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM plantas LIKE 'ubicacion'");
if ($res && $res->num_rows) {
    $sql = "SELECT id, nombre, codigo, ubicacion, activa, created_at FROM plantas ORDER BY nombre ASC";
}

$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
echo json_encode(['ok'=>true,'plantas'=>$rows]);
