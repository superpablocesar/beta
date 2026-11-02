-- Ejecutar una vez después de importar el esquema base y crear las tablas
-- adicionales. Omite tablas/columnas ya retiradas, por lo que sirve tanto
-- para una base existente como para una instalación nueva.
DELIMITER //
DROP PROCEDURE IF EXISTS flowher_eliminar_club_id//
CREATE PROCEDURE flowher_eliminar_club_id(
    IN p_tabla VARCHAR(64),
    IN p_indice_club VARCHAR(64),
    IN p_indice_nuevo VARCHAR(64),
    IN p_columnas_nuevo VARCHAR(255)
)
BEGIN
    DECLARE v_sql TEXT;
    DECLARE v_tabla_existe INT DEFAULT 0;
    DECLARE v_columna_existe INT DEFAULT 0;
    DECLARE v_indice_club_existe INT DEFAULT 0;
    DECLARE v_indice_nuevo_existe INT DEFAULT 0;

    SELECT COUNT(*) INTO v_tabla_existe
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla;
    SELECT COUNT(*) INTO v_columna_existe
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND COLUMN_NAME = 'club_id';

    IF v_tabla_existe > 0 AND v_columna_existe > 0 THEN
        IF p_indice_club IS NOT NULL THEN
            SELECT COUNT(*) INTO v_indice_club_existe
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND INDEX_NAME = p_indice_club;
        END IF;
        IF p_indice_nuevo IS NOT NULL THEN
            SELECT COUNT(*) INTO v_indice_nuevo_existe
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND INDEX_NAME = p_indice_nuevo;
        END IF;

        SET v_sql = CONCAT('ALTER TABLE `', p_tabla, '`');
        IF v_indice_club_existe > 0 THEN
            SET v_sql = CONCAT(v_sql, ' DROP INDEX `', p_indice_club, '`,');
        END IF;
        SET v_sql = CONCAT(v_sql, ' DROP COLUMN `club_id`');
        IF p_indice_nuevo IS NOT NULL AND v_indice_nuevo_existe = 0 THEN
            SET v_sql = CONCAT(v_sql, ', ADD INDEX `', p_indice_nuevo, '` (', p_columnas_nuevo, ')');
        END IF;

        SET @flowher_club_sql = v_sql;
        PREPARE flowher_club_stmt FROM @flowher_club_sql;
        EXECUTE flowher_club_stmt;
        DEALLOCATE PREPARE flowher_club_stmt;
    END IF;
END//

CALL flowher_eliminar_club_id('variedades', 'idx_club', NULL, NULL)//
CALL flowher_eliminar_club_id('plantas_madre', 'idx_club', NULL, NULL)//
CALL flowher_eliminar_club_id('banco_semillas', 'idx_club', NULL, NULL)//
CALL flowher_eliminar_club_id('lotes_cultivo', 'idx_club', NULL, NULL)//
CALL flowher_eliminar_club_id('cosechas', 'idx_club', NULL, NULL)//
CALL flowher_eliminar_club_id('inventario_derivado', 'idx_club', NULL, NULL)//
CALL flowher_eliminar_club_id('socios', 'idx_club', NULL, NULL)//
CALL flowher_eliminar_club_id('cuotas_socios', 'idx_club_finanzas', NULL, NULL)//
CALL flowher_eliminar_club_id('dispensacion', 'idx_club_dispensacion', NULL, NULL)//
CALL flowher_eliminar_club_id('movimientos_inventario', 'idx_mov_club_fecha', 'idx_mov_fecha', '`created_at`')//
CALL flowher_eliminar_club_id('movimientos_banco_semillas', 'idx_mov_banco_club_fecha', 'idx_mov_banco_fecha_global', '`created_at`')//
CALL flowher_eliminar_club_id('clones_esquejes', 'idx_clones_club_estado', 'idx_clones_estado', '`estado`')//
CALL flowher_eliminar_club_id('controles_calidad', 'idx_calidad_club_fecha', 'idx_calidad_fecha', '`fecha_control`')//
CALL flowher_eliminar_club_id('tareas_cultivo', 'idx_tareas_club_fecha', 'idx_tareas_fecha_estado', '`fecha_programada`, `estado`')//
CALL flowher_eliminar_club_id('bitacora_cambios', NULL, NULL, NULL)//
CALL flowher_eliminar_club_id('pos_ventas', 'idx_club_pos', NULL, NULL)//

DROP PROCEDURE flowher_eliminar_club_id//
DELIMITER ;
