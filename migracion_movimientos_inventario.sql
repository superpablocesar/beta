CREATE TABLE IF NOT EXISTS `movimientos_inventario` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `inventario_derivado_id` int(11) NOT NULL,
    `usuario_id` int(11) DEFAULT NULL,
    `tipo_movimiento` varchar(40) NOT NULL,
    `gramos_disponibles_antes` decimal(8,2) NOT NULL,
    `gramos_disponibles_despues` decimal(8,2) NOT NULL,
    `gramos_reservados_antes` decimal(8,2) NOT NULL,
    `gramos_reservados_despues` decimal(8,2) NOT NULL,
    `dispensacion_id` int(11) DEFAULT NULL,
    `motivo` varchar(255) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_mov_inv_fecha` (`inventario_derivado_id`, `created_at`),
    KEY `idx_mov_fecha` (`created_at`),
    KEY `idx_mov_dispensacion` (`dispensacion_id`),
    CONSTRAINT `mov_inv_inventario_fk` FOREIGN KEY (`inventario_derivado_id`) REFERENCES `inventario_derivado` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `mov_inv_operador_fk` FOREIGN KEY (`usuario_id`) REFERENCES `operadores` (`id`) ON DELETE SET NULL,
    CONSTRAINT `mov_inv_dispensacion_fk` FOREIGN KEY (`dispensacion_id`) REFERENCES `dispensacion` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `movimientos_inventario` (
    `inventario_derivado_id`, `usuario_id`, `tipo_movimiento`,
    `gramos_disponibles_antes`, `gramos_disponibles_despues`,
    `gramos_reservados_antes`, `gramos_reservados_despues`, `dispensacion_id`,
    `motivo`, `created_at`
)
SELECT
    i.`id`, NULL, 'Saldo inicial',
    0.00, i.`gramos_disponibles`,
    0.00, COALESCE(i.`gramos_reservados`, 0.00), NULL,
    'Saldo existente al habilitar la trazabilidad.', i.`created_at`
FROM `inventario_derivado` AS i
WHERE NOT EXISTS (
    SELECT 1
    FROM `movimientos_inventario` AS m
    WHERE m.`inventario_derivado_id` = i.`id`
      AND m.`tipo_movimiento` = 'Saldo inicial'
);
