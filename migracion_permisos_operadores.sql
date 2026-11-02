-- Ejecutar una sola vez sobre la base existente antes de subir la versión PHP.
-- Crea los permisos individuales por operador; conserva los roles y sus permisos actuales.
CREATE TABLE IF NOT EXISTS `permisos_operadores` (
  `operador_id` int(11) NOT NULL,
  `modulo` varchar(40) NOT NULL,
  `puede_consultar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_modificar` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`operador_id`,`modulo`),
  KEY `idx_permisos_operadores_modulo` (`modulo`),
  CONSTRAINT `permisos_operadores_ibfk_1` FOREIGN KEY (`operador_id`) REFERENCES `operadores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
