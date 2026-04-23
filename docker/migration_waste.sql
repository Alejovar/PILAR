-- /docker/migration_waste.sql
-- TASK-01: Migración de mermas (Sprint 1)
-- Se modifica order_details y sales_history_details en lugar de crear tabla nueva.
-- Usar CREATE TABLE IF NOT EXISTS / ALTER TABLE ... IF NOT EXISTS (vía procedure) para idempotencia.

-- ── 1. order_details: columnas para merma (ítems activos/abiertos) ──────────
ALTER TABLE `order_details`
    ADD COLUMN IF NOT EXISTS `is_waste`       TINYINT(1)   NOT NULL DEFAULT 0
        COMMENT '1 = cancelado como merma por gerente',
    ADD COLUMN IF NOT EXISTS `waste_reason`   ENUM(
        'expired',
        'kitchen_error',
        'waiter_error',
        'damaged',
        'other'
    ) DEFAULT NULL
        COMMENT 'Motivo de la merma',
    ADD COLUMN IF NOT EXISTS `waste_price`    DECIMAL(10,2) DEFAULT NULL
        COMMENT 'Precio real del producto al momento de la merma (para reportes). price_at_order se pone en 0.',
    ADD COLUMN IF NOT EXISTS `waste_recorded_by` INT(11) DEFAULT NULL
        COMMENT 'FK al gerente que registró la merma',
    ADD COLUMN IF NOT EXISTS `waste_recorded_at` DATETIME DEFAULT NULL
        COMMENT 'Timestamp de cuando se marcó como merma';

-- Índice para acelerar el reporte de mermas
CREATE INDEX IF NOT EXISTS `idx_waste` ON `order_details` (`is_waste`, `waste_recorded_at`);

-- ── 2. sales_history_details: columnas para merma (órdenes ya cerradas) ──────
-- Estas columnas permiten que el reporte abarque también ventas históricas cerradas.
ALTER TABLE `sales_history_details`
    ADD COLUMN IF NOT EXISTS `is_waste`     TINYINT(1)    NOT NULL DEFAULT 0
        COMMENT '1 = este ítem fue una merma',
    ADD COLUMN IF NOT EXISTS `waste_reason` VARCHAR(50)   DEFAULT NULL
        COMMENT 'Motivo de la merma',
    ADD COLUMN IF NOT EXISTS `waste_price`  DECIMAL(10,2) DEFAULT NULL
        COMMENT 'Precio real del producto para el reporte de mermas';

-- Nota: ADD COLUMN IF NOT EXISTS requiere MySQL 8.0+.
-- Si están en 5.7 o MariaDB < 10.2, usar el procedure de abajo:

/*
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

CALL sp_add_col_if_not_exists('order_details', 'is_waste',       'TINYINT(1) NOT NULL DEFAULT 0');
CALL sp_add_col_if_not_exists('order_details', 'waste_reason',   "ENUM(\'expired\',\'kitchen_error\',\'waiter_error\',\'damaged\',\'other\') DEFAULT NULL");
CALL sp_add_col_if_not_exists('order_details', 'waste_price',    'DECIMAL(10,2) DEFAULT NULL');
CALL sp_add_col_if_not_exists('order_details', 'waste_recorded_by', 'INT(11) DEFAULT NULL');
CALL sp_add_col_if_not_exists('order_details', 'waste_recorded_at', 'DATETIME DEFAULT NULL');
CALL sp_add_col_if_not_exists('sales_history_details', 'is_waste',    'TINYINT(1) NOT NULL DEFAULT 0');
CALL sp_add_col_if_not_exists('sales_history_details', 'waste_reason','VARCHAR(50) DEFAULT NULL');
CALL sp_add_col_if_not_exists('sales_history_details', 'waste_price', 'DECIMAL(10,2) DEFAULT NULL');
DROP PROCEDURE IF EXISTS sp_add_col_if_not_exists;
*/
