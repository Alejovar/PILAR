<?php
// src/php/deploy_db.php - Webhook seguro para CI/CD de GitLab
$secret_token = "KITCHENLINK_TOKEN_SECRETO_123"; 

if (!isset($_GET['token']) || $_GET['token'] !== $secret_token) {
    http_response_code(403);
    die("Acceso denegado.");
}

require_once __DIR__ . '/db_connection.php'; 

if (!isset($conn) || $conn->connect_error) {
    die("Error de conexion.");
}

// SQL Limpio para el Pipeline
$sql = "
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO roles (id, rol_name) VALUES
(1, 'gerente'), (2, 'mesero'), (3, 'jefe de cocina'),
(4, 'hostess'), (5, 'encargado de barra'), (6, 'cajero');

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user VARCHAR(25) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    rol_id INT,
    status ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    CONSTRAINT fk_users_roles FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    status ENUM('disponible', 'ocupado') NOT NULL DEFAULT 'disponible'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cash_shifts (
    shift_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id_opened INT NOT NULL,
    start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('OPEN', 'CLOSED') NOT NULL DEFAULT 'OPEN',
    CONSTRAINT fk_shift_user FOREIGN KEY (user_id_opened) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Ejecucion
if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) { $result->free(); }
    } while ($conn->more_results() && $conn->next_result());
    echo "✅ Sincronizado";
} else {
    echo "❌ Error: " . $conn->error;
}
$conn->close();
?>