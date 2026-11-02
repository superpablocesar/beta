<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION["usuario"])) {
    require_once __DIR__ . '/alertify_error.php';
    redirigir_error_alertify('Inicia sesión para acceder a este reporte.', 403);
}

require_once "./conexion.php";
require_once __DIR__ . '/reportes_helper.php';
$conexion = conexion();

// Evita que campos de texto controlados por usuarios se interpreten como fórmulas en Excel.
function escribir_fila_csv_segura($salida, array $fila): void
{
    $valores = array_map(static function ($valor) {
        if (!is_string($valor) || is_numeric($valor)) return $valor;
        return preg_match('/^[\x00-\x20]*[=+@-]/', $valor) ? "'" . $valor : $valor;
    }, array_values($fila));
    fputcsv($salida, $valores, ';');
}

$tipo = $_GET['tipo'] ?? '';

if ($tipo == 'socios') {
    $filename = "padron_socios_reprocann_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8 en Excel
    fputcsv($output, array('Tipo de fila', 'Fecha de exportación', 'Operador exportador', 'ID', 'Nombre', 'Apellido', 'DNI', 'Teléfono', 'Email', 'REPROCANN', 'Vencimiento', 'Límite Gramos', 'Estado operativo', 'Estado resumido', 'Cantidad de socios'), ';');
    
    $res = $conexion->query("SELECT id, nombre, apellido, dni, telefono, email, reprocann_codigo, reprocann_vencimiento, limite_gramos_mensual, estado_membresia, CASE WHEN estado_membresia = 'Activo' AND reprocann_vencimiento < CURRENT_DATE() THEN 'Activo · REPROCANN vencido' ELSE estado_membresia END AS estado_operativo FROM socios ORDER BY apellido ASC");
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $totales_estado = [];
    $total_socios = 0;
    while ($row = $res->fetch_assoc()) {
        $total_socios++;
        $estado = (string)$row['estado_membresia'];
        $totales_estado[$estado] = ($totales_estado[$estado] ?? 0) + 1;
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $row['id'], $row['nombre'], $row['apellido'],
            $row['dni'], $row['telefono'], $row['email'], $row['reprocann_codigo'], $row['reprocann_vencimiento'],
            $row['limite_gramos_mensual'], $row['estado_operativo'], '', '',
        ]);
    }
    foreach ($totales_estado as $estado => $cantidad_estado) {
        escribir_fila_csv_segura($output, ['TOTAL ESTADO', $fecha_exportacion, $operador_exportacion, '', '', '', '', '', '', '', '', '', '', $estado, $cantidad_estado]);
    }
    escribir_fila_csv_segura($output, ['TOTAL GENERAL', $fecha_exportacion, $operador_exportacion, '', '', '', '', '', '', '', '', '', '', '', $total_socios]);
    fclose($output);
    exit();
}

if ($tipo == 'inventario') {
    $filename = "inventario_derivado_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de fila', 'Fecha de exportación', 'Operador exportador', 'ID', 'Código QR', 'Tipo Producto', 'Cosecha', 'Fecha Cosecha', 'Lote', 'Genética', 'Gramos Disponibles', 'Gramos Reservados', 'Estado', 'Fecha Creación', 'Cantidad de productos', 'Total disponible (g)', 'Total reservado (g)'), ';');
    
    $res = $conexion->query("SELECT i.id, i.codigo_qr, i.tipo_producto, c.codigo_cosecha, c.fecha_cosecha, lc.codigo_lote, v.nombre AS variedad, i.gramos_disponibles, i.gramos_reservados, i.estado_inventario, i.created_at FROM inventario_derivado i INNER JOIN cosechas c ON c.id = i.cosecha_id INNER JOIN lotes_cultivo lc ON lc.id = c.lote_id INNER JOIN variedades v ON v.id = lc.variedad_id ORDER BY i.id DESC");
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $cantidad_productos = 0;
    $total_disponible = 0.0;
    $total_reservado = 0.0;
    while ($row = $res->fetch_assoc()) {
        $cantidad_productos++;
        $total_disponible += (float)$row['gramos_disponibles'];
        $total_reservado += (float)$row['gramos_reservados'];
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $row['id'], $row['codigo_qr'], $row['tipo_producto'],
            $row['codigo_cosecha'], $row['fecha_cosecha'], $row['codigo_lote'], $row['variedad'],
            $row['gramos_disponibles'], $row['gramos_reservados'], $row['estado_inventario'], $row['created_at'], '', '', '',
        ]);
    }
    escribir_fila_csv_segura($output, ['TOTAL', $fecha_exportacion, $operador_exportacion, '', '', '', '', '', '', '', '', '', '', '', $cantidad_productos, number_format($total_disponible, 2, '.', ''), number_format($total_reservado, 2, '.', '')]);
    fclose($output);
    exit();
}

if ($tipo == 'banco_semillas') {
    $filename = 'banco_semillas_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de fila', 'Fecha de exportación', 'Operador exportador', 'ID material', 'Genética', 'Tipo de genética', 'Tipo de material', 'Origen', 'Proveedor', 'Lote de origen', 'Planta madre', 'Planta padre', 'Fecha de polinización', 'Unidades disponibles', 'Ubicación', 'Cantidad de materiales', 'Unidades disponibles total', 'Materiales agotados'), ';');
    $res = $conexion->query("SELECT bs.id,bs.tipo_material,bs.origen_tipo,bs.origen_proveedor,bs.numero_lote_origen,bs.fecha_polinizacion,bs.cantidad_disponible,bs.ubicacion_almacen,v.nombre AS variedad,v.tipo AS variedad_tipo,pm.codigo_planta AS planta_madre,pp.codigo_planta AS planta_padre FROM banco_semillas bs INNER JOIN variedades v ON v.id=bs.variedad_id LEFT JOIN plantas_madre pm ON pm.id=bs.planta_madre_id LEFT JOIN plantas_madre pp ON pp.id=bs.planta_padre_id ORDER BY bs.id DESC");
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $cantidad_materiales = 0;
    $unidades_disponibles = 0;
    $materiales_agotados = 0;
    while ($row = $res->fetch_assoc()) {
        $cantidad_materiales++;
        $unidades_disponibles += (int)$row['cantidad_disponible'];
        if ((int)$row['cantidad_disponible'] <= 0) $materiales_agotados++;
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $row['id'], $row['variedad'], $row['variedad_tipo'],
            $row['tipo_material'], $row['origen_tipo'], $row['origen_proveedor'], $row['numero_lote_origen'],
            $row['planta_madre'], $row['planta_padre'], $row['fecha_polinizacion'], $row['cantidad_disponible'],
            $row['ubicacion_almacen'], '', '', '',
        ]);
    }
    escribir_fila_csv_segura($output, ['TOTAL', $fecha_exportacion, $operador_exportacion, '', '', '', '', '', '', '', '', '', '', '', '', $cantidad_materiales, $unidades_disponibles, $materiales_agotados]);
    fclose($output);
    exit();
}

if ($tipo == 'trazabilidad_producto') {
    $filename = 'trazabilidad_producto_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de fila', 'Fecha de exportación', 'Operador exportador', 'ID Producto', 'Código QR', 'Tipo de producto', 'Genética', 'Origen del material', 'Origen de lote', 'Lote de cultivo', 'Cosecha', 'Fecha de cosecha', 'Peso fresco (g)', 'Peso seco (g)', 'Gramos disponibles', 'Gramos reservados', 'Estado de inventario', 'Cantidad de dispensaciones', 'Gramos dispensados', 'Última dispensación', 'Cantidad de productos', 'Total disponible (g)', 'Total reservado (g)', 'Total de dispensaciones', 'Total dispensado (g)'), ';');
    $res = $conexion->query("SELECT i.id AS producto_id,i.codigo_qr,i.tipo_producto,v.nombre AS variedad,lc.codigo_lote,lc.origen_tipo,c.codigo_cosecha,c.fecha_cosecha,c.peso_fresco_gramos,c.peso_seco_gramos,i.gramos_disponibles,i.gramos_reservados,i.estado_inventario,CASE WHEN b.id IS NOT NULL THEN CONCAT(b.tipo_material,' · ',b.origen_tipo,' · ',COALESCE(NULLIF(b.numero_lote_origen,''),NULLIF(b.origen_proveedor,''),CONCAT('Banco #',b.id)),CASE WHEN pm_semilla.codigo_planta IS NULL THEN '' ELSE CONCAT(' · madre ',pm_semilla.codigo_planta) END) WHEN pm_lote.codigo_planta IS NOT NULL THEN CONCAT('Esqueje de madre · ',pm_lote.codigo_planta) ELSE lc.origen_tipo END AS origen_material,COALESCE(d.cantidad_dispensaciones,0) AS cantidad_dispensaciones,COALESCE(d.gramos_dispensados,0) AS gramos_dispensados,d.ultima_dispensacion FROM inventario_derivado i INNER JOIN cosechas c ON c.id=i.cosecha_id INNER JOIN lotes_cultivo lc ON lc.id=c.lote_id INNER JOIN variedades v ON v.id=lc.variedad_id LEFT JOIN banco_semillas b ON b.id=lc.banco_semillas_id LEFT JOIN plantas_madre pm_semilla ON pm_semilla.id=b.planta_madre_id LEFT JOIN plantas_madre pm_lote ON pm_lote.id=lc.planta_madre_id LEFT JOIN (SELECT inventario_derivado_id,COUNT(*) AS cantidad_dispensaciones,SUM(gramos_entregados) AS gramos_dispensados,MAX(fecha_dispensacion) AS ultima_dispensacion FROM dispensacion GROUP BY inventario_derivado_id) d ON d.inventario_derivado_id=i.id ORDER BY c.fecha_cosecha DESC,i.id DESC");
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $totales = ['productos' => 0, 'disponible' => 0.0, 'reservado' => 0.0, 'dispensaciones' => 0, 'gramos_dispensados' => 0.0];
    while ($row = $res->fetch_assoc()) {
        $totales['productos']++;
        $totales['disponible'] += (float)$row['gramos_disponibles'];
        $totales['reservado'] += (float)$row['gramos_reservados'];
        $totales['dispensaciones'] += (int)$row['cantidad_dispensaciones'];
        $totales['gramos_dispensados'] += (float)$row['gramos_dispensados'];
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $row['producto_id'], $row['codigo_qr'],
            $row['tipo_producto'], $row['variedad'], $row['origen_material'], $row['origen_tipo'], $row['codigo_lote'],
            $row['codigo_cosecha'], $row['fecha_cosecha'], $row['peso_fresco_gramos'], $row['peso_seco_gramos'],
            $row['gramos_disponibles'], $row['gramos_reservados'], $row['estado_inventario'], $row['cantidad_dispensaciones'],
            $row['gramos_dispensados'], $row['ultima_dispensacion'], '', '', '', '', '',
        ]);
    }
    escribir_fila_csv_segura($output, ['TOTAL', $fecha_exportacion, $operador_exportacion, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', $totales['productos'], number_format($totales['disponible'], 2, '.', ''), number_format($totales['reservado'], 2, '.', ''), $totales['dispensaciones'], number_format($totales['gramos_dispensados'], 2, '.', '')]);
    fclose($output);
    exit();
}

if ($tipo == 'cosechas') {
    $filename = "rendimiento_cosechas_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de fila', 'Fecha de exportación', 'Operador exportador', 'ID Cosecha', 'Código Cosecha', 'Fecha', 'Lote', 'Genética', 'Peso Fresco (g)', 'Peso Seco (g)', 'Merma (%)', 'Duración del Ciclo (días)', 'Ubicación', 'Observaciones', 'Cantidad de cosechas', 'Total fresco (g)', 'Total seco (g)'), ';');

    $res = $conexion->query("SELECT c.id, c.codigo_cosecha, c.fecha_cosecha, lc.codigo_lote, v.nombre AS variedad, c.peso_fresco_gramos, c.peso_seco_gramos, c.porcentaje_merma, CASE WHEN lc.fecha_inicio IS NULL OR lc.fecha_inicio = '0000-00-00' THEN NULL ELSE DATEDIFF(c.fecha_cosecha, lc.fecha_inicio) END AS dias_ciclo, lc.ubicacion_actual, c.observaciones FROM cosechas c INNER JOIN lotes_cultivo lc ON c.lote_id = lc.id INNER JOIN variedades v ON lc.variedad_id = v.id ORDER BY c.fecha_cosecha DESC, c.id DESC");
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $cantidad_cosechas = 0;
    $peso_fresco_total = 0.0;
    $peso_seco_total = 0.0;
    while ($row = $res->fetch_assoc()) {
        $cantidad_cosechas++;
        $peso_fresco_total += (float)$row['peso_fresco_gramos'];
        $peso_seco_total += (float)$row['peso_seco_gramos'];
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $row['id'], $row['codigo_cosecha'],
            $row['fecha_cosecha'], $row['codigo_lote'], $row['variedad'], $row['peso_fresco_gramos'],
            $row['peso_seco_gramos'], $row['porcentaje_merma'], $row['dias_ciclo'], $row['ubicacion_actual'],
            $row['observaciones'], '', '', '',
        ]);
    }
    escribir_fila_csv_segura($output, ['TOTAL', $fecha_exportacion, $operador_exportacion, '', '', '', '', '', '', '', '', '', '', '', $cantidad_cosechas, number_format($peso_fresco_total, 2, '.', ''), number_format($peso_seco_total, 2, '.', '')]);
    fclose($output);
    exit();
}

if ($tipo == 'rendimiento_genetica') {
    $fecha_desde = (new DateTimeImmutable('first day of this month'))->modify('-11 months')->format('Y-m-d');
    $filename = 'rendimiento_genetica_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Genética', 'Lotes cosechados', 'Plantas iniciales', 'Cosechas', 'Peso fresco total (g)', 'Peso seco total (g)', 'Merma calculada (%)', 'Peso seco por planta inicial (g)', 'Duración media del ciclo (días)', 'Período', 'Fecha de exportación', 'Operador exportador'), ';');
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    foreach (obtener_rendimiento_por_genetica($conexion, $fecha_desde) as $fila) {
        escribir_fila_csv_segura($output, [
            $fila['variedad'], $fila['lotes'], $fila['plantas_iniciales'], $fila['cantidad_cosechas'],
            $fila['peso_fresco_gramos'], $fila['peso_seco_gramos'], $fila['merma_porcentaje'],
            $fila['seco_por_planta'], $fila['dias_ciclo_promedio'], $fecha_desde . ' a ' . date('Y-m-d'),
            $fecha_exportacion, $operador_exportacion,
        ]);
    }
    fclose($output);
    exit();
}

if ($tipo == 'actividad_madres_clones') {
    $fecha_desde = (new DateTimeImmutable('first day of this month'))->modify('-11 months')->format('Y-m-d');
    $filename = 'actividad_madres_clones_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Planta madre', 'Genética', 'Estado madre', 'Extracciones', 'Esquejes extraídos', 'Clones seguidos', 'Pendientes', 'Enraizados (incluye trasplantados)', 'Porcentaje enraizado sobre clones seguidos (%)', 'Trasplantados', 'Descartados', 'Días medios hasta el enraizado', 'Fecha última extracción', 'Fecha último corte', 'Período', 'Fecha de exportación', 'Operador exportador'), ';');
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    foreach (obtener_actividad_madres_clones($conexion, $fecha_desde) as $fila) {
        escribir_fila_csv_segura($output, [
            $fila['codigo_planta'], $fila['variedad'], $fila['estado_madre'], $fila['extracciones'],
            $fila['esquejes_extraidos'], $fila['total_clones'], $fila['pendientes'], $fila['enraizados'], $fila['porcentaje_enraizados'],
            $fila['trasplantados'], $fila['descartados'], $fila['dias_enraizado_promedio'],
            $fila['ultima_extraccion'], $fila['ultimo_corte'], $fecha_desde . ' a ' . date('Y-m-d'),
            $fecha_exportacion, $operador_exportacion,
        ]);
    }
    fclose($output);
    exit();
}

if ($tipo == 'alertas_vencimientos') {
    $filename = 'alertas_vencimientos_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de alerta', 'Referencia', 'Detalle', 'Fecha', 'Días restantes (negativo = vencido)', 'Situación', 'Estado asociado', 'Fecha de exportación', 'Operador exportador'), ';');
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    foreach (obtener_alertas_vencimientos($conexion) as $alerta) {
        escribir_fila_csv_segura($output, [
            $alerta['categoria'], $alerta['referencia'], $alerta['detalle'], $alerta['fecha'],
            $alerta['dias_restantes'], $alerta['situacion'], $alerta['estado'],
            $fecha_exportacion, $operador_exportacion,
        ]);
    }
    fclose($output);
    exit();
}

if ($tipo == 'dispensacion') {
    $filename = "libro_dispensacion_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de fila', 'ID Entrega', 'Socio / Paciente', 'DNI Socio', 'REPROCANN', 'Producto QR', 'Gramos Entregados', 'Fecha Dispensación', 'Observaciones', 'Cantidad de registros', 'Fecha de exportación', 'Operador exportador'), ';');
    
    $res = $conexion->query("SELECT d.id, CONCAT(s.apellido, ', ', s.nombre) AS socio, s.dni, s.reprocann_codigo, idv.codigo_qr, d.gramos_entregados, d.fecha_dispensacion, d.observaciones 
                             FROM dispensacion d 
                             INNER JOIN socios s ON d.socio_id = s.id 
                             INNER JOIN inventario_derivado idv ON d.inventario_derivado_id = idv.id 
                             ORDER BY d.id DESC");
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $cantidad_registros = 0;
    $total_gramos = 0.0;
    while ($row = $res->fetch_assoc()) {
        $cantidad_registros++;
        $total_gramos += (float)$row['gramos_entregados'];
        escribir_fila_csv_segura($output, [
            'DETALLE', $row['id'], $row['socio'], $row['dni'], $row['reprocann_codigo'], $row['codigo_qr'],
            $row['gramos_entregados'], $row['fecha_dispensacion'], $row['observaciones'], '',
            $fecha_exportacion, $operador_exportacion,
        ]);
    }
    escribir_fila_csv_segura($output, [
        'TOTAL', '', '', '', '', '', number_format($total_gramos, 2, '.', ''), '', '', $cantidad_registros,
        $fecha_exportacion, $operador_exportacion,
    ]);
    fclose($output);
    exit();
}

if ($tipo == 'movimientos') {
    $producto_id = filter_var($_GET['producto'] ?? null, FILTER_VALIDATE_INT);
    $producto_id = $producto_id && $producto_id > 0 ? $producto_id : 0;
    $tipo_movimiento = is_scalar($_GET['filtro_tipo'] ?? null) ? trim((string)$_GET['filtro_tipo']) : '';
    $tipos_disponibles = array_column($conexion->query("SELECT DISTINCT tipo_movimiento FROM movimientos_inventario")->fetch_all(MYSQLI_ASSOC), 'tipo_movimiento');
    if ($tipo_movimiento !== '' && !in_array($tipo_movimiento, $tipos_disponibles, true)) $tipo_movimiento = '';
    $validar_fecha_mov = static function (string $valor): ?DateTimeImmutable {
        if ($valor === '') return null;
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return $fecha && $fecha->format('Y-m-d') === $valor ? $fecha : null;
    };
    $desde = $validar_fecha_mov(is_scalar($_GET['desde'] ?? null) ? trim((string)$_GET['desde']) : '');
    $hasta = $validar_fecha_mov(is_scalar($_GET['hasta'] ?? null) ? trim((string)$_GET['hasta']) : '');
    if ($desde && $hasta && $desde > $hasta) { $desde = null; $hasta = null; }
    $condiciones = [];
    $parametros = [];
    $tipos = '';
    if ($producto_id > 0) { $condiciones[] = 'm.inventario_derivado_id = ?'; $tipos .= 'i'; $parametros[] = $producto_id; }
    if ($tipo_movimiento !== '') { $condiciones[] = 'm.tipo_movimiento = ?'; $tipos .= 's'; $parametros[] = $tipo_movimiento; }
    if ($desde) { $condiciones[] = 'm.created_at >= ?'; $tipos .= 's'; $parametros[] = $desde->format('Y-m-d 00:00:00'); }
    if ($hasta) { $condiciones[] = 'm.created_at < ?'; $tipos .= 's'; $parametros[] = $hasta->modify('+1 day')->format('Y-m-d 00:00:00'); }
    $where = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
    $filename = "movimientos_inventario_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de fila', 'Fecha de exportación', 'Operador exportador', 'Filtros aplicados', 'ID Movimiento', 'Fecha', 'Tipo', 'Producto QR', 'Producto', 'Cosecha', 'Lote', 'Genética', 'Disponible Antes (g)', 'Disponible Después (g)', 'Reservado Antes (g)', 'Reservado Después (g)', 'Operador del movimiento', 'ID Dispensación', 'Motivo', 'Registros', 'Cambio neto disponible (g)', 'Cambio neto reservado (g)'), ';');
    $filtros_exportacion = 'producto=' . ($producto_id ?: 'todos') . '; tipo=' . ($tipo_movimiento ?: 'todos') . '; desde=' . ($desde ? $desde->format('Y-m-d') : 'sin límite') . '; hasta=' . ($hasta ? $hasta->format('Y-m-d') : 'sin límite');
    $sql = "SELECT m.id, m.created_at, m.tipo_movimiento, i.codigo_qr, i.tipo_producto, c.codigo_cosecha, lc.codigo_lote, v.nombre AS variedad, m.gramos_disponibles_antes, m.gramos_disponibles_despues, m.gramos_reservados_antes, m.gramos_reservados_despues, COALESCE(o.usuario, 'Sistema / migración') AS operador, m.dispensacion_id, m.motivo FROM movimientos_inventario m INNER JOIN inventario_derivado i ON i.id = m.inventario_derivado_id INNER JOIN cosechas c ON c.id = i.cosecha_id INNER JOIN lotes_cultivo lc ON lc.id = c.lote_id INNER JOIN variedades v ON v.id = lc.variedad_id LEFT JOIN operadores o ON o.id = m.usuario_id" . $where . " ORDER BY m.created_at DESC, m.id DESC";
    $stmt_movimientos = $conexion->prepare($sql);
    if ($tipos !== '') {
        $argumentos = [$tipos];
        foreach ($parametros as $indice => &$valor) $argumentos[] = &$valor;
        call_user_func_array([$stmt_movimientos, 'bind_param'], $argumentos);
    }
    $stmt_movimientos->execute();
    $res = $stmt_movimientos->get_result();
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $cantidad_movimientos = 0;
    $cambio_disponible = 0.0;
    $cambio_reservado = 0.0;
    while ($row = $res->fetch_assoc()) {
        $cantidad_movimientos++;
        $cambio_disponible += (float)$row['gramos_disponibles_despues'] - (float)$row['gramos_disponibles_antes'];
        $cambio_reservado += (float)$row['gramos_reservados_despues'] - (float)$row['gramos_reservados_antes'];
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $filtros_exportacion, $row['id'], $row['created_at'],
            $row['tipo_movimiento'], $row['codigo_qr'], $row['tipo_producto'], $row['codigo_cosecha'], $row['codigo_lote'],
            $row['variedad'], $row['gramos_disponibles_antes'], $row['gramos_disponibles_despues'],
            $row['gramos_reservados_antes'], $row['gramos_reservados_despues'], $row['operador'],
            $row['dispensacion_id'], $row['motivo'], '', '', '',
        ]);
    }
    $stmt_movimientos->close();
    escribir_fila_csv_segura($output, ['TOTAL', $fecha_exportacion, $operador_exportacion, $filtros_exportacion, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', $cantidad_movimientos, number_format($cambio_disponible, 2, '.', ''), number_format($cambio_reservado, 2, '.', '')]);
    fclose($output);
    exit();
}

if ($tipo == 'cuotas') {
    $filename = "cuotas_socios_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de fila', 'Fecha de exportación', 'Operador exportador', 'ID', 'Apellido', 'Nombre', 'DNI', 'Concepto', 'Monto', 'Fecha de Pago', 'Método de Pago', 'Estado', 'Observaciones', 'Cantidad de registros', 'Importe total'), ';');
    $res = $conexion->query("SELECT cs.id, s.apellido, s.nombre, s.dni, cs.concepto, cs.monto, cs.fecha_pago, cs.metodo_pago, cs.estado, cs.observaciones FROM cuotas_socios cs INNER JOIN socios s ON s.id = cs.socio_id ORDER BY cs.fecha_pago DESC, cs.id DESC");
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $totales_estado = [];
    $total_registros = 0;
    $importe_total = 0.0;
    while ($row = $res->fetch_assoc()) {
        $total_registros++;
        $importe_total += (float)$row['monto'];
        $estado = (string)$row['estado'];
        if (!isset($totales_estado[$estado])) $totales_estado[$estado] = ['cantidad' => 0, 'importe' => 0.0];
        $totales_estado[$estado]['cantidad']++;
        $totales_estado[$estado]['importe'] += (float)$row['monto'];
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $row['id'], $row['apellido'], $row['nombre'],
            $row['dni'], $row['concepto'], $row['monto'], $row['fecha_pago'], $row['metodo_pago'], $estado,
            $row['observaciones'], '', '',
        ]);
    }
    foreach ($totales_estado as $estado => $total_estado) {
        escribir_fila_csv_segura($output, ['TOTAL ESTADO', $fecha_exportacion, $operador_exportacion, '', '', '', '', '', '', '', '', $estado, '', $total_estado['cantidad'], number_format($total_estado['importe'], 2, '.', '')]);
    }
    escribir_fila_csv_segura($output, ['TOTAL GENERAL', $fecha_exportacion, $operador_exportacion, '', '', '', '', '', '', '', '', '', '', $total_registros, number_format($importe_total, 2, '.', '')]);
    fclose($output);
    exit();
}

