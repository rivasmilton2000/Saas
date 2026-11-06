-- Zentra: esquema de la version PHP de master (b3d3007).
-- Importar en phpMyAdmin o con el cliente de MariaDB/MySQL.
-- Sin DROP, TRUNCATE ni registros historicos o contrasenas predeterminadas.
-- Reimportar conserva los registros. Una tabla existente no se modifica.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `zentra`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `zentra`;

-- AuthController::login consulta estos cinco campos.
-- Los tipos y los roles se contrastaron con el esquema historico del proyecto.
-- No existen relaciones con otras tablas en la version actual.
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `rol` VARCHAR(10) NOT NULL DEFAULT 'user',
    `estado` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `usuarios_username_unique` (`username`),
    CONSTRAINT `usuarios_rol_check` CHECK (`rol` IN ('admin', 'user')),
    CONSTRAINT `usuarios_estado_check` CHECK (`estado` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
