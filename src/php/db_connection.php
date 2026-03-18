<?php
if (!isset($conn)) {
    $servername = getenv('DB_HOST');
    $username_db = getenv('DB_USER');
    $password_db = getenv('DB_PASSWORD');
    $dbname      = getenv('DB_NAME');

    $conn = new mysqli($servername, $username_db, $password_db, $dbname);

    $conn->query("SET time_zone = '-05:00'");

    if ($conn->connect_error) {
        die("Error de conexión a la base de datos: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
}
?>