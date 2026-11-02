<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION["usuario"])) {
    header("Location: ./login.php");
    exit();
}

require_once "./conexion.php";
require_once __DIR__ . '/reportes_helper.php';
$conexion = conexion();

// Indicadores operativos y de vencimiento.
$total_socios = $conexion->query("SELECT COUNT(*) as t FROM socios WHERE estado_membresia = 'Activo' AND reprocann_vencimiento >= CURRENT_DATE()")->fetch_assoc()['t'];
$total_plantas = $conexion->query("SELECT COUNT(*) as t FROM plantas_madre WHERE estado = 'Activa'")->fetch_assoc()['t'];
$total_stock_gramos = $conexion->query("SELECT COALESCE(SUM(gramos_disponibles), 0) as t FROM inventario_derivado WHERE estado_inventario = 'Disponible'")->fetch_assoc()['t'] ?? 0;
$total_dispensado_mes = $conexion->query("SELECT SUM(gramos_entregados) as t FROM dispensacion WHERE MONTH(fecha_dispensacion) = MONTH(CURRENT_DATE()) AND YEAR(fecha_dispensacion) = YEAR(CURRENT_DATE())")->fetch_assoc()['t'] ?? 0;
$socios_activos_vencidos = $conexion->query("SELECT COUNT(*) AS t FROM socios WHERE estado_membresia = 'Activo' AND reprocann_vencimiento < CURRENT_DATE()")->fetch_assoc()['t'];
$socios_por_vencer = $conexion->query("SELECT COUNT(*) AS t FROM socios WHERE estado_membresia = 'Activo' AND reprocann_vencimiento >= CURRENT_DATE() AND reprocann_vencimiento < DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)")->fetch_assoc()['t'];

$inicio_mes_actual = new DateTimeImmutable('first day of this month');
$inicio_periodo_mensual = $inicio_mes_actual->modify('-5 months')->format('Y-m-d');
$fin_periodo_mensual = $inicio_mes_actual->modify('+1 month')->format('Y-m-d');
$stmt_disp = $conexion->prepare("SELECT DATE_FORMAT(fecha_dispensacion, '%Y-%m') AS mes, SUM(gramos_entregados) AS gramos FROM dispensacion WHERE fecha_dispensacion >= ? AND fecha_dispensacion < ? GROUP BY YEAR(fecha_dispensacion), MONTH(fecha_dispensacion) ORDER BY YEAR(fecha_dispensacion), MONTH(fecha_dispensacion)");
$stmt_disp->bind_param("ss", $inicio_periodo_mensual, $fin_periodo_mensual);
$stmt_disp->execute();
$dispensaciones_mensuales = $stmt_disp->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_disp->close();
$dispensaciones_por_mes = [];
foreach ($dispensaciones_mensuales as $fila_mes) {
    $dispensaciones_por_mes[$fila_mes['mes']] = (float)$fila_mes['gramos'];
}
$serie_mensual_completa = [];
$primer_mes_grafico = $inicio_mes_actual->modify('-5 months');
for ($i = 0; $i < 6; $i++) {
    $clave_mes = $primer_mes_grafico->modify('+' . $i . ' months')->format('Y-m');
    $serie_mensual_completa[] = ['mes' => $clave_mes, 'gramos' => $dispensaciones_por_mes[$clave_mes] ?? 0];
}
$dispensaciones_mensuales = $serie_mensual_completa;
$max_dispensacion = max(array_map(static fn($r) => (float)$r['gramos'], $dispensaciones_mensuales) ?: [0]);

$inicio_produccion = $inicio_mes_actual->modify('-11 months')->format('Y-m-d');
$stmt_cosechas = $conexion->prepare("SELECT v.nombre AS variedad, COUNT(c.id) AS cantidad_cosechas, COALESCE(SUM(c.peso_seco_gramos), 0) AS gramos_secos FROM cosechas c INNER JOIN lotes_cultivo lc ON c.lote_id = lc.id INNER JOIN variedades v ON lc.variedad_id = v.id WHERE c.fecha_cosecha >= ? AND c.fecha_cosecha <= CURRENT_DATE() GROUP BY v.id, v.nombre ORDER BY gramos_secos DESC LIMIT 10");
$stmt_cosechas->bind_param("s", $inicio_produccion);
$stmt_cosechas->execute();
$produccion_variedad = $stmt_cosechas->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cosechas->close();
$max_produccion = max(array_map(static fn($r) => (float)$r['gramos_secos'], $produccion_variedad) ?: [0]);
$rendimiento_por_genetica = obtener_rendimiento_por_genetica($conexion, $inicio_produccion);

