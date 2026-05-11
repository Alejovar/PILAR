<?php
// /src/api/attendance/record_attendance.php
// Registra entrada o salida. Puede ser por reconocimiento facial (no requiere sesión)
// o manual (requiere user_id + password).

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']); exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$user_id = intval($data['user_id']  ?? 0);
$type    = strtoupper(trim($data['type']    ?? '')); // ENTRADA | SALIDA
$method  = strtoupper(trim($data['method']  ?? 'FACIAL')); // FACIAL | MANUAL
$comment = trim($data['comment'] ?? '');

// Para método MANUAL, necesitamos autenticar con user+password
if ($method === 'MANUAL') {
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Usuario y contraseña requeridos']); exit;
    }

    $stmt = $conn->prepare("SELECT id, password, name, rol_id, status FROM users WHERE user = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows !== 1) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']); exit;
    }
    $row = $res->fetch_assoc();
    if ($row['status'] !== 'ACTIVO') {
        echo json_encode(['success' => false, 'message' => 'Cuenta desactivada']); exit;
    }
    if (!password_verify($password, $row['password'])) {
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']); exit;
    }
    $user_id   = (int)$row['id'];
    $user_name = $row['name'];
} else {
    // FACIAL: user_id ya viene identificado por el cliente
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de usuario inválido']); exit;
    }
    $stmt = $conn->prepare("SELECT id, name, status FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows !== 1) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']); exit;
    }
    $row = $res->fetch_assoc();
    if ($row['status'] !== 'ACTIVO') {
        echo json_encode(['success' => false, 'message' => 'Cuenta desactivada']); exit;
    }
    $user_name = $row['name'];
}

if (!in_array($type, ['ENTRADA', 'SALIDA'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo inválido (ENTRADA o SALIDA)']); exit;
}

// Limitar comentario
$comment = mb_substr($comment, 0, 500);

try {
    $stmt_ins = $conn->prepare(
        "INSERT INTO attendance_records (user_id, type, method, comment) VALUES (?, ?, ?, ?)"
    );
    $stmt_ins->bind_param("isss", $user_id, $type, $method, $comment);
    $stmt_ins->execute();
    $record_id = $conn->insert_id;

    // Devolver datos para el ticket
    echo json_encode([
        'success'    => true,
        'record_id'  => $record_id,
        'user_id'    => $user_id,
        'user_name'  => $user_name,
        'type'       => $type,
        'method'     => $method,
        'timestamp'  => date('Y-m-d H:i:s'),
        'comment'    => $comment
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
