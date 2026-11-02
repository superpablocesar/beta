<?php
function obtener_rendimiento_por_genetica(mysqli $conexion, string $fecha_desde): array
{
    $sql = "SELECT v.nombre AS variedad,COUNT(*) AS lotes,COALESCE(SUM(lc.cantidad_inicial),0) AS plantas_iniciales,SUM(r.cantidad_cosechas) AS cantidad_cosechas,SUM(r.peso_fresco_gramos) AS peso_fresco_gramos,SUM(r.peso_seco_gramos) AS peso_seco_gramos,CASE WHEN SUM(r.peso_fresco_gramos)>0 THEN (SUM(r.peso_fresco_gramos)-SUM(r.peso_seco_gramos))*100/SUM(r.peso_fresco_gramos) ELSE NULL END AS merma_porcentaje,CASE WHEN SUM(lc.cantidad_inicial)>0 THEN SUM(r.peso_seco_gramos)/SUM(lc.cantidad_inicial) ELSE NULL END AS seco_por_planta,AVG(r.dias_ciclo_promedio) AS dias_ciclo_promedio FROM (SELECT c.lote_id,COUNT(*) AS cantidad_cosechas,SUM(c.peso_fresco_gramos) AS peso_fresco_gramos,SUM(c.peso_seco_gramos) AS peso_seco_gramos,AVG(CASE WHEN lc.fecha_inicio IS NULL OR lc.fecha_inicio='0000-00-00' THEN NULL ELSE DATEDIFF(c.fecha_cosecha,lc.fecha_inicio) END) AS dias_ciclo_promedio FROM cosechas c INNER JOIN lotes_cultivo lc ON lc.id=c.lote_id WHERE c.fecha_cosecha>=? AND c.fecha_cosecha<=CURRENT_DATE() GROUP BY c.lote_id) r INNER JOIN lotes_cultivo lc ON lc.id=r.lote_id INNER JOIN variedades v ON v.id=lc.variedad_id GROUP BY v.id,v.nombre ORDER BY peso_seco_gramos DESC,v.nombre ASC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('s', $fecha_desde);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

function obtener_actividad_madres_clones(mysqli $conexion, string $fecha_desde): array
{
    $sql = "SELECT pm.id AS planta_madre_id,pm.codigo_planta,pm.estado AS estado_madre,v.nombre AS variedad,COALESCE(e.extracciones,0) AS extracciones,COALESCE(e.esquejes_extraidos,0) AS esquejes_extraidos,e.ultima_extraccion,COALESCE(c.total_clones,0) AS total_clones,COALESCE(c.enraizados,0) AS enraizados,CASE WHEN COALESCE(c.total_clones,0)>0 THEN c.enraizados*100/c.total_clones ELSE NULL END AS porcentaje_enraizados,COALESCE(c.trasplantados,0) AS trasplantados,COALESCE(c.descartados,0) AS descartados,GREATEST(COALESCE(c.total_clones,0)-COALESCE(c.enraizados,0)-COALESCE(c.descartados,0),0) AS pendientes, c.dias_enraizado_promedio,c.ultimo_corte FROM plantas_madre pm INNER JOIN variedades v ON v.id=pm.variedad_id LEFT JOIN (SELECT planta_madre_id,COUNT(*) AS extracciones,SUM(cantidad_esquejes_cortados) AS esquejes_extraidos,MAX(created_at) AS ultima_extraccion FROM extracciones_madre WHERE created_at>=? AND created_at<DATE_ADD(CURRENT_DATE(),INTERVAL 1 DAY) GROUP BY planta_madre_id) e ON e.planta_madre_id=pm.id LEFT JOIN (SELECT e.planta_madre_id,COUNT(c.id) AS total_clones,SUM(c.estado IN ('Enraizado','Trasplantado')) AS enraizados,SUM(c.estado='Trasplantado') AS trasplantados,SUM(c.estado='Descartado') AS descartados,AVG(CASE WHEN c.fecha_enraizado IS NOT NULL THEN DATEDIFF(c.fecha_enraizado,c.fecha_corte) END) AS dias_enraizado_promedio,MAX(c.fecha_corte) AS ultimo_corte FROM clones_esquejes c INNER JOIN extracciones_madre e ON e.id=c.extraccion_madre_id WHERE c.fecha_corte>=? AND c.fecha_corte<=CURRENT_DATE() GROUP BY e.planta_madre_id) c ON c.planta_madre_id=pm.id WHERE e.planta_madre_id IS NOT NULL OR c.planta_madre_id IS NOT NULL ORDER BY COALESCE(e.ultima_extraccion,c.ultimo_corte) DESC,pm.codigo_planta ASC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('ss', $fecha_desde, $fecha_desde);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

function obtener_alertas_vencimientos(mysqli $conexion): array
{
    $sql = "SELECT alertas.*,DATEDIFF(alertas.fecha,CURRENT_DATE()) AS dias_restantes,CASE WHEN alertas.fecha<CURRENT_DATE() THEN 'Vencido' WHEN alertas.fecha=CURRENT_DATE() THEN 'Hoy' ELSE 'Próximo' END AS situacion FROM (SELECT CONVERT('Permiso REPROCANN' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS categoria,CONVERT(CONCAT(s.apellido,', ',s.nombre) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS referencia,CONVERT(CONCAT('Socio · ',s.estado_membresia) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS detalle,s.reprocann_vencimiento AS fecha,CONVERT(s.estado_membresia USING utf8mb4) COLLATE utf8mb4_unicode_ci AS estado FROM socios s WHERE s.reprocann_vencimiento IS NOT NULL UNION ALL SELECT CONVERT('Cosecha estimada' USING utf8mb4) COLLATE utf8mb4_unicode_ci,CONVERT(lc.codigo_lote USING utf8mb4) COLLATE utf8mb4_unicode_ci,CONVERT(CONCAT(v.nombre,' · etapa ',lc.estado_actual) USING utf8mb4) COLLATE utf8mb4_unicode_ci,lc.fecha_estimada_cosecha,CONVERT(lc.estado_actual USING utf8mb4) COLLATE utf8mb4_unicode_ci FROM lotes_cultivo lc INNER JOIN variedades v ON v.id=lc.variedad_id WHERE lc.fecha_estimada_cosecha IS NOT NULL AND lc.estado_actual NOT IN ('Cosechado','Descartado') UNION ALL SELECT CONVERT('Tarea de cultivo' USING utf8mb4) COLLATE utf8mb4_unicode_ci,CONVERT(lc.codigo_lote USING utf8mb4) COLLATE utf8mb4_unicode_ci,CONVERT(CONCAT(t.titulo,' · ',COALESCE(o.usuario,'Sin asignar')) USING utf8mb4) COLLATE utf8mb4_unicode_ci,t.fecha_programada,CONVERT(t.estado USING utf8mb4) COLLATE utf8mb4_unicode_ci FROM tareas_cultivo t INNER JOIN lotes_cultivo lc ON lc.id=t.lote_id LEFT JOIN operadores o ON o.id=t.responsable_id WHERE t.estado IN ('Pendiente','En curso') AND t.fecha_programada IS NOT NULL) alertas WHERE alertas.fecha<=DATE_ADD(CURRENT_DATE(),INTERVAL 30 DAY) ORDER BY alertas.fecha ASC,alertas.categoria,alertas.referencia";
    $resultado = $conexion->query($sql);
    if (!$resultado) {
        throw new RuntimeException('No se pudo obtener el reporte de vencimientos.');
    }
    $filas = $resultado->fetch_all(MYSQLI_ASSOC);
    $resultado->free();
    return $filas;
}