if ($tipo == 'movimientos_banco_semillas') {
    $producto_id = filter_var($_GET['producto'] ?? null, FILTER_VALIDATE_INT);
    $producto_id = $producto_id && $producto_id > 0 ? (int)$producto_id : 0;
    $where = $producto_id > 0 ? ' WHERE m.banco_semillas_id = ?' : '';
    $filename = "movimientos_banco_semillas_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de fila', 'Fecha de exportación', 'Operador exportador', 'Filtro aplicado', 'ID Movimiento', 'Fecha', 'Genética', 'Tipo de material', 'ID material', 'Movimiento', 'Unidades antes', 'Unidades después', 'Cambio unidades', 'Lote de cultivo', 'Operador del movimiento', 'Motivo', 'Cantidad de movimientos', 'Unidades ingresadas', 'Unidades retiradas', 'Cambio neto'), ';');
    $sql = "SELECT m.id,m.created_at,v.nombre AS variedad,b.tipo_material,b.id AS banco_id,m.tipo_movimiento,m.cantidad_antes,m.cantidad_despues,COALESCE(lc.codigo_lote,'') AS codigo_lote,COALESCE(o.usuario,'Sistema / migración') AS operador,m.motivo FROM movimientos_banco_semillas m INNER JOIN banco_semillas b ON b.id=m.banco_semillas_id INNER JOIN variedades v ON v.id=b.variedad_id LEFT JOIN lotes_cultivo lc ON lc.id=m.lote_cultivo_id LEFT JOIN operadores o ON o.id=m.usuario_id" . $where . " ORDER BY m.created_at DESC,m.id DESC";
    $stmt_banco = $conexion->prepare($sql);
    if ($producto_id > 0) $stmt_banco->bind_param('i', $producto_id);
    $stmt_banco->execute();
    $res = $stmt_banco->get_result();
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $filtro_exportacion = 'material=' . ($producto_id > 0 ? $producto_id : 'todos');
    $cantidad_movimientos = 0;
    $unidades_ingresadas = 0;
    $unidades_retiradas = 0;
    while ($row = $res->fetch_assoc()) {
        $cambio = (int)$row['cantidad_despues'] - (int)$row['cantidad_antes'];
        $cantidad_movimientos++;
        if ($cambio > 0) $unidades_ingresadas += $cambio;
        if ($cambio < 0) $unidades_retiradas += abs($cambio);
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $filtro_exportacion, $row['id'], $row['created_at'],
            $row['variedad'], $row['tipo_material'], $row['banco_id'], $row['tipo_movimiento'],
            $row['cantidad_antes'], $row['cantidad_despues'], $cambio, $row['codigo_lote'], $row['operador'], $row['motivo'], '', '', '', '',
        ]);
    }
    $stmt_banco->close();
    escribir_fila_csv_segura($output, ['TOTAL', $fecha_exportacion, $operador_exportacion, $filtro_exportacion, '', '', '', '', '', '', '', '', '', '', '', '', $cantidad_movimientos, $unidades_ingresadas, $unidades_retiradas, $unidades_ingresadas - $unidades_retiradas]);
    fclose($output);
    exit();
}

if ($tipo == 'clones') {
    $filename = 'seguimiento_clones_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de fila', 'Fecha de exportación', 'Operador exportador', 'Código clon', 'Extracción', 'Planta madre', 'Genética', 'Fecha de corte', 'Fecha de enraizado', 'Etapa', 'Ubicación', 'Lote de destino', 'Observaciones', 'Registrado por', 'Actualizado por', 'Alta', 'Última actualización', 'Total clones', 'Cortados', 'Enraizando', 'Enraizados', 'Trasplantados', 'Descartados'), ';');
    $res = $conexion->query("SELECT c.codigo_clon, e.id AS extraccion_id, pm.codigo_planta, v.nombre AS variedad, c.fecha_corte, c.fecha_enraizado, c.estado, c.ubicacion_actual, COALESCE(lc.codigo_lote, '') AS codigo_lote, c.observaciones, COALESCE(oa.usuario, '') AS creado_por, COALESCE(ou.usuario, '') AS actualizado_por, c.created_at, c.updated_at FROM clones_esquejes c INNER JOIN extracciones_madre e ON e.id = c.extraccion_madre_id INNER JOIN plantas_madre pm ON pm.id = e.planta_madre_id INNER JOIN variedades v ON v.id = pm.variedad_id LEFT JOIN lotes_cultivo lc ON lc.id = c.lote_cultivo_id LEFT JOIN operadores oa ON oa.id = c.created_by LEFT JOIN operadores ou ON ou.id = c.updated_by ORDER BY c.fecha_corte DESC, c.id DESC");
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $total_clones = 0;
    $totales_estado = ['Cortado' => 0, 'Enraizando' => 0, 'Enraizado' => 0, 'Trasplantado' => 0, 'Descartado' => 0];
    while ($row = $res->fetch_assoc()) {
        $total_clones++;
        if (isset($totales_estado[$row['estado']])) $totales_estado[$row['estado']]++;
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $row['codigo_clon'], $row['extraccion_id'],
            $row['codigo_planta'], $row['variedad'], $row['fecha_corte'], $row['fecha_enraizado'], $row['estado'],
            $row['ubicacion_actual'], $row['codigo_lote'], $row['observaciones'], $row['creado_por'], $row['actualizado_por'],
            $row['created_at'], $row['updated_at'], '', '', '', '', '', '',
        ]);
    }
    escribir_fila_csv_segura($output, ['TOTAL', $fecha_exportacion, $operador_exportacion, '', '', '', '', '', '', '', '', '', '', '', '', '', '', $total_clones, $totales_estado['Cortado'], $totales_estado['Enraizando'], $totales_estado['Enraizado'], $totales_estado['Trasplantado'], $totales_estado['Descartado']]);
    fclose($output);
    exit();
}

