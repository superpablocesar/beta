-- FLOWHER: esquema actualizado para pruebas desde cero.
-- Incluye solo la cuenta de operador existente para permitir el acceso.
-- El resto de las tablas queda vacío. Importar dentro de la base de datos de destino.


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
DROP TABLE IF EXISTS `banco_semillas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `banco_semillas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `variedad_id` int(11) NOT NULL,
  `tipo_material` enum('Semilla Feminizada','Semilla Regular','Semilla Automatica','Esqueje/Clon') NOT NULL,
  `origen_tipo` enum('Compra_Externa','Produccion_Propia') NOT NULL DEFAULT 'Compra_Externa',
  `origen_proveedor` varchar(150) DEFAULT NULL,
  `numero_lote_origen` varchar(100) DEFAULT NULL,
  `planta_madre_id` int(11) DEFAULT NULL,
  `planta_padre_id` int(11) DEFAULT NULL,
  `fecha_polinizacion` date DEFAULT NULL,
  `cantidad_disponible` int(11) NOT NULL DEFAULT 0,
  `ubicacion_almacen` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `variedad_id` (`variedad_id`),
  KEY `planta_madre_id` (`planta_madre_id`),
  KEY `planta_padre_id` (`planta_padre_id`),
  KEY `idx_origen` (`origen_tipo`),
  CONSTRAINT `banco_semillas_ibfk_1` FOREIGN KEY (`variedad_id`) REFERENCES `variedades` (`id`),
  CONSTRAINT `banco_semillas_ibfk_2` FOREIGN KEY (`planta_madre_id`) REFERENCES `plantas_madre` (`id`) ON DELETE SET NULL,
  CONSTRAINT `banco_semillas_ibfk_3` FOREIGN KEY (`planta_padre_id`) REFERENCES `plantas_madre` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bitacora_cambios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bitacora_cambios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `operador_id` int(11) DEFAULT NULL,
  `modulo` varchar(40) NOT NULL,
  `accion` enum('Crear','Actualizar','Eliminar','Cambio de rol') NOT NULL,
  `entidad` varchar(80) NOT NULL,
  `entidad_id` bigint(20) unsigned DEFAULT NULL,
  `valores_anteriores` longtext DEFAULT NULL,
  `valores_nuevos` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bitacora_fecha` (`created_at`),
  KEY `idx_bitacora_modulo` (`modulo`,`created_at`),
  KEY `idx_bitacora_entidad` (`entidad`,`entidad_id`),
  KEY `idx_bitacora_operador` (`operador_id`,`created_at`),
  CONSTRAINT `fk_bitacora_operador` FOREIGN KEY (`operador_id`) REFERENCES `operadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clones_esquejes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clones_esquejes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `extraccion_madre_id` int(11) NOT NULL,
  `codigo_clon` varchar(32) NOT NULL,
  `fecha_corte` date NOT NULL,
  `fecha_enraizado` date DEFAULT NULL,
  `estado` enum('Cortado','Enraizando','Enraizado','Trasplantado','Descartado') NOT NULL DEFAULT 'Cortado',
  `ubicacion_actual` varchar(100) DEFAULT NULL,
  `lote_cultivo_id` int(11) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clones_codigo` (`codigo_clon`),
  KEY `idx_clones_extraccion` (`extraccion_madre_id`),
  KEY `idx_clones_lote` (`lote_cultivo_id`),
  KEY `fk_clones_created_by` (`created_by`),
  KEY `fk_clones_updated_by` (`updated_by`),
  KEY `idx_clones_estado` (`estado`),
  CONSTRAINT `fk_clones_created_by` FOREIGN KEY (`created_by`) REFERENCES `operadores` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_clones_extraccion` FOREIGN KEY (`extraccion_madre_id`) REFERENCES `extracciones_madre` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_clones_lote` FOREIGN KEY (`lote_cultivo_id`) REFERENCES `lotes_cultivo` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_clones_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `operadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `controles_calidad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `controles_calidad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cosecha_id` int(11) NOT NULL,
  `inventario_derivado_id` int(11) DEFAULT NULL,
  `tipo_control` enum('Humedad','Inspección visual','Contaminantes','Análisis de laboratorio','Otro') NOT NULL DEFAULT 'Inspección visual',
  `fecha_control` date NOT NULL,
  `resultado` enum('Pendiente','Conforme','No conforme') NOT NULL DEFAULT 'Pendiente',
  `valor` decimal(10,3) DEFAULT NULL,
  `unidad` varchar(20) DEFAULT NULL,
  `observaciones` varchar(1000) DEFAULT NULL,
  `operador_id` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_calidad_cosecha` (`cosecha_id`),
  KEY `idx_calidad_producto` (`inventario_derivado_id`),
  KEY `fk_calidad_operador` (`operador_id`),
  KEY `fk_calidad_updated_by` (`updated_by`),
  KEY `idx_calidad_fecha` (`fecha_control`),
  CONSTRAINT `fk_calidad_cosecha` FOREIGN KEY (`cosecha_id`) REFERENCES `cosechas` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_calidad_operador` FOREIGN KEY (`operador_id`) REFERENCES `operadores` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_calidad_producto` FOREIGN KEY (`inventario_derivado_id`) REFERENCES `inventario_derivado` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_calidad_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `operadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cosechas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cosechas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lote_id` int(11) NOT NULL,
  `codigo_cosecha` varchar(50) NOT NULL,
  `fecha_cosecha` date NOT NULL,
  `peso_fresco_gramos` decimal(8,2) NOT NULL,
  `peso_seco_gramos` decimal(8,2) DEFAULT 0.00,
  `porcentaje_merma` decimal(5,2) GENERATED ALWAYS AS (case when `peso_fresco_gramos` > 0 then (`peso_fresco_gramos` - `peso_seco_gramos`) / `peso_fresco_gramos` * 100 else 0 end) STORED,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_cosecha` (`codigo_cosecha`),
  KEY `lote_id` (`lote_id`),
  CONSTRAINT `cosechas_ibfk_1` FOREIGN KEY (`lote_id`) REFERENCES `lotes_cultivo` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cuotas_socios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cuotas_socios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `socio_id` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `concepto` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej. Cuota Social Octubre 2026, Fondo Cultivo',
  `fecha_pago` date NOT NULL,
  `metodo_pago` enum('Efectivo','Transferencia','MercadoPago','Otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Efectivo',
  `estado` enum('Pagado Pendiente','Completado','Anulado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Completado',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_socio_pago` (`socio_id`,`fecha_pago`),
  CONSTRAINT `cuotas_socios_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dispensacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dispensacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `socio_id` int(11) NOT NULL,
  `inventario_derivado_id` int(11) NOT NULL,
  `fecha_dispensacion` date NOT NULL,
  `gramos_entregados` decimal(8,2) NOT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `inventario_derivado_id` (`inventario_derivado_id`),
  KEY `idx_socio_fecha` (`socio_id`,`fecha_dispensacion`),
  CONSTRAINT `dispensacion_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `dispensacion_ibfk_2` FOREIGN KEY (`inventario_derivado_id`) REFERENCES `inventario_derivado` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `extracciones_madre`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `extracciones_madre` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `planta_madre_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `cantidad_esquejes_cortados` int(11) NOT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_madre` (`planta_madre_id`),
  CONSTRAINT `extracciones_madre_ibfk_1` FOREIGN KEY (`planta_madre_id`) REFERENCES `plantas_madre` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `historial_lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historial_lotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lote_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `estado_anterior` varchar(50) NOT NULL,
  `estado_nuevo` varchar(50) NOT NULL,
  `cantidad_afectada` int(11) NOT NULL DEFAULT 0,
  `motivo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lote` (`lote_id`),
  CONSTRAINT `historial_lotes_ibfk_1` FOREIGN KEY (`lote_id`) REFERENCES `lotes_cultivo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventario_derivado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventario_derivado` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cosecha_id` int(11) NOT NULL,
  `codigo_qr` varchar(100) NOT NULL,
  `tipo_producto` enum('Flor Seca','Aceite/Tintura','Extracto/Rosine','Preroll') NOT NULL,
  `gramos_disponibles` decimal(8,2) NOT NULL,
  `gramos_reservados` decimal(8,2) DEFAULT 0.00,
  `estado_inventario` enum('En Curado','Disponible','Agotado','Retirado por Calidad') DEFAULT 'En Curado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_qr` (`codigo_qr`),
  KEY `cosecha_id` (`cosecha_id`),
  KEY `idx_disponible` (`estado_inventario`,`gramos_disponibles`),
  CONSTRAINT `inventario_derivado_ibfk_1` FOREIGN KEY (`cosecha_id`) REFERENCES `cosechas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lotes_cultivo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lotes_cultivo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_lote` varchar(50) NOT NULL,
  `variedad_id` int(11) NOT NULL,
  `origen_tipo` enum('Desde_Semilla','Desde_Esqueje_Madre','Desde_Esqueje_Externo') NOT NULL,
  `banco_semillas_id` int(11) DEFAULT NULL,
  `planta_madre_id` int(11) DEFAULT NULL,
  `ubicacion_actual` varchar(100) DEFAULT NULL,
  `estado_actual` enum('Germinacion','Vegetativo','Floracion','Cosechado','Descartado') DEFAULT 'Germinacion',
  `cantidad_inicial` int(11) NOT NULL,
  `cantidad_actual` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_estimada_cosecha` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_lote` (`codigo_lote`),
  KEY `variedad_id` (`variedad_id`),
  KEY `banco_semillas_id` (`banco_semillas_id`),
  KEY `planta_madre_id` (`planta_madre_id`),
  KEY `idx_estado` (`estado_actual`),
  CONSTRAINT `lotes_cultivo_ibfk_1` FOREIGN KEY (`variedad_id`) REFERENCES `variedades` (`id`),
  CONSTRAINT `lotes_cultivo_ibfk_2` FOREIGN KEY (`banco_semillas_id`) REFERENCES `banco_semillas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lotes_cultivo_ibfk_3` FOREIGN KEY (`planta_madre_id`) REFERENCES `plantas_madre` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movimientos_banco_semillas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimientos_banco_semillas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `banco_semillas_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tipo_movimiento` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad_antes` int(11) NOT NULL,
  `cantidad_despues` int(11) NOT NULL,
  `lote_cultivo_id` int(11) DEFAULT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mov_banco_fecha` (`banco_semillas_id`,`created_at`),
  KEY `idx_mov_banco_lote` (`lote_cultivo_id`),
  KEY `mov_banco_operador_fk` (`usuario_id`),
  KEY `idx_mov_banco_fecha_global` (`created_at`),
  CONSTRAINT `mov_banco_lote_fk` FOREIGN KEY (`lote_cultivo_id`) REFERENCES `lotes_cultivo` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mov_banco_operador_fk` FOREIGN KEY (`usuario_id`) REFERENCES `operadores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mov_banco_semillas_fk` FOREIGN KEY (`banco_semillas_id`) REFERENCES `banco_semillas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movimientos_inventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimientos_inventario` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inventario_derivado_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tipo_movimiento` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gramos_disponibles_antes` decimal(8,2) NOT NULL,
  `gramos_disponibles_despues` decimal(8,2) NOT NULL,
  `gramos_reservados_antes` decimal(8,2) NOT NULL,
  `gramos_reservados_despues` decimal(8,2) NOT NULL,
  `dispensacion_id` int(11) DEFAULT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mov_inv_fecha` (`inventario_derivado_id`,`created_at`),
  KEY `idx_mov_dispensacion` (`dispensacion_id`),
  KEY `mov_inv_operador_fk` (`usuario_id`),
  KEY `idx_mov_fecha` (`created_at`),
  CONSTRAINT `mov_inv_dispensacion_fk` FOREIGN KEY (`dispensacion_id`) REFERENCES `dispensacion` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mov_inv_inventario_fk` FOREIGN KEY (`inventario_derivado_id`) REFERENCES `inventario_derivado` (`id`),
  CONSTRAINT `mov_inv_operador_fk` FOREIGN KEY (`usuario_id`) REFERENCES `operadores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permisos_operadores`;
DROP TABLE IF EXISTS `operadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `operadores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `clave` varchar(255) NOT NULL,
  `rol` enum('administrador','cultivo','inventario','dispensacion','cuotas','consulta') NOT NULL DEFAULT 'consulta',
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
CREATE TABLE `permisos_operadores` (
  `operador_id` int(11) NOT NULL,
  `modulo` varchar(40) NOT NULL,
  `puede_consultar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_modificar` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`operador_id`,`modulo`),
  KEY `idx_permisos_operadores_modulo` (`modulo`),
  CONSTRAINT `permisos_operadores_ibfk_1` FOREIGN KEY (`operador_id`) REFERENCES `operadores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permisos_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permisos_roles` (
  `rol` enum('administrador','cultivo','inventario','dispensacion','cuotas','consulta') NOT NULL,
  `modulo` varchar(40) NOT NULL,
  `puede_consultar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_modificar` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`rol`,`modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `permisos_roles` (`rol`, `modulo`, `puede_consultar`, `puede_modificar`) VALUES
