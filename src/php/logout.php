<?php
// logout.php - VERSIÓN CORREGIDA Y SEGURA

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Obtener los datos ANTES de destruir la sesión
$userId = $_SESSION['user_id'] ?? null;
$sessionToken = $_SESSION['session_token'] ?? null; // <-- Obtenemos también el token

// 2. CONEXIÓN Y BORRADO CONDICIONAL DE TOKEN EN DB
if ($userId && $sessionToken) { // <-- Nos aseguramos de tener ambos datos
    require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';
    
    if (isset($conn) && $conn->connect_errno === 0) {
        try {
            // 🔑 CAMBIO CLAVE: Añadimos "AND session_token = ?"
            // Ahora, solo borramos el token si el ID y el TOKEN coinciden con
            // los de la sesión que está intentando cerrar.
            $stmt = $conn->prepare("UPDATE users SET session_token = NULL WHERE id = ? AND session_token = ?");
            $stmt->bind_param("is", $userId, $sessionToken); // <-- Pasamos ambos parámetros
            $stmt->execute();
            $stmt->close();
            $conn->close(); 
            
        } catch (\Throwable $e) {
            error_log("Error al limpiar token en logout: " . $e->getMessage());
        }
    }
}

// 3. Destruir la sesión PHP (Limpieza de servidor)
// Esto se ejecuta siempre, sin importar si el token en la DB se borró o no.
session_unset();
session_destroy();

// 4. Destruir la cookie de sesión del navegador (Limpieza de cliente)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 5. Redirigir al inicio
header("Location: /index.php?status=logout");
exit();
?>
