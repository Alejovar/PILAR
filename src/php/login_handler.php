<?php
// /src/php/login_handler.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>28800,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

function login_debug_log(string $message): void {
    error_log('[login_handler] ' . $message);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    login_debug_log('Rejected request: method=' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido.']);
    exit();
}

$input    = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

login_debug_log('Login attempt received for username=' . ($username !== '' ? $username : '[empty]'));

if (!$username || !$password) {
    login_debug_log('Rejected request: missing username or password');
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

if ($user) {
    login_debug_log('User found: id=' . $user['id'] . ', activo=' . $user['activo'] . ', rol=' . $user['rol']);
} else {
    login_debug_log('No user found for username=' . $username);
}

if (!$user || !$user['activo']) {
    login_debug_log('Rejected request: user missing or inactive');
    echo json_encode(['ok'=>false,'msg'=>'Credenciales inválidas.']);
    exit();
}

login_debug_log('Password hash format for user id=' . $user['id'] . ': ' . substr((string)$user['password_hash'], 0, 7) . '...');

$passwordMatchesHash = password_verify($password, $user['password_hash']);
$passwordMatchesPlain = hash_equals((string)$user['password_hash'], (string)$password);

login_debug_log('Password checks for user id=' . $user['id'] . ': hash_verify=' . ($passwordMatchesHash ? 'true' : 'false') . ', plain_match=' . ($passwordMatchesPlain ? 'true' : 'false'));

if (!$passwordMatchesHash) {
    // Compatibilidad con usuarios antiguos almacenados sin hash
    if (!$passwordMatchesPlain) {
        login_debug_log('Rejected request: password mismatch for user id=' . $user['id']);
        echo json_encode(['ok'=>false,'msg'=>'Credenciales inválidas.']);
        exit();
    }

    login_debug_log('Legacy plaintext password matched for user id=' . $user['id'] . ', upgrading to bcrypt');
    $newHash = password_hash($password, PASSWORD_BCRYPT);
    $updateStmt = $conn->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
    $updateStmt->bind_param('si', $newHash, $user['id']);
    $updateStmt->execute();
    $updateStmt->close();

    $user['password_hash'] = $newHash;
}

session_regenerate_id(true);
$_SESSION['user_id']   = $user['id'];
$_SESSION['username']  = $user['username'];
$_SESSION['rol']       = $user['rol'];
$_SESSION['user_name'] = trim(($user['nombre'] ?? '') . ' ' . ($user['apellido_paterno'] ?? '')) ?: $user['username'];

login_debug_log('Login success for user id=' . $user['id'] . ', username=' . $user['username']);

echo json_encode(['ok'=>true,'redirect'=>'/dashboard.php']);
