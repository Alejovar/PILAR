<?php
if (!isset($conn)) {
    $servername = getenv('DB_HOST') ?: 'db';
    $username_db = getenv('DB_USER');
    $password_db = getenv('DB_PASSWORD');
    $dbname      = getenv('DB_NAME') ?: 'KitchenLink';

    // Intentamos la conexión de forma silenciosa para que no mande Warnings
    $conn = @new mysqli($servername, $username_db, $password_db, $dbname);

    if ($conn->connect_error) {
        // En lugar de die(), mandamos un JSON que el JS sí entienda
        header('Content-Type: application/json');
        echo json_encode([
            "status" => "error",
            "message" => "Error de conexión: " . $conn->connect_error,
            "debug_user" => $username_db // Esto te dirá si Vault mandó el usuario bien
        ]);
        exit;
    }

    $conn->query("SET time_zone = '-05:00'");
    $conn->set_charset("utf8mb4");
}
?>