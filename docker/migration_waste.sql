-- /docker/migration_waste.sql
-- TASK-01: Migración de mermas (Sprint 1)
-- Compatible con MySQL 8.0+ (ADD COLUMN IF NOT EXISTS NO existe en MySQL, sí en MariaDB).
-- Se usa procedure idempotente para agregar columnas solo si no existen.

-- ── Procedure helper ─────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_add_col_if_not_exists;

DELIMITER $$
CREATE PROCEDURE sp_add_col_if_not_exists(
    IN p_table VARCHAR(64),
    IN p_col   VARCHAR(64),
    IN p_def   TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND COLUMN_NAME  = p_col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_col, '` ', p_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- ── 1. order_details: columnas para merma (ítems activos/abiertos) ───────────
CALL sp_add_col_if_not_exists('order_details', 'is_waste',
    'TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = cancelado como merma por gerente''');

CALL sp_add_col_if_not_exists('order_details', 'waste_reason',
    'ENUM(''expired'',''kitchen_error'',''waiter_error'',''damaged'',''other'') DEFAULT NULL COMMENT ''Motivo de la merma''');

CALL sp_add_col_if_not_exists('order_details', 'waste_price',
    'DECIMAL(10,2) DEFAULT NULL COMMENT ''Precio real del producto al momento de la merma''');

CALL sp_add_col_if_not_exists('order_details', 'waste_recorded_by',
    'INT(11) DEFAULT NULL COMMENT ''FK al gerente que registró la merma''');

CALL sp_add_col_if_not_exists('order_details', 'waste_recorded_at',
    'DATETIME DEFAULT NULL COMMENT ''Timestamp de cuando se marcó como merma''');

-- Índice para acelerar el reporte de mermas (idempotente vía procedure)
DROP PROCEDURE IF EXISTS sp_add_index_if_not_exists;

DELIMITER $$
CREATE PROCEDURE sp_add_index_if_not_exists(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_cols  TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND INDEX_NAME   = p_index
    ) THEN
        SET @sql = CONCAT('CREATE INDEX `', p_index, '` ON `', p_table, '` (', p_cols, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL sp_add_index_if_not_exists('order_details', 'idx_waste', '`is_waste`, `waste_recorded_at`');

-- ── 2. sales_history_details: columnas para merma (órdenes ya cerradas) ──────
CALL sp_add_col_if_not_exists('sales_history_details', 'is_waste',
    'TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = este ítem fue una merma''');

CALL sp_add_col_if_not_exists('sales_history_details', 'waste_reason',
    'VARCHAR(50) DEFAULT NULL COMMENT ''Motivo de la merma''');

CALL sp_add_col_if_not_exists('sales_history_details', 'waste_price',
    'DECIMAL(10,2) DEFAULT NULL COMMENT ''Precio real del producto para el reporte de mermas''');

-- ── Limpieza de procedures temporales ────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_add_col_if_not_exists;
DROP PROCEDURE IF EXISTS sp_add_index_if_not_exists;