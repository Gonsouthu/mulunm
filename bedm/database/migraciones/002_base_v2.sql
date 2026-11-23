-- ════════════════════════════════════════════════════════════
-- BEDM · MIGRACIÓN 002 · de la versión 1 a la versión 2 de la base de datos
-- Conserva tus datos (usuarios, etc.). Se puede ejecutar más de una vez sin problemas.
-- Pensada para MariaDB (XAMPP). Ejecutar con la base `bedm` ya seleccionada
-- (phpMyAdmin → clic en `bedm` a la izquierda → pestaña "Importar").
-- ════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
USE `bedm`;

-- 0. Registro de versiones del esquema
CREATE TABLE IF NOT EXISTS `migraciones` (
  `version` varchar(20) NOT NULL,
  `aplicada_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `migraciones` (`version`) VALUES ('001');

-- 1. Mismo juego de caracteres y orden alfabético en todas las tablas
ALTER TABLE `usuarios` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `materias` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `trabajos` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `votos`    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Usuarios: aceptación de términos y baja lógica
ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `terminos_aceptados_en` datetime DEFAULT NULL AFTER `email_verificado`,
  ADD COLUMN IF NOT EXISTS `baja_en` datetime DEFAULT NULL AFTER `activo`;

-- 3. Materias: clave única + carga de las 47 materias de la carrera
ALTER TABLE `materias`
  ADD UNIQUE KEY IF NOT EXISTS `uq_materia` (`carrera_id`,`anio_carrera`,`cuatrimestre`,`nombre`);
-- (el índice simple `carrera_id` de la versión 1 queda cubierto por `uq_materia`)
ALTER TABLE `materias` DROP INDEX IF EXISTS `carrera_id`;

INSERT IGNORE INTO `materias` (`carrera_id`, `nombre`, `anio_carrera`, `cuatrimestre`, `categoria`, `icono`, `es_electiva`) VALUES
(1, 'Taller de Diseño I', 1, 1, 'taller', 'fa-pen-nib', 0),
(1, 'Tecnología I', 1, 1, 'tecnología', 'fa-microchip', 0),
(1, 'Dibujo', 1, 1, 'sistemas', 'fa-pencil', 0),
(1, 'Matemática', 1, 1, 'sistemas', 'fa-calculator', 0),
(1, 'Física', 1, 1, 'tecnología', 'fa-atom', 0),
(1, 'Taller de Diseño II', 1, 2, 'taller', 'fa-pen-nib', 0),
(1, 'Tecnología II', 1, 2, 'tecnología', 'fa-microchip', 0),
(1, 'Morfología', 1, 2, 'taller', 'fa-shapes', 0),
(1, 'Problemas Socioeconómicos Argentinos', 1, 2, 'sistemas', 'fa-users', 0),
(1, 'Taller de Diseño Orientado I', 2, 1, 'taller', 'fa-bullseye', 0),
(1, 'Tecnología Diseño Orientado I', 2, 1, 'tecnología', 'fa-code', 0),
(1, 'Sistemas de Representación Orientado I', 2, 1, 'sistemas', 'fa-compass-drafting', 0),
(1, 'Química', 2, 1, 'tecnología', 'fa-flask', 0),
(1, 'Panorama Histórico y Social del Diseño', 2, 1, 'sistemas', 'fa-landmark', 0),
(1, 'Taller de Diseño Orientado II', 2, 2, 'taller', 'fa-bullseye', 0),
(1, 'Tecnología de Diseño Orientado II', 2, 2, 'tecnología', 'fa-code', 0),
(1, 'Sistemas de Representación Orientado II', 2, 2, 'sistemas', 'fa-compass-drafting', 0),
(1, 'Dibujo Asistido por PC', 2, 2, 'sistemas', 'fa-desktop', 0),
(1, 'Asignatura Electiva', 2, 2, 'taller', 'fa-puzzle-piece', 1),
(1, 'Taller de Diseño Multimedial I', 3, 1, 'taller', 'fa-layer-group', 0),
(1, 'Tecnología de Diseño Multimedial I', 3, 1, 'tecnología', 'fa-code', 0),
(1, 'Sistemas de Representación para Diseño Multimedial I', 3, 1, 'sistemas', 'fa-compass-drafting', 0),
(1, 'Semiótica Aplicada al Diseño I', 3, 1, 'sistemas', 'fa-eye', 0),
(1, 'Asignatura Electiva', 3, 1, 'taller', 'fa-puzzle-piece', 1),
(1, 'Taller de Diseño Multimedial II', 3, 2, 'taller', 'fa-layer-group', 0),
(1, 'Tecnología de Diseño Multimedial II', 3, 2, 'tecnología', 'fa-code', 0),
(1, 'Sistemas de Representación para Diseño Multimedial II', 3, 2, 'sistemas', 'fa-compass-drafting', 0),
(1, 'Semiótica Aplicada al Diseño II', 3, 2, 'sistemas', 'fa-eye', 0),
(1, 'Asignatura Electiva', 3, 2, 'taller', 'fa-puzzle-piece', 1),
(1, 'Taller de Diseño Multimedial III', 4, 1, 'taller', 'fa-layer-group', 0),
(1, 'Tecnología de Diseño Multimedial III', 4, 1, 'tecnología', 'fa-code', 0),
(1, 'Diseño Estratégico, Innovación y Emprendimiento I', 4, 1, 'sistemas', 'fa-lightbulb', 0),
(1, 'Ciencia, Tecnología y Proyecto de País', 4, 1, 'sistemas', 'fa-rocket', 0),
(1, 'Asig. Electiva Diseño Multimedial', 4, 1, 'taller', 'fa-puzzle-piece', 1),
(1, 'Taller de Diseño Multimedial IV', 4, 2, 'taller', 'fa-layer-group', 0),
(1, 'Tecnología de Diseño Multimedial IV', 4, 2, 'tecnología', 'fa-code', 0),
(1, 'Diseño Estratégico e Innovación II', 4, 2, 'sistemas', 'fa-lightbulb', 0),
(1, 'Innovación y Desarrollo Industrial', 4, 2, 'sistemas', 'fa-industry', 0),
(1, 'Herramientas para Exposiciones', 4, 2, 'tecnología', 'fa-vr-cardboard', 0),
(1, 'Taller de Proyecto de Diseño Multimedial', 5, 1, 'taller', 'fa-cubes', 0),
(1, 'Metodología Científica Aplicada al Diseño', 5, 1, 'sistemas', 'fa-magnifying-glass', 0),
(1, 'Formulación, Evaluación de Proyectos y Financiamiento', 5, 1, 'sistemas', 'fa-chart-pie', 0),
(1, 'Asig. Electiva Diseño Multimedial', 5, 1, 'taller', 'fa-puzzle-piece', 1),
(1, 'Comunicación del Proyecto', 5, 2, 'sistemas', 'fa-bullhorn', 0),
(1, 'Proyecto Final de Diseño Multimedial', 5, 2, 'taller', 'fa-flag-checkered', 0),
(1, 'Legislación y Práctica Profesional', 5, 2, 'sistemas', 'fa-scale-balanced', 0),
(1, 'Asig. Electiva Diseño Multimedial', 5, 2, 'taller', 'fa-puzzle-piece', 1);

-- 4. Trabajos: origen del archivo, estado de procesamiento, autoría y licencia
ALTER TABLE `trabajos`
  ADD COLUMN IF NOT EXISTS `electiva_nombre` varchar(120) DEFAULT NULL AFTER `ciclo_lectivo`,
  CHANGE COLUMN IF EXISTS `pdf_url` `origen_url` varchar(2048) NOT NULL,
  ADD COLUMN IF NOT EXISTS `origen_tipo` enum('pdf','zip') DEFAULT NULL AFTER `origen_url`,
  ADD COLUMN IF NOT EXISTS `proveedor` enum('drive','dropbox','onedrive','directo','otro') DEFAULT NULL AFTER `origen_tipo`,
  ADD COLUMN IF NOT EXISTS `estado_proceso` enum('pendiente','procesando','listo','error') NOT NULL DEFAULT 'pendiente' AFTER `portada_url`,
  ADD COLUMN IF NOT EXISTS `error_proceso` varchar(255) DEFAULT NULL AFTER `estado_proceso`,
  ADD COLUMN IF NOT EXISTS `sha256` char(64) DEFAULT NULL AFTER `error_proceso`,
  ADD COLUMN IF NOT EXISTS `tamano_bytes` bigint(20) UNSIGNED DEFAULT NULL AFTER `sha256`,
  ADD COLUMN IF NOT EXISTS `procesado_en` datetime DEFAULT NULL AFTER `tamano_bytes`,
  ADD COLUMN IF NOT EXISTS `licencia` enum('todos_los_derechos','cc_by','cc_by_nc','cc_by_nc_nd') NOT NULL DEFAULT 'todos_los_derechos' AFTER `procesado_en`,
  ADD COLUMN IF NOT EXISTS `nota_publica` tinyint(1) NOT NULL DEFAULT 0 AFTER `nota_final`,
  MODIFY COLUMN `estado` enum('borrador','publicado','oculto') NOT NULL DEFAULT 'borrador',
  ADD COLUMN IF NOT EXISTS `eliminado_en` datetime DEFAULT NULL AFTER `actualizado_en`,
  ADD INDEX IF NOT EXISTS `idx_estado_creado` (`estado`,`creado_en`),
  ADD CONSTRAINT IF NOT EXISTS `chk_trabajo_nota` CHECK (`nota_final` IS NULL OR (`nota_final` >= 0 AND `nota_final` <= 10));

-- Un usuario con trabajos ya no se borra en cascada: se da de baja (usuarios.activo = 0)
ALTER TABLE `trabajos` DROP FOREIGN KEY IF EXISTS `trabajos_ibfk_1`;
ALTER TABLE `trabajos`
  ADD CONSTRAINT `fk_trabajos_usuario` FOREIGN KEY IF NOT EXISTS (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT;

-- 5. Votos: solo +1 o -1
ALTER TABLE `votos`
  ADD CONSTRAINT IF NOT EXISTS `chk_voto_valor` CHECK (`valor` IN (-1, 1));

-- 6. Tablas nuevas
CREATE TABLE IF NOT EXISTS `trabajo_autores` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `trabajo_id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `nombre_externo` varchar(120) DEFAULT NULL,
  `orden` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `estado` enum('pendiente','aceptado','rechazado') NOT NULL DEFAULT 'pendiente',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trabajo_usuario` (`trabajo_id`,`usuario_id`),
  KEY `idx_autor_usuario` (`usuario_id`),
  CONSTRAINT `fk_autores_trabajo` FOREIGN KEY (`trabajo_id`) REFERENCES `trabajos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_autores_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_autor_identidad` CHECK (`usuario_id` IS NOT NULL OR `nombre_externo` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tokens_usuario` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('verificar_email','recuperar_password') NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado_en` datetime DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_token_usuario` (`usuario_id`,`tipo`),
  CONSTRAINT `fk_tokens_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `intentos_login` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` varbinary(16) NOT NULL,
  `email` varchar(120) NOT NULL,
  `exito` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_intentos_ip` (`ip`,`creado_en`),
  KEY `idx_intentos_email` (`email`,`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nickname_historial` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `nickname` varchar(20) NOT NULL,
  `cambiado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nick_historial` (`nickname`),
  KEY `idx_nick_usuario` (`usuario_id`),
  CONSTRAINT `fk_nickhist_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `denuncias` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `trabajo_id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `motivo` enum('derechos_autor','suplantacion','contenido_inapropiado','otro') NOT NULL,
  `detalle` varchar(1000) DEFAULT NULL,
  `estado` enum('abierta','en_revision','resuelta','descartada') NOT NULL DEFAULT 'abierta',
  `resuelta_por` int(10) UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `resuelta_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_denuncia_estado` (`estado`,`creado_en`),
  KEY `idx_denuncia_trabajo` (`trabajo_id`),
  CONSTRAINT `fk_denuncias_trabajo` FOREIGN KEY (`trabajo_id`) REFERENCES `trabajos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_denuncias_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_denuncias_resuelta_por` FOREIGN KEY (`resuelta_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auditoria` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `accion` varchar(60) NOT NULL,
  `entidad` varchar(40) DEFAULT NULL,
  `entidad_id` int(10) UNSIGNED DEFAULT NULL,
  `detalle` varchar(500) DEFAULT NULL,
  `ip` varbinary(16) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_entidad` (`entidad`,`entidad_id`),
  KEY `idx_auditoria_usuario` (`usuario_id`,`creado_en`),
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. El autor que sube un trabajo figura como autor aceptado (para trabajos que ya existan)
INSERT IGNORE INTO `trabajo_autores` (`trabajo_id`, `usuario_id`, `orden`, `estado`)
  SELECT `id`, `usuario_id`, 1, 'aceptado' FROM `trabajos`;

-- 8. Disparadores (validan que el ciclo lectivo corresponda al año de la materia)
DROP TRIGGER IF EXISTS `trg_trabajo_ciclo_valido`;
DROP TRIGGER IF EXISTS `trg_trabajo_ciclo_valido_upd`;
DELIMITER $$
CREATE TRIGGER `trg_trabajo_ciclo_valido` BEFORE INSERT ON `trabajos` FOR EACH ROW
BEGIN
  DECLARE v_inicio SMALLINT; DECLARE v_anio SMALLINT;
  SELECT c.anio_inicio, m.anio_carrera INTO v_inicio, v_anio
    FROM materias m JOIN carreras c ON c.id = m.carrera_id WHERE m.id = NEW.materia_id;
  IF NEW.ciclo_lectivo < v_inicio + v_anio - 1 OR NEW.ciclo_lectivo > YEAR(CURDATE()) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ciclo lectivo inválido para el año de la materia';
  END IF;
END
$$
CREATE TRIGGER `trg_trabajo_ciclo_valido_upd` BEFORE UPDATE ON `trabajos` FOR EACH ROW
BEGIN
  DECLARE v_inicio SMALLINT; DECLARE v_anio SMALLINT;
  IF NEW.materia_id <> OLD.materia_id OR NEW.ciclo_lectivo <> OLD.ciclo_lectivo THEN
    SELECT c.anio_inicio, m.anio_carrera INTO v_inicio, v_anio
      FROM materias m JOIN carreras c ON c.id = m.carrera_id WHERE m.id = NEW.materia_id;
    IF NEW.ciclo_lectivo < v_inicio + v_anio - 1 OR NEW.ciclo_lectivo > YEAR(CURDATE()) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ciclo lectivo inválido para el año de la materia';
    END IF;
  END IF;
END
$$
DELIMITER ;

-- 9. Listo
INSERT IGNORE INTO `migraciones` (`version`) VALUES ('002');
