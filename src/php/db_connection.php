<?php
// Archivo de conexion con la base de datos
// IMPORTANTE: Modificar esto para correrlo de forma local segun sean las credenciales de tu base de datos

// -----------------------------------------------------
// 💡 SOLUCIÓN: Verifica si $conn YA EXISTE
// Si $conn existe, no hace nada y usa la conexión abierta previamente.
// Esto evita el problema de abrir y cerrar prematuramente la conexión.
// -----------------------------------------------------
if (!isset($conn)) {
    $servername = "localhost";
    $username_db = "KitchenLink";
    $password_db = "Kl03102026?";
    $dbname = "KitchenLink";

    // Crea la conexión
    $conn = new mysqli($servername, $username_db, $password_db, $dbname);

    // 🟢 SOLUCIÓN FINAL PARA EL ERROR DE TIMEZONE
    // Usa el desplazamiento UTC-6, que es compatible con todos los servidores MySQL.
    $conn->query("SET time_zone = '-05:00'");
    
    // Verifica la conexión
    if ($conn->connect_error) {
        // En lugar de die(), usa un mecanismo que permita a tu API manejar el error.
        // Die() es correcto para errores fatales de conexión.
        die("Error de conexión a la base de datos: " . $conn->connect_error);
    }

    // --- Forzar UTF-8 para que json_encode funcione correctamente ---
    $conn->set_charset("utf8mb4");
}

// ⚠️ IMPORTANTE: No incluyas `$conn->close();` en este archivo. 
// Las APIs que necesitan la conexión deben cerrarla explícitamente al final (o dejar que PHP lo haga).
?>
