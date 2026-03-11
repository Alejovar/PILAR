<?php
// check_session.php - UNIVERSAL PARA TODAS LAS INTERFACES

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Validar que exista sesión
if (!isset($_SESSION['user_id'], $_SESSION['session_token'])) {
    session_unset();
    session_destroy();
    header('Location: /index.php?error=session_expired');
    exit;
}

// 2. Conexión a DB
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';
if (!isset($conn) || $conn->connect_error) {
    die("Error de conexión a la base de datos");
}

// 3. Obtener token y rol de la base de datos
$stmt = $conn->prepare("SELECT session_token, rol_id, name FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user_row = $result->fetch_assoc();
$stmt->close();

// 4. Validar token
if (!$user_row || $user_row['session_token'] !== $_SESSION['session_token']) {
    // Solo destruir la sesión local, no tocar token en DB
    session_unset();
    session_destroy();
    header('Location: /index.php?error=session_expired');
    exit;
}

// 5. Guardar rol y nombre actualizado en sesión
$_SESSION['rol_id'] = $user_row['rol_id'];
$_SESSION['user_name'] = $user_row['name'];
?>
