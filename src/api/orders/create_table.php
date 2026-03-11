<?php
// /src/api/orders/create_table.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php'; 

// 1. VERIFICACIÓN DE TURNO
$stmt_shift = $conn->prepare("SELECT 1 FROM cash_shifts WHERE status = 'OPEN' LIMIT 1");
$stmt_shift->execute();
if ($stmt_shift->get_result()->num_rows === 0) {
    $stmt_shift->close();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'El turno de caja está cerrado.']);
    exit;
}
$stmt_shift->close();

// 2. VERIFICAR AUTENTICACIÓN
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$table_number = filter_var($data['table_number'] ?? null, FILTER_VALIDATE_INT);
$client_count = filter_var($data['client_count'] ?? null, FILTER_VALIDATE_INT);

// --- 👇 LÓGICA DE ASIGNACIÓN CORREGIDA 👇 ---
$current_user_id = $_SESSION['user_id'];
$rol_id = $_SESSION['rol_id'];

// Por defecto, la mesa se asigna a quien la crea (el Mesero se la auto-asigna)
$target_server_id = $current_user_id; 

// ÚNICAMENTE el Gerente (Rol 1) puede asignar mesas a otros.
// La cajera ya no está incluida aquí.
if ($rol_id == 1 && !empty($data['assigned_server_id'])) {
    $target_server_id = intval($data['assigned_server_id']);
}
// --- 👆 FIN DE LA CORRECCIÓN 👆 ---


// Validaciones
$min_table = 1; $max_table = 9999;
$min_client = 1; $max_client = 99;

if (!$table_number || $table_number < $min_table || $table_number > $max_table) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => "Número de mesa inválido."]);
    exit();
}

if (!$client_count || $client_count < $min_client || $client_count > $max_client) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => "Número de personas inválido."]);
    exit();
}

// Verificar duplicados
$sql_check = "SELECT COUNT(table_id) FROM restaurant_tables WHERE table_number = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $table_number);
$stmt_check->execute();
$stmt_check->bind_result($is_duplicate);
$stmt_check->fetch();
$stmt_check->close();

if ($is_duplicate > 0) {
    http_response_code(409); 
    echo json_encode(['success' => false, 'message' => "La mesa {$table_number} ya existe."]);
    exit();
}

// Insertar
$sql_insert = "INSERT INTO restaurant_tables (table_number, assigned_server_id, client_count) VALUES (?, ?, ?)";

try {
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("iii", $table_number, $target_server_id, $client_count); 
    $stmt_insert->execute();
    $stmt_insert->close();

    echo json_encode(['success' => true, 'message' => "Mesa creada."]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de BD.']);
}
?>
