-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: zentra
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `zentra`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `zentra` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `zentra`;

--
-- Table structure for table `analytics_page_visits`
--

DROP TABLE IF EXISTS `analytics_page_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `analytics_page_visits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `username_snapshot` varchar(100) NOT NULL,
  `rol_snapshot` varchar(20) NOT NULL DEFAULT 'user',
  `pais_snapshot` varchar(80) DEFAULT NULL,
  `route_key` varchar(80) NOT NULL,
  `route_label` varchar(120) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_analytics_page_visit_country` (`pais_snapshot`,`created_at`),
  KEY `idx_analytics_page_visit_date` (`created_at`),
  KEY `idx_analytics_page_visit_route` (`route_key`,`created_at`),
  KEY `idx_analytics_page_visit_user` (`id_usuario`),
  CONSTRAINT `fk_analytics_page_visit_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_page_visits`
--

LOCK TABLES `analytics_page_visits` WRITE;
/*!40000 ALTER TABLE `analytics_page_visits` DISABLE KEYS */;
/*!40000 ALTER TABLE `analytics_page_visits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bitacora_movimientos`
--

DROP TABLE IF EXISTS `bitacora_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bitacora_movimientos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `username_snapshot` varchar(100) NOT NULL,
  `rol_snapshot` varchar(20) DEFAULT NULL,
  `modulo` varchar(80) NOT NULL,
  `accion` varchar(120) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `entidad_tipo` varchar(80) DEFAULT NULL,
  `entidad_id` int(11) DEFAULT NULL,
  `contexto_json` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bitacora_fecha` (`created_at`),
  KEY `idx_bitacora_modulo` (`modulo`),
  KEY `idx_bitacora_usuario` (`id_usuario`),
  CONSTRAINT `fk_bitacora_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bitacora_movimientos`
--

LOCK TABLES `bitacora_movimientos` WRITE;
/*!40000 ALTER TABLE `bitacora_movimientos` DISABLE KEYS */;
INSERT INTO `bitacora_movimientos` VALUES (1,10,'ztest_455f0c1c11_admin','admin','auth','login','Inicio de sesion correcto.',NULL,NULL,NULL,'::1',NULL,'2026-11-05 23:04:51'),(2,11,'ztest_455f0c1c11_user','user','auth','login','Inicio de sesion correcto.',NULL,NULL,NULL,'::1',NULL,'2026-11-05 23:04:51'),(3,12,'ztest_455f0c1c11_other','user','auth','login','Inicio de sesion correcto.',NULL,NULL,NULL,'::1',NULL,'2026-11-05 23:04:51');
/*!40000 ALTER TABLE `bitacora_movimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `centro_mando_avance`
--

DROP TABLE IF EXISTS `centro_mando_avance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `centro_mando_avance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `mes` smallint(6) NOT NULL,
  `anio` int(11) NOT NULL,
  `item_key` varchar(80) NOT NULL,
  `completado` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_empresa_periodo_item` (`id_empresa`,`mes`,`anio`,`item_key`),
  KEY `idx_centro_mando_avance_usuario` (`id_usuario`),
  CONSTRAINT `fk_centro_mando_avance_empresa` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_centro_mando_avance_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `centro_mando_avance_anio_check` CHECK (`anio` >= 2000 and `anio` <= 9999),
  CONSTRAINT `centro_mando_avance_mes_check` CHECK (`mes` >= 1 and `mes` <= 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `centro_mando_avance`
--

LOCK TABLES `centro_mando_avance` WRITE;
/*!40000 ALTER TABLE `centro_mando_avance` DISABLE KEYS */;
/*!40000 ALTER TABLE `centro_mando_avance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `centro_mando_config`
--

DROP TABLE IF EXISTS `centro_mando_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `centro_mando_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `item_key` varchar(80) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_empresa_item` (`id_empresa`,`item_key`),
  KEY `idx_centro_mando_config_usuario` (`id_usuario`),
  CONSTRAINT `fk_centro_mando_config_empresa` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_centro_mando_config_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `centro_mando_config`
--

LOCK TABLES `centro_mando_config` WRITE;
/*!40000 ALTER TABLE `centro_mando_config` DISABLE KEYS */;
/*!40000 ALTER TABLE `centro_mando_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `centro_mando_notas`
--

DROP TABLE IF EXISTS `centro_mando_notas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `centro_mando_notas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `mes` smallint(6) NOT NULL,
  `anio` int(11) NOT NULL,
  `nota` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_empresa_periodo_nota` (`id_empresa`,`mes`,`anio`),
  KEY `idx_centro_mando_notas_usuario` (`id_usuario`),
  CONSTRAINT `fk_centro_mando_notas_empresa` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_centro_mando_notas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `centro_mando_notas_anio_check` CHECK (`anio` >= 2000 and `anio` <= 9999),
  CONSTRAINT `centro_mando_notas_mes_check` CHECK (`mes` >= 1 and `mes` <= 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `centro_mando_notas`
--

LOCK TABLES `centro_mando_notas` WRITE;
/*!40000 ALTER TABLE `centro_mando_notas` DISABLE KEYS */;
/*!40000 ALTER TABLE `centro_mando_notas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `centro_mando_preferencias`
--

