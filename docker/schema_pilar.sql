-- ============================================================
--  PILAR — Schema de migración
--  Se ejecuta sobre la BD pilar_db existente:
--    1. DROP de todas las tablas anteriores
--    2. CREATE de las tablas de Pilar
--    3. Datos semilla
--
--  El pipeline lo corre así:
--    docker exec -i pilar-db mysql -uroot -p'XXX' pilar_db < schema_pilar.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── DROP tablas heredadas ──
DROP TABLE IF EXISTS sales_history_details;
DROP TABLE IF EXISTS sales_history;
DROP TABLE IF EXISTS order_details;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS reservation_tables;
DROP TABLE IF EXISTS reservations_history;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS restaurant_tables;
DROP TABLE IF EXISTS waiting_list_history;
DROP TABLE IF EXISTS waiting_list;
DROP TABLE IF EXISTS bar_production_history;
DROP TABLE IF EXISTS kitchen_production_history;
DROP TABLE IF EXISTS cash_shifts;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS modifiers;
DROP TABLE IF EXISTS modifier_groups;
DROP TABLE IF EXISTS menu_categories;
DROP TABLE IF EXISTS tables;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

-- ── DROP tablas de Pilar (por si es un re-deploy) ──
DROP TABLE IF EXISTS empleado_plantas;
DROP TABLE IF EXISTS sistema_config;
DROP TABLE IF EXISTS registros_asistencia;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS empleados;
DROP TABLE IF EXISTS puestos;
DROP TABLE IF EXISTS areas;
DROP TABLE IF EXISTS plantas;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- PLANTAS
-- ============================================================
CREATE TABLE plantas (
    id              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(100)   NOT NULL,
    codigo          VARCHAR(20)    NOT NULL UNIQUE,
    ubicacion       VARCHAR(255),
    latitud         DECIMAL(10,8),
    longitud        DECIMAL(11,8),
    radio_permitido INT            DEFAULT 100,
    activa          BOOLEAN        DEFAULT TRUE,
    created_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ÁREAS
-- ============================================================
CREATE TABLE areas (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PUESTOS
-- ============================================================
CREATE TABLE puestos (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre   VARCHAR(100) NOT NULL,
    area_id  INT UNSIGNED,
    FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL,
    UNIQUE KEY uq_puesto_area (nombre, area_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EMPLEADOS
-- ============================================================
CREATE TABLE empleados (
    id                INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    numero_empleado   VARCHAR(20)   NOT NULL UNIQUE,
    nombre            VARCHAR(80)   NOT NULL,
    apellido_paterno  VARCHAR(80)   NOT NULL,
    apellido_materno  VARCHAR(80),
    email             VARCHAR(120)  UNIQUE,
    rfc               VARCHAR(15),
    curp              VARCHAR(20),
    puesto_id         INT UNSIGNED  NOT NULL,
    planta_id         INT UNSIGNED  NOT NULL,
    face_descriptor   LONGTEXT,
    foto_url          VARCHAR(255),
    activo            BOOLEAN       DEFAULT TRUE,
    created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (puesto_id) REFERENCES puestos(id),
    FOREIGN KEY (planta_id) REFERENCES plantas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USUARIOS DEL SISTEMA
-- ============================================================
CREATE TABLE usuarios (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    empleado_id    INT UNSIGNED  UNIQUE,
    username       VARCHAR(50)   NOT NULL UNIQUE,
    password_hash  VARCHAR(255)  NOT NULL,
    rol            ENUM('admin','empleado') DEFAULT 'empleado',
    activo         BOOLEAN       DEFAULT TRUE,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- REGISTROS DE ASISTENCIA
-- entrada → salida_comida → regreso_comida → salida
-- Horas = (salida_comida-entrada) + (salida-regreso_comida)
-- Catorcena = 90 h normales | Extra = MAX(0, total-90)
-- ============================================================
CREATE TABLE registros_asistencia (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empleado_id  INT UNSIGNED    NOT NULL,
    planta_id    INT UNSIGNED    NOT NULL,
    tipo_evento  ENUM('entrada','salida_comida','regreso_comida','salida') NOT NULL,
    fecha_hora   DATETIME        NOT NULL,
    latitud      DECIMAL(10,8),
    longitud     DECIMAL(11,8),
    face_score   DECIMAL(5,4),
    created_at   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id),
    FOREIGN KEY (planta_id)   REFERENCES plantas(id),
    INDEX idx_emp_fecha (empleado_id, fecha_hora),
    INDEX idx_fecha     (fecha_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- EMPLEADO_PLANTAS (many-to-many)
-- ============================================================
CREATE TABLE empleado_plantas (
    empleado_id  INT UNSIGNED NOT NULL,
    planta_id    INT UNSIGNED NOT NULL,
    es_principal BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (empleado_id, planta_id),
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
    FOREIGN KEY (planta_id)   REFERENCES plantas(id)   ON DELETE CASCADE
);

-- Migrar planta_id actual → registro principal en la pivote
INSERT IGNORE INTO empleado_plantas (empleado_id, planta_id, es_principal)
SELECT id, planta_id, TRUE FROM empleados WHERE planta_id IS NOT NULL;


-- ============================================================
-- SISTEMA CONFIG (feature flags y configuración global)
-- ============================================================
CREATE TABLE sistema_config (
    clave      VARCHAR(50)  NOT NULL PRIMARY KEY,
    valor      VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO sistema_config (clave, valor) VALUES ('liveness', 'off');

-- ============================================================
-- DATOS SEMILLA
-- ============================================================
INSERT INTO plantas (nombre, codigo, ubicacion, activa)
VALUES ('Planta Principal', 'PLT-01', 'Saltillo, Coahuila', TRUE);

INSERT INTO areas (nombre) VALUES
  ('Producción'), ('Mantenimiento'), ('Administración'), ('Logística');

INSERT INTO puestos (nombre, area_id) VALUES
  ('Operador',        1),
  ('Supervisor',      1),
  ('Técnico',         2),
  ('Administrativo',  3),
  ('Chofer',          4);

-- Empleado admin de sistema
INSERT INTO empleados
  (numero_empleado, nombre, apellido_paterno, email, puesto_id, planta_id, activo)
VALUES
  ('00000000001', 'Admin', 'Sistema', 'admin@pilar.com', 3, 1, TRUE);

-- password = 'Admin1234' (bcrypt cost 12 — cambiar en producción)
INSERT INTO usuarios (empleado_id, username, password_hash, rol, activo)
SELECT e.id, 'admin',
       '$2y$12$uZYhTF9YxGw.GF4n2GKuVuY5dGW0z3Hk0Ud3jV3T5l6e3sL5U5Ga2',
       'admin', TRUE
FROM empleados e
WHERE e.numero_empleado = '00000000001'
LIMIT 1;