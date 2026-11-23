-- ════════════════════════════════════════════════════════════
-- BEDM · esquema de base de datos · VERSIÓN 4 (instalación nueva)
-- Motor: MariaDB 10.4+ (XAMPP) / MySQL 8+. Importar con la base `bedm` inexistente o vacía.
-- Si ya tenés la base de la versión 1, NO uses este archivo: usá
-- database/migraciones/ (002, 003 y 004; conservan tus datos).
-- ════════════════════════════════════════════════════════════

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `bedm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bedm`;

-- ── Carreras ───────────────────────────────────────────────
CREATE TABLE `carreras` (
  `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `anio_inicio` smallint(5) UNSIGNED NOT NULL,
  `duracion_anios` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `carreras` (`id`, `nombre`, `anio_inicio`, `duracion_anios`) VALUES
(1, 'Diseño Multimedial', 2023, 5);

-- ── Usuarios ───────────────────────────────────────────────
CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `carrera_id` tinyint(3) UNSIGNED NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `apellido` varchar(60) NOT NULL,
  `nickname` varchar(20) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `legajo` varchar(12) DEFAULT NULL,
  `rol` enum('alumno','docente','admin') NOT NULL DEFAULT 'alumno',
  `avatar_url` varchar(255) DEFAULT NULL,
  `banner_url` varchar(255) DEFAULT NULL,
  `bio` varchar(280) DEFAULT NULL,
  `anio_ingreso` smallint(5) UNSIGNED DEFAULT NULL,
  `email_verificado` tinyint(1) NOT NULL DEFAULT 0,
  `terminos_aceptados_en` datetime DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `baja_en` datetime DEFAULT NULL,
  `sesion_version` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nickname` (`nickname`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `legajo` (`legajo`),
  KEY `carrera_id` (`carrera_id`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Materias ───────────────────────────────────────────────
CREATE TABLE `materias` (
  `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `carrera_id` tinyint(3) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `anio_carrera` tinyint(3) UNSIGNED NOT NULL,
  `cuatrimestre` tinyint(3) UNSIGNED NOT NULL,
  `categoria` enum('taller','tecnología','sistemas') NOT NULL,
  `icono` varchar(40) NOT NULL DEFAULT 'fa-book',
  `es_electiva` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_materia` (`carrera_id`,`anio_carrera`,`cuatrimestre`,`nombre`),
  CONSTRAINT `materias_ibfk_1` FOREIGN KEY (`carrera_id`) REFERENCES `carreras` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- ── Cursadas (qué materias cursa cada alumno) ──────────────
CREATE TABLE `cursadas` (
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `materia_id` smallint(5) UNSIGNED NOT NULL,
  `ciclo_lectivo` smallint(5) UNSIGNED NOT NULL,
  `estado` enum('cursando','aprobada','libre','abandonada') NOT NULL DEFAULT 'cursando',
  PRIMARY KEY (`usuario_id`,`materia_id`,`ciclo_lectivo`),
  KEY `materia_id` (`materia_id`),
  CONSTRAINT `cursadas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cursadas_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Trabajos ───────────────────────────────────────────────
-- usuario_id = quien lo subió. Los autores (incluido quien sube) están en trabajo_autores.
-- Un usuario con trabajos NO se puede borrar (ON DELETE RESTRICT): se da de baja con usuarios.activo = 0.
CREATE TABLE `trabajos` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `materia_id` smallint(5) UNSIGNED NOT NULL,
  `ciclo_lectivo` smallint(5) UNSIGNED NOT NULL,
  `electiva_nombre` varchar(120) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `origen_url` varchar(2048) NOT NULL,
  `origen_tipo` enum('pdf','zip') DEFAULT NULL,
  `proveedor` enum('drive','dropbox','onedrive','directo','otro') DEFAULT NULL,
  `portada_url` varchar(255) DEFAULT NULL,
  `estado_proceso` enum('pendiente','procesando','listo','error') NOT NULL DEFAULT 'pendiente',
  `error_proceso` varchar(255) DEFAULT NULL,
  `sha256` char(64) DEFAULT NULL,
  `tamano_bytes` bigint(20) UNSIGNED DEFAULT NULL,
  `procesado_en` datetime DEFAULT NULL,
  `licencia` enum('todos_los_derechos','cc_by','cc_by_nc','cc_by_nc_nd') NOT NULL DEFAULT 'todos_los_derechos',
  `nota_final` decimal(4,2) DEFAULT NULL,
  `nota_publica` tinyint(1) NOT NULL DEFAULT 0,
  `estado` enum('borrador','publicado','oculto') NOT NULL DEFAULT 'borrador',
  `likes_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `eliminado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_materia_ciclo` (`materia_id`,`ciclo_lectivo`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_estado_creado` (`estado`,`creado_en`),
  UNIQUE KEY `uq_trabajo_sha256` (`sha256`),
  CONSTRAINT `fk_trabajos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `trabajos_ibfk_2` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`),
  CONSTRAINT `chk_trabajo_nota` CHECK (`nota_final` IS NULL OR (`nota_final` >= 0 AND `nota_final` <= 10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- ── Páginas de previsualización de cada trabajo ────────────
CREATE TABLE `trabajo_paginas` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `trabajo_id` int(10) UNSIGNED NOT NULL,
  `numero` smallint(5) UNSIGNED NOT NULL,
  `imagen_url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trabajo_pagina` (`trabajo_id`,`numero`),
  CONSTRAINT `trabajo_paginas_ibfk_1` FOREIGN KEY (`trabajo_id`) REFERENCES `trabajos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Autores y coautores ────────────────────────────────────
-- Un coautor puede ser un usuario registrado (usuario_id) o alguien que todavía no tiene cuenta (nombre_externo).
-- Un coautor registrado debe aceptar figurar en el trabajo (estado = 'aceptado').
CREATE TABLE `trabajo_autores` (
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

-- ── Comentarios y votos ────────────────────────────────────
CREATE TABLE `comentarios` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `trabajo_id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `texto` varchar(1000) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_trabajo` (`trabajo_id`,`creado_en`),
  CONSTRAINT `comentarios_ibfk_1` FOREIGN KEY (`trabajo_id`) REFERENCES `trabajos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comentarios_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `votos` (
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `trabajo_id` int(10) UNSIGNED NOT NULL,
  `valor` tinyint(4) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usuario_id`,`trabajo_id`),
  KEY `trabajo_id` (`trabajo_id`),
  CONSTRAINT `votos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `votos_ibfk_2` FOREIGN KEY (`trabajo_id`) REFERENCES `trabajos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_voto_valor` CHECK (`valor` IN (-1, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seguridad de cuenta ────────────────────────────────────
-- Tokens de un solo uso para verificar email y recuperar contraseña (se guarda solo el hash del token).
CREATE TABLE `tokens_usuario` (
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

-- Intentos de inicio de sesión (para limitar la fuerza bruta). ip = INET6_ATON(ip).
CREATE TABLE `intentos_login` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` varbinary(16) NOT NULL,
  `email` varchar(120) NOT NULL,
  `exito` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_intentos_ip` (`ip`,`creado_en`),
  KEY `idx_intentos_email` (`email`,`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nicknames que alguien usó antes: quedan reservados para que nadie pueda hacerse pasar por ese autor.
CREATE TABLE `nickname_historial` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `nickname` varchar(20) NOT NULL,
  `cambiado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nick_historial` (`nickname`),
  KEY `idx_nick_usuario` (`usuario_id`),
  CONSTRAINT `fk_nickhist_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Moderación y auditoría ─────────────────────────────────
CREATE TABLE `denuncias` (
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

CREATE TABLE `auditoria` (
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

-- ── Control de versiones del esquema ───────────────────────
CREATE TABLE `migraciones` (
  `version` varchar(20) NOT NULL,
  `aplicada_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `migraciones` (`version`) VALUES ('001'), ('002'), ('003'), ('004');
