<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session.php';
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

$input = json_decode(file_get_contents('php://input'), true);
$table_number = $input['table_number'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$table_number) exit;

// Solo renovamos si YO sigo siendo el dueño del bloqueo
$sql = "UPDATE restaurant_tables 
        SET locked_at = NOW() 
        WHERE table_number = ? AND locked_by = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $table_number, $user_id);
$stmt->execute();

echo json_encode(['success' => $stmt->affected_rows > 0]);
?>
