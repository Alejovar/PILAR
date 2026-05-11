-- ============================================================
--  KitchenLink — Migracion: users.id AUTO_INCREMENT
--  Ejecutar sobre la BD KitchenLink existente
-- ============================================================

USE KitchenLink;

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `attendance_records`
  DROP FOREIGN KEY `fk_attendance_user`;

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `attendance_records`
  ADD CONSTRAINT `fk_attendance_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
