<?php
// /src/php/api/config/save_config.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/php/security/check_session_api.php';

if ($_SESSION['rol'] !== 'admin') {
    echo json_encode(['ok' => false, 'msg' => 'Sin permisos.']); exit();
}

$data  = json_decode(file_get_contents('php://input'), true);
$clave = trim($data['clave'] ?? '');
$valor = trim($data['valor'] ?? '');

if (!$clave) {
    echo json_encode(['ok' => false, 'msg' => 'Clave requerida.']); exit();
}

// Crear tabla si no existe
$conn->query("
    CREATE TABLE IF NOT EXISTS sistema_config (
        clave      VARCHAR(50)  NOT NULL PRIMARY KEY,
        valor      VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$stmt = $conn->prepare("
    INSERT INTO sistema_config (clave, valor) VALUES (?, ?)
    ON DUPLICATE KEY UPDATE valor = VALUES(valor)
");
$stmt->bind_param('ss', $clave, $valor);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => $ok]);
