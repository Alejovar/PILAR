<?php
// /src/api/orders/lock_table.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

// 1. Verificar Turno
$stmt_shift = $conn->prepare("SELECT 1 FROM cash_shifts WHERE status = 'OPEN' LIMIT 1");
$stmt_shift->execute();
if ($stmt_shift->get_result()->num_rows === 0) {
    $stmt_shift->close();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'El turno de caja está cerrado.']);
    exit;
}
$stmt_shift->close();

$input = json_decode(file_get_contents('php://input'), true);
$table_number = $input['table_number'] ?? null;
$user_id = $_SESSION['user_id']; // QUIÉN INTENTA ENTRAR (Sea Gerente o Mesero)

if (!$table_number) {
    echo json_encode(['success' => false, 'message' => 'Mesa no especificada.']);
    exit;
}

try {
    // 2. Ver quién está adentro
    $sql_check = "SELECT t.locked_by, t.locked_at, u.name as locker_name 
                  FROM restaurant_tables t 
                  LEFT JOIN users u ON t.locked_by = u.id 
                  WHERE t.table_number = ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("i", $table_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $table = $result->fetch_assoc();
    $stmt->close();

    if (!$table) throw new Exception("Mesa no encontrada.");

    $current_locker_id = $table['locked_by']; // Quién la tiene bloqueada
    $locker_name = $table['locker_name'] ?? 'Otro usuario';
    $locked_at_ts = $table['locked_at'] ? strtotime($table['locked_at']) : 0;
    $now = time();
    $timeout_seconds = 60; // 1 minuto de tolerancia

    $can_enter = false;

    // --- LÓGICA DE SEMÁFORO ---
    
    if ($current_locker_id === null) {
        // A. NADIE la tiene -> Pase
        $can_enter = true;
    } elseif ($current_locker_id == $user_id) {
        // B. YO MISMO la tengo -> Pase (renovar)
        $can_enter = true;
    } elseif (($now - $locked_at_ts) > $timeout_seconds) {
        // C. CADUCÓ (hace más de 60s) -> Pase (robar bloqueo)
        $can_enter = true;
    } else {
        // D. OCUPADA por alguien más y está activo -> BLOQUEO
        // Aquí cae el Gerente si el Mesero está dentro.
        // Aquí cae el Mesero si el Gerente está dentro.
        echo json_encode([
            'success' => false, 
            'message' => "Mesa bloqueada por: {$locker_name}.\nEspera a que termine..."
        ]);
        exit;
    }

    // 3. Aplicar Bloqueo
    if ($can_enter) {
        $sql_lock = "UPDATE restaurant_tables SET locked_by = ?, locked_at = NOW() WHERE table_number = ?";
        $stmt_lock = $conn->prepare($sql_lock);
        $stmt_lock->bind_param("ii", $user_id, $table_number);
        $stmt_lock->execute();
        $stmt_lock->close();

        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
