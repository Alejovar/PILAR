<?php
// src/php/deploy_db.php - Webhook seguro para CI/CD de GitLab
$secret_token = "KITCHENLINK_TOKEN_SECRETO_123"; 

if (!isset($_GET['token']) || $_GET['token'] !== $secret_token) {
    http_response_code(403);
    die("❌ Acceso denegado. Token inválido.");
}

require_once __DIR__ . '/db_connection.php'; 

if (!isset($conn) || $conn->connect_error) {
    die("❌ Error fatal: No se pudo conectar a la base de datos.");
}

// Creamos tablas básicas de forma segura para no romper lo que ya tienes
$sql = "
-- Script para creacion de las tablas en la base de datos, con algunos registros inluidos, como los roles que existen
-- un restaurante y ademas las mesas que existen en el restaurante para el cual esta elaborado
-- este proyecto, en caso de ser usado para otro, por el momento el unico registro que seria necesario modificar seria
-- el de las mesas
-- ========= TABLA DE ROLES =========
-- Almacena los diferentes tipos de empleados.
CREATE TABLE roles (
    id INT AUTO_INCREMENT,
    rol_name VARCHAR(50) NOT NULL,
    CONSTRAINT pk_roles PRIMARY KEY (id),
    CONSTRAINT uq_rol_name UNIQUE (rol_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertamos los roles iniciales
INSERT INTO roles (id, rol_name) VALUES
(1, 'gerente'),
(2, 'mesero'),
(3, 'jefe de cocina'),
(4, 'hostess'),
(5, 'encargado de barra'),
(6, 'cajero');


-- ========= TABLA DE USUARIOS =========
-- Guarda la información de los empleados para el inicio de sesión.
CREATE TABLE users (
    id INT AUTO_INCREMENT,
    user VARCHAR(25) NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    rol_id INT,
    CONSTRAINT pk_users PRIMARY KEY (id),
    CONSTRAINT uq_user UNIQUE (user),
    CONSTRAINT fk_users_roles FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users ADD session_token VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE users 
ADD COLUMN status ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO';

-- ========= TABLA DE MESAS =========
-- Contiene las mesas físicas del restaurante y su estado actual.
CREATE TABLE tables (
    id INT AUTO_INCREMENT,
    table_name VARCHAR(50) NOT NULL,
    status ENUM('disponible', 'ocupado') NOT NULL DEFAULT 'disponible',
    status_changed_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT pk_tables PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertamos las mesas del restaurante
INSERT INTO tables (table_name) VALUES
('Mesa 10'), ('Mesa 11'), ('Mesa 12'), ('Mesa 13'),
('Mesa 20'), ('Mesa 21'), ('Mesa 22'), ('Mesa 23'),
('Mesa 30'), ('Mesa 40'), ('Mesa 41'), ('Mesa 42'),
('Mesa 50'), ('Mesa 51'), ('Mesa 52'),
('Mesa 60'), ('Mesa 61'), ('Mesa 62'),
('Mesa 70'), ('Mesa 71'), ('Mesa 72'),
('Mesa 80'), ('Mesa 81');


-- ========= TABLA DE RESERVACIONES (ACTIVAS) =========
-- Guarda los datos de las reservaciones pendientes o futuras.
CREATE TABLE reservations (
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
    INDEX idx_reservation_date (reservation_date) -- Índice para búsquedas rápidas por fecha
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ========= TABLA DE UNIÓN RESERVATION_TABLES =========
-- Conecta una reservación con una o más mesas.
CREATE TABLE reservation_tables (
    reservation_id INT NOT NULL,
    table_id INT NOT NULL,
    CONSTRAINT pk_reservation_tables PRIMARY KEY (reservation_id, table_id),
    CONSTRAINT fk_rt_reservations FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    CONSTRAINT fk_rt_tables FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ========= TABLA DE HISTORIAL DE RESERVACIONES =========
-- Guarda un registro permanente de todas las reservaciones procesadas.
CREATE TABLE reservations_history (
    id INT AUTO_INCREMENT,
    original_reservation_id INT NOT NULL,
    hostess_id INT,
    table_id INT, -- Permite nulos, ya que una reservación puede tener varias mesas
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

-- ========= TABLA DE LISTA DE ESPERA =========
-- Guarda los datos de las personas en la lista de espera
CREATE TABLE `waiting_list` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_name` VARCHAR(150) NOT NULL,
  `number_of_people` INT NOT NULL,
  `customer_phone` VARCHAR(20) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========= TABLA DE HISTORIAL DE LISTA DE ESPERA =========
-- Guarda un registro permanente de todas las personas que estuvieron en lista de espera y ya fueron procesadas.
CREATE TABLE `waiting_list_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `original_id` INT NOT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `number_of_people` INT NOT NULL,
  `customer_phone` VARCHAR(20) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL,
  `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('seated', 'cancelled') NOT NULL COMMENT 'Indica si el cliente fue sentado o canceló.',
  `tables_assigned` VARCHAR(255) DEFAULT NULL COMMENT 'Nombres de las mesas asignadas, separados por comas'
);

-- ========= TABLA DE MESAS PARA MESEROS (ACTUALIZADA) =========
-- Almacena el estado de las mesas actualmente ocupadas, incluyendo quién las atiende, 
-- cuántos clientes hay y el momento exacto en que se ocuparon.

CREATE TABLE restaurant_tables (
    table_id INT PRIMARY KEY AUTO_INCREMENT, 
    table_number INT NOT NULL,
    assigned_server_id INT NOT NULL, 
    client_count INT NOT NULL,
    occupied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Registra el momento exacto en que la mesa es ocupada

    -- Restricción de Unicidad Global
    CONSTRAINT UK_TableNumber UNIQUE (table_number),
    
    -- Llave Foránea al Mesero
    CONSTRAINT FK_ServerAssignment FOREIGN KEY (assigned_server_id) 
        REFERENCES users(id)
        ON DELETE RESTRICT 
        ON UPDATE CASCADE
);

ALTER TABLE restaurant_tables
ADD COLUMN pre_bill_status ENUM('ACTIVE', 'REQUESTED') NOT NULL DEFAULT 'ACTIVE' 
COMMENT 'Estado de la ocupación: ACTIVE=en curso, REQUESTED=cuenta solicitada.';

ALTER TABLE restaurant_tables
ADD COLUMN locked_by INT NULL COMMENT 'ID del usuario que está editando actualmente la mesa',
ADD COLUMN locked_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Momento exacto en que inició la edición',
ADD CONSTRAINT fk_table_lock_user FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL;


-- ######################################################################
-- # 2. ESTRUCTURA DE MENÚ DINÁMICO Y MODIFICADORES
-- ######################################################################

CREATE TABLE IF NOT EXISTS menu_categories (
    category_id INT NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    
    -- ✅ NUEVA COLUMNA DE DESTINO
    preparation_area ENUM('COCINA', 'BARRA') NOT NULL DEFAULT 'COCINA', 
    
    CONSTRAINT PK_MenuCategory PRIMARY KEY (category_id), 
    CONSTRAINT UK_CategoryName UNIQUE (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.2. Tabla de Grupos de Modificadores
CREATE TABLE IF NOT EXISTS modifier_groups (
    group_id INT NOT NULL AUTO_INCREMENT,
    group_name VARCHAR(100) NOT NULL,
    selection_type VARCHAR(20) DEFAULT 'SINGLE',
    
    CONSTRAINT PK_ModifierGroup PRIMARY KEY (group_id)
);

-- 2.3. Tabla de Opciones de Modificadores
CREATE TABLE IF NOT EXISTS modifiers (
    modifier_id INT NOT NULL AUTO_INCREMENT,
    group_id INT NOT NULL,
    modifier_name VARCHAR(100) NOT NULL,
    modifier_price DECIMAL(10, 2) DEFAULT 0.00,
    
    CONSTRAINT PK_ModifierOption PRIMARY KEY (modifier_id),
    CONSTRAINT FK_OptionGroup FOREIGN KEY (group_id) REFERENCES modifier_groups(group_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

ALTER TABLE modifiers
ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Disponible, 0=Agotado';

ALTER TABLE modifiers
ADD COLUMN stock_quantity INT NULL DEFAULT NULL COMMENT 'NULL=Infinito, Número=Cuenta regresiva';

-- 2.4. Tabla de Productos (Catálogo)
CREATE TABLE IF NOT EXISTS products (
    product_id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    category_id INT NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    modifier_group_id INT NULL,
    
    CONSTRAINT PK_Product PRIMARY KEY (product_id),
    CONSTRAINT FK_ProductCategory FOREIGN KEY (category_id) REFERENCES menu_categories(category_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT FK_ProductModifierGroup FOREIGN KEY (modifier_group_id) REFERENCES modifier_groups(group_id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

ALTER TABLE products
ADD COLUMN stock_quantity INT NULL DEFAULT NULL COMMENT 'NULL = Infinito, Número = Cantidad disponible';


-- ######################################################################
-- # 3. ESTRUCTURA DE ÓRDENES Y DETALLES
-- ######################################################################

-- 3.1. Tabla de Órdenes (Comandas)
-- Se define con el motor InnoDB desde el inicio.
-- Se asume que las tablas 'restaurant_tables' y 'users' ya existen.
CREATE TABLE IF NOT EXISTS orders (
    order_id INT NOT NULL AUTO_INCREMENT,
    table_id INT NOT NULL,
    server_id INT NOT NULL, 
    order_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) NOT NULL DEFAULT 'PENDING', 
    
    CONSTRAINT PK_Order PRIMARY KEY (order_id),
    CONSTRAINT FK_OrderTable FOREIGN KEY (table_id) REFERENCES restaurant_tables(table_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT FK_OrderServer FOREIGN KEY (server_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE orders
ADD COLUMN total DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'El total calculado de la orden';

---



-- Estructura actualizada para la tabla: order_details
-- Incluye la hora de adición (added_at) y la marca de lote (batch_timestamp)

CREATE TABLE IF NOT EXISTS order_details (
    detail_id INT NOT NULL AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price_at_order DECIMAL(10, 2) NOT NULL,
    special_notes TEXT,
    
    -- ✅ ESTADO DE FLUJO: Para que Cocina/Barra pueda marcar el proceso.
    item_status ENUM('PENDIENTE', 'EN_PREPARACION', 'LISTO', 'COMPLETADO', 'CANCELADO') NOT NULL DEFAULT 'PENDIENTE',
    
    -- ✅ ÁREA DE PREPARACIÓN: Para filtrar la comanda a Cocina o Barra.
    preparation_area ENUM('COCINA', 'BARRA') NOT NULL COMMENT 'Área de preparación determinada por la categoría del producto.',
    
    modifier_id INT NULL,
    added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    batch_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- ✅ CAMPOS DE CANCELACIÓN: (Auditoría Gerencial)
    is_cancelled BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Indica si el ítem fue cancelado (true) o no (false).',
    cancellation_reason VARCHAR(255) DEFAULT NULL COMMENT 'Razón por la que el ítem fue marcado como cancelado.', 

    -- ✅ TIEMPO DE FINALIZACIÓN: Para que el mesero sepa cuándo recoger el producto.
    completed_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp cuando el ítem fue marcado como LISTO para ser recogido.',
    
    CONSTRAINT PK_OrderDetail PRIMARY KEY (detail_id),
    CONSTRAINT FK_DetailOrder FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT FK_DetailProduct FOREIGN KEY (product_id) REFERENCES products(product_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT FK_DetailModifier FOREIGN KEY (modifier_id) REFERENCES modifiers(modifier_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE order_details
ADD COLUMN service_time INT NOT NULL DEFAULT 1 COMMENT 'Secuencia de servicio (tiempo) para agrupar ítems en la comanda. Por defecto es 1.';
ALTER TABLE order_details
ADD COLUMN notified_waiter BOOLEAN NOT NULL DEFAULT FALSE 
COMMENT 'FALSE=No notificado, TRUE=Ya notificado al mesero';

-- ######################################################################
-- # 4. INSERCIÓN DE DATOS INICIALES (MENÚ COMPLETO)
-- ######################################################################

-- 4.2. Grupos de Modificadores (Asumidos IDs 1, 2, 3)
INSERT INTO modifier_groups (group_id, group_name) VALUES
(1, 'Guiso a Elegir'), (2, 'Temperatura'), (3, 'Sabor Bebida Fría')
ON DUPLICATE KEY UPDATE group_name=VALUES(group_name);

-- 4.3. Categorías
INSERT INTO menu_categories (category_id, category_name, display_order) VALUES
(1, 'Chilaquiles', 10), (2, 'Otros Productos', 20), (3, 'Muuk Fortaleza del dia', 30),
(4, 'La Musa Mestiza', 40), (5, 'Keto', 50), (6, 'Menu de ninos', 60),
(7, 'Sorbitos de amor', 70), (8, 'Sorbitos especiales', 80), (9, 'Amanecer Detox', 90),
(10, 'Cheers', 100), (11, 'Amanecer frio', 95)
ON DUPLICATE KEY UPDATE category_name=VALUES(category_name);

-- TRUNCATE TABLE products; -- (Ejecutar si es necesario para reiniciar la tabla)

-- IDs de Modificadores: 1: Guiso, 2: Temperatura, 3: Sabor
-- IDs de Categoría: 1:Chilaquiles, 4:Musa Mestiza, 7:Sorbitos Amor, 10:Cheers, etc.

INSERT INTO products (name, price, category_id, modifier_group_id) VALUES
-- 1. Chilaquiles (ID 1): NO REQUIERE MODIFICADOR
('Mi amor bonito', 228.00, 1, NULL), 
('Nativos', 219.00, 1, NULL), 
('Dona susana', 216.00, 1, NULL),
('Nene', 216.00, 1, NULL), 
('La Chef', 219.00, 1, NULL), 
('Mosqueteras', 228.00, 1, NULL),
('Levanta muertos', 223.00, 1, NULL), 
('Pa Que Amarre', 238.00, 1, NULL),

-- 2. Otros Productos (ID 2): NO REQUIERE MODIFICADOR
('Ingrediente extra', 44.00, 2, NULL), 
('Tortillas de harina', 0.00, 2, NULL),
('Tortillas de maiz', 0.00, 2, NULL), 
('Tortillas de Nixtamal azul', 44.00, 2, NULL),
('La reliquia', 540.00, 2, NULL),

-- 3. Muuk Fortaleza del dia (ID 3): NO REQUIERE MODIFICADOR
('Surenos', 223.00, 3, NULL), 
('Montados', 228.00, 3, NULL), 
('Montados nativos', 228.00, 3, NULL),
('Montados levantamuertos', 228.00, 3, NULL), 
('Madrileno', 219.00, 3, NULL), 
('A la mexicana', 216.00, 3, NULL),
('Rebeldia', 228.00, 3, NULL),

-- 4. La Musa Mestiza (ID 4): REQUIERE GUISO (ID 1)
('Mexico lindo', 116.00, 4, 1), 
('La regia', 116.00, 4, 1), 

-- 5. Keto (ID 5): NO REQUIERE MODIFICADOR
('MUCHOQUESO', 216.00, 5, NULL), 
('Primera Dama', 239.00, 5, NULL), 
('La Notario', 228.00, 5, NULL),
('El desayuo del Sr. Viktor', 239.00, 5, NULL), 
('Toast Mestizo', 226.00, 5, NULL), 
('Retono de amor', 216.00, 5, NULL),
('Sarape de Amor', 0.00, 5, NULL), 
('Mi saltillo', 216.00, 5, NULL), 
('Nopales', 216.00, 5, NULL),

-- 6. Menu de ninos (ID 6): NO REQUIERE MODIFICADOR
('Mi abuelita', 128.00, 6, NULL), 
('Surenito', 118.00, 6, NULL), 
('Molletin', 109.00, 6, NULL),

-- 7. Sorbitos de amor (ID 7): Chocomilk requiere Temperatura (ID 2)
('Tollimare', 118.00, 7, NULL), 
('El conta', 119.00, 7, NULL), 
('Capuccino cabrito', 124.00, 7, NULL),
('Olla Mestiza', 74.00, 7, NULL), 
('Tierra Mestiza', 77.00, 7, NULL), 
('Americano Mosere', 71.00, 7, NULL),
('Americano', 87.00, 7, NULL), 
('Chocomilk', 124.00, 7, 2), -- <--- REQUIERE TEMPERATURA (ID 2)

-- 8. Sorbitos especiales (ID 8): NO REQUIERE MODIFICADOR
('Chocoavena', 124.00, 8, NULL), 
('Jamaica Guayaba', 112.00, 8, NULL), 
('Limonada Pink', 119.00, 8, NULL), 
('Limonada', 117.00, 8, NULL), 
('Limonada Mineral', 124.00, 8, NULL), 
('Naranjada', 117.00, 8, NULL),
('Naranjada Mineral', 124.00, 8, NULL), 
('Agua embotellada', 39.00, 8, NULL), 
('Refresco', 64.00, 8, NULL),

-- 9. Amanecer Detox (ID 9): NO REQUIERE MODIFICADOR
('Verde despertar', 124.00, 9, NULL), 
('Naranja', 87.00, 9, NULL),

-- 10. Amanecer frio (ID 10, Asumimos que esta categoría tiene ID 10)
('Iced Latte', 134.00, 11, 3), -- <--- REQUIERE SABOR (ID 3)
('Iced Capuccino', 124.00, 11, NULL), 
('Cafe frio', 124.00, 11, NULL),

-- 11. Cheers (ID 10): Mimosas NO REQUIERE MODIFICADOR
('Mimosas', 184.00, 10, NULL), 
('Mixologia', 224.00, 10, NULL),
('Tisanas mestizas', 124.00, 10, NULL), 
('Carrito Mimosas', 920.00, 10, NULL)

-- Usar ON DUPLICATE KEY UPDATE si es una actualización de datos
ON DUPLICATE KEY UPDATE 
    price=VALUES(price), category_id=VALUES(category_id), modifier_group_id=VALUES(modifier_group_id);



-- Modifiers 

-- 2. INSERCIÓN COMPLETA DE MODIFICADORES

INSERT INTO modifiers (group_id, modifier_name, modifier_price) VALUES
-- Grupo 1: Guisos a Elegir (Para La Regia, Mexico Lindo, Chilaquiles, Muuk)
(1, 'Chicharron', 0.00),
(1, 'Huevo en salsa', 0.00),
(1, 'Picadillo', 0.00),
(1, 'Queso en salsa', 0.00),
(1, 'Nopalitos', 0.00),
(1, 'Rajas poblanas', 0.00),
(1, 'Papa con chorizo', 0.00),
(1, 'Sin guiso', 0.00),

-- Grupo 2: Temperatura (Para Chocomilk, Sorbitos de amor)
(2, 'Caliente', 0.00),
(2, 'Frío', 0.00),

-- Grupo 3: Sabores de Bebida Fría (Para Iced Latte, Mimosas)
(3, 'Vainilla', 0.00),
(3, 'Caramelo', 0.00),
(3, 'Chocolate', 0.00);

UPDATE menu_categories
SET preparation_area = 'BARRA'
WHERE category_id IN (7, 8, 9, 10, 11);

UPDATE menu_categories
SET preparation_area = 'COCINA'
WHERE category_id IN (1, 2, 3, 4, 5, 6);

-- ========= TABLA DE HISTORIAL DE PRODUCCIÓN DE COCINA (VERSIÓN FINAL) =========
-- Almacena un registro permanente de cada ítem que fue marcado como 'LISTO' 
-- por el área de Cocina. Incluye todos los datos necesarios para reconstruir 
-- la vista de la comanda tal como se vio en producción.

CREATE TABLE kitchen_production_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- IDs para referencia y auditoría
    original_detail_id INT NOT NULL COMMENT 'El ID del registro original en `order_details`',
    order_id INT NOT NULL,

    -- Datos clave para agrupar y mostrar en la tarjeta de comanda
    table_number INT NOT NULL COMMENT 'El número de la mesa donde se ordenó',
    batch_timestamp TIMESTAMP NOT NULL COMMENT 'Agrupa los ítems enviados juntos en la misma comanda',
    service_time INT NOT NULL COMMENT 'El tiempo de servicio (1, 2, 3...) para agrupar en la comanda',
    
    -- Información descriptiva del producto
    server_name VARCHAR(255) COMMENT 'El nombre del mesero que tomó la orden',
    product_name VARCHAR(255) NOT NULL COMMENT 'El nombre del producto en el momento del pedido',
    quantity INT NOT NULL,
    special_notes TEXT,
    
    -- Marcas de tiempo para análisis de rendimiento
    timestamp_added DATETIME NOT NULL COMMENT 'Hora en que el cliente pidió el platillo',
    timestamp_completed DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Hora en que se marcó como LISTO',
    
    -- Índice para que las búsquedas por fecha sean ultra rápidas
    INDEX idx_completed_date (timestamp_completed)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========= TABLA DE HISTORIAL DE PRODUCCIÓN DE BARRA =========
-- Almacena un registro permanente de cada ítem que fue marcado como 'LISTO' 
-- por el área de Barra. Es una copia estructural de la tabla de cocina.

CREATE TABLE bar_production_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- IDs para referencia y auditoría
    original_detail_id INT NOT NULL COMMENT 'El ID del registro original en `order_details`',
    order_id INT NOT NULL,

    -- Datos clave para agrupar y mostrar en la tarjeta de comanda
    table_number INT NOT NULL COMMENT 'El número de la mesa donde se ordenó',
    batch_timestamp TIMESTAMP NOT NULL COMMENT 'Agrupa los ítems enviados juntos en la misma comanda',
    service_time INT NOT NULL COMMENT 'El tiempo de servicio (1, 2, 3...) para agrupar en la comanda',
    
    -- Información descriptiva del producto
    server_name VARCHAR(255) COMMENT 'El nombre del mesero que tomó la orden',
    product_name VARCHAR(255) NOT NULL COMMENT 'El nombre del producto en el momento del pedido',
    quantity INT NOT NULL,
    special_notes TEXT,
    
    -- Marcas de tiempo para análisis de rendimiento
    timestamp_added DATETIME NOT NULL COMMENT 'Hora en que el cliente pidió el producto',
    timestamp_completed DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Hora en que se marcó como LISTO',
    
    -- Índice para que las búsquedas por fecha sean ultra rápidas
    INDEX idx_completed_date (timestamp_completed)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Añade la columna para el nombre del modificador a ambas tablas de historial
ALTER TABLE kitchen_production_history
ADD COLUMN modifier_name VARCHAR(255) NULL DEFAULT NULL COMMENT 'Nombre del modificador seleccionado' AFTER product_name;

ALTER TABLE bar_production_history
ADD COLUMN modifier_name VARCHAR(255) NULL DEFAULT NULL COMMENT 'Nombre del modificador seleccionado' AFTER product_name;

CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL COMMENT 'Dirección IP del atacante',
    username VARCHAR(25) NULL COMMENT 'Nombre de usuario intentado (si se conoce)',
    attempt_time DATETIME NOT NULL COMMENT 'Momento del intento fallido'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Añadir un índice para búsquedas rápidas por IP o usuario
CREATE INDEX idx_ip_time ON login_attempts (ip_address, attempt_time);

ALTER TABLE login_attempts
ADD COLUMN device_identifier VARCHAR(64) NULL DEFAULT NULL AFTER username;

-- ========= TABLA DE HISTORIAL DE VENTAS (VERSIÓN FINAL COMPLETA) =========
-- Almacena el resumen de cada cuenta que ha sido pagada y cerrada.
CREATE TABLE `sales_history` (
  `sale_id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Número de movimiento único para el ticket',
  `original_order_id` INT NOT NULL,
  `table_number` INT NOT NULL,
  `client_count` INT NOT NULL COMMENT 'Número de personas en la mesa',
  `server_name` VARCHAR(255) NOT NULL,
  `cashier_id` INT NULL COMMENT 'ID del usuario (cajero) que procesó el pago', -- <<< NUEVO
  `time_occupied` TIMESTAMP NOT NULL COMMENT 'Hora en que se ocupó la mesa',
  `payment_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Hora en que se pagó la cuenta',
  `subtotal` DECIMAL(10, 2) NOT NULL,
  `tax_amount` DECIMAL(10, 2) NOT NULL,
  `discount_amount` DECIMAL(10, 2) DEFAULT 0.00,
  `tip_amount_card` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Propina pagada con tarjeta',
  `grand_total` DECIMAL(10, 2) NOT NULL COMMENT 'Monto final pagado por el cliente',
  `is_courtesy` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'TRUE si toda la venta fue una cortesía, FALSE si no.',
  `payment_methods` JSON COMMENT 'Un JSON que describe los métodos de pago, ej: [{"method": "Cash", "amount": 500}]',
  
  -- Llave foránea para el cajero
  CONSTRAINT `fk_sales_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE -- <<< NUEVO
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========= TABLA DE DETALLES DEL HISTORIAL DE VENTAS =========
-- Guarda cada producto de una venta archivada para mantener un registro completo.
CREATE TABLE `sales_history_details` (
  `sale_detail_id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `modifier_name` VARCHAR(255) NULL,
  `quantity` INT NOT NULL,
  `price_at_order` DECIMAL(10, 2) NOT NULL,
  `was_cancelled` BOOLEAN NOT NULL DEFAULT FALSE,
  CONSTRAINT `fk_sale_history` FOREIGN KEY (`sale_id`) REFERENCES `sales_history`(`sale_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `cash_shifts` (
  `shift_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id_opened` INT NOT NULL COMMENT 'ID del cajero/gerente que abrió',
  `start_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Hora de apertura',
  `end_time` TIMESTAMP NULL DEFAULT NULL COMMENT 'Hora de cierre',
  `starting_cash` DECIMAL(10, 2) NOT NULL COMMENT 'Fondo de caja inicial',
  `ending_cash` DECIMAL(10, 2) NULL DEFAULT NULL COMMENT 'Efectivo contado al cerrar',
  `status` ENUM('OPEN', 'CLOSED') NOT NULL DEFAULT 'OPEN',
  
  CONSTRAINT `fk_shift_user` FOREIGN KEY (`user_id_opened`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
;

// Ejecutamos silenciosamente
$conn->multi_query($sql);
while ($conn->more_results() && $conn->next_result()) {;}

echo "✅ BD Sincronizada con éxito vía CI/CD";
$conn->close();
?>