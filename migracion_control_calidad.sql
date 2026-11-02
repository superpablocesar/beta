-- Registros de inspección y análisis ligados a cosechas y, opcionalmente, a un producto fraccionado.
CREATE TABLE IF NOT EXISTS controles_calidad (
    id INT NOT NULL AUTO_INCREMENT,
    cosecha_id INT NOT NULL,
    inventario_derivado_id INT DEFAULT NULL,
    tipo_control ENUM('Humedad','Inspección visual','Contaminantes','Análisis de laboratorio','Otro') NOT NULL DEFAULT 'Inspección visual',
    fecha_control DATE NOT NULL,
    resultado ENUM('Pendiente','Conforme','No conforme') NOT NULL DEFAULT 'Pendiente',
    valor DECIMAL(10,3) DEFAULT NULL,
    unidad VARCHAR(20) DEFAULT NULL,
    observaciones VARCHAR(1000) DEFAULT NULL,
    operador_id INT NOT NULL,
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_calidad_fecha (fecha_control),
    KEY idx_calidad_cosecha (cosecha_id),
    KEY idx_calidad_producto (inventario_derivado_id),
    CONSTRAINT fk_calidad_cosecha FOREIGN KEY (cosecha_id) REFERENCES cosechas (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_calidad_producto FOREIGN KEY (inventario_derivado_id) REFERENCES inventario_derivado (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_calidad_operador FOREIGN KEY (operador_id) REFERENCES operadores (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_calidad_updated_by FOREIGN KEY (updated_by) REFERENCES operadores (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
