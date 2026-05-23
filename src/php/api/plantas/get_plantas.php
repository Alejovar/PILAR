<?php
// /src/php/api/plantas/get_plantas.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

$rows = $conn->query("
    SELECT id, nombre, codigo, ubicacion,
           latitud, longitud, radio_permitido,
           activa, created_at
    FROM   plantas
    ORDER  BY nombre ASC
")->fetch_all(MYSQLI_ASSOC);

// Castear tipos para JSON
foreach ($rows as &$r) {
    $r['id']              = intval($r['id']);
    $r['activa']          = (bool)$r['activa'];
    $r['radio_permitido'] = intval($r['radio_permitido'] ?? 100);
    $r['latitud']         = $r['latitud']  !== null ? floatval($r['latitud'])  : null;
    $r['longitud']        = $r['longitud'] !== null ? floatval($r['longitud']) : null;
}
unset($r);

echo json_encode(['ok' => true, 'plantas' => $rows]);