if ($tipo == 'conciliacion_inventario') {
    $filename = 'conciliacion_inventario_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de fila', 'Fecha de exportación', 'Operador exportador', 'ID Producto', 'Código QR', 'Tipo', 'Cosecha', 'Disponible actual (g)', 'Disponible último movimiento (g)', 'Diferencia disponible (g)', 'Reservado actual (g)', 'Reservado último movimiento (g)', 'Diferencia reservado (g)', 'Último movimiento', 'Fecha último movimiento', 'Resultado', 'Productos revisados', 'Conciliados', 'Con diferencia', 'Sin movimientos', 'Diferencia disponible total (g)', 'Diferencia reservada total (g)'), ';');
    $res = $conexion->query("SELECT i.id, i.codigo_qr, i.tipo_producto, c.codigo_cosecha, i.gramos_disponibles AS disponible_actual, COALESCE(ultimo.gramos_disponibles_despues, 0) AS disponible_ultimo_movimiento, i.gramos_disponibles - COALESCE(ultimo.gramos_disponibles_despues, 0) AS diferencia_disponible, COALESCE(i.gramos_reservados, 0) AS reservado_actual, COALESCE(ultimo.gramos_reservados_despues, 0) AS reservado_ultimo_movimiento, COALESCE(i.gramos_reservados, 0) - COALESCE(ultimo.gramos_reservados_despues, 0) AS diferencia_reservado, COALESCE(ultimo.tipo_movimiento, '') AS ultimo_movimiento, ultimo.created_at AS fecha_ultimo_movimiento, CASE WHEN ultimo.id IS NULL THEN 'SIN_MOVIMIENTOS' WHEN i.gramos_disponibles <> ultimo.gramos_disponibles_despues OR COALESCE(i.gramos_reservados, 0) <> ultimo.gramos_reservados_despues THEN 'DIFERENCIA' ELSE 'CONCILIADO' END AS resultado FROM inventario_derivado i INNER JOIN cosechas c ON c.id = i.cosecha_id LEFT JOIN (SELECT m.* FROM movimientos_inventario m INNER JOIN (SELECT inventario_derivado_id, MAX(id) AS ultimo_id FROM movimientos_inventario GROUP BY inventario_derivado_id) ultimos ON ultimos.ultimo_id = m.id) ultimo ON ultimo.inventario_derivado_id = i.id ORDER BY resultado DESC, i.id DESC");
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $totales = ['productos' => 0, 'conciliados' => 0, 'diferencia' => 0, 'sin_movimientos' => 0, 'disponible' => 0.0, 'reservado' => 0.0];
    while ($row = $res->fetch_assoc()) {
        $totales['productos']++;
        if ($row['resultado'] === 'CONCILIADO') $totales['conciliados']++;
        elseif ($row['resultado'] === 'DIFERENCIA') {
            $totales['diferencia']++;
            $totales['disponible'] += (float)$row['diferencia_disponible'];
            $totales['reservado'] += (float)$row['diferencia_reservado'];
        } else $totales['sin_movimientos']++;
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $row['id'], $row['codigo_qr'], $row['tipo_producto'],
            $row['codigo_cosecha'], $row['disponible_actual'], $row['disponible_ultimo_movimiento'],
            $row['diferencia_disponible'], $row['reservado_actual'], $row['reservado_ultimo_movimiento'],
            $row['diferencia_reservado'], $row['ultimo_movimiento'], $row['fecha_ultimo_movimiento'], $row['resultado'],
            '', '', '', '', '', '',
        ]);
    }
    escribir_fila_csv_segura($output, ['TOTAL', $fecha_exportacion, $operador_exportacion, '', '', '', '', '', '', '', '', '', '', '', '', '', $totales['productos'], $totales['conciliados'], $totales['diferencia'], $totales['sin_movimientos'], number_format($totales['disponible'], 2, '.', ''), number_format($totales['reservado'], 2, '.', '')]);
    fclose($output);
    exit();
}

