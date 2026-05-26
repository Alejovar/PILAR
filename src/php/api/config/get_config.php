<?php
// /src/php/api/config/get_config.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/db_connection.php';

// Crear tabla si no existe
$conn->query("
    CREATE TABLE IF NOT EXISTS sistema_config (
        clave    VARCHAR(50)  NOT NULL PRIMARY KEY,
        valor    VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$clave = trim($_GET['clave'] ?? '');
if (!$clave) {
    // Devolver toda la config
    $rows = $conn->query("SELECT clave, valor FROM sistema_config")->fetch_all(MYSQLI_ASSOC);
    $config = [];
    foreach ($rows as $r) $config[$r['clave']] = $r['valor'];
    echo json_encode(['ok' => true, 'config' => $config]);
} else {
    $stmt = $conn->prepare("SELECT valor FROM sistema_config WHERE clave = ?");
    $stmt->bind_param('s', $clave);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode(['ok' => true, 'valor' => $row['valor'] ?? null]);
}
