<?php
// /src/php/login_handler.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>28800,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido.']);
    exit();
}

$input    = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (!$username || !$password) {
    echo json_encode(['ok'=>false,'msg'=>'Usuario y contraseña requeridos.']);
    exit();
}

$stmt = $conn->prepare(
    "SELECT u.id, u.username, u.password_hash, u.rol, u.activo,
            e.nombre, e.apellido_paterno
     FROM   usuarios u
     LEFT JOIN empleados e ON e.id = u.empleado_id
     WHERE  u.username = ? LIMIT 1"
);
$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !$user['activo']) {
    echo json_encode(['ok'=>false,'msg'=>'Credenciales inválidas.']);
    exit();
}

if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(['ok'=>false,'msg'=>'Credenciales inválidas.']);
    exit();
}

session_regenerate_id(true);
$_SESSION['user_id']   = $user['id'];
$_SESSION['username']  = $user['username'];
$_SESSION['rol']       = $user['rol'];
$_SESSION['user_name'] = trim(($user['nombre'] ?? '') . ' ' . ($user['apellido_paterno'] ?? '')) ?: $user['username'];

echo json_encode(['ok'=>true,'redirect'=>'/dashboard.php']);
