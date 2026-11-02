-- Fase 1 de roles. Los operadores existentes conservan acceso administrativo
-- durante la preparación de la matriz; los operadores futuros serán de consulta.
ALTER TABLE operadores
    ADD COLUMN rol ENUM('administrador','cultivo','inventario','dispensacion','cuotas','consulta')
        NOT NULL DEFAULT 'consulta' AFTER clave;

UPDATE operadores SET rol = 'administrador';

CREATE TABLE IF NOT EXISTS permisos_roles (
    rol ENUM('administrador','cultivo','inventario','dispensacion','cuotas','consulta') NOT NULL,
    modulo VARCHAR(40) NOT NULL,
    puede_consultar TINYINT(1) NOT NULL DEFAULT 0,
    puede_modificar TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (rol, modulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO permisos_roles (rol,modulo,puede_consultar,puede_modificar) VALUES
('administrador','variedades',1,1),('administrador','plantas_madre',1,1),('administrador','clones',1,1),
('administrador','banco_semillas',1,1),('administrador','lotes',1,1),('administrador','historial_lotes',1,1),
('administrador','cosechas',1,1),('administrador','calidad',1,1),('administrador','tareas',1,1),
('administrador','inventario',1,1),('administrador','movimientos_inventario',1,0),
('administrador','socios',1,1),('administrador','dispensacion',1,1),('administrador','cuotas',1,1),
('administrador','reportes',1,0),('administrador','operadores',1,1),
('cultivo','variedades',1,1),('cultivo','plantas_madre',1,1),('cultivo','clones',1,1),
('cultivo','banco_semillas',1,1),('cultivo','lotes',1,1),('cultivo','historial_lotes',1,1),
('cultivo','cosechas',1,1),('cultivo','calidad',1,1),('cultivo','tareas',1,1),('cultivo','reportes',1,0),
('inventario','inventario',1,1),('inventario','movimientos_inventario',1,0),('inventario','banco_semillas',1,1),('inventario','reportes',1,0),
('dispensacion','socios',1,0),('dispensacion','dispensacion',1,1),('dispensacion','inventario',1,0),('dispensacion','movimientos_inventario',1,0),('dispensacion','reportes',1,0),
('cuotas','socios',1,0),('cuotas','cuotas',1,1),('cuotas','reportes',1,0),
('consulta','variedades',1,0),('consulta','plantas_madre',1,0),('consulta','clones',1,0),('consulta','banco_semillas',1,0),
('consulta','lotes',1,0),('consulta','historial_lotes',1,0),('consulta','cosechas',1,0),('consulta','calidad',1,0),
('consulta','tareas',1,0),('consulta','inventario',1,0),('consulta','movimientos_inventario',1,0),
('consulta','socios',1,0),('consulta','dispensacion',1,0),('consulta','cuotas',1,0),('consulta','reportes',1,0)
ON DUPLICATE KEY UPDATE puede_consultar=VALUES(puede_consultar),puede_modificar=VALUES(puede_modificar);