('administrador','variedades',1,1),
('administrador','plantas_madre',1,1),
('administrador','clones',1,1),
('administrador','banco_semillas',1,1),
('administrador','lotes',1,1),
('administrador','historial_lotes',1,1),
('administrador','cosechas',1,1),
('administrador','calidad',1,1),
('administrador','tareas',1,1),
('administrador','inventario',1,1),
('administrador','movimientos_inventario',1,1),
('administrador','socios',1,1),
('administrador','dispensacion',1,1),
('administrador','cuotas',1,1),
('administrador','reportes',1,1),
('administrador','operadores',1,1),
('administrador','bitacora',1,1);
DROP TABLE IF EXISTS `plantas_madre`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plantas_madre` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_planta` varchar(50) NOT NULL,
  `variedad_id` int(11) NOT NULL,
  `ubicacion_sala` varchar(100) DEFAULT NULL,
  `estado` enum('Activa','En Descanso','Jubilada/Baja') DEFAULT 'Activa',
  `fecha_nacimiento` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_planta` (`codigo_planta`),
  KEY `variedad_id` (`variedad_id`),
  CONSTRAINT `plantas_madre_ibfk_1` FOREIGN KEY (`variedad_id`) REFERENCES `variedades` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pos_ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pos_ventas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `socio_id` int(11) NOT NULL,
  `inventario_derivado_id` int(11) NOT NULL,
  `gramos_retirados` decimal(8,2) NOT NULL,
  `precio_unitario_gramo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_abonado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` enum('Efectivo','Transferencia','Sin Cargo / Mutual','Otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Efectivo',
  `fecha_venta` date NOT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `socio_id` (`socio_id`),
  KEY `inventario_derivado_id` (`inventario_derivado_id`),
  KEY `idx_fecha_venta` (`fecha_venta`),
  CONSTRAINT `pos_ventas_ibfk_1` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `pos_ventas_ibfk_2` FOREIGN KEY (`inventario_derivado_id`) REFERENCES `inventario_derivado` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `socios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `socios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dni` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reprocann_codigo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reprocann_vencimiento` date NOT NULL,
  `limite_gramos_mensual` decimal(8,2) NOT NULL DEFAULT 40.00,
  `estado_membresia` enum('Activo','Pendiente REPROCANN','Vencido','Suspendido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Activo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `dni` (`dni`),
  UNIQUE KEY `reprocann_codigo` (`reprocann_codigo`),
  KEY `idx_reprocann` (`reprocann_codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tareas_cultivo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tareas_cultivo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lote_id` int(11) NOT NULL,
  `titulo` varchar(120) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `categoria` enum('Riego','Nutrición','Poda','Trasplante','Control sanitario','Seguimiento','Cosecha','Otro') NOT NULL DEFAULT 'Seguimiento',
  `fecha_programada` date NOT NULL,
  `estado` enum('Pendiente','En curso','Completada','Cancelada') NOT NULL DEFAULT 'Pendiente',
  `prioridad` enum('Baja','Media','Alta') NOT NULL DEFAULT 'Media',
  `responsable_id` int(11) DEFAULT NULL,
  `completada_en` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tareas_lote` (`lote_id`),
  KEY `idx_tareas_responsable` (`responsable_id`),
  KEY `fk_tareas_created_by` (`created_by`),
  KEY `fk_tareas_updated_by` (`updated_by`),
  KEY `idx_tareas_fecha_estado` (`fecha_programada`,`estado`),
  CONSTRAINT `fk_tareas_created_by` FOREIGN KEY (`created_by`) REFERENCES `operadores` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_tareas_lote` FOREIGN KEY (`lote_id`) REFERENCES `lotes_cultivo` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_tareas_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `operadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tareas_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `operadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `variedades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `variedades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('Sativa','Indica','Hibrida','CBD','Alta en THC') NOT NULL,
  `porcentaje_thc_estimado` decimal(4,2) DEFAULT NULL,
  `porcentaje_cbd_estimado` decimal(4,2) DEFAULT NULL,
  `dias_floracion_estimados` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

/*!40000 ALTER TABLE `operadores` DISABLE KEYS */;
INSERT INTO `operadores` VALUES (1,'admin','$2y$10$cpJIcNE5v1iRJ/w7Tt/p3.Xs7t5XfE7DbrvNcOtRm34TebwKuxhQC','administrador');
/*!40000 ALTER TABLE `operadores` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
