<?php
// /src/php/db_connection.php
// La BD sigue siendo KitchenLink (mismo contenedor/servidor que antes).
// Las variables vienen del .env que escribe el pipeline via Vault.

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_USER', getenv('DB_USER') ?: 'KitchenLink');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'KitchenLink');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(503);
    die(json_encode(['ok' => false, 'msg' => 'Error de conexión a la base de datos.']));
}

$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '-06:00'"); // America/Monterrey (UTC-6)
date_default_timezone_set('America/Monterrey'); // PHP date() también en hora local