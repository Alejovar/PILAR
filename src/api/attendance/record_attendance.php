<?php
// /src/api/attendance/record_attendance.php
// Registra entrada o salida. Puede ser por reconocimiento facial (no requiere sesión)
// o manual (requiere user_id + password).

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';
header('Content-Type: application/json');

date_default_timezone_set('America/Mexico_City');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']); exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$user_id = intval($data['user_id']  ?? 0);
$type    = strtoupper(trim($data['type']    ?? '')); // ENTRADA | SALIDA
$method  = strtoupper(trim($data['method']  ?? 'FACIAL')); // FACIAL | MANUAL
$comment = trim($data['comment'] ?? '');
// Timestamp consistente para ticket e insercion
$now = date('Y-m-d H:i:s');

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

// Validar alternancia: no permitir ENTRADA/ENTRADA ni SALIDA/SALIDA seguidas.
$last_type = null;
$last_timestamp = null;
$stmt_last = $conn->prepare(
    "SELECT type, timestamp FROM attendance_records WHERE user_id = ? ORDER BY timestamp DESC, id DESC LIMIT 1"
);
$stmt_last->bind_param("i", $user_id);
$stmt_last->execute();
$res_last = $stmt_last->get_result();
if ($res_last && $res_last->num_rows === 1) {
    $last_row = $res_last->fetch_assoc();
    $last_type = strtoupper($last_row['type'] ?? '');
    $last_timestamp = $last_row['timestamp'] ?? null;
}
$stmt_last->close();

if ($type === 'ENTRADA' && $last_type === 'ENTRADA') {
    echo json_encode(['success' => false, 'message' => 'Ya tienes una ENTRADA activa. Debes registrar SALIDA antes de volver a checar entrada.']); exit;
}

if ($type === 'SALIDA') {
    if ($last_type !== 'ENTRADA') {
        echo json_encode(['success' => false, 'message' => 'No puedes registrar SALIDA sin una ENTRADA previa activa.']); exit;
    }
}

// Para SALIDA, usar la ultima ENTRADA registrada para el ticket
$entry_timestamp = null;
if ($type === 'SALIDA') {
    $stmt_entry = $conn->prepare(
        "SELECT timestamp FROM attendance_records WHERE user_id = ? AND type = 'ENTRADA' ORDER BY timestamp DESC, id DESC LIMIT 1"
    );
    $stmt_entry->bind_param("i", $user_id);
    $stmt_entry->execute();
    $res_entry = $stmt_entry->get_result();
    $entry_timestamp = $res_entry->num_rows ? $res_entry->fetch_assoc()['timestamp'] : null;
    $stmt_entry->close();
}

// Limitar comentario
$comment = mb_substr($comment, 0, 60);

try {
    $stmt_ins = $conn->prepare(
        "INSERT INTO attendance_records (user_id, type, method, comment, timestamp) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt_ins->bind_param("issss", $user_id, $type, $method, $comment, $now);
    $stmt_ins->execute();
    $record_id = $conn->insert_id;

    if ($stmt_ins->affected_rows !== 1 || $record_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'No se pudo confirmar el guardado del registro de asistencia']);
        exit;
    }

    // Devolver datos para el ticket
    echo json_encode([
        'success'    => true,
        'record_id'  => (int)$record_id,
        'user_id'    => $user_id,
        'user_name'  => $user_name,
        'type'       => $type,
        'method'     => $method,
        'timestamp'  => $now,
        'entry_timestamp' => $type === 'SALIDA' ? $entry_timestamp : $now,
        'exit_timestamp'  => $type === 'SALIDA' ? $now : null,
        'comment'    => $comment
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
