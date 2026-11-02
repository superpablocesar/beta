-- Bitácora de cambios de aplicación. Los valores JSON se guardan como texto
-- UTF-8 para mantener compatibilidad entre versiones de MariaDB/MySQL.
CREATE TABLE IF NOT EXISTS bitacora_cambios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    operador_id INT DEFAULT NULL,
    modulo VARCHAR(40) NOT NULL,
    accion ENUM('Crear','Actualizar','Eliminar','Cambio de rol') NOT NULL,
    entidad VARCHAR(80) NOT NULL,
    entidad_id BIGINT UNSIGNED DEFAULT NULL,
    valores_anteriores LONGTEXT DEFAULT NULL,
    valores_nuevos LONGTEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bitacora_fecha (created_at),
    KEY idx_bitacora_modulo (modulo, created_at),
    KEY idx_bitacora_entidad (entidad, entidad_id),
    KEY idx_bitacora_operador (operador_id, created_at),
    CONSTRAINT fk_bitacora_operador FOREIGN KEY (operador_id) REFERENCES operadores (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO permisos_roles (rol,modulo,puede_consultar,puede_modificar)
VALUES ('administrador','bitacora',1,0)
ON DUPLICATE KEY UPDATE puede_consultar=VALUES(puede_consultar),puede_modificar=0;