$stock_por_producto = $conexion->query("SELECT tipo_producto, COUNT(*) AS cantidad_lotes, COALESCE(SUM(CASE WHEN estado_inventario = 'Disponible' THEN gramos_disponibles ELSE 0 END), 0) AS gramos_disponibles, COALESCE(SUM(gramos_reservados), 0) AS gramos_reservados FROM inventario_derivado GROUP BY tipo_producto ORDER BY tipo_producto")->fetch_all(MYSQLI_ASSOC);
$conciliacion_saldos = $conexion->query("SELECT COUNT(*) AS productos, COALESCE(SUM(ultimo.id IS NULL), 0) AS sin_movimientos, COALESCE(SUM(ultimo.id IS NOT NULL AND (i.gramos_disponibles <> ultimo.gramos_disponibles_despues OR COALESCE(i.gramos_reservados, 0) <> ultimo.gramos_reservados_despues)), 0) AS saldo_distinto FROM inventario_derivado i LEFT JOIN (SELECT m.* FROM movimientos_inventario m INNER JOIN (SELECT inventario_derivado_id, MAX(id) AS ultimo_id FROM movimientos_inventario GROUP BY inventario_derivado_id) ultimos ON ultimos.ultimo_id = m.id) ultimo ON ultimo.inventario_derivado_id = i.id")->fetch_assoc();
$productos_sin_conciliar = $conexion->query("SELECT i.id, i.codigo_qr, i.gramos_disponibles, COALESCE(i.gramos_reservados, 0) AS gramos_reservados, ultimo.id AS movimiento_id, ultimo.tipo_movimiento, ultimo.created_at AS fecha_movimiento, ultimo.gramos_disponibles_despues AS disponible_auditado, ultimo.gramos_reservados_despues AS reservado_auditado FROM inventario_derivado i LEFT JOIN (SELECT m.* FROM movimientos_inventario m INNER JOIN (SELECT inventario_derivado_id, MAX(id) AS ultimo_id FROM movimientos_inventario GROUP BY inventario_derivado_id) ultimos ON ultimos.ultimo_id = m.id) ultimo ON ultimo.inventario_derivado_id = i.id WHERE ultimo.id IS NULL OR i.gramos_disponibles <> ultimo.gramos_disponibles_despues OR COALESCE(i.gramos_reservados, 0) <> ultimo.gramos_reservados_despues ORDER BY i.id DESC")->fetch_all(MYSQLI_ASSOC);
$saltos_movimientos = $conexion->query("SELECT h.id AS movimiento_id, h.inventario_derivado_id, i.codigo_qr, h.created_at, h.gramos_disponibles_antes, h.disponible_anterior, h.gramos_reservados_antes, h.reservado_anterior FROM (SELECT m.id, m.inventario_derivado_id, m.created_at, m.gramos_disponibles_antes, m.gramos_reservados_antes, LAG(m.gramos_disponibles_despues) OVER (PARTITION BY m.inventario_derivado_id ORDER BY m.id) AS disponible_anterior, LAG(m.gramos_reservados_despues) OVER (PARTITION BY m.inventario_derivado_id ORDER BY m.id) AS reservado_anterior FROM movimientos_inventario m) h INNER JOIN inventario_derivado i ON i.id = h.inventario_derivado_id WHERE h.disponible_anterior IS NOT NULL AND (h.gramos_disponibles_antes <> h.disponible_anterior OR h.gramos_reservados_antes <> h.reservado_anterior) ORDER BY h.id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
$inicio_mes_cuotas = $inicio_mes_actual->format('Y-m-d');
$inicio_mes_siguiente_cuotas = $inicio_mes_actual->modify('+1 month')->format('Y-m-d');
$stmt_cuotas = $conexion->prepare("SELECT estado, COUNT(*) AS cantidad, COALESCE(SUM(monto), 0) AS total_historico, COALESCE(SUM(CASE WHEN fecha_pago >= ? AND fecha_pago < ? THEN monto ELSE 0 END), 0) AS total_mes FROM cuotas_socios GROUP BY estado");
$stmt_cuotas->bind_param('ss', $inicio_mes_cuotas, $inicio_mes_siguiente_cuotas);
$stmt_cuotas->execute();
$cuotas_por_estado = [];
foreach ($stmt_cuotas->get_result()->fetch_all(MYSQLI_ASSOC) as $fila_cuota) {
    $cuotas_por_estado[$fila_cuota['estado']] = $fila_cuota;
}
$stmt_cuotas->close();
$estados_cuotas = ['Completado', 'Pagado Pendiente', 'Anulado'];

$periodo_limites = is_scalar($_GET['periodo'] ?? null) ? trim((string)$_GET['periodo']) : date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo_limites)) $periodo_limites = date('Y-m');
$inicio_limites = $periodo_limites . '-01';
$fecha_inicio_limites = DateTimeImmutable::createFromFormat('!Y-m-d', $inicio_limites);
if (!$fecha_inicio_limites || $fecha_inicio_limites->format('Y-m-d') !== $inicio_limites) {
    $periodo_limites = date('Y-m');
    $inicio_limites = $periodo_limites . '-01';
    $fecha_inicio_limites = new DateTimeImmutable($inicio_limites);
}
$fin_limites = $fecha_inicio_limites->modify('+1 month')->format('Y-m-d');
$stmt_limites = $conexion->prepare("SELECT s.id, s.nombre, s.apellido, s.dni, s.limite_gramos_mensual, s.estado_membresia, s.reprocann_vencimiento, COALESCE(SUM(d.gramos_entregados), 0) AS gramos_entregados, COALESCE(SUM(CASE WHEN d.id IS NOT NULL AND d.fecha_dispensacion > s.reprocann_vencimiento THEN 1 ELSE 0 END), 0) AS entregas_fuera_vigencia FROM socios s LEFT JOIN dispensacion d ON d.socio_id = s.id AND d.fecha_dispensacion >= ? AND d.fecha_dispensacion < ? GROUP BY s.id, s.nombre, s.apellido, s.dni, s.limite_gramos_mensual, s.estado_membresia, s.reprocann_vencimiento ORDER BY s.apellido, s.nombre");
$stmt_limites->bind_param('ss', $inicio_limites, $fin_limites);
$stmt_limites->execute();
$dispensaciones_por_socio_periodo = $stmt_limites->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_limites->close();
$socios_con_alertas_dispensacion = 0;
foreach ($dispensaciones_por_socio_periodo as $fila_dispensacion_socio) {
    if ((float)$fila_dispensacion_socio['gramos_entregados'] > (float)$fila_dispensacion_socio['limite_gramos_mensual']
        || (int)$fila_dispensacion_socio['entregas_fuera_vigencia'] > 0
        || $fila_dispensacion_socio['estado_membresia'] !== 'Activo'
        || $fila_dispensacion_socio['reprocann_vencimiento'] < date('Y-m-d')) {
        $socios_con_alertas_dispensacion++;
    }
}
$stmt_cuotas_socio = $conexion->prepare("SELECT s.id, s.apellido, s.nombre, s.dni, COUNT(cs.id) AS registros, SUM(CASE WHEN cs.estado = 'Completado' THEN 1 ELSE 0 END) AS cantidad_completados, COALESCE(SUM(CASE WHEN cs.estado = 'Completado' THEN cs.monto ELSE 0 END), 0) AS importe_completado, SUM(CASE WHEN cs.estado = 'Pagado Pendiente' THEN 1 ELSE 0 END) AS cantidad_pendientes, COALESCE(SUM(CASE WHEN cs.estado = 'Pagado Pendiente' THEN cs.monto ELSE 0 END), 0) AS importe_pendiente, SUM(CASE WHEN cs.estado = 'Anulado' THEN 1 ELSE 0 END) AS cantidad_anulados, COALESCE(SUM(CASE WHEN cs.estado = 'Anulado' THEN cs.monto ELSE 0 END), 0) AS importe_anulado, MAX(cs.fecha_pago) AS ultimo_registro FROM socios s LEFT JOIN cuotas_socios cs ON cs.socio_id = s.id AND cs.fecha_pago >= ? AND cs.fecha_pago < ? GROUP BY s.id, s.apellido, s.nombre, s.dni ORDER BY s.apellido, s.nombre");
$stmt_cuotas_socio->bind_param('ss', $inicio_limites, $fin_limites);
$stmt_cuotas_socio->execute();
$cuotas_por_socio_periodo = $stmt_cuotas_socio->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cuotas_socio->close();
$stmt_cuotas_metodo = $conexion->prepare("SELECT metodo_pago, SUM(CASE WHEN estado = 'Completado' THEN 1 ELSE 0 END) AS cantidad_completados, COALESCE(SUM(CASE WHEN estado = 'Completado' THEN monto ELSE 0 END), 0) AS importe_completado, SUM(CASE WHEN estado = 'Pagado Pendiente' THEN 1 ELSE 0 END) AS cantidad_pendientes, COALESCE(SUM(CASE WHEN estado = 'Pagado Pendiente' THEN monto ELSE 0 END), 0) AS importe_pendiente, SUM(CASE WHEN estado = 'Anulado' THEN 1 ELSE 0 END) AS cantidad_anulados, COALESCE(SUM(CASE WHEN estado = 'Anulado' THEN monto ELSE 0 END), 0) AS importe_anulado FROM cuotas_socios WHERE fecha_pago >= ? AND fecha_pago < ? GROUP BY metodo_pago ORDER BY metodo_pago");
$stmt_cuotas_metodo->bind_param('ss', $inicio_limites, $fin_limites);
$stmt_cuotas_metodo->execute();
$cuotas_por_metodo_periodo = $stmt_cuotas_metodo->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cuotas_metodo->close();
$resumen_cuotas_periodo = $conexion->prepare("SELECT COUNT(*) AS registros, COALESCE(SUM(CASE WHEN estado = 'Completado' THEN monto ELSE 0 END), 0) AS importe_completado, COALESCE(SUM(CASE WHEN estado = 'Pagado Pendiente' THEN monto ELSE 0 END), 0) AS importe_pendiente, COALESCE(SUM(CASE WHEN estado = 'Anulado' THEN monto ELSE 0 END), 0) AS importe_anulado FROM cuotas_socios WHERE fecha_pago >= ? AND fecha_pago < ?");
$resumen_cuotas_periodo->bind_param('ss', $inicio_limites, $fin_limites);
$resumen_cuotas_periodo->execute();
$resumen_cuotas_periodo = $resumen_cuotas_periodo->get_result()->fetch_assoc();

