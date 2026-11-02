CREATE TABLE IF NOT EXISTS `movimientos_banco_semillas` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `banco_semillas_id` int(11) NOT NULL,
    `usuario_id` int(11) DEFAULT NULL,
    `tipo_movimiento` varchar(40) NOT NULL,
    `cantidad_antes` int(11) NOT NULL,
    `cantidad_despues` int(11) NOT NULL,
    `lote_cultivo_id` int(11) DEFAULT NULL,
    `motivo` varchar(255) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_mov_banco_fecha` (`banco_semillas_id`, `created_at`),
    KEY `idx_mov_banco_fecha_global` (`created_at`),
    KEY `idx_mov_banco_lote` (`lote_cultivo_id`),
    CONSTRAINT `mov_banco_semillas_fk` FOREIGN KEY (`banco_semillas_id`) REFERENCES `banco_semillas` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `mov_banco_operador_fk` FOREIGN KEY (`usuario_id`) REFERENCES `operadores` (`id`) ON DELETE SET NULL,
    CONSTRAINT `mov_banco_lote_fk` FOREIGN KEY (`lote_cultivo_id`) REFERENCES `lotes_cultivo` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `movimientos_banco_semillas` (
    `banco_semillas_id`, `usuario_id`, `tipo_movimiento`,
    `cantidad_antes`, `cantidad_despues`, `lote_cultivo_id`, `motivo`, `created_at`
)
SELECT
    b.`id`, NULL, 'Saldo inicial',
    0, b.`cantidad_disponible`, NULL,
    'Saldo existente al habilitar la trazabilidad del banco.', CURRENT_TIMESTAMP
FROM `banco_semillas` AS b
WHERE NOT EXISTS (
    SELECT 1
    FROM `movimientos_banco_semillas` AS m
    WHERE m.`banco_semillas_id` = b.`id`
      AND m.`tipo_movimiento` = 'Saldo inicial'
);