if ($tipo == 'dispensacion_limites') {
    $periodo = is_scalar($_GET['periodo'] ?? null) ? trim((string)$_GET['periodo']) : date('Y-m');
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) $periodo = date('Y-m');
    $inicio = $periodo . '-01';
    $fecha_inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $inicio);
    if (!$fecha_inicio || $fecha_inicio->format('Y-m-d') !== $inicio) {
        $periodo = date('Y-m');
        $inicio = $periodo . '-01';
        $fecha_inicio = new DateTimeImmutable($inicio);
    }
    $fin = $fecha_inicio->modify('+1 month')->format('Y-m-d');
    $filename = 'dispensaciones_limites_' . $periodo . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de fila', 'Fecha de exportación', 'Operador exportador', 'ID Socio', 'Apellido', 'Nombre', 'DNI', 'Período', 'Límite mensual configurado (g)', 'Entregado en período (g)', 'Saldo (g)', 'Membresía actual', 'Vencimiento REPROCANN', 'Entregas posteriores al vencimiento', 'Revisión', 'Socios incluidos', 'Suma de límites (g)', 'Total entregado (g)', 'Saldo agregado (g)', 'Entregas fuera de vigencia'), ';');
    $stmt = $conexion->prepare("SELECT s.id, s.apellido, s.nombre, s.dni, s.limite_gramos_mensual, s.estado_membresia, s.reprocann_vencimiento, COALESCE(SUM(d.gramos_entregados), 0) AS gramos_entregados, s.limite_gramos_mensual - COALESCE(SUM(d.gramos_entregados), 0) AS saldo, COALESCE(SUM(CASE WHEN d.id IS NOT NULL AND d.fecha_dispensacion > s.reprocann_vencimiento THEN 1 ELSE 0 END), 0) AS entregas_fuera_vigencia, CASE WHEN COALESCE(SUM(d.gramos_entregados), 0) > s.limite_gramos_mensual THEN 'SUPERA LÍMITE' WHEN COALESCE(SUM(CASE WHEN d.id IS NOT NULL AND d.fecha_dispensacion > s.reprocann_vencimiento THEN 1 ELSE 0 END), 0) > 0 THEN 'ENTREGAS FUERA DE VIGENCIA' WHEN s.estado_membresia <> 'Activo' THEN CONCAT('MEMBRESÍA: ', s.estado_membresia) WHEN s.reprocann_vencimiento < CURRENT_DATE() THEN 'REPROCANN VENCIDO ACTUALMENTE' ELSE 'SIN ALERTAS' END AS revision FROM socios s LEFT JOIN dispensacion d ON d.socio_id = s.id AND d.fecha_dispensacion >= ? AND d.fecha_dispensacion < ? GROUP BY s.id, s.apellido, s.nombre, s.dni, s.limite_gramos_mensual, s.estado_membresia, s.reprocann_vencimiento ORDER BY s.apellido, s.nombre");
    $stmt->bind_param('ss', $inicio, $fin);
    $stmt->execute();
    $res = $stmt->get_result();
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $totales = ['socios' => 0, 'limites' => 0.0, 'entregado' => 0.0, 'saldo' => 0.0, 'fuera_vigencia' => 0];
    while ($row = $res->fetch_assoc()) {
        $alertas = [];
        if ((float)$row['gramos_entregados'] > (float)$row['limite_gramos_mensual']) $alertas[] = 'Supera límite';
        if ((int)$row['entregas_fuera_vigencia'] > 0) $alertas[] = (int)$row['entregas_fuera_vigencia'] . ' entrega(s) posterior(es) al vencimiento';
        if ($row['estado_membresia'] !== 'Activo') $alertas[] = 'Membresía actual: ' . $row['estado_membresia'];
        if ($row['reprocann_vencimiento'] < date('Y-m-d')) $alertas[] = 'REPROCANN vencido actualmente';
        $revision = $alertas ? implode(' · ', $alertas) : 'Sin alertas';
        $totales['socios']++;
        $totales['limites'] += (float)$row['limite_gramos_mensual'];
        $totales['entregado'] += (float)$row['gramos_entregados'];
        $totales['saldo'] += (float)$row['saldo'];
        $totales['fuera_vigencia'] += (int)$row['entregas_fuera_vigencia'];
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $row['id'], $row['apellido'], $row['nombre'], $row['dni'], $periodo,
            $row['limite_gramos_mensual'], $row['gramos_entregados'], $row['saldo'],
            $row['estado_membresia'], $row['reprocann_vencimiento'],
            $row['entregas_fuera_vigencia'], $revision, '', '', '', '', '',
        ]);
    }
    escribir_fila_csv_segura($output, ['TOTAL', $fecha_exportacion, $operador_exportacion, '', '', '', '', $periodo, '', '', '', '', '', '', '', $totales['socios'], number_format($totales['limites'], 2, '.', ''), number_format($totales['entregado'], 2, '.', ''), number_format($totales['saldo'], 2, '.', ''), $totales['fuera_vigencia']]);
    $stmt->close();
    fclose($output);
    exit();
}

if ($tipo == 'cuotas_periodo') {
    $periodo = is_scalar($_GET['periodo'] ?? null) ? trim((string)$_GET['periodo']) : date('Y-m');
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) $periodo = date('Y-m');
    $inicio = $periodo . '-01';
    $fecha_inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $inicio);
    if (!$fecha_inicio || $fecha_inicio->format('Y-m-d') !== $inicio) {
        $periodo = date('Y-m');
        $inicio = $periodo . '-01';
        $fecha_inicio = new DateTimeImmutable($inicio);
    }
    $fin = $fecha_inicio->modify('+1 month')->format('Y-m-d');
    $filename = 'cuotas_socios_' . $periodo . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de fila', 'Fecha de exportación', 'Operador de sesión', 'Período', 'ID Socio', 'Apellido', 'Nombre', 'DNI', 'Fecha de registro', 'Concepto', 'Estado', 'Medio de pago', 'Importe', 'Cantidad de registros del estado', 'Importe total del estado'), ';');
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    escribir_fila_csv_segura($output, ['METADATOS', $fecha_exportacion, $operador_exportacion, $periodo, '', '', '', '', '', '', '', '', '', '', '']);
    $stmt = $conexion->prepare("SELECT ? AS periodo, s.id AS socio_id, s.apellido, s.nombre, s.dni, cs.fecha_pago, cs.concepto, cs.estado, cs.metodo_pago, cs.monto FROM cuotas_socios cs INNER JOIN socios s ON s.id = cs.socio_id WHERE cs.fecha_pago >= ? AND cs.fecha_pago < ? ORDER BY s.apellido, s.nombre, cs.fecha_pago, cs.id");
    $stmt->bind_param('sss', $periodo, $inicio, $fin);
    $stmt->execute();
    $res = $stmt->get_result();
    $totales_estado = [
        'Completado' => ['cantidad' => 0, 'importe' => 0.0],
        'Pagado Pendiente' => ['cantidad' => 0, 'importe' => 0.0],
        'Anulado' => ['cantidad' => 0, 'importe' => 0.0],
    ];
    while ($row = $res->fetch_assoc()) {
        if (isset($totales_estado[$row['estado']])) {
            $totales_estado[$row['estado']]['cantidad']++;
            $totales_estado[$row['estado']]['importe'] += (float)$row['monto'];
        }
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $row['periodo'], $row['socio_id'],
            $row['apellido'], $row['nombre'], $row['dni'], $row['fecha_pago'], $row['concepto'],
            $row['estado'], $row['metodo_pago'], $row['monto'], '', '',
        ]);
    }
    $stmt->close();
    foreach ($totales_estado as $estado => $total_estado) {
        escribir_fila_csv_segura($output, [
            'TOTAL ESTADO', $fecha_exportacion, $operador_exportacion, $periodo, '', '', '', '', '', '',
            $estado, '', '', $total_estado['cantidad'], number_format($total_estado['importe'], 2, '.', ''),
        ]);
    }
    fclose($output);
    exit();
}