$lotes_por_estado = $conexion->query("SELECT estado_actual, COUNT(*) AS cantidad_lotes, COALESCE(SUM(cantidad_actual), 0) AS plantas_actuales FROM lotes_cultivo GROUP BY estado_actual ORDER BY FIELD(estado_actual, 'Germinacion', 'Vegetativo', 'Floracion', 'Cosechado', 'Descartado')")->fetch_all(MYSQLI_ASSOC);
$max_plantas_estado = max(array_map(static fn($fila) => (int)$fila['plantas_actuales'], $lotes_por_estado) ?: [0]);
$inicio_periodo_clones = $inicio_mes_actual->modify('-11 months')->format('Y-m-d');
$actividad_madres_clones = obtener_actividad_madres_clones($conexion, $inicio_periodo_clones);
$total_lotes_vencidos = (int)$conexion->query("SELECT COUNT(*) AS total FROM lotes_cultivo WHERE fecha_estimada_cosecha < CURRENT_DATE() AND estado_actual NOT IN ('Cosechado', 'Descartado')")->fetch_assoc()['total'];
$lotes_vencidos = $conexion->query("SELECT lc.codigo_lote, v.nombre AS variedad, lc.estado_actual, lc.fecha_estimada_cosecha FROM lotes_cultivo lc INNER JOIN variedades v ON v.id = lc.variedad_id WHERE lc.fecha_estimada_cosecha < CURRENT_DATE() AND lc.estado_actual NOT IN ('Cosechado', 'Descartado') ORDER BY lc.fecha_estimada_cosecha ASC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$tareas_cultivo_resumen = $conexion->query("SELECT SUM(estado IN ('Pendiente','En curso')) AS abiertas, SUM(estado IN ('Pendiente','En curso') AND fecha_programada < CURRENT_DATE()) AS vencidas, SUM(estado IN ('Pendiente','En curso') AND fecha_programada BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY)) AS proximas FROM tareas_cultivo")->fetch_assoc();
$tareas_cultivo_agenda = $conexion->query("SELECT t.id,t.titulo,t.categoria,t.fecha_programada,t.estado,t.prioridad,lc.codigo_lote,COALESCE(o.usuario,'Sin asignar') AS responsable FROM tareas_cultivo t INNER JOIN lotes_cultivo lc ON lc.id=t.lote_id LEFT JOIN operadores o ON o.id=t.responsable_id WHERE t.estado IN ('Pendiente','En curso') AND t.fecha_programada <= DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY) ORDER BY t.fecha_programada,t.prioridad DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
$alertas_vencimientos = obtener_alertas_vencimientos($conexion);
$alertas_vencidas = count(array_filter($alertas_vencimientos, static fn($alerta) => (int)$alerta['dias_restantes'] < 0));
$alertas_proximas = count($alertas_vencimientos) - $alertas_vencidas;
$inventarios_sobre_cosecha = $conexion->query("SELECT c.codigo_cosecha, c.peso_seco_gramos, COUNT(i.id) AS productos, COALESCE(SUM(i.gramos_disponibles), 0) AS disponibles, COALESCE(SUM(i.gramos_reservados), 0) AS reservados, COALESCE(SUM(i.gramos_disponibles + i.gramos_reservados), 0) AS total_inventario, COALESCE(SUM(i.gramos_disponibles + i.gramos_reservados), 0) - c.peso_seco_gramos AS exceso FROM cosechas c INNER JOIN inventario_derivado i ON i.cosecha_id = c.id GROUP BY c.id, c.codigo_cosecha, c.peso_seco_gramos HAVING total_inventario > c.peso_seco_gramos ORDER BY exceso DESC")->fetch_all(MYSQLI_ASSOC);
$productos_en_cosechas_excedidas = $conexion->query("SELECT i.id, i.codigo_qr, i.cosecha_id, c.codigo_cosecha, i.gramos_disponibles, i.gramos_reservados, i.estado_inventario FROM inventario_derivado i INNER JOIN cosechas c ON c.id = i.cosecha_id INNER JOIN (SELECT c2.id FROM cosechas c2 INNER JOIN inventario_derivado i2 ON i2.cosecha_id = c2.id GROUP BY c2.id, c2.peso_seco_gramos HAVING SUM(i2.gramos_disponibles + i2.gramos_reservados) > c2.peso_seco_gramos) exceso ON exceso.id = c.id ORDER BY c.codigo_cosecha, i.id")->fetch_all(MYSQLI_ASSOC);
$resumen_calidad = $conexion->query("SELECT COUNT(*) AS total, COALESCE(SUM(resultado='Pendiente'),0) AS pendientes, COALESCE(SUM(resultado='No conforme'),0) AS no_conformes FROM controles_calidad")->fetch_assoc();
$ultimos_controles_calidad = $conexion->query("SELECT q.id,q.fecha_control,q.tipo_control,q.resultado,q.valor,q.unidad,c.codigo_cosecha,COALESCE(i.codigo_qr,'Cosecha completa') AS producto,o.usuario AS operador FROM controles_calidad q INNER JOIN cosechas c ON c.id=q.cosecha_id LEFT JOIN inventario_derivado i ON i.id=q.inventario_derivado_id INNER JOIN operadores o ON o.id=q.operador_id ORDER BY q.fecha_control DESC,q.id DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$trazabilidad_producto_sql = "SELECT i.id AS producto_id,i.codigo_qr,i.tipo_producto,v.nombre AS variedad,lc.codigo_lote,lc.origen_tipo,c.codigo_cosecha,c.fecha_cosecha,c.peso_fresco_gramos,c.peso_seco_gramos,i.gramos_disponibles,i.gramos_reservados,i.estado_inventario,CASE WHEN b.id IS NOT NULL THEN CONCAT(b.tipo_material,' · ',b.origen_tipo,' · ',COALESCE(NULLIF(b.numero_lote_origen,''),NULLIF(b.origen_proveedor,''),CONCAT('Banco #',b.id)),CASE WHEN pm_semilla.codigo_planta IS NULL THEN '' ELSE CONCAT(' · madre ',pm_semilla.codigo_planta) END) WHEN pm_lote.codigo_planta IS NOT NULL THEN CONCAT('Esqueje de madre · ',pm_lote.codigo_planta) ELSE lc.origen_tipo END AS origen_material,COALESCE(d.cantidad_dispensaciones,0) AS cantidad_dispensaciones,COALESCE(d.gramos_dispensados,0) AS gramos_dispensados,d.ultima_dispensacion FROM inventario_derivado i INNER JOIN cosechas c ON c.id=i.cosecha_id INNER JOIN lotes_cultivo lc ON lc.id=c.lote_id INNER JOIN variedades v ON v.id=lc.variedad_id LEFT JOIN banco_semillas b ON b.id=lc.banco_semillas_id LEFT JOIN plantas_madre pm_semilla ON pm_semilla.id=b.planta_madre_id LEFT JOIN plantas_madre pm_lote ON pm_lote.id=lc.planta_madre_id LEFT JOIN (SELECT inventario_derivado_id,COUNT(*) AS cantidad_dispensaciones,SUM(gramos_entregados) AS gramos_dispensados,MAX(fecha_dispensacion) AS ultima_dispensacion FROM dispensacion GROUP BY inventario_derivado_id) d ON d.inventario_derivado_id=i.id ORDER BY c.fecha_cosecha DESC,i.id DESC";
$trazabilidad_productos = $conexion->query($trazabilidad_producto_sql . ' LIMIT 20')->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">
    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>

    <nav class="navbar expand navbar-dark" aria-label="Nav">
        <div class="container-lg px-3">
            <a class="navbar-brand" href="./">
                <div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver al Panel</div>
            </a>
            <div class="navbar-brand">
                <div class="fs-14 text-admin-3 ui-f-700 mb-0"><i class="fa-light fa-file-chart-column me-1"></i> Reportes y Trazabilidad Regulatoria</div>
            </div>
        </div>
    </nav>

    <div class="container pb-4 pt-3">
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-2">
                <div class="card py-3 px-3 text-center">
                <span class="text-muted fs-12 uppercase">Socios habilitados</span>
                    <h3 class="ui-f-700 text-admin-3 mb-0 mt-1"><?php echo $total_socios; ?></h3>
                </div>
            </div>
            <div class="col-6 col-xl-2">
                <div class="card py-3 px-3 text-center">
                    <span class="text-muted fs-12 uppercase">Plantas Madres Activas</span>
                    <h3 class="ui-f-700 text-admin-3 mb-0 mt-1"><?php echo $total_plantas; ?></h3>
                </div>
            </div>
            <div class="col-6 col-xl-2">
                <div class="card py-3 px-3 text-center">
                    <span class="text-muted fs-12 uppercase">Stock disponible</span>
                    <h3 class="ui-f-700 text-admin-3 mb-0 mt-1"><?php echo number_format($total_stock_gramos, 2, ',', '.'); ?> g</h3>
                </div>
            </div>
            <div class="col-6 col-xl-2">
                <div class="card py-3 px-3 text-center">
                    <span class="text-muted fs-12 uppercase">Dispensado este Mes</span>
                    <h3 class="ui-f-700 text-admin-3 mb-0 mt-1"><?php echo number_format($total_dispensado_mes, 2, ',', '.'); ?> g</h3>
                </div>
            </div>
            <div class="col-6 col-xl-2">
                <div class="card py-3 px-3 text-center">
                    <span class="text-muted fs-12 uppercase">Activos con permiso vencido</span>
                    <h3 class="ui-f-700 text-danger mb-0 mt-1"><?php echo (int)$socios_activos_vencidos; ?></h3>
                </div>
            </div>
            <div class="col-6 col-xl-2">
                <div class="card py-3 px-3 text-center">
                    <span class="text-muted fs-12 uppercase">Vencen en 30 días</span>
                    <h3 class="ui-f-700 text-warning mb-0 mt-1"><?php echo (int)$socios_por_vencer; ?></h3>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card py-3 px-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div><h5 class="ui-f-700 text-admin-3 mb-1">Rendimiento por genética</h5><small class="text-muted">Cosechas de los últimos 12 meses. Cada lote y su cantidad inicial se cuentan una sola vez.</small></div>
                        <a class="btn btn-sm btn-dark" href="exportar_excel.php?tipo=rendimiento_genetica"><i class="fa-light fa-file-excel me-1"></i> Exportar informe</a>
                    </div>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Genética</th><th>Lotes</th><th>Plantas iniciales</th><th>Cosechas</th><th>Peso fresco</th><th>Peso seco</th><th>Merma</th><th>Seco por planta inicial</th><th>Ciclo medio</th></tr></thead>
                        <tbody><?php if (!$rendimiento_por_genetica): ?><tr><td colspan="9" class="text-muted">No hay cosechas registradas en los últimos 12 meses.</td><?php else: foreach ($rendimiento_por_genetica as $rendimiento): ?>
                            <tr><td><?php echo htmlspecialchars($rendimiento['variedad'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int)$rendimiento['lotes']; ?></td><td><?php echo (int)$rendimiento['plantas_iniciales']; ?></td><td><?php echo (int)$rendimiento['cantidad_cosechas']; ?></td><td><?php echo number_format((float)$rendimiento['peso_fresco_gramos'], 2, ',', '.'); ?> g</td><td><?php echo number_format((float)$rendimiento['peso_seco_gramos'], 2, ',', '.'); ?> g</td><td><?php echo $rendimiento['merma_porcentaje'] === null ? '—' : number_format((float)$rendimiento['merma_porcentaje'], 2, ',', '.') . ' %'; ?></td><td><?php echo $rendimiento['seco_por_planta'] === null ? '—' : number_format((float)$rendimiento['seco_por_planta'], 2, ',', '.') . ' g'; ?></td><td><?php echo $rendimiento['dias_ciclo_promedio'] === null ? '—' : number_format((float)$rendimiento['dias_ciclo_promedio'], 1, ',', '.') . ' días'; ?></td></tr>
                        <?php endforeach; endif; ?></tbody>
                    </table></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card py-3 px-4 h-100">
                    <h5 class="ui-f-700 text-admin-3 mb-3">Dispensación mensual <small class="text-muted fs-12">últimos 6 meses</small></h5>
                    <div style="height:240px"><canvas id="graficoDispensaciones" role="img" aria-label="Gramos dispensados por mes durante los últimos seis meses"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card py-3 px-4 h-100">
                    <h5 class="ui-f-700 text-admin-3 mb-3">Cosecha seca por genética <small class="text-muted fs-12">últimos 12 meses</small></h5>
                    <div style="height:240px"><canvas id="graficoCosechas" role="img" aria-label="Gramos secos cosechados por genética durante los últimos doce meses"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card py-3 px-4 h-100">
                    <h5 class="ui-f-700 text-admin-3 mb-3">Lotes de cultivo por etapa</h5>
                    <div style="height:240px"><canvas id="graficoLotes" role="img" aria-label="Cantidad de lotes de cultivo por etapa"></canvas></div>
                    <?php if ($total_lotes_vencidos > 0): ?>
                        <div class="alert alert-warning py-2 mt-3 mb-0">
                            <strong><?php echo $total_lotes_vencidos; ?> lotes</strong> tienen fecha estimada de cosecha superada. Se muestran hasta 10:
                            <?php foreach ($lotes_vencidos as $lote_vencido): ?>
                                <div class="small"><?php echo htmlspecialchars($lote_vencido['codigo_lote']); ?> · <?php echo htmlspecialchars($lote_vencido['variedad']); ?> · <?php echo date('d/m/Y', strtotime($lote_vencido['fecha_estimada_cosecha'])); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-12">
                <div class="card py-3 px-4 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h5 class="ui-f-700 text-admin-3 mb-1">Agenda de tareas de cultivo</h5><small class="text-muted">Tareas abiertas vencidas y programadas para los próximos 14 días.</small></div><a class="btn btn-sm btn-dark" href="tareas_cultivo.php">Abrir agenda</a></div>
                    <div class="d-flex flex-wrap gap-3 mb-3 small"><span>Abiertas: <strong><?php echo (int)($tareas_cultivo_resumen['abiertas'] ?? 0); ?></strong></span><span class="text-danger">Vencidas: <strong><?php echo (int)($tareas_cultivo_resumen['vencidas'] ?? 0); ?></strong></span><span>Próximas (14 días): <strong><?php echo (int)($tareas_cultivo_resumen['proximas'] ?? 0); ?></strong></span></div>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Fecha</th><th>Tarea</th><th>Lote</th><th>Responsable</th><th>Estado</th><th>Prioridad</th></tr></thead><tbody>
                        <?php if (!$tareas_cultivo_agenda): ?><tr><td colspan="6" class="text-muted">No hay tareas abiertas vencidas o próximas.</td><?php else: foreach ($tareas_cultivo_agenda as $tarea_agenda): ?><tr class="<?php echo $tarea_agenda['fecha_programada'] < date('Y-m-d') ? 'table-warning' : ''; ?>"><td><?php echo date('d/m/Y', strtotime($tarea_agenda['fecha_programada'])); ?></td><td><?php echo htmlspecialchars($tarea_agenda['titulo'], ENT_QUOTES, 'UTF-8'); ?><small class="d-block text-muted"><?php echo htmlspecialchars($tarea_agenda['categoria'], ENT_QUOTES, 'UTF-8'); ?></small></td><td><?php echo htmlspecialchars($tarea_agenda['codigo_lote'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($tarea_agenda['responsable'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($tarea_agenda['estado'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($tarea_agenda['prioridad'], ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
                </div>
            </div>
            <div class="col-12">
                <div class="card py-3 px-4 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h5 class="ui-f-700 text-admin-3 mb-1">Alertas de vencimientos y seguimiento</h5><small class="text-muted">Permisos REPROCANN vencidos o próximos, cosechas estimadas y tareas con fecha vencida o dentro de 30 días.</small></div><a class="btn btn-sm btn-dark" href="exportar_excel.php?tipo=alertas_vencimientos">Exportar alertas</a></div>
                    <div class="d-flex gap-3 mb-3 small"><span class="text-danger">Vencidas: <strong><?php echo $alertas_vencidas; ?></strong></span><span class="text-warning">Hoy o próximas (30 días): <strong><?php echo $alertas_proximas; ?></strong></span></div>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Fecha</th><th>Tipo</th><th>Referencia</th><th>Detalle</th><th>Situación</th><th>Días</th></tr></thead><tbody>
                        <?php if (!$alertas_vencimientos): ?><tr><td colspan="6" class="text-muted">No hay vencimientos ni seguimientos dentro de este período.</td><?php else: foreach ($alertas_vencimientos as $alerta): ?><tr class="<?php echo (int)$alerta['dias_restantes'] < 0 ? 'table-danger' : ((int)$alerta['dias_restantes'] <= 7 ? 'table-warning' : ''); ?>"><td><?php echo date('d/m/Y', strtotime($alerta['fecha'])); ?></td><td><?php echo htmlspecialchars($alerta['categoria'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($alerta['referencia'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($alerta['detalle'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($alerta['situacion'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int)$alerta['dias_restantes'] < 0 ? abs((int)$alerta['dias_restantes']) . ' vencidos' : ((int)$alerta['dias_restantes'] === 0 ? 'Hoy' : 'En ' . (int)$alerta['dias_restantes'] . ' días'); ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
                </div>
            </div>
            <div class="col-12">
                <div class="card py-3 px-4 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><div><h5 class="ui-f-700 text-admin-3 mb-1">Actividad de plantas madre y resultados de clones</h5><small class="text-muted">Extracciones y cortes de los últimos 12 meses. Enraizados incluye los trasplantados; el porcentaje usa clones registrados como base.</small></div><a class="btn btn-sm btn-dark" href="exportar_excel.php?tipo=actividad_madres_clones">Exportar informe</a></div>
                    <div style="height:260px" class="mb-3"><canvas id="graficoClones" role="img" aria-label="Estados de los clones registrados durante los últimos doce meses"></canvas></div>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Planta madre</th><th>Genética</th><th>Estado madre</th><th>Extracciones</th><th>Esquejes</th><th>Clones seguidos</th><th>Pendientes</th><th>Enraizados</th><th>% enraizado</th><th>Trasplantados</th><th>Descartados</th><th>Días medios al enraizar</th></tr></thead>
                        <tbody><?php if (!$actividad_madres_clones): ?><tr><td colspan="12" class="text-muted">No hay extracciones ni clones con actividad en este período.</td></tr><?php else: foreach ($actividad_madres_clones as $actividad_madre): ?>
                            <tr><td><?php echo htmlspecialchars($actividad_madre['codigo_planta'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($actividad_madre['variedad'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($actividad_madre['estado_madre'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int)$actividad_madre['extracciones']; ?></td><td><?php echo (int)$actividad_madre['esquejes_extraidos']; ?></td><td><?php echo (int)$actividad_madre['total_clones']; ?></td><td><?php echo (int)$actividad_madre['pendientes']; ?></td><td><?php echo (int)$actividad_madre['enraizados']; ?></td><td><?php echo $actividad_madre['porcentaje_enraizados'] === null ? '—' : number_format((float)$actividad_madre['porcentaje_enraizados'], 1, ',', '.') . '%'; ?></td><td><?php echo (int)$actividad_madre['trasplantados']; ?></td><td><?php echo (int)$actividad_madre['descartados']; ?></td><td><?php echo $actividad_madre['dias_enraizado_promedio'] === null ? '—' : number_format((float)$actividad_madre['dias_enraizado_promedio'], 1, ',', '.'); ?></td></tr>
                        <?php endforeach; endif; ?></tbody>
                    </table></div>
                </div>
            </div>
            <div class="col-12">
                <div class="card py-3 px-4 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div><h5 class="ui-f-700 text-admin-3 mb-1">Conciliación de inventario</h5><small class="text-muted">Compara el saldo operativo con el último movimiento y revisa la continuidad del historial.</small></div>
                        <div class="d-flex gap-2"><a class="btn btn-sm btn-outline-dark" href="movimientos_inventario.php">Ver movimientos</a><a class="btn btn-sm btn-dark" href="exportar_excel.php?tipo=conciliacion_inventario">Exportar conciliación</a></div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 mb-3 small">
                        <span>Productos: <strong><?php echo (int)$conciliacion_saldos['productos']; ?></strong></span>
                        <span>Sin movimientos: <strong><?php echo (int)$conciliacion_saldos['sin_movimientos']; ?></strong></span>
                        <span>Saldo diferente: <strong><?php echo (int)$conciliacion_saldos['saldo_distinto']; ?></strong></span>
                        <span>Saltos en historial: <strong><?php echo count($saltos_movimientos); ?></strong></span>
                    </div>
                    <?php if (!$productos_sin_conciliar && !$saltos_movimientos): ?>
                        <div class="alert alert-success py-2 mb-0">Los saldos actuales coinciden con los movimientos y la secuencia auditada no presenta saltos.</div>
                    <?php else: ?>
                        <?php if ($productos_sin_conciliar): ?><div class="table-responsive mb-3"><table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Producto</th><th>Disponible actual</th><th>Disponible auditado</th><th>Reservado actual</th><th>Reservado auditado</th><th>Último movimiento</th></tr></thead>
                            <tbody><?php foreach ($productos_sin_conciliar as $diferencia): ?><tr>
                                <td><?php echo htmlspecialchars($diferencia['codigo_qr'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo number_format((float)$diferencia['gramos_disponibles'], 2, ',', '.'); ?> g</td>
                                <td><?php echo $diferencia['movimiento_id'] === null ? 'Sin registro' : number_format((float)$diferencia['disponible_auditado'], 2, ',', '.') . ' g'; ?></td>
                                <td><?php echo number_format((float)$diferencia['gramos_reservados'], 2, ',', '.'); ?> g</td>
                                <td><?php echo $diferencia['movimiento_id'] === null ? 'Sin registro' : number_format((float)$diferencia['reservado_auditado'], 2, ',', '.') . ' g'; ?></td>
                                <td><?php echo htmlspecialchars($diferencia['tipo_movimiento'] ?? 'Ninguno', ENT_QUOTES, 'UTF-8'); ?><?php if ($diferencia['fecha_movimiento']): ?> · <?php echo date('d/m/Y H:i', strtotime($diferencia['fecha_movimiento'])); ?><?php endif; ?></td>
                            </tr><?php endforeach; ?></tbody>
                        </table></div><?php endif; ?>
                        <?php if ($saltos_movimientos): ?><div class="table-responsive"><table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Producto</th><th>Movimiento</th><th>Disponible anterior / esperado</th><th>Reservado anterior / esperado</th><th>Fecha</th></tr></thead>
                            <tbody><?php foreach ($saltos_movimientos as $salto): ?><tr><td><?php echo htmlspecialchars($salto['codigo_qr'], ENT_QUOTES, 'UTF-8'); ?></td><td>#<?php echo (int)$salto['movimiento_id']; ?></td><td><?php echo number_format((float)$salto['gramos_disponibles_antes'], 2, ',', '.'); ?> / <?php echo number_format((float)$salto['disponible_anterior'], 2, ',', '.'); ?> g</td><td><?php echo number_format((float)$salto['gramos_reservados_antes'], 2, ',', '.'); ?> / <?php echo number_format((float)$salto['reservado_anterior'], 2, ',', '.'); ?> g</td><td><?php echo date('d/m/Y H:i', strtotime($salto['created_at'])); ?></td></tr><?php endforeach; ?></tbody>
                        </table></div><?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-12">
                <div class="card py-3 px-4 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
                        <div><h5 class="ui-f-700 text-admin-3 mb-1">Dispensaciones y límites por socio</h5><small class="text-muted">El saldo usa el límite configurado actualmente. Las entregas con permiso vencido se señalan para revisión.</small></div>
                        <a class="btn btn-sm btn-dark" href="exportar_excel.php?tipo=dispensacion_limites&amp;periodo=<?php echo rawurlencode($periodo_limites); ?>">Exportar período</a>
                    </div>
                    <form method="get" class="row g-2 align-items-end mb-3">
                        <div class="col-auto"><label class="form-label mb-1" for="periodo">Período</label><input class="form-control form-control-sm" type="month" id="periodo" name="periodo" value="<?php echo htmlspecialchars($periodo_limites, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                        <div class="col-auto"><button class="btn btn-sm btn-outline-dark" type="submit">Consultar</button></div>
                        <div class="col-auto small text-muted">Socios con alertas de revisión: <strong><?php echo $socios_con_alertas_dispensacion; ?></strong></div>
                    </form>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Socio</th><th>Límite actual</th><th>Entregado</th><th>Saldo</th><th>Uso</th><th>Permiso / alertas</th></tr></thead>
                        <tbody><?php if (!$dispensaciones_por_socio_periodo): ?><tr><td colspan="6" class="text-muted">No hay socios registrados.</td></tr><?php else: foreach ($dispensaciones_por_socio_periodo as $fila_dispensacion_socio):
                            $limite_socio = (float)$fila_dispensacion_socio['limite_gramos_mensual'];
                            $entregado_socio = (float)$fila_dispensacion_socio['gramos_entregados'];
                            $saldo_socio = $limite_socio - $entregado_socio;
                            $porcentaje_uso = $limite_socio > 0 ? (int)min(100, round($entregado_socio / $limite_socio * 100)) : 0;
                            $alertas_socio = [];
                            if ($entregado_socio > $limite_socio) $alertas_socio[] = 'Supera el límite';
                            if ((int)$fila_dispensacion_socio['entregas_fuera_vigencia'] > 0) $alertas_socio[] = (int)$fila_dispensacion_socio['entregas_fuera_vigencia'] . ' entrega(s) posterior(es) al vencimiento';
                            if ($fila_dispensacion_socio['estado_membresia'] !== 'Activo') $alertas_socio[] = 'Membresía actual: ' . $fila_dispensacion_socio['estado_membresia'];
                            if ($fila_dispensacion_socio['reprocann_vencimiento'] < date('Y-m-d')) $alertas_socio[] = 'REPROCANN vencido actualmente';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fila_dispensacion_socio['apellido'] . ', ' . $fila_dispensacion_socio['nombre'], ENT_QUOTES, 'UTF-8'); ?><small class="d-block text-muted"><?php echo htmlspecialchars($fila_dispensacion_socio['dni'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                <td><?php echo number_format($limite_socio, 2, ',', '.'); ?> g</td>
                                <td><?php echo number_format($entregado_socio, 2, ',', '.'); ?> g</td>
                                <td class="<?php echo $saldo_socio < 0 ? 'text-danger fw-bold' : ''; ?>"><?php echo number_format($saldo_socio, 2, ',', '.'); ?> g</td>
                                <td><div class="progress" style="height:8px" title="<?php echo $porcentaje_uso; ?>%"><div class="progress-bar <?php echo $entregado_socio > $limite_socio ? 'bg-danger' : 'bg-success'; ?>" style="width:<?php echo $porcentaje_uso; ?>%"></div></div><small><?php echo $porcentaje_uso; ?>%</small></td>
                                <td><?php echo $alertas_socio ? '<span class="text-warning">' . htmlspecialchars(implode(' · ', $alertas_socio), ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="text-success">Sin alertas</span>'; ?><small class="d-block text-muted">Vence: <?php echo htmlspecialchars((string)$fila_dispensacion_socio['reprocann_vencimiento'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                            </tr>
                        <?php endforeach; endif; ?></tbody>
                    </table></div>
                </div>
            </div>
            <div class="col-12">
                <div class="card py-3 px-4 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
                        <div><h5 class="ui-f-700 text-admin-3 mb-1">Cuotas por socio y período</h5><small class="text-muted">Importes registrados, separados por estado. Este módulo no guarda cargos periódicos previstos, por lo que no calcula deuda.</small></div>
                        <a class="btn btn-sm btn-dark" href="exportar_excel.php?tipo=cuotas_periodo&amp;periodo=<?php echo rawurlencode($periodo_limites); ?>">Exportar detalle</a>
                    </div>
                    <form method="get" class="row g-2 align-items-end mb-3">
                        <div class="col-auto"><label class="form-label mb-1" for="periodo_cuotas">Período</label><input class="form-control form-control-sm" type="month" id="periodo_cuotas" name="periodo" value="<?php echo htmlspecialchars($periodo_limites, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                        <div class="col-auto"><button class="btn btn-sm btn-outline-dark" type="submit">Consultar</button></div>
                    </form>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted">Completado</small><div class="fs-5 fw-bold">$<?php echo number_format((float)$resumen_cuotas_periodo['importe_completado'], 2, ',', '.'); ?></div></div></div>
                        <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted">Pagado pendiente de confirmación</small><div class="fs-5 fw-bold">$<?php echo number_format((float)$resumen_cuotas_periodo['importe_pendiente'], 2, ',', '.'); ?></div></div></div>
                        <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted">Anulado (informativo)</small><div class="fs-5 fw-bold">$<?php echo number_format((float)$resumen_cuotas_periodo['importe_anulado'], 2, ',', '.'); ?></div></div></div>
                    </div>
                    <div class="table-responsive mb-4"><table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Socio</th><th>Completado</th><th>Pagado pendiente</th><th>Anulado</th><th>Último registro</th></tr></thead>
                        <tbody><?php if (!$cuotas_por_socio_periodo): ?><tr><td colspan="5" class="text-muted">No hay socios registrados.</td></tr><?php else: foreach ($cuotas_por_socio_periodo as $fila_cuota_socio): ?>
                            <tr><td><?php echo htmlspecialchars($fila_cuota_socio['apellido'] . ', ' . $fila_cuota_socio['nombre'], ENT_QUOTES, 'UTF-8'); ?><small class="d-block text-muted"><?php echo htmlspecialchars($fila_cuota_socio['dni'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                <td><?php echo (int)$fila_cuota_socio['cantidad_completados']; ?> · $<?php echo number_format((float)$fila_cuota_socio['importe_completado'], 2, ',', '.'); ?></td>
                                <td><?php echo (int)$fila_cuota_socio['cantidad_pendientes']; ?> · $<?php echo number_format((float)$fila_cuota_socio['importe_pendiente'], 2, ',', '.'); ?></td>
                                <td><?php echo (int)$fila_cuota_socio['cantidad_anulados']; ?> · $<?php echo number_format((float)$fila_cuota_socio['importe_anulado'], 2, ',', '.'); ?></td>
                                <td><?php echo $fila_cuota_socio['ultimo_registro'] ? date('d/m/Y', strtotime($fila_cuota_socio['ultimo_registro'])) : '—'; ?></td></tr>
                        <?php endforeach; endif; ?></tbody>
                    </table></div>
                    <h6 class="fw-bold">Importes por medio de pago</h6>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Medio</th><th>Completado</th><th>Pagado pendiente</th><th>Anulado</th></tr></thead><tbody>
                        <?php if (!$cuotas_por_metodo_periodo): ?><tr><td colspan="4" class="text-muted">No hay cuotas registradas en este período.</td></tr><?php else: foreach ($cuotas_por_metodo_periodo as $fila_metodo_cuota): ?>
                            <tr><td><?php echo htmlspecialchars((string)$fila_metodo_cuota['metodo_pago'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int)$fila_metodo_cuota['cantidad_completados']; ?> · $<?php echo number_format((float)$fila_metodo_cuota['importe_completado'], 2, ',', '.'); ?></td><td><?php echo (int)$fila_metodo_cuota['cantidad_pendientes']; ?> · $<?php echo number_format((float)$fila_metodo_cuota['importe_pendiente'], 2, ',', '.'); ?></td><td><?php echo (int)$fila_metodo_cuota['cantidad_anulados']; ?> · $<?php echo number_format((float)$fila_metodo_cuota['importe_anulado'], 2, ',', '.'); ?></td></tr>
                        <?php endforeach; endif; ?></tbody></table></div>
                    <small class="text-muted mt-3">“Pagado Pendiente” identifica un cobro aún no confirmado. No equivale a deuda calculada.</small>
                </div>
            </div>
            <div class="col-12">
                <div class="card py-3 px-4">
                    <h5 class="ui-f-700 text-admin-3 mb-3">Inventario disponible y reservado por producto</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Producto</th><th>Lotes</th><th>Disponible</th><th>Reservado</th></tr></thead>
                            <tbody>
                            <?php if (!$stock_por_producto): ?>
                                <tr><td colspan="4" class="text-muted">No hay productos registrados.</td></tr>
                            <?php else: foreach ($stock_por_producto as $stock): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stock['tipo_producto']); ?></td>
                                    <td><?php echo (int)$stock['cantidad_lotes']; ?></td>
                                    <td><?php echo number_format((float)$stock['gramos_disponibles'], 2, ',', '.'); ?> g</td>
                                    <td><?php echo number_format((float)$stock['gramos_reservados'], 2, ',', '.'); ?> g</td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card py-3 px-4">
                    <h5 class="ui-f-700 text-admin-3 mb-1">Cuotas registradas por estado</h5>
                    <p class="text-muted fs-14 mb-3">Los importes se muestran por separado; los registros anulados no se mezclan con los completados.</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Estado</th><th>Registros</th><th>Importe histórico</th><th>Importe este mes</th></tr></thead>
                            <tbody>
                            <?php foreach ($estados_cuotas as $estado_cuota): $fila_cuota = $cuotas_por_estado[$estado_cuota] ?? ['cantidad'=>0,'total_historico'=>0,'total_mes'=>0]; ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($estado_cuota); ?></td>
                                    <td><?php echo (int)$fila_cuota['cantidad']; ?></td>
                                    <td>$<?php echo number_format((float)$fila_cuota['total_historico'], 2, ',', '.'); ?></td>
                                    <td>$<?php echo number_format((float)$fila_cuota['total_mes'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center mb-3">
            <div class="col-12">
                <div class="card py-3 px-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div><h5 class="ui-f-700 text-admin-3 mb-1">Trazabilidad completa de producto</h5><small class="text-muted">Origen, madre o banco, lote, cosecha, fraccionamiento y dispensaciones; se muestran los 20 productos más recientes.</small></div>
                        <a class="btn btn-sm btn-dark" href="exportar_excel.php?tipo=trazabilidad_producto"><i class="fa-light fa-file-excel me-1"></i> Exportar informe completo</a>
                    </div>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Producto QR</th><th>Genética</th><th>Origen</th><th>Lote</th><th>Cosecha</th><th>Producto</th><th>Disponible</th><th>Reservado</th><th>Dispensaciones</th><th>Entregado</th></tr></thead>
                        <tbody><?php if (!$trazabilidad_productos): ?><tr><td colspan="10" class="text-muted">Todavía no hay productos derivados para trazar.</td><?php else: foreach ($trazabilidad_productos as $traza): ?>
                            <tr><td><?php echo htmlspecialchars($traza['codigo_qr'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($traza['variedad'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($traza['origen_material'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($traza['codigo_lote'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($traza['codigo_cosecha'], ENT_QUOTES, 'UTF-8'); ?><small class="d-block text-muted"><?php echo date('d/m/Y', strtotime($traza['fecha_cosecha'])); ?></small></td><td><?php echo htmlspecialchars($traza['tipo_producto'], ENT_QUOTES, 'UTF-8'); ?><small class="d-block text-muted"><?php echo htmlspecialchars($traza['estado_inventario'], ENT_QUOTES, 'UTF-8'); ?></small></td><td><?php echo number_format((float)$traza['gramos_disponibles'], 2, ',', '.'); ?> g</td><td><?php echo number_format((float)$traza['gramos_reservados'], 2, ',', '.'); ?> g</td><td><?php echo (int)$traza['cantidad_dispensaciones']; ?></td><td><?php echo number_format((float)$traza['gramos_dispensados'], 2, ',', '.'); ?> g</td></tr>
                        <?php endforeach; endif; ?></tbody>
                    </table></div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card py-3 px-4">
                    <h5 class="ui-f-700 text-admin-3 mb-3"><i class="fa-light fa-download me-1"></i> Exportación de Informes de Control</h5>
                    <p class="text-muted fs-14">Descargue los registros consolidados en formato compatible con planillas de cálculo para auditorías y trazabilidad legal.</p>
                    
                    <div class="list-group">
                        <a href="exportar_excel.php?tipo=socios" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-1 ui-f-700">1. Padrón de Socios y Vencimientos REPROCANN</h6>
                                <small class="text-muted">Listado completo de pacientes, códigos de vinculación y límites autorizados.</small>
                            </div>
                            <span class="btn btn-sm btn-dark"><i class="fa-light fa-file-excel me-1"></i> Exportar</span>
                        </a>

                        <a href="exportar_excel.php?tipo=inventario" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-1 ui-f-700">2. Trazabilidad de Stock e Inventario Derivado</h6>
                                <small class="text-muted">Frascos activos, códigos QR de origen, variedades y estados de curado.</small>
                            </div>
                            <span class="btn btn-sm btn-dark"><i class="fa-light fa-file-excel me-1"></i> Exportar</span>
                        </a>

                        <a href="exportar_excel.php?tipo=cosechas" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-1 ui-f-700">3. Rendimiento por cosecha y genética</h6>
                                <small class="text-muted">Pesos fresco y seco, merma y duración del ciclo; duración vacía si la fecha de inicio no es válida.</small>
                            </div>
                            <span class="btn btn-sm btn-dark"><i class="fa-light fa-file-excel me-1"></i> Exportar</span>
                        </a>
                        <a href="exportar_excel.php?tipo=rendimiento_genetica" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div><h6 class="mb-1 ui-f-700">Rendimiento agregado por genética</h6><small class="text-muted">Plantas iniciales, cosechas, pesos, merma calculada, peso seco por planta y duración media del ciclo.</small></div>
                            <span class="btn btn-sm btn-dark"><i class="fa-light fa-file-excel me-1"></i> Exportar</span>
                        </a>

                        <a href="exportar_excel.php?tipo=dispensacion" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-1 ui-f-700">4. Libro de Registro de Dispensaciones y Retiros</h6>
                                <small class="text-muted">Historial detallado de entregas mensuales por paciente y validaciones de tope legal.</small>
                            </div>
                            <span class="btn btn-sm btn-dark"><i class="fa-light fa-file-excel me-1"></i> Exportar</span>
                        </a>
                        <a href="exportar_excel.php?tipo=movimientos" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-1 ui-f-700">5. Libro de movimientos de inventario</h6>
                                <small class="text-muted">Entradas, ajustes y dispensaciones con saldos antes y después, operador y trazabilidad de origen.</small>
                            </div>
                            <span class="btn btn-sm btn-dark"><i class="fa-light fa-file-excel me-1"></i> Exportar</span>
                        </a>
                        <a href="exportar_excel.php?tipo=cuotas" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-1 ui-f-700">6. Libro de cuotas y cobros</h6>
                                <small class="text-muted">Importes, conceptos, socios y estados para conciliación; conserva los anulados identificados.</small>
                            </div>
                            <span class="btn btn-sm btn-dark"><i class="fa-light fa-file-excel me-1"></i> Exportar</span>
                        </a>
                        <a href="exportar_excel.php?tipo=movimientos_banco_semillas" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-1 ui-f-700">7. Movimientos del banco de semillas</h6>
                                <small class="text-muted">Ingresos, ajustes y consumo por lote con operador y saldo antes/después.</small>
                            </div>
                            <span class="btn btn-sm btn-dark"><i class="fa-light fa-file-excel me-1"></i> Exportar</span>
                        </a>
                        <a href="exportar_excel.php?tipo=clones" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-1 ui-f-700">8. Trazabilidad individual de clones</h6>
                                <small class="text-muted">Origen, planta madre, etapa, fechas, ubicación, lote de destino y operador.</small>
                            </div>
                            <span class="btn btn-sm btn-dark"><i class="fa-light fa-file-excel me-1"></i> Exportar</span>
                        </a>
                        <a href="exportar_excel.php?tipo=control_calidad" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                            <div><h6 class="mb-1 ui-f-700">9. Controles de calidad</h6><small class="text-muted">Resultado, mediciones, lote de producto, fecha y operador responsable.</small></div>
                            <span class="btn btn-sm btn-dark"><i class="fa-light fa-file-excel me-1"></i> Exportar</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center mt-3 mb-3">
            <div class="col-lg-10">
                <div class="card py-3 px-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h5 class="ui-f-700 text-admin-3 mb-1">Control de calidad</h5><small class="text-muted">Inspecciones y análisis registrados por cosecha o producto derivado.</small></div><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-dark" href="control_calidad.php">Ver controles</a><a class="btn btn-sm btn-dark" href="control_calidad_agregar.php">Registrar control</a></div></div>
                    <div class="d-flex gap-3 small mb-3"><span>Controles: <strong><?php echo (int)$resumen_calidad['total']; ?></strong></span><span>Pendientes: <strong><?php echo (int)$resumen_calidad['pendientes']; ?></strong></span><span class="text-danger">No conformes: <strong><?php echo (int)$resumen_calidad['no_conformes']; ?></strong></span></div>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Fecha</th><th>Cosecha / producto</th><th>Control</th><th>Resultado</th><th>Medición</th><th>Operador</th></tr></thead><tbody><?php if(!$ultimos_controles_calidad):?><tr><td colspan="6" class="text-muted">Aún no hay controles registrados.</td><?php else:foreach($ultimos_controles_calidad as $control):?><tr class="<?php echo $control['resultado']==='No conforme'?'table-danger':'';?>"><td><?php echo date('d/m/Y',strtotime($control['fecha_control']));?></td><td><?php echo htmlspecialchars($control['codigo_cosecha'],ENT_QUOTES,'UTF-8');?><small class="d-block text-muted"><?php echo htmlspecialchars($control['producto'],ENT_QUOTES,'UTF-8');?></small></td><td><?php echo htmlspecialchars($control['tipo_control'],ENT_QUOTES,'UTF-8');?></td><td><?php echo htmlspecialchars($control['resultado'],ENT_QUOTES,'UTF-8');?></td><td><?php echo $control['valor']===null?'—':htmlspecialchars((string)$control['valor'].' '.(string)$control['unidad'],ENT_QUOTES,'UTF-8');?></td><td><?php echo htmlspecialchars($control['operador'],ENT_QUOTES,'UTF-8');?></td></tr><?php endforeach;endif;?></tbody></table></div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center mt-3">
            <div class="col-lg-8">
                <div class="card py-3 px-4">
                    <h5 class="ui-f-700 text-admin-3 mb-1">Control de stock frente al peso seco</h5>
                    <p class="text-muted fs-14 mb-3">Compara el inventario disponible más reservado con el peso seco registrado para cada cosecha.</p>
                    <?php if (!$inventarios_sobre_cosecha): ?>
                        <div class="alert alert-success mb-0">No hay cosechas con existencias superiores a su peso seco.</div>
                    <?php else: ?>
                        <div class="alert alert-danger py-2">Hay cosechas con exceso de stock. Se muestran sin modificar sus registros.</div>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-3">
                            <thead><tr><th>Cosecha</th><th>Productos</th><th>Peso seco</th><th>Disponible</th><th>Reservado</th><th>Exceso</th></tr></thead>
                            <tbody><?php foreach ($inventarios_sobre_cosecha as $exceso): ?>
                                <tr><td><?php echo htmlspecialchars($exceso['codigo_cosecha']); ?></td><td><?php echo (int)$exceso['productos']; ?></td><td><?php echo number_format((float)$exceso['peso_seco_gramos'], 2, ',', '.'); ?> g</td><td><?php echo number_format((float)$exceso['disponibles'], 2, ',', '.'); ?> g</td><td><?php echo number_format((float)$exceso['reservados'], 2, ',', '.'); ?> g</td><td class="text-danger ui-f-700"><?php echo number_format((float)$exceso['exceso'], 2, ',', '.'); ?> g</td></tr>
                            <?php endforeach; ?></tbody>
                        </table></div>
                        <h6 class="ui-f-700 text-admin-3">Productos asociados</h6>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-3">
                            <thead><tr><th>Cosecha</th><th>Producto QR</th><th>Disponible</th><th>Reservado</th><th>Estado</th><th></th></tr></thead>
                            <tbody><?php foreach ($productos_en_cosechas_excedidas as $producto_excedido): ?>
                                <tr><td><?php echo htmlspecialchars($producto_excedido['codigo_cosecha']); ?></td><td><?php echo htmlspecialchars($producto_excedido['codigo_qr']); ?></td><td><?php echo number_format((float)$producto_excedido['gramos_disponibles'], 2, ',', '.'); ?> g</td><td><?php echo number_format((float)$producto_excedido['gramos_reservados'], 2, ',', '.'); ?> g</td><td><?php echo htmlspecialchars($producto_excedido['estado_inventario']); ?></td><td><a class="btn btn-outline-dark btn-sm" href="inventario_derivado_editar.php?id=<?php echo (int)$producto_excedido['id']; ?>" aria-label="Editar producto <?php echo htmlspecialchars($producto_excedido['codigo_qr'], ENT_QUOTES); ?>">Editar</a></td></tr>
                            <?php endforeach; ?></tbody>
                        </table></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            if (typeof Chart === 'undefined') return;
            const dispensaciones = <?php echo json_encode($dispensaciones_mensuales, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            const cosechas = <?php echo json_encode($produccion_variedad, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            const etapas = <?php echo json_encode($lotes_por_estado, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            const clones = <?php echo json_encode($actividad_madres_clones, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            const crear = (id, tipo, etiquetas, datasets, extra = {}) => {
                const elemento = document.getElementById(id);
                if (!elemento) return;
                new Chart(elemento, {type: tipo, data: {labels: etiquetas, datasets}, options: {responsive: true, maintainAspectRatio: false, ...extra}});
            };
            crear('graficoDispensaciones', 'line', dispensaciones.map(x => x.mes), [{label: 'Gramos entregados', data: dispensaciones.map(x => Number(x.gramos)), borderColor: '#16805d', backgroundColor: 'rgba(22,128,93,.16)', fill: true, tension: .25}], {scales: {y: {beginAtZero: true}}});
            crear('graficoCosechas', 'bar', cosechas.map(x => x.variedad), [{label: 'Gramos secos', data: cosechas.map(x => Number(x.gramos_secos)), backgroundColor: '#5577c8'}], {indexAxis: 'y', scales: {x: {beginAtZero: true}}});
            crear('graficoLotes', 'doughnut', etapas.map(x => x.estado_actual), [{label: 'Lotes', data: etapas.map(x => Number(x.cantidad_lotes)), backgroundColor: ['#65a8a1','#85b867','#d1a64c','#6381bd','#bf6e6e']}], {plugins: {legend: {position: 'bottom'}}});
            crear('graficoClones', 'bar', clones.map(x => x.codigo_planta), [
                {label: 'Pendientes de enraizar', data: clones.map(x => Number(x.pendientes)), backgroundColor: '#d1a64c'},
                {label: 'Enraizados (sin trasplantados)', data: clones.map(x => Math.max(0, Number(x.enraizados) - Number(x.trasplantados))), backgroundColor: '#73a97a'},
                {label: 'Trasplantados', data: clones.map(x => Number(x.trasplantados)), backgroundColor: '#6381bd'},
                {label: 'Descartados', data: clones.map(x => Number(x.descartados)), backgroundColor: '#bf6e6e'}
            ], {scales: {x: {stacked: true}, y: {stacked: true, beginAtZero: true}}});
        })();
    </script>
    <script src="https://buho.pm/admin/js/jquery.min.js"></script>
    <script>
        document.body.style.overflow = "hidden";

        $(document).ready(function() {
            // 1. Desvanecer loader al cargar la página (o al volver atrás)
            function ocultarLoader() {
                $(".ui-loader").fadeOut(function() {
                    $(".ui-loader-bg").fadeOut().addClass("sale");
                    document.body.style.overflow = "auto";
                });
            }

            // Ejecución inicial al cargar
            setTimeout(ocultarLoader, 250);

            // 3. SOLUCIÓN BOTÓN ATRÁS (BFCache)
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    // Si el usuario vuelve atrás, ocultamos el loader y reactivamos clics
                    ocultarLoader();
                    $("a").css("pointer-events", "auto");
                }
            });
        });
    </script>
</body>
</html>
