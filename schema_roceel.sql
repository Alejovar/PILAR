-- ============================================================
--  ROCEEL Servicios Especializados — Base de Datos v2
--  MySQL 8.0+  |  utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS roceel_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE roceel_db;

-- ============================================================
-- PLANTAS / INSTALACIONES
-- ============================================================
CREATE TABLE IF NOT EXISTS plantas (
    id            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100)   NOT NULL,
    codigo        VARCHAR(20)    NOT NULL UNIQUE,
    ubicacion     VARCHAR(255),               -- dirección legible
    latitud       DECIMAL(10,8),
    longitud      DECIMAL(11,8),
    radio_permitido INT          DEFAULT 100,
    activa        BOOLEAN        DEFAULT TRUE,
    created_at    TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- ÁREAS (departamentos dentro de una empresa)
-- ============================================================
CREATE TABLE IF NOT EXISTS areas (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL UNIQUE
);

-- ============================================================
-- PUESTOS / ROLES  (pertenecen a un área; varios empleados pueden
--                   tener el mismo puesto)
-- ============================================================
CREATE TABLE IF NOT EXISTS puestos (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre   VARCHAR(100) NOT NULL,
    area_id  INT UNSIGNED,
    FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL,
    UNIQUE KEY uq_puesto_area (nombre, area_id)
);

-- ============================================================
-- EMPLEADOS
-- ============================================================
CREATE TABLE IF NOT EXISTS empleados (
    id                INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    numero_empleado   VARCHAR(20)    NOT NULL UNIQUE,   -- NSS o N° interno
    nombre            VARCHAR(80)    NOT NULL,
    apellido_paterno  VARCHAR(80)    NOT NULL,
    apellido_materno  VARCHAR(80),
    email             VARCHAR(120)   UNIQUE,
    rfc               VARCHAR(15),
    curp              VARCHAR(20),
    puesto_id         INT UNSIGNED   NOT NULL,
    planta_id         INT UNSIGNED   NOT NULL,
    foto_url          VARCHAR(255),
    activo            BOOLEAN        DEFAULT TRUE,
    created_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (puesto_id)  REFERENCES puestos(id),
    FOREIGN KEY (planta_id)  REFERENCES plantas(id)
);

-- ============================================================
-- USUARIOS DEL SISTEMA (admin / RRHH)
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    empleado_id    INT UNSIGNED  UNIQUE,
    username       VARCHAR(50)   NOT NULL UNIQUE,
    password_hash  VARCHAR(255)  NOT NULL,
    rol            ENUM('admin','empleado') DEFAULT 'empleado',
    activo         BOOLEAN       DEFAULT TRUE,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id)
);

-- ============================================================
-- REGISTROS DE ASISTENCIA
-- tipo_evento:
--   entrada        → inicio de jornada
--   salida_comida  → sale a comer
--   regreso_comida → regresa de comer
--   salida         → fin de jornada
-- Horas trabajadas = (salida_comida - entrada) + (salida - regreso_comida)
-- Catorcena normal = 90 h  (lun–vie, 14 días)
-- Horas extra = MAX(0, total - 90)
-- ============================================================
CREATE TABLE IF NOT EXISTS registros_asistencia (
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
);

-- ============================================================
-- USUARIO ADMIN POR DEFECTO
-- Contraseña: Admin1234  (bcrypt — cambiar en producción)
-- ============================================================
INSERT IGNORE INTO plantas (nombre, codigo, ubicacion, activa)
VALUES ('Planta Principal', 'PLT-01', 'Saltillo, Coahuila', TRUE);

INSERT IGNORE INTO areas (nombre) VALUES
  ('Producción'), ('Mantenimiento'), ('Administración'), ('Logística');

INSERT IGNORE INTO puestos (nombre, area_id) VALUES
  ('Operador',        1),
  ('Supervisor',      1),
  ('Técnico',         2),
  ('Administrativo',  3),
  ('Chofer',          4);

-- Empleado y usuario admin de prueba
INSERT IGNORE INTO empleados
  (numero_empleado, nombre, apellido_paterno, apellido_materno, email, puesto_id, planta_id, activo)
VALUES
  ('00000000001','Admin','Sistema','Roceel','admin@roceel.com', 3, 1, TRUE);

-- password_hash de 'Admin1234' con bcrypt (PHP: password_hash('Admin1234', PASSWORD_BCRYPT))
-- Reemplazar en producción
INSERT IGNORE INTO usuarios (empleado_id, username, password_hash, rol, activo)
SELECT e.id, 'admin', '$2y$12$uZYhTF9YxGw.GF4n2GKuVuY5dGW0z3Hk0Ud3jV3T5l6e3sL5U5Ga2', 'admin', TRUE
FROM empleados e WHERE e.numero_empleado = '00000000001' LIMIT 1;
