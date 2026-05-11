-- ============================================================
--  KitchenLink — Migracion: users.id AUTO_INCREMENT
--  Ejecutar sobre la BD KitchenLink existente
-- ============================================================

USE KitchenLink;

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
