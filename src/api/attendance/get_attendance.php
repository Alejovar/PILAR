<?php
// /src/api/attendance/get_attendance.php
// Devuelve registros de asistencia.
// - Sin sesión: devuelve solo los registros del user_id enviado como GET param (vista empleado pública)
// - Con sesión gerente: puede filtrar por any user_id y/o rango de fechas

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';
header('Content-Type: application/json');

date_default_timezone_set('America/Mexico_City');

// Detectar si hay sesión activa de gerente
session_start();
$is_manager = isset($_SESSION['rol_id']) && $_SESSION['rol_id'] == 1;

$user_id    = intval($_GET['user_id']    ?? 0);
$date_from  = trim($_GET['date_from']   ?? '');
$date_to    = trim($_GET['date_to']     ?? '');

// Validar fechas
$date_from = $date_from ?: date('Y-m-01'); // Inicio del mes actual por defecto
$date_to   = $date_to   ?: date('Y-m-d');  // Hoy por defecto

// Si NO es gerente, solo puede ver sus propios registros y necesita enviar user_id
if (!$is_manager) {
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de usuario requerido']); exit;
    }
}

try {
    $conditions = ["DATE(ar.timestamp) BETWEEN ? AND ?"];
    $types      = "ss";
    $params     = [$date_from, $date_to];

    if ($user_id > 0) {
        $conditions[] = "ar.user_id = ?";
        $types       .= "i";
        $params[]     = $user_id;
    }

    $where = "WHERE " . implode(" AND ", $conditions);

        $sql = "SELECT ar.id, ar.user_id, u.name AS user_name, u.user AS username,
                 u.nss, u.plant, u.salary_per_day, u.tax_rate, u.overtime_rate,
                 r.rol_name,
                 ar.type, ar.method, ar.timestamp, ar.comment,
                 ar.entry_status, ar.minutes_late, ar.worked_minutes, ar.overtime_minutes, ar.permission_id,
                 ap.reason AS permission_reason
            FROM attendance_records ar
            JOIN users u ON u.id = ar.user_id
            LEFT JOIN roles r ON r.id = u.rol_id
             LEFT JOIN attendance_permissions ap ON ap.id = ar.permission_id
            $where
            ORDER BY ar.timestamp DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'records' => $records]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
