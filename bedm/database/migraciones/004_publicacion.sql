-- ════════════════════════════════════════════════════════════
-- BEDM · MIGRACIÓN 004 · publicación de trabajos
-- La huella digital (SHA-256) del archivo pasa a ser única: el mismo archivo no se puede publicar dos veces
-- (protege la autoría: quien llega primero queda registrado; el resto debe ser agregado como coautor/a).
-- Se puede ejecutar más de una vez sin problemas. Pensada para MariaDB (XAMPP).
-- ════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
USE `bedm`;

ALTER TABLE `trabajos`
  ADD UNIQUE KEY IF NOT EXISTS `uq_trabajo_sha256` (`sha256`);

INSERT IGNORE INTO `migraciones` (`version`) VALUES ('004');
