<?php
/**
 * KitchenLink - Webhook de Migración de Base de Datos (CD)
 * Versión con soporte multi_query para esquemas complejos.
 */

// ==========================================================
// 1. CAPA DE SEGURIDAD (Validación de Token)
// ==========================================================
$secret_token = "KITCHENLINK_TOKEN_SECRETO_123";

if (!isset($_GET['token']) || $_GET['token'] !== $secret_token) {
    http_response_code(403);
    die("🚫 Acceso denegado. Pipeline Token inválido o ausente.");
}

// ==========================================================
// 2. CONEXIÓN A LA BASE DE DATOS
// ==========================================================
require_once 'db_connection.php';

echo "<h2>🚀 Iniciando Despliegue de Base de Datos KitchenLink...</h2>";
echo "Conexión al servidor establecida exitosamente.<br><br>";

// ==========================================================
// 3. ESTRUCTURA SQL COMPLETA (Heredoc Syntax)
// ==========================================================
// Toda tu estructura tal cual, procesada como un solo bloque masivo.
$sql_dump = <<<SQL

-- ========= TABLA DE ROLES =========
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT,
    rol_name VARCHAR(50) NOT NULL,
    CONSTRAINT pk_roles PRIMARY KEY (id),
    CONSTRAINT uq_rol_name UNIQUE (rol_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO roles (id, rol_name) VALUES
(1, 'gerente'), (2, 'mesero'), (3, 'jefe de cocina'),
(4, 'hostess'), (5, 'encargado de barra'), (6, 'cajero');

-- ========= TABLA DE USUARIOS =========
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT,
    user VARCHAR(25) NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    rol_id INT,
    session_token VARCHAR(255) NULL DEFAULT NULL,
    status ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    CONSTRAINT pk_users PRIMARY KEY (id),
    CONSTRAINT uq_user UNIQUE (user),
    CONSTRAINT fk_users_roles FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========= TABLA DE MESAS =========
CREATE TABLE IF NOT EXISTS tables (
    id INT AUTO_INCREMENT,
    table_name VARCHAR(50) NOT NULL,
    status ENUM('disponible', 'ocupado') NOT NULL DEFAULT 'disponible',
    status_changed_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT pk_tables PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO tables (table_name) VALUES
('Mesa 10'), ('Mesa 11'), ('Mesa 12'), ('Mesa 13'),
('Mesa 20'), ('Mesa 21'), ('Mesa 22'), ('Mesa 23'),
('Mesa 30'), ('Mesa 40'), ('Mesa 41'), ('Mesa 42'),
('Mesa 50'), ('Mesa 51'), ('Mesa 52'),
('Mesa 60'), ('Mesa 61'), ('Mesa 62'),
('Mesa 70'), ('Mesa 71'), ('Mesa 72'),
('Mesa 80'), ('Mesa 81');

-- ========= TABLA DE RESERVACIONES (ACTIVAS) =========
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT,
    hostess_id INT,
    customer_name VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(20),
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    number_of_people INT NOT NULL,
    special_requests TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_reservations PRIMARY KEY (id),
    CONSTRAINT fk_reservations_users FOREIGN KEY (hostess_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reservation_date (reservation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========= TABLA DE UNIÓN RESERVATION_TABLES =========
CREATE TABLE IF NOT EXISTS reservation_tables (
    reservation_id INT NOT NULL,
    table_id INT NOT NULL,
    CONSTRAINT pk_reservation_tables PRIMARY KEY (reservation_id, table_id),
    CONSTRAINT fk_rt_reservations FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    CONSTRAINT fk_rt_tables FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========= TABLA DE HISTORIAL DE RESERVACIONES =========
CREATE TABLE IF NOT EXISTS reservations_history (
    id INT AUTO_INCREMENT,
    original_reservation_id INT NOT NULL,
    hostess_id INT,
    table_id INT, 
    customer_name VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(20),
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    number_of_people INT NOT NULL,
    special_requests TEXT,
    created_at TIMESTAMP NULL,
    archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    final_status ENUM('completada','cancelada','no-show') NOT NULL,
    CONSTRAINT pk_reservations_history PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========= TABLAS DE ESPERA =========
CREATE TABLE IF NOT EXISTS waiting_list (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_name VARCHAR(150) NOT NULL,
  number_of_people INT NOT NULL,
  customer_phone VARCHAR(20) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS waiting_list_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  original_id INT NOT NULL,
  customer_name VARCHAR(150) NOT NULL,
  number_of_people INT NOT NULL,
  customer_phone VARCHAR(20) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL,
  archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('seated', 'cancelled') NOT NULL,
  tables_assigned VARCHAR(255) DEFAULT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========= TABLA DE MESAS PARA MESEROS =========
CREATE TABLE IF NOT EXISTS restaurant_tables (
    table_id INT PRIMARY KEY AUTO_INCREMENT, 
    table_number INT NOT NULL,
    assigned_server_id INT NOT NULL, 
    client_count INT NOT NULL,
    occupied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    pre_bill_status ENUM('ACTIVE', 'REQUESTED') NOT NULL DEFAULT 'ACTIVE',
    locked_by INT NULL,
    locked_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT UK_TableNumber UNIQUE (table_number),
    CONSTRAINT FK_ServerAssignment FOREIGN KEY (assigned_server_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_table_lock_user FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========= MENÚ Y MODIFICADORES =========
CREATE TABLE IF NOT EXISTS menu_categories (
    category_id INT NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    preparation_area ENUM('COCINA', 'BARRA') NOT NULL DEFAULT 'COCINA', 
    CONSTRAINT PK_MenuCategory PRIMARY KEY (category_id), 
    CONSTRAINT UK_CategoryName UNIQUE (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS modifier_groups (
    group_id INT NOT NULL AUTO_INCREMENT,
    group_name VARCHAR(100) NOT NULL,
    selection_type VARCHAR(20) DEFAULT 'SINGLE',
    CONSTRAINT PK_ModifierGroup PRIMARY KEY (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS modifiers (
    modifier_id INT NOT NULL AUTO_INCREMENT,
    group_id INT NOT NULL,
    modifier_name VARCHAR(100) NOT NULL,
    modifier_price DECIMAL(10, 2) DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    stock_quantity INT NULL DEFAULT NULL,
    CONSTRAINT PK_ModifierOption PRIMARY KEY (modifier_id),
    CONSTRAINT FK_OptionGroup FOREIGN KEY (group_id) REFERENCES modifier_groups(group_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    product_id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    category_id INT NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    modifier_group_id INT NULL,
    stock_quantity INT NULL DEFAULT NULL,
    CONSTRAINT PK_Product PRIMARY KEY (product_id),
    CONSTRAINT FK_ProductCategory FOREIGN KEY (category_id) REFERENCES menu_categories(category_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT FK_ProductModifierGroup FOREIGN KEY (modifier_group_id) REFERENCES modifier_groups(group_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========= ÓRDENES Y DETALLES =========
CREATE TABLE IF NOT EXISTS orders (
    order_id INT NOT NULL AUTO_INCREMENT,
    table_id INT NOT NULL,
    server_id INT NOT NULL, 
    order_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) NOT NULL DEFAULT 'PENDING', 
    total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    CONSTRAINT PK_Order PRIMARY KEY (order_id),
    CONSTRAINT FK_OrderTable FOREIGN KEY (table_id) REFERENCES restaurant_tables(table_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT FK_OrderServer FOREIGN KEY (server_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_details (
    detail_id INT NOT NULL AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price_at_order DECIMAL(10, 2) NOT NULL,
    special_notes TEXT,
    item_status ENUM('PENDIENTE', 'EN_PREPARACION', 'LISTO', 'COMPLETADO', 'CANCELADO') NOT NULL DEFAULT 'PENDIENTE',
    preparation_area ENUM('COCINA', 'BARRA') NOT NULL,
    modifier_id INT NULL,
    added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    batch_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_cancelled BOOLEAN NOT NULL DEFAULT FALSE,
    cancellation_reason VARCHAR(255) DEFAULT NULL, 
    completed_at TIMESTAMP NULL DEFAULT NULL,
    service_time INT NOT NULL DEFAULT 1,
    notified_waiter BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT PK_OrderDetail PRIMARY KEY (detail_id),
    CONSTRAINT FK_DetailOrder FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT FK_DetailProduct FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT FK_DetailModifier FOREIGN KEY (modifier_id) REFERENCES modifiers(modifier_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========= HISTORIAL DE PRODUCCIÓN =========
CREATE TABLE IF NOT EXISTS kitchen_production_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_detail_id INT NOT NULL,
    order_id INT NOT NULL,
    table_number INT NOT NULL,
    batch_timestamp TIMESTAMP NOT NULL,
    service_time INT NOT NULL,
    server_name VARCHAR(255),
    product_name VARCHAR(255) NOT NULL,
    modifier_name VARCHAR(255) NULL DEFAULT NULL,
    quantity INT NOT NULL,
    special_notes TEXT,
    timestamp_added DATETIME NOT NULL,
    timestamp_completed DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_completed_date (timestamp_completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bar_production_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_detail_id INT NOT NULL,
    order_id INT NOT NULL,
    table_number INT NOT NULL,
    batch_timestamp TIMESTAMP NOT NULL,
    service_time INT NOT NULL,
    server_name VARCHAR(255),
    product_name VARCHAR(255) NOT NULL,
    modifier_name VARCHAR(255) NULL DEFAULT NULL,
    quantity INT NOT NULL,
    special_notes TEXT,
    timestamp_added DATETIME NOT NULL,
    timestamp_completed DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_completed_date (timestamp_completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========= SEGURIDAD =========
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(25) NULL,
    device_identifier VARCHAR(64) NULL DEFAULT NULL,
    attempt_time DATETIME NOT NULL,
    INDEX idx_ip_time (ip_address, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========= FINANZAS Y CAJA =========
CREATE TABLE IF NOT EXISTS sales_history (
  sale_id INT AUTO_INCREMENT PRIMARY KEY,
  original_order_id INT NOT NULL,
  table_number INT NOT NULL,
  client_count INT NOT NULL,
  server_name VARCHAR(255) NOT NULL,
  cashier_id INT NULL, 
  time_occupied TIMESTAMP NOT NULL,
  payment_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  subtotal DECIMAL(10, 2) NOT NULL,
  tax_amount DECIMAL(10, 2) NOT NULL,
  discount_amount DECIMAL(10, 2) DEFAULT 0.00,
  tip_amount_card DECIMAL(10, 2) DEFAULT 0.00,
  grand_total DECIMAL(10, 2) NOT NULL,
  is_courtesy BOOLEAN NOT NULL DEFAULT FALSE,
  payment_methods JSON,
  CONSTRAINT fk_sales_cashier FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sales_history_details (
  sale_detail_id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  product_name VARCHAR(255) NOT NULL,
  modifier_name VARCHAR(255) NULL,
  quantity INT NOT NULL,
  price_at_order DECIMAL(10, 2) NOT NULL,
  was_cancelled BOOLEAN NOT NULL DEFAULT FALSE,
  CONSTRAINT fk_sale_history FOREIGN KEY (sale_id) REFERENCES sales_history(sale_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cash_shifts (
  shift_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id_opened INT NOT NULL,
  start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  end_time TIMESTAMP NULL DEFAULT NULL,
  starting_cash DECIMAL(10, 2) NOT NULL,
  ending_cash DECIMAL(10, 2) NULL DEFAULT NULL,
  status ENUM('OPEN', 'CLOSED') NOT NULL DEFAULT 'OPEN',
  CONSTRAINT fk_shift_user FOREIGN KEY (user_id_opened) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SQL;

// ==========================================================
// 4. EJECUCIÓN DEL BATCH (MULTI_QUERY)
// ==========================================================
$errores = false;

if ($conn->multi_query($sql_dump)) {
    do {
        // Almacena el resultado para limpiar la memoria y permitir que pase a la siguiente consulta
        if ($result = $conn->store_result()) {
            $result->free();
        }
        // Revisa si la consulta individual falló
        if ($conn->error) {
            echo "❌ Error en bloque SQL: " . $conn->error . "<br>";
            $errores = true;
        }
    } while ($conn->more_results() && $conn->next_result());
} else {
    echo "❌ Fallo crítico al procesar multi_query: " . $conn->error . "<br>";
    $errores = true;
}

// ==========================================================
// 5. REPORTE FINAL AL PIPELINE
// ==========================================================
echo "<hr>";
if (!$errores) {
    echo "<h3>🎉 ¡Migración Masiva de Base de Datos completada sin errores!</h3>";
} else {
    http_response_code(500);
    echo "<h3>⚠️ El despliegue terminó con errores. Revisa la estructura.</h3>";
}
?>