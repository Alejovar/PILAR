<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

$input = json_decode(file_get_contents('php://input'), true);
$table_number = $input['table_number'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$table_number) {
    echo json_encode(['success' => false]);
    exit;
}

// Liberamos la mesa (ponemos NULL) solo si yo la tengo bloqueada
$sql = "UPDATE restaurant_tables SET locked_by = NULL, locked_at = NULL 
        WHERE table_number = ? AND locked_by = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $table_number, $user_id);
$stmt->execute();

echo json_encode(['success' => true]);
?>
