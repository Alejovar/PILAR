-- ============================================================
--  KitchenLink — Migración: Reconocimiento Facial + Checador
--  Ejecutar sobre la BD KitchenLink existente
--  Idempotente: usa IF NOT EXISTS / columna condicional
-- ============================================================

USE KitchenLink;

-- ── 1. Columna face_descriptor en users ──
-- Se agrega solo si no existe (evita error en re-runs)
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'KitchenLink'
    AND TABLE_NAME   = 'users'
    AND COLUMN_NAME  = 'face_descriptor'
);

SET @sql = IF(
  @col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `face_descriptor` TEXT DEFAULT NULL AFTER `session_token`',
  'SELECT "face_descriptor ya existe, skip" AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── 2. Tabla de asistencias ──
CREATE TABLE IF NOT EXISTS `attendance_records` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`   INT(11)      NOT NULL,
  `type`      ENUM('ENTRADA','SALIDA') NOT NULL,
  `method`    ENUM('FACIAL','MANUAL') NOT NULL DEFAULT 'FACIAL',
  `timestamp` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment`   VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`, `timestamp`),
  CONSTRAINT `fk_attendance_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
