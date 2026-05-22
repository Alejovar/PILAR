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

    $stmt = $conn->prepare("SELECT id, password, name, rol_id, status, shift_start_time, shift_end_time, late_after_minutes, absence_after_minutes FROM users WHERE user = ?");
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
    $stmt = $conn->prepare("SELECT id, name, status, shift_start_time, shift_end_time, late_after_minutes, absence_after_minutes FROM users WHERE id = ?");
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

$shift_start_time = $row['shift_start_time'] ?? '08:00:00';
$shift_end_time = $row['shift_end_time'] ?? '18:00:00';
$late_after_minutes = max(0, (int)($row['late_after_minutes'] ?? 0));
$absence_after_minutes = max($late_after_minutes, (int)($row['absence_after_minutes'] ?? 15));

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

$entryStatus = null;
$minutesLate = 0;
$permissionId = null;

if ($type === 'ENTRADA') {
    $today = date('Y-m-d');
    $shiftStartTs = strtotime($today . ' ' . $shift_start_time);
    $lateThresholdTs = $shiftStartTs + ($late_after_minutes * 60);
    $absenceThresholdTs = $shiftStartTs + ($absence_after_minutes * 60);
    $currentTs = strtotime($now);

    $stmtPerm = $conn->prepare(
        "SELECT id FROM attendance_permissions
         WHERE user_id = ? AND active = 1 AND valid_from <= ? AND valid_to >= ?
         ORDER BY id DESC LIMIT 1"
    );
    $stmtPerm->bind_param("iss", $user_id, $today, $today);
    $stmtPerm->execute();
    $permRes = $stmtPerm->get_result();
    if ($permRes && $permRes->num_rows === 1) {
        $permRow = $permRes->fetch_assoc();
        $permissionId = (int)$permRow['id'];
    }
    $stmtPerm->close();

    if ($currentTs > $absenceThresholdTs && !$permissionId) {
        echo json_encode([
            'success' => false,
            'message' => 'Tu entrada ya se considera falta y no tienes permiso especial asignado para este día.'
        ]);
        exit;
    }

    if ($permissionId) {
        $entryStatus = 'PERMISO';
        $minutesLate = max(0, (int)floor(($currentTs - $shiftStartTs) / 60));
    } elseif ($currentTs > $lateThresholdTs) {
        $entryStatus = 'RETARDO';
        $minutesLate = max(0, (int)floor(($currentTs - $shiftStartTs) / 60));
    } else {
        $entryStatus = 'NORMAL';
        $minutesLate = 0;
    }
}

// Para SALIDA, usar la ultima ENTRADA registrada para el ticket
$entry_timestamp = null;
$workedMinutes = null;
$overtimeMinutes = 0;
if ($type === 'SALIDA') {
    $stmt_entry = $conn->prepare(
        "SELECT timestamp FROM attendance_records WHERE user_id = ? AND type = 'ENTRADA' ORDER BY timestamp DESC, id DESC LIMIT 1"
    );
    $stmt_entry->bind_param("i", $user_id);
    $stmt_entry->execute();
    $res_entry = $stmt_entry->get_result();
    $entry_timestamp = $res_entry->num_rows ? $res_entry->fetch_assoc()['timestamp'] : null;
    $stmt_entry->close();

    if ($entry_timestamp) {
        $workedSeconds = max(0, strtotime($now) - strtotime($entry_timestamp));
        $workedMinutes = (int)floor($workedSeconds / 60);
        $shiftEndTs = strtotime($today . ' ' . $shift_end_time);
        $overtimeMinutes = max(0, (int)floor((strtotime($now) - $shiftEndTs) / 60));
    }
}

// Limitar comentario
$comment = mb_substr($comment, 0, 60);

try {
    $stmt_ins = $conn->prepare(
        "INSERT INTO attendance_records (user_id, type, method, comment, timestamp, entry_status, minutes_late, worked_minutes, overtime_minutes, permission_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt_ins->bind_param(
        "isssssiiii",
        $user_id,
        $type,
        $method,
        $comment,
        $now,
        $entryStatus,
        $minutesLate,
        $workedMinutes,
        $overtimeMinutes,
        $permissionId
    );
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
        'id'         => (int)$record_id,
        'user_id'    => $user_id,
        'user_name'  => $user_name,
        'type'       => $type,
        'method'     => $method,
        'timestamp'  => $now,
        'entry_status' => $entryStatus,
        'minutes_late' => $minutesLate,
        'worked_minutes' => $workedMinutes,
        'overtime_minutes' => $overtimeMinutes,
        'permission_id' => $permissionId,
        'entry_timestamp' => $type === 'SALIDA' ? $entry_timestamp : $now,
        'exit_timestamp'  => $type === 'SALIDA' ? $now : null,
        'comment'    => $comment
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
