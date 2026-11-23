-- ════════════════════════════════════════════════════════════
-- BEDM · MIGRACIÓN 003 · seguridad de cuentas
-- Agrega la "versión de sesión" de cada usuario: al cambiar la contraseña se suma 1
-- y todas las sesiones abiertas con la contraseña vieja dejan de valer.
-- Se puede ejecutar más de una vez sin problemas. Pensada para MariaDB (XAMPP).
-- ════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
USE `bedm`;

ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `sesion_version` smallint(5) UNSIGNED NOT NULL DEFAULT 1 AFTER `baja_en`;

INSERT IGNORE INTO `migraciones` (`version`) VALUES ('003');
