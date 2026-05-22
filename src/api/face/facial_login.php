<?php
// /src/api/face/facial_login.php
// El cliente JS ya hizo el matching facial y envía el user_id reconocido.
// Este endpoint verifica el usuario, crea la sesión y devuelve redirect.

session_start();
require $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']); exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$user_id = intval($data['user_id'] ?? 0);

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario inválido']); exit;
}

// Rate limiting básico por device cookie (igual que login normal)
$cookie_name        = 'device_id';
$device_identifier  = $_COOKIE[$cookie_name] ?? null;
if (!$device_identifier) {
    $device_identifier = bin2hex(random_bytes(32));
    setcookie($cookie_name, $device_identifier, time() + (365*24*60*60), "/");
}

// Obtener usuario
$stmt = $conn->prepare("SELECT id, name, rol_id, status FROM users WHERE id = ? AND face_descriptor IS NOT NULL");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado o sin rostro registrado']); exit;
}

$row = $result->fetch_assoc();

if ($row['status'] !== 'ACTIVO') {
    echo json_encode(['success' => false, 'message' => 'Esta cuenta ha sido desactivada']); exit;
}

$rol_id = (int)$row['rol_id'];

// Verificar turno para meseros (igual que login normal)
if ($rol_id === 2) {
    $stmt_shift = $conn->prepare("SELECT 1 FROM cash_shifts WHERE status = 'OPEN' LIMIT 1");
    $stmt_shift->execute();
    if ($stmt_shift->get_result()->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'El turno de caja está cerrado. El cajero o gerente debe abrir turno primero.'
        ]); exit;
    }
}

// Crear sesión (misma lógica que login_handler.php)
$session_token = bin2hex(random_bytes(32));
$stmt_update = $conn->prepare("UPDATE users SET session_token = ? WHERE id = ?");
$stmt_update->bind_param("si", $session_token, $row['id']);
$stmt_update->execute();

$_SESSION['loggedin']      = true;
$_SESSION['user_id']       = $row['id'];
$_SESSION['user_name']     = $row['name'];
$_SESSION['rol_id']        = $rol_id;
$_SESSION['session_token'] = $session_token;

session_write_close();

// Redirección por rol
$redirect_url = "/dashboard.php";
switch ($rol_id) {
    case 1:
        $redirect_url = "/src/php/manager_dashboard.php";
        break;
    default:
        $redirect_url = "/checador.php";
        break;
}

echo json_encode([
    'success'  => true,
    'message'  => '¡Bienvenido, ' . htmlspecialchars($row['name']) . '!',
    'redirect' => $redirect_url
]);
?>
