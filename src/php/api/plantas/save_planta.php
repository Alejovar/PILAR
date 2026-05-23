<?php
// /src/php/api/plantas/save_planta.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido.']); exit();
}

$data     = json_decode(file_get_contents('php://input'), true);
$id       = intval($data['id']       ?? 0);
$nombre   = trim($data['nombre']     ?? '');
$codigo   = strtoupper(trim($data['codigo'] ?? ''));
$ubicacion= trim($data['ubicacion']  ?? '');
$activa   = isset($data['activa']) ? (bool)$data['activa'] : true;
// Optional geo fields
$lat      = isset($data['latitud']) && $data['latitud'] !== null && $data['latitud'] !== '' ? floatval($data['latitud']) : null;
$lng      = isset($data['longitud']) && $data['longitud'] !== null && $data['longitud'] !== '' ? floatval($data['longitud']) : null;
$radio    = isset($data['radio_permitido']) && $data['radio_permitido'] !== null && $data['radio_permitido'] !== '' ? intval($data['radio_permitido']) : null;

if (!$nombre || !$codigo) {
    echo json_encode(['ok'=>false,'msg'=>'Nombre y código son requeridos.']); exit();
}

// Verificar si el campo ubicacion existe
$hasUbicacion = false;
$res = $conn->query("SHOW COLUMNS FROM plantas LIKE 'ubicacion'");
if ($res && $res->num_rows) $hasUbicacion = true;

// Verificar si existen columnas geo
$hasLat = false; $hasLng = false; $hasRadio = false;
$r = $conn->query("SHOW COLUMNS FROM plantas LIKE 'latitud'"); if ($r && $r->num_rows) $hasLat = true;
$r = $conn->query("SHOW COLUMNS FROM plantas LIKE 'longitud'"); if ($r && $r->num_rows) $hasLng = true;
$r = $conn->query("SHOW COLUMNS FROM plantas LIKE 'radio_permitido'"); if ($r && $r->num_rows) $hasRadio = true;

if ($id) {
    // UPDATE — build fields conditionally
    $fields = ['nombre=?','codigo=?'];
    $types  = 'ss';
    $params = [$nombre, $codigo];

    if ($hasUbicacion) { $fields[] = 'ubicacion=?'; $types .= 's'; $params[] = $ubicacion; }
    $fields[] = 'activa=?'; $types .= 'i'; $params[] = $activa ? 1 : 0;

    if ($hasLat && $lat !== null)   { $fields[] = 'latitud=?'; $types .= 'd'; $params[] = $lat; }
    if ($hasLng && $lng !== null)   { $fields[] = 'longitud=?'; $types .= 'd'; $params[] = $lng; }
    if ($hasRadio && $radio !== null) { $fields[] = 'radio_permitido=?'; $types .= 'i'; $params[] = $radio; }

    $sql = "UPDATE plantas SET " . implode(', ', $fields) . " WHERE id=?";
    $types .= 'i'; $params[] = $id;
    $stmt = $conn->prepare($sql);
    if ($stmt === false) { echo json_encode(['ok'=>false,'msg'=>'Error interno.']); exit(); }
    // bind params by reference
    $bind_names[] = $types;
    for ($i=0;$i<count($params);$i++) $bind_names[] = &$params[$i];
    call_user_func_array([$stmt,'bind_param'], $bind_names);
} else {
    // INSERT — build columns conditionally
    $cols = ['nombre','codigo'];
    $vals = ['?','?'];
    $types = 'ss';
    $params = [$nombre, $codigo];

    if ($hasUbicacion) { $cols[] = 'ubicacion'; $vals[] = '?'; $types .= 's'; $params[] = $ubicacion; }
    $cols[] = 'activa'; $vals[] = '?'; $types .= 'i'; $params[] = $activa ? 1 : 0;

    if ($hasLat && $lat !== null)   { $cols[] = 'latitud'; $vals[] = '?'; $types .= 'd'; $params[] = $lat; }
    if ($hasLng && $lng !== null)   { $cols[] = 'longitud'; $vals[] = '?'; $types .= 'd'; $params[] = $lng; }
    if ($hasRadio && $radio !== null) { $cols[] = 'radio_permitido'; $vals[] = '?'; $types .= 'i'; $params[] = $radio; }

    $sql = "INSERT INTO plantas (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) { echo json_encode(['ok'=>false,'msg'=>'Error interno.']); exit(); }
    $bind_names[] = $types;
    for ($i=0;$i<count($params);$i++) $bind_names[] = &$params[$i];
    call_user_func_array([$stmt,'bind_param'], $bind_names);
}

$ok = $stmt->execute();
$err= $stmt->error;
$stmt->close();

if ($ok) {
    echo json_encode(['ok'=>true]);
} else {
    // Código duplicado
    if (str_contains($err, 'Duplicate')) {
        echo json_encode(['ok'=>false,'msg'=>"El código '{$codigo}' ya existe."]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'Error al guardar: '.$err]);
    }
}
