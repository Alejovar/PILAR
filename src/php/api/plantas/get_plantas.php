<?php
// /src/php/api/plantas/get_plantas.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

function get_plantas_log(string $m) { error_log('[get_plantas] '.$m); }

// Intentar leer plantas
$sql = "SELECT id, codigo, nombre, ubicacion, activa, latitud, longitud, radio_permitido, created_at FROM plantas ORDER BY created_at DESC";
$res = $conn->query($sql);
if (!$res) {
    get_plantas_log('Query failed: ' . $conn->error);
    echo json_encode(['ok'=>false,'msg'=>'Error al obtener plantas.']);
    exit();
}

$rows = $res->fetch_all(MYSQLI_ASSOC);
echo json_encode(['ok'=>true,'plantas'=>$rows]);