DROP TABLE IF EXISTS `centro_mando_preferencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `centro_mando_preferencias` (
  `id_usuario` int(10) unsigned NOT NULL,
  `ocultar_bienvenida` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  CONSTRAINT `fk_centro_mando_preferencias_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `centro_mando_preferencias`
--

LOCK TABLES `centro_mando_preferencias` WRITE;
/*!40000 ALTER TABLE `centro_mando_preferencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `centro_mando_preferencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dte_empresas`
--

DROP TABLE IF EXISTS `dte_empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dte_empresas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `iniciales` varchar(4) DEFAULT NULL,
  `color_emblema` varchar(20) DEFAULT '#f97316',
  `dui` varchar(20) DEFAULT NULL,
  `nit` varchar(20) DEFAULT NULL,
  `nrc` varchar(20) DEFAULT NULL,
  `tipo_legal` varchar(20) DEFAULT 'natural',
  `estado` smallint(6) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ultima_vez_usada` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dte_empresas_usuario` (`id_usuario`),
  KEY `idx_dte_empresas_estado` (`estado`),
  KEY `idx_dte_empresas_nit` (`nit`),
  KEY `idx_dte_empresas_nrc` (`nrc`),
  CONSTRAINT `fk_dte_empresas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `dte_usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_dte_empresas_tipo_legal` CHECK (`tipo_legal` in ('natural','juridica')),
  CONSTRAINT `chk_dte_empresas_estado` CHECK (`estado` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dte_empresas`
--

LOCK TABLES `dte_empresas` WRITE;
/*!40000 ALTER TABLE `dte_empresas` DISABLE KEYS */;
/*!40000 ALTER TABLE `dte_empresas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dte_facturas`
--

DROP TABLE IF EXISTS `dte_facturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dte_facturas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_libro` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `codigo_generacion` varchar(50) NOT NULL,
  `sello_recepcion` text DEFAULT NULL,
  `numero_control` varchar(50) DEFAULT NULL,
  `tipo_dte` varchar(10) DEFAULT NULL,
  `fecha` date NOT NULL,
  `nrc` varchar(20) DEFAULT NULL,
  `nit` varchar(20) DEFAULT NULL,
  `nombre_proveedor` varchar(200) DEFAULT NULL,
  `ventas_internas` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ventas_importacion` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ventas_internas_exentas` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ventas_importacion_exentas` decimal(10,2) NOT NULL DEFAULT 0.00,
  `credito_fiscal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_compras` decimal(10,2) NOT NULL DEFAULT 0.00,
  `iva_percibido` decimal(10,2) NOT NULL DEFAULT 0.00,
  `iva_retenido` decimal(10,2) NOT NULL DEFAULT 0.00,
  `numero_control_completo` varchar(100) DEFAULT NULL,
  `raw_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `nombre_cliente` varchar(200) DEFAULT NULL,
  `nrc_cliente` varchar(20) DEFAULT NULL,
  `numero_control_preimpreso` varchar(50) DEFAULT NULL,
  `numero_control_interno` varchar(50) DEFAULT NULL,
  `dia_emision` date DEFAULT NULL,
  `del_numero` int(11) DEFAULT NULL,
  `al_numero` int(11) DEFAULT NULL,
  `codigo_generacion_desde` varchar(50) DEFAULT NULL,
  `codigo_generacion_hasta` varchar(50) DEFAULT NULL,
  `ventas_exentas` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ventas_internas_gravadas` decimal(10,2) NOT NULL DEFAULT 0.00,
  `exportaciones` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_ventas_diarias_propias` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ventas_cuenta_terceros` decimal(10,2) NOT NULL DEFAULT 0.00,
  `debito_fiscal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ventas_exentas_contribuyente` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ventas_internas_gravadas_contribuyente` decimal(10,2) NOT NULL DEFAULT 0.00,
  `debito_fiscal_contribuyente` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ventas_totales` decimal(10,2) NOT NULL DEFAULT 0.00,
  `nit_agente_retencion` varchar(20) DEFAULT NULL,
  `fecha_emision_retencion` date DEFAULT NULL,
  `tipo_documento_relacionado` varchar(20) DEFAULT NULL,
  `serie_documento` varchar(50) DEFAULT NULL,
  `numero_documento` varchar(50) DEFAULT NULL,
  `monto_sujeto_retencion` decimal(10,2) NOT NULL DEFAULT 0.00,
  `retencion_iva_1` decimal(10,2) NOT NULL DEFAULT 0.00,
  `dui_agente_retencion` varchar(20) DEFAULT NULL,
  `numero_anexo` varchar(20) DEFAULT NULL,
  `compras_exentas` decimal(10,2) NOT NULL DEFAULT 0.00,
  `compras_gravadas` decimal(10,2) NOT NULL DEFAULT 0.00,
  `impuestos_calculados` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fovial_otros` decimal(10,2) NOT NULL DEFAULT 0.00,
  `import_batch_id` varchar(64) DEFAULT NULL,
  `import_index` int(11) DEFAULT NULL,
  `source_filename` varchar(255) DEFAULT NULL,
  `raw_json_hash` char(64) DEFAULT NULL,
  `posible_duplicado` smallint(6) NOT NULL DEFAULT 0,
  `fuera_de_periodo` smallint(6) NOT NULL DEFAULT 0,
  `motivo_no_contabilizado` varchar(255) DEFAULT NULL,
  `tipo_documento_nombre` varchar(100) DEFAULT NULL,
  `documento_relacionado_numero_control` varchar(100) DEFAULT NULL,
  `documento_relacionado_codigo_generacion` varchar(100) DEFAULT NULL,
  `documento_relacionado_fecha` date DEFAULT NULL,
  `tipo_movimiento` smallint(6) NOT NULL DEFAULT 1,
  `signo_contable` smallint(6) NOT NULL DEFAULT 1,
  `es_repetido` smallint(6) NOT NULL DEFAULT 0,
  `motivo_exclusion` varchar(255) DEFAULT NULL,
  `contabilizable` smallint(6) NOT NULL DEFAULT 1,
  `documento_relacionado_tipo` varchar(20) DEFAULT NULL,
  `documento_relacionado_numero` varchar(60) DEFAULT NULL,
  `documento_relacionado_codigo` varchar(60) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dte_facturas_usuario` (`id_usuario`),
  KEY `idx_dte_facturas_fecha` (`fecha`),
  KEY `idx_dte_facturas_libro` (`id_libro`),
  KEY `idx_dte_facturas_codigo_generacion` (`codigo_generacion`),
  KEY `idx_dte_facturas_raw_json_hash` (`raw_json_hash`),
  CONSTRAINT `fk_dte_facturas_libro` FOREIGN KEY (`id_libro`) REFERENCES `dte_libros` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dte_facturas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `dte_usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dte_facturas`
--

LOCK TABLES `dte_facturas` WRITE;
/*!40000 ALTER TABLE `dte_facturas` DISABLE KEYS */;
/*!40000 ALTER TABLE `dte_facturas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dte_facturas_disponibles`
--

DROP TABLE IF EXISTS `dte_facturas_disponibles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dte_facturas_disponibles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `total` int(11) NOT NULL DEFAULT 50,
  `consumidas` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dte_facturas_disponibles_usuario` (`id_usuario`),
  CONSTRAINT `fk_dte_facturas_disponibles_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `dte_usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dte_facturas_disponibles`
--

LOCK TABLES `dte_facturas_disponibles` WRITE;
/*!40000 ALTER TABLE `dte_facturas_disponibles` DISABLE KEYS */;
/*!40000 ALTER TABLE `dte_facturas_disponibles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dte_libros`
--

DROP TABLE IF EXISTS `dte_libros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dte_libros` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `mes` smallint(6) NOT NULL,
  `anio` int(11) NOT NULL,
  `nombre` varchar(100) GENERATED ALWAYS AS (concat(`tipo`,'-',`anio`,'-',`mes`)) STORED,
  `estado` smallint(6) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dte_libro` (`id_empresa`,`tipo`,`mes`,`anio`),
  KEY `idx_dte_libros_usuario` (`id_usuario`),
  KEY `idx_dte_libros_estado` (`estado`),
  CONSTRAINT `fk_dte_libros_empresa` FOREIGN KEY (`id_empresa`) REFERENCES `dte_empresas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dte_libros_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `dte_usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_dte_libros_tipo` CHECK (`tipo` in ('compras','ventas_consumidor','ventas_contribuyente','retencion_iva')),
  CONSTRAINT `chk_dte_libros_mes` CHECK (`mes` between 1 and 12),
  CONSTRAINT `chk_dte_libros_anio` CHECK (`anio` between 2000 and 9999),
  CONSTRAINT `chk_dte_libros_estado` CHECK (`estado` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dte_libros`
--

LOCK TABLES `dte_libros` WRITE;
/*!40000 ALTER TABLE `dte_libros` DISABLE KEYS */;
/*!40000 ALTER TABLE `dte_libros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dte_user_bridge`
--

DROP TABLE IF EXISTS `dte_user_bridge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dte_user_bridge` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `main_user_id` int(10) unsigned NOT NULL,
  `dte_user_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dte_user_bridge_main_user` (`main_user_id`),
  UNIQUE KEY `uq_dte_user_bridge_dte_user` (`dte_user_id`),
  KEY `idx_dte_user_bridge_main_user` (`main_user_id`),
  KEY `idx_dte_user_bridge_dte_user` (`dte_user_id`),
  CONSTRAINT `fk_dte_user_bridge_dte_user` FOREIGN KEY (`dte_user_id`) REFERENCES `dte_usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dte_user_bridge_main_user` FOREIGN KEY (`main_user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dte_user_bridge`
--

LOCK TABLES `dte_user_bridge` WRITE;
/*!40000 ALTER TABLE `dte_user_bridge` DISABLE KEYS */;
/*!40000 ALTER TABLE `dte_user_bridge` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dte_user_module_preferences`
--

DROP TABLE IF EXISTS `dte_user_module_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dte_user_module_preferences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `module_key` varchar(80) NOT NULL,
  `preferences_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`preferences_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_module_preferences` (`user_id`,`module_key`),
  KEY `idx_dte_user_module_preferences_user` (`user_id`),
  CONSTRAINT `fk_dte_user_module_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `dte_usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dte_user_module_preferences`
--

LOCK TABLES `dte_user_module_preferences` WRITE;
/*!40000 ALTER TABLE `dte_user_module_preferences` DISABLE KEYS */;
/*!40000 ALTER TABLE `dte_user_module_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dte_usuarios`
--

DROP TABLE IF EXISTS `dte_usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dte_usuarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `nombre_completo` varchar(150) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `rol` varchar(20) NOT NULL DEFAULT 'user',
  `estado` smallint(6) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dte_usuarios_username` (`username`),
  KEY `idx_dte_usuarios_estado` (`estado`),
  KEY `idx_dte_usuarios_rol` (`rol`),
  CONSTRAINT `chk_dte_usuarios_rol` CHECK (`rol` in ('admin','user')),
  CONSTRAINT `chk_dte_usuarios_estado` CHECK (`estado` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dte_usuarios`
--

LOCK TABLES `dte_usuarios` WRITE;
/*!40000 ALTER TABLE `dte_usuarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `dte_usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_logs`
--

DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `type` varchar(80) NOT NULL,
  `reference_key` varchar(191) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_logs_user_type` (`user_id`,`type`,`status`),
  CONSTRAINT `fk_email_logs_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_logs`
--

LOCK TABLES `email_logs` WRITE;
/*!40000 ALTER TABLE `email_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `empresas`
--

DROP TABLE IF EXISTS `empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empresas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `iniciales` varchar(4) DEFAULT NULL,
  `color_emblema` varchar(20) DEFAULT '#f97316',
  `dui` varchar(20) DEFAULT NULL,
  `nit` varchar(20) DEFAULT NULL,
  `nrc` varchar(20) DEFAULT NULL,
  `tipo_legal` varchar(15) DEFAULT 'natural',
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ultima_vez_usada` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_empresas_usuario` (`id_usuario`),
  CONSTRAINT `fk_empresas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `empresas_tipo_legal_check` CHECK (`tipo_legal` in ('natural','juridica'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `empresas`
--

LOCK TABLES `empresas` WRITE;
/*!40000 ALTER TABLE `empresas` DISABLE KEYS */;
/*!40000 ALTER TABLE `empresas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturas`
--

DROP TABLE IF EXISTS `facturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `facturas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_libro` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `codigo_generacion` varchar(50) NOT NULL,
  `sello_recepcion` text DEFAULT NULL,
  `numero_control` varchar(50) DEFAULT NULL,
  `tipo_dte` varchar(10) DEFAULT NULL,
  `fecha` date NOT NULL,
  `nrc` varchar(20) DEFAULT NULL,
  `nit` varchar(20) DEFAULT NULL,
  `nombre_proveedor` varchar(200) DEFAULT NULL,
  `ventas_internas` decimal(10,2) DEFAULT 0.00,
  `ventas_importacion` decimal(10,2) DEFAULT 0.00,
  `ventas_internas_exentas` decimal(10,2) DEFAULT 0.00,
  `ventas_importacion_exentas` decimal(10,2) DEFAULT 0.00,
  `credito_fiscal` decimal(10,2) DEFAULT 0.00,
  `total_compras` decimal(10,2) DEFAULT 0.00,
  `iva_percibido` decimal(10,2) DEFAULT 0.00,
  `iva_retenido` decimal(10,2) DEFAULT 0.00,
  `numero_control_completo` varchar(100) DEFAULT NULL,
  `raw_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `nombre_cliente` varchar(200) DEFAULT NULL,
  `nrc_cliente` varchar(20) DEFAULT NULL,
  `numero_control_preimpreso` varchar(50) DEFAULT NULL,
  `numero_control_interno` varchar(50) DEFAULT NULL,
  `dia_emision` date DEFAULT NULL,
  `del_numero` int(11) DEFAULT NULL,
  `al_numero` int(11) DEFAULT NULL,
  `codigo_generacion_desde` varchar(50) DEFAULT NULL,
  `codigo_generacion_hasta` varchar(50) DEFAULT NULL,
  `ventas_exentas` decimal(10,2) DEFAULT 0.00,
  `ventas_internas_gravadas` decimal(10,2) DEFAULT 0.00,
  `exportaciones` decimal(10,2) DEFAULT 0.00,
  `total_ventas_diarias_propias` decimal(10,2) DEFAULT 0.00,
  `ventas_cuenta_terceros` decimal(10,2) DEFAULT 0.00,
  `debito_fiscal` decimal(10,2) DEFAULT 0.00,
  `ventas_exentas_contribuyente` decimal(10,2) DEFAULT 0.00,
  `ventas_internas_gravadas_contribuyente` decimal(10,2) DEFAULT 0.00,
  `debito_fiscal_contribuyente` decimal(10,2) DEFAULT 0.00,
  `ventas_totales` decimal(10,2) DEFAULT 0.00,
  `nit_agente_retencion` varchar(20) DEFAULT NULL,
  `fecha_emision_retencion` date DEFAULT NULL,
  `tipo_documento_relacionado` varchar(20) DEFAULT NULL,
  `serie_documento` varchar(50) DEFAULT NULL,
  `numero_documento` varchar(50) DEFAULT NULL,
  `monto_sujeto_retencion` decimal(10,2) DEFAULT 0.00,
  `retencion_iva_1` decimal(10,2) DEFAULT 0.00,
  `dui_agente_retencion` varchar(20) DEFAULT NULL,
  `numero_anexo` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_factura_libro` (`id_libro`,`codigo_generacion`),
  KEY `idx_facturas_usuario` (`id_usuario`),
  CONSTRAINT `fk_facturas_libro` FOREIGN KEY (`id_libro`) REFERENCES `libros` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_facturas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturas`
--

LOCK TABLES `facturas` WRITE;
/*!40000 ALTER TABLE `facturas` DISABLE KEYS */;
/*!40000 ALTER TABLE `facturas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturas_disponibles`
--

DROP TABLE IF EXISTS `facturas_disponibles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `facturas_disponibles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `total` int(11) NOT NULL DEFAULT 50,
  `consumidas` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `facturas_disponibles_id_usuario_key` (`id_usuario`),
  CONSTRAINT `fk_facturas_disponibles_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturas_disponibles`
--

LOCK TABLES `facturas_disponibles` WRITE;
/*!40000 ALTER TABLE `facturas_disponibles` DISABLE KEYS */;
/*!40000 ALTER TABLE `facturas_disponibles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `libros`
--

DROP TABLE IF EXISTS `libros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `libros` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `tipo` varchar(30) NOT NULL,
  `mes` smallint(6) NOT NULL,
  `anio` int(11) NOT NULL,
  `nombre` varchar(100) GENERATED ALWAYS AS (concat(`tipo`,'-',`anio`,'-',`mes`)) STORED,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_libro` (`id_empresa`,`tipo`,`mes`,`anio`),
  KEY `idx_libros_usuario` (`id_usuario`),
  CONSTRAINT `fk_libros_empresa` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_libros_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `libros_anio_check` CHECK (`anio` >= 2000 and `anio` <= 9999),
  CONSTRAINT `libros_mes_check` CHECK (`mes` >= 1 and `mes` <= 12),
  CONSTRAINT `libros_tipo_check` CHECK (`tipo` in ('compras','ventas_consumidor','ventas_contribuyente','retencion_iva'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `libros`
--

LOCK TABLES `libros` WRITE;
/*!40000 ALTER TABLE `libros` DISABLE KEYS */;
/*!40000 ALTER TABLE `libros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mensajes_contacto`
--

DROP TABLE IF EXISTS `mensajes_contacto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mensajes_contacto` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `asunto` varchar(150) NOT NULL,
  `mensaje` text NOT NULL,
  `fecha_envio` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `ck_mensajes_contacto_email` CHECK (`email` regexp '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+[.][A-Za-z]{2,}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mensajes_contacto`
--

LOCK TABLES `mensajes_contacto` WRITE;
/*!40000 ALTER TABLE `mensajes_contacto` DISABLE KEYS */;
/*!40000 ALTER TABLE `mensajes_contacto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `subscription_id` int(10) unsigned DEFAULT NULL,
  `stripe_checkout_session_id` varchar(120) DEFAULT NULL,
  `stripe_invoice_id` varchar(120) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'pending',
  `receipt_url` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_payments_invoice_unique` (`stripe_invoice_id`),
  KEY `idx_payments_checkout_session` (`stripe_checkout_session_id`,`created_at`),
  KEY `idx_payments_user_status` (`user_id`,`status`,`created_at`),
  KEY `fk_payments_plan` (`plan_id`),
  KEY `fk_payments_subscription` (`subscription_id`),
  CONSTRAINT `fk_payments_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id_plan`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planes`
--

DROP TABLE IF EXISTS `planes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `planes` (
  `id_plan` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `moneda` varchar(10) NOT NULL DEFAULT 'USD',
  `periodo` varchar(20) NOT NULL DEFAULT 'mes',
  `destacado` tinyint(1) NOT NULL DEFAULT 0,
  `personalizado` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `limite_empresas` int(11) DEFAULT NULL,
  `limite_usuarios` int(11) DEFAULT NULL,
  `limite_documentos` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `stripe_price_id` varchar(120) DEFAULT NULL,
  `billing_interval` varchar(20) DEFAULT NULL,
  `is_free` tinyint(1) NOT NULL DEFAULT 0,
  `trial_days` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_plan`),
  UNIQUE KEY `planes_slug_key` (`slug`),
  KEY `idx_planes_activo_orden` (`activo`,`orden`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planes`
--

LOCK TABLES `planes` WRITE;
/*!40000 ALTER TABLE `planes` DISABLE KEYS */;
INSERT INTO `planes` VALUES (1,'free','Free','Ideal para conocer Zentra antes de contratar un plan.',0.00,'USD','prueba',0,0,1,1,1,1,25,'2026-11-05 23:03:02',NULL,NULL,1,0,'2026-11-05 23:03:02'),(2,'light','Light','Para emprendedores, contadores pequenos o negocios que estan iniciando.',6.99,'USD','mes',0,0,1,2,1,1,150,'2026-11-05 23:03:02',NULL,'month',0,NULL,'2026-11-05 23:03:02'),(3,'pro','Pro','Para negocios en crecimiento que necesitan facturacion, control y reportes mas completos.',17.99,'USD','mes',1,0,1,3,3,3,700,'2026-11-05 23:03:02',NULL,'month',0,NULL,'2026-11-05 23:03:02'),(4,'ultra','Ultra','Para empresas con operacion mas alta o contadores con varios clientes.',34.99,'USD','mes',0,0,1,4,10,8,2000,'2026-11-05 23:03:02',NULL,'month',0,NULL,'2026-11-05 23:03:02'),(5,'enterprise','Enterprise','Para empresas que necesitan mas capacidad, mas usuarios o modulos especiales.',NULL,'USD','cotizacion',0,1,1,5,NULL,NULL,NULL,'2026-11-05 23:03:02',NULL,NULL,0,NULL,'2026-11-05 23:03:02');
/*!40000 ALTER TABLE `planes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planes_caracteristicas`
--

DROP TABLE IF EXISTS `planes_caracteristicas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `planes_caracteristicas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_plan` int(10) unsigned NOT NULL,
  `caracteristica` varchar(255) NOT NULL,
  `incluido` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_planes_caracteristicas_plan` (`id_plan`,`orden`),
  CONSTRAINT `fk_planes_caracteristicas_plan` FOREIGN KEY (`id_plan`) REFERENCES `planes` (`id_plan`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planes_caracteristicas`
--

LOCK TABLES `planes_caracteristicas` WRITE;
/*!40000 ALTER TABLE `planes_caracteristicas` DISABLE KEYS */;
INSERT INTO `planes_caracteristicas` VALUES (1,1,'1 empresa de prueba',1,1),(2,1,'1 usuario administrador',1,2),(3,1,'Hasta 25 documentos de prueba',1,3),(4,1,'Acceso limitado al dashboard',1,4),(5,1,'Clientes y productos de prueba',1,5),(6,1,'Reportes basicos',1,6),(7,1,'Soporte por correo en 48-72 horas',1,7),(8,1,'Funciones nuevas en evaluacion',1,8),(9,2,'1 empresa activa',1,1),(10,2,'1 usuario administrador',1,2),(11,2,'Hasta 150 documentos mensuales',1,3),(12,2,'Clientes',1,4),(13,2,'Productos o servicios',1,5),(14,2,'Ventas',1,6),(15,2,'Reportes basicos',1,7),(16,2,'Exportacion a Excel',1,8),(17,2,'Acceso mensual a Zentra',1,9),(18,2,'Soporte por correo en 24-48 horas',1,10),(19,2,'Actualizaciones incluidas',1,11),(20,3,'Hasta 3 empresas/clientes',1,1),(21,3,'Hasta 3 usuarios',1,2),(22,3,'Hasta 700 documentos mensuales',1,3),(23,3,'Facturacion electronica DTE',1,4),(24,3,'Clientes y proveedores',1,5),(25,3,'Ventas y compras',1,6),(26,3,'Inventario basico',1,7),(27,3,'Dashboard avanzado',1,8),(28,3,'Reportes en Excel y PDF',1,9),(29,3,'Roles de usuario',1,10),(30,3,'Soporte prioritario en 12-24 horas',1,11),(31,3,'Funciones nuevas antes de liberacion general',1,12),(32,4,'Hasta 10 empresas/clientes',1,1),(33,4,'Hasta 8 usuarios',1,2),(34,4,'Hasta 2,000 documentos mensuales',1,3),(35,4,'Modulo DTE completo',1,4),(36,4,'Ventas',1,5),(37,4,'Compras',1,6),(38,4,'Inventario',1,7),(39,4,'Clientes',1,8),(40,4,'Proveedores',1,9),(41,4,'Cuentas por cobrar',1,10),(42,4,'Cuentas por pagar',1,11),(43,4,'Reportes avanzados',1,12),(44,4,'Exportacion Excel/PDF',1,13),(45,4,'Auditoria de usuarios',1,14),(46,4,'Soporte prioritario',1,15),(47,4,'Mayor capacidad operativa',1,16),(48,4,'Capacitacion inicial basica',1,17),(49,5,'Empresas ilimitadas o personalizadas',1,1),(50,5,'Usuarios personalizados',1,2),(51,5,'Documentos mensuales personalizados',1,3),(52,5,'Multi-sucursal',1,4),(53,5,'Integraciones API',1,5),(54,5,'Migracion de datos',1,6),(55,5,'Reportes personalizados',1,7),(56,5,'Capacitacion completa',1,8),(57,5,'Soporte tecnico dedicado',1,9),(58,5,'Desarrollo de modulos especiales',1,10);
/*!40000 ALTER TABLE `planes_caracteristicas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registros_pago_pendientes`
--

DROP TABLE IF EXISTS `registros_pago_pendientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registros_pago_pendientes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre_completo` varchar(150) NOT NULL,
  `email` varchar(160) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `pais` varchar(80) NOT NULL,
  `id_plan` int(10) unsigned NOT NULL,
  `plan_slug_snapshot` varchar(40) NOT NULL,
  `plan_nombre_snapshot` varchar(80) NOT NULL,
  `plan_precio_snapshot` decimal(10,2) DEFAULT NULL,
  `stripe_checkout_session_id` varchar(120) DEFAULT NULL,
  `stripe_customer_id` varchar(120) DEFAULT NULL,
  `stripe_subscription_id` varchar(120) DEFAULT NULL,
  `stripe_status` varchar(40) DEFAULT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'draft',
  `stripe_payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`stripe_payload_json`)),
  `created_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `auth_provider` varchar(30) DEFAULT NULL,
  `google_id` varchar(191) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `email_verificado_at` datetime DEFAULT NULL,
  `stripe_invoice_id` varchar(120) DEFAULT NULL,
  `stripe_invoice_url` text DEFAULT NULL,
  `stripe_invoice_pdf_url` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_registros_pago_checkout_unique` (`stripe_checkout_session_id`),
  UNIQUE KEY `idx_registros_pago_google_id_unique` (`google_id`),
  UNIQUE KEY `idx_registros_pago_subscription_unique` (`stripe_subscription_id`),
  KEY `idx_registros_pago_email` (`email`),
  KEY `idx_registros_pago_estado` (`estado`,`created_at`),
  KEY `idx_registros_pago_google_id` (`google_id`),
  KEY `idx_registros_pago_username` (`username`),
  KEY `fk_registros_pago_pendientes_plan` (`id_plan`),
  KEY `fk_registros_pago_pendientes_usuario` (`created_user_id`),
  CONSTRAINT `fk_registros_pago_pendientes_plan` FOREIGN KEY (`id_plan`) REFERENCES `planes` (`id_plan`) ON UPDATE CASCADE,
  CONSTRAINT `fk_registros_pago_pendientes_usuario` FOREIGN KEY (`created_user_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registros_pago_pendientes`
--

LOCK TABLES `registros_pago_pendientes` WRITE;
/*!40000 ALTER TABLE `registros_pago_pendientes` DISABLE KEYS */;
/*!40000 ALTER TABLE `registros_pago_pendientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `stripe_customer_id` varchar(120) DEFAULT NULL,
  `stripe_subscription_id` varchar(120) DEFAULT NULL,
  `stripe_checkout_session_id` varchar(120) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'payment_pending',
  `amount` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `billing_interval` varchar(20) DEFAULT NULL,
  `current_period_start` datetime DEFAULT NULL,
  `current_period_end` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_subscriptions_checkout_session_unique` (`stripe_checkout_session_id`),
  UNIQUE KEY `idx_subscriptions_stripe_subscription_unique` (`stripe_subscription_id`),
  KEY `idx_subscriptions_plan_status` (`plan_id`,`status`,`updated_at`),
  KEY `idx_subscriptions_user_status` (`user_id`,`status`,`updated_at`),
  CONSTRAINT `fk_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id_plan`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `rol` varchar(10) NOT NULL DEFAULT 'user',
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `nombre_completo` varchar(150) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `pais` varchar(80) DEFAULT NULL,
  `id_plan` int(10) unsigned DEFAULT NULL,
  `ultimo_login_at` datetime DEFAULT NULL,
  `ultima_actividad_at` datetime DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `stripe_customer_id` varchar(120) DEFAULT NULL,
  `stripe_subscription_id` varchar(120) DEFAULT NULL,
  `suscripcion_estado` varchar(40) DEFAULT NULL,
  `suscripcion_renueva_at` datetime DEFAULT NULL,
  `auth_provider` varchar(30) DEFAULT NULL,
  `google_id` varchar(191) DEFAULT NULL,
  `email_verificado_at` datetime DEFAULT NULL,
  `stripe_checkout_session_id` varchar(120) DEFAULT NULL,
  `stripe_invoice_id` varchar(120) DEFAULT NULL,
  `stripe_invoice_url` text DEFAULT NULL,
  `stripe_invoice_pdf_url` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuarios_username_unique` (`username`),
  UNIQUE KEY `idx_usuarios_checkout_session_unique` (`stripe_checkout_session_id`),
  UNIQUE KEY `idx_usuarios_email_unique` (`email`),
  UNIQUE KEY `idx_usuarios_google_id_unique` (`google_id`),
  UNIQUE KEY `idx_usuarios_stripe_subscription_unique` (`stripe_subscription_id`),
  KEY `idx_usuarios_checkout_session` (`stripe_checkout_session_id`),
  KEY `idx_usuarios_google_id` (`google_id`),
  KEY `idx_usuarios_id_plan` (`id_plan`),
  KEY `idx_usuarios_pais` (`pais`),
  KEY `idx_usuarios_stripe_customer` (`stripe_customer_id`),
  KEY `idx_usuarios_suscripcion_estado` (`suscripcion_estado`),
  KEY `idx_usuarios_ultima_actividad` (`ultima_actividad_at`),
  KEY `idx_usuarios_ultimo_login` (`ultimo_login_at`),
  CONSTRAINT `fk_usuarios_plan` FOREIGN KEY (`id_plan`) REFERENCES `planes` (`id_plan`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `usuarios_rol_check` CHECK (`rol` in ('admin','user')),
  CONSTRAINT `usuarios_estado_check` CHECK (`estado` in (0,1))
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'admin','$2y$10$zMxxLgUnZrye/5zT5Ot5ze9avnYJAi1kCyDUjmZoZ.DiX/kbNabl6','admin',1,NULL,NULL,'2026-11-05 23:00:32','El Salvador',NULL,NULL,NULL,NULL,NULL,NULL,'admin',NULL,'local',NULL,NULL,NULL,NULL,NULL,NULL),(2,'user','$2y$10$Blv9fgvr/a5Zu4IUT0LX2OqJhKdXWT7pw0jmGO.esHjTPGGALrRYy','user',1,NULL,NULL,'2026-11-05 23:00:32','El Salvador',1,NULL,NULL,NULL,NULL,NULL,'free',NULL,'local',NULL,NULL,NULL,NULL,NULL,NULL),(3,'zentra_admin','$2y$10$kXhPm8ZCLQW/XWCbrjRIceU0VKO5CdIFm/jYEzxBCl682iy10RZwO','admin',1,NULL,NULL,'2026-11-05 23:00:32','El Salvador',NULL,NULL,NULL,NULL,NULL,NULL,'admin',NULL,'local',NULL,NULL,NULL,NULL,NULL,NULL),(10,'ztest_455f0c1c11_admin','$2y$10$mYnVXhw46MKPcUapOilgH.PgIStIs2qVp9yfj2vulXfof4DaKvRqu','admin',1,NULL,NULL,'2026-11-05 23:04:50','El Salvador',1,'2026-11-05 23:04:51','2026-11-05 23:04:51','ztest_455f0c1c11_admin@example.invalid',NULL,NULL,'admin',NULL,'local',NULL,NULL,NULL,NULL,NULL,NULL),(11,'ztest_455f0c1c11_user','$2y$10$34p./LO.yV34.qKpxn4RJOlrPzHlqjOU9mdO6m6NsrPkeJrNI/nky','user',1,NULL,NULL,'2026-11-05 23:04:50','El Salvador',1,'2026-11-05 23:04:51','2026-11-05 23:04:51','ztest_455f0c1c11_user@example.invalid',NULL,NULL,'free',NULL,'local',NULL,NULL,NULL,NULL,NULL,NULL),(12,'ztest_455f0c1c11_other','$2y$10$bjW7sSrfW9BSEuXjO6E.ZujFTBMhAKYFxhuvXj3JUGSiRavT4f5ja','user',1,NULL,NULL,'2026-11-05 23:04:50','El Salvador',1,'2026-11-05 23:04:51','2026-11-05 23:04:51','ztest_455f0c1c11_other@example.invalid',NULL,NULL,'free',NULL,'local',NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'zentra'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-05 23:04:52
