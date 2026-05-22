-- ============================================================
--  KitchenLink — Migración: Nómina + Permisos + Asistencia
--  Se ejecuta después de un bootstrap limpio de la base.
--  El pipeline elimina el volumen de MySQL antes de levantar Docker.
-- ============================================================

USE KitchenLink;

-- ── 1. Nuevos campos de nómina en users ──
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `users` ADD COLUMN `nss` VARCHAR(20) DEFAULT NULL AFTER `name`, ADD COLUMN `plant` VARCHAR(100) DEFAULT NULL AFTER `nss`, ADD COLUMN `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `plant`, ADD COLUMN `salary_per_day` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `tax_rate`, ADD COLUMN `overtime_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `salary_per_day`, ADD COLUMN `shift_start_time` TIME NOT NULL DEFAULT ''08:00:00'' AFTER `overtime_rate`, ADD COLUMN `shift_end_time` TIME NOT NULL DEFAULT ''18:00:00'' AFTER `shift_start_time`, ADD COLUMN `late_after_minutes` INT NOT NULL DEFAULT 0 AFTER `shift_end_time`, ADD COLUMN `absence_after_minutes` INT NOT NULL DEFAULT 15 AFTER `late_after_minutes`, ADD UNIQUE KEY `uq_users_nss` (`nss`)',
    'SELECT "usuarios ya tienen campos de nomina, skip" AS info'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'KitchenLink' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'nss'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── 2. Tabla de permisos especiales ──
CREATE TABLE IF NOT EXISTS `attendance_permissions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `granted_by` INT DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_permission_range` (`user_id`, `valid_from`, `valid_to`),
  CONSTRAINT `fk_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_permissions_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 3. Base mínima de asistencia, extendida con nómina ──
CREATE TABLE IF NOT EXISTS `attendance_records` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `type` ENUM('ENTRADA','SALIDA') NOT NULL,
  `method` ENUM('FACIAL','MANUAL') NOT NULL DEFAULT 'FACIAL',
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment` VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attendance_user_date` (`user_id`, `timestamp`),
  KEY `idx_attendance_type_date` (`type`, `timestamp`),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `attendance_records` ADD COLUMN `entry_status` ENUM(''NORMAL'',''RETARDO'',''PERMISO'') DEFAULT NULL AFTER `comment`, ADD COLUMN `minutes_late` INT NOT NULL DEFAULT 0 AFTER `entry_status`, ADD COLUMN `worked_minutes` INT DEFAULT NULL AFTER `minutes_late`, ADD COLUMN `overtime_minutes` INT NOT NULL DEFAULT 0 AFTER `worked_minutes`, ADD COLUMN `permission_id` INT DEFAULT NULL AFTER `overtime_minutes`, ADD KEY `idx_attendance_user_date` (`user_id`, `timestamp`), ADD KEY `idx_attendance_type_date` (`type`, `timestamp`), ADD CONSTRAINT `fk_attendance_permission` FOREIGN KEY (`permission_id`) REFERENCES `attendance_permissions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "attendance_records ya tiene campos nuevos, skip" AS info'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'KitchenLink' AND TABLE_NAME = 'attendance_records' AND COLUMN_NAME = 'entry_status'
);

PREPARE stmt2 FROM @sql;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