if ($tipo == 'control_calidad') {
    $filename = 'controles_calidad_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Tipo de fila', 'Fecha de exportación', 'Operador exportador', 'ID Control', 'Cosecha', 'Producto QR', 'Tipo de control', 'Fecha', 'Resultado', 'Valor', 'Unidad', 'Observaciones', 'Operador del control', 'Actualizado por', 'Creado el', 'Actualizado el', 'Cantidad de controles'), ';');
    $res = $conexion->query("SELECT q.id,c.codigo_cosecha,COALESCE(i.codigo_qr,'Cosecha completa') AS producto,q.tipo_control,q.fecha_control,q.resultado,q.valor,q.unidad,q.observaciones,o.usuario AS operador,COALESCE(u.usuario,'') AS actualizado_por,q.created_at,q.updated_at FROM controles_calidad q INNER JOIN cosechas c ON c.id=q.cosecha_id LEFT JOIN inventario_derivado i ON i.id=q.inventario_derivado_id INNER JOIN operadores o ON o.id=q.operador_id LEFT JOIN operadores u ON u.id=q.updated_by ORDER BY q.fecha_control DESC,q.id DESC");
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $totales_resultado = [];
    $total_controles = 0;
    while ($row = $res->fetch_assoc()) {
        $total_controles++;
        $totales_resultado[$row['resultado']] = ($totales_resultado[$row['resultado']] ?? 0) + 1;
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $row['id'], $row['codigo_cosecha'], $row['producto'],
            $row['tipo_control'], $row['fecha_control'], $row['resultado'], $row['valor'], $row['unidad'],
            $row['observaciones'], $row['operador'], $row['actualizado_por'], $row['created_at'], $row['updated_at'], '',
        ]);
    }
    foreach ($totales_resultado as $resultado => $cantidad_resultado) {
        escribir_fila_csv_segura($output, ['TOTAL RESULTADO', $fecha_exportacion, $operador_exportacion, '', '', '', '', '', $resultado, '', '', '', '', '', '', '', $cantidad_resultado]);
    }
    escribir_fila_csv_segura($output, ['TOTAL GENERAL', $fecha_exportacion, $operador_exportacion, '', '', '', '', '', '', '', '', '', '', '', '', '', $total_controles]);
    fclose($output);
    exit();
}

if ($tipo == 'bitacora') {
    $modulo = is_scalar($_GET['modulo'] ?? null) ? trim((string)$_GET['modulo']) : '';
    $modulos = ['operadores','tareas','calidad','inventario','dispensacion','cuotas','lotes','cosechas','banco_semillas','socios','variedades','plantas_madre','clones'];
    if (!in_array($modulo, $modulos, true)) $modulo = '';
    $desde = is_scalar($_GET['desde'] ?? null) ? trim((string)$_GET['desde']) : '';
    $hasta = is_scalar($_GET['hasta'] ?? null) ? trim((string)$_GET['hasta']) : '';
    foreach (['desde' => &$desde, 'hasta' => &$hasta] as &$fecha) {
        if ($fecha !== '') {
            $fecha_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            if (!$fecha_obj || $fecha_obj->format('Y-m-d') !== $fecha) $fecha = '';
        }
    }
    unset($fecha);
    $condiciones = [];
    $parametros = [];
    $tipos = '';
    if ($modulo !== '') { $condiciones[] = 'b.modulo=?'; $parametros[] = $modulo; $tipos .= 's'; }
    if ($desde !== '') { $condiciones[] = 'b.created_at>=?'; $parametros[] = $desde . ' 00:00:00'; $tipos .= 's'; }
    if ($hasta !== '') { $fecha_fin = DateTimeImmutable::createFromFormat('!Y-m-d', $hasta)->modify('+1 day')->format('Y-m-d 00:00:00'); $condiciones[] = 'b.created_at<?'; $parametros[] = $fecha_fin; $tipos .= 's'; }
    $where = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
    $filename = 'bitacora_cambios_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['Tipo de fila','Fecha de exportación','Operador exportador','Filtros aplicados','ID','Fecha del cambio','Operador del cambio','Módulo','Acción','Entidad','ID entidad','Valores anteriores','Valores nuevos','Cantidad de registros'], ';');
    $stmt = $conexion->prepare('SELECT b.id,b.created_at,COALESCE(o.usuario,\'Operador eliminado\') AS usuario,b.modulo,b.accion,b.entidad,b.entidad_id,b.valores_anteriores,b.valores_nuevos FROM bitacora_cambios b LEFT JOIN operadores o ON o.id=b.operador_id' . $where . ' ORDER BY b.id DESC');
    if ($parametros) $stmt->bind_param($tipos, ...$parametros);
    $stmt->execute();
    $res = $stmt->get_result();
    $fecha_exportacion = date('Y-m-d H:i:s P');
    $operador_exportacion = (string)($_SESSION['usuario'] ?? '');
    $filtros_exportacion = 'módulo=' . ($modulo !== '' ? $modulo : 'todos') . '; desde=' . ($desde !== '' ? $desde : 'sin límite') . '; hasta=' . ($hasta !== '' ? $hasta : 'sin límite');
    $totales_modulo = [];
    $total_registros = 0;
    while ($row = $res->fetch_assoc()) {
        $total_registros++;
        $totales_modulo[$row['modulo']] = ($totales_modulo[$row['modulo']] ?? 0) + 1;
        escribir_fila_csv_segura($output, [
            'DETALLE', $fecha_exportacion, $operador_exportacion, $filtros_exportacion, $row['id'], $row['created_at'],
            $row['usuario'], $row['modulo'], $row['accion'], $row['entidad'], $row['entidad_id'],
            $row['valores_anteriores'], $row['valores_nuevos'], '',
        ]);
    }
    $stmt->close();
    foreach ($totales_modulo as $modulo_resumen => $cantidad_modulo) {
        escribir_fila_csv_segura($output, ['TOTAL MÓDULO', $fecha_exportacion, $operador_exportacion, $filtros_exportacion, '', '', '', $modulo_resumen, '', '', '', '', '', $cantidad_modulo]);
    }
    escribir_fila_csv_segura($output, ['TOTAL GENERAL', $fecha_exportacion, $operador_exportacion, $filtros_exportacion, '', '', '', '', '', '', '', '', '', $total_registros]);
    fclose($output);
    exit();
}

header("Location: reportes.php");
exit();
?>
