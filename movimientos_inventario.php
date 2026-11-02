<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) {
    header('Location: ./login.php');
    exit();
}

require_once './conexion.php';
$conexion = conexion();

$productos = $conexion->query("SELECT DISTINCT i.id, i.codigo_qr, i.tipo_producto FROM movimientos_inventario m INNER JOIN inventario_derivado i ON i.id = m.inventario_derivado_id ORDER BY i.codigo_qr")->fetch_all(MYSQLI_ASSOC);
$tipos_resultado = $conexion->query("SELECT DISTINCT tipo_movimiento FROM movimientos_inventario ORDER BY tipo_movimiento");
$tipos_movimiento = array_column($tipos_resultado->fetch_all(MYSQLI_ASSOC), 'tipo_movimiento');

$producto_id = filter_var($_GET['producto'] ?? null, FILTER_VALIDATE_INT);
$producto_id = $producto_id && $producto_id > 0 ? $producto_id : 0;
$tipo_movimiento = is_scalar($_GET['tipo'] ?? null) ? trim((string)$_GET['tipo']) : '';
$desde_raw = is_scalar($_GET['desde'] ?? null) ? trim((string)$_GET['desde']) : '';
$hasta_raw = is_scalar($_GET['hasta'] ?? null) ? trim((string)$_GET['hasta']) : '';
$validar_fecha = static function (string $valor): ?DateTimeImmutable {
    if ($valor === '') return null;
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return $fecha && $fecha->format('Y-m-d') === $valor ? $fecha : null;
};
$desde = $validar_fecha($desde_raw);
$hasta = $validar_fecha($hasta_raw);
if ($tipo_movimiento !== '' && !in_array($tipo_movimiento, $tipos_movimiento, true)) {
    $tipo_movimiento = '';
}
if ($desde && $hasta && $desde > $hasta) {
    $desde = null;
    $hasta = null;
}

$condiciones = [];
$tipos = '';
$parametros = [];
if ($producto_id > 0) {
    $condiciones[] = 'm.inventario_derivado_id = ?';
    $tipos .= 'i';
    $parametros[] = $producto_id;
}
if ($tipo_movimiento !== '') {
    $condiciones[] = 'm.tipo_movimiento = ?';
    $tipos .= 's';
    $parametros[] = $tipo_movimiento;
}
if ($desde) {
    $condiciones[] = 'm.created_at >= ?';
    $tipos .= 's';
    $parametros[] = $desde->format('Y-m-d 00:00:00');
}
if ($hasta) {
    $condiciones[] = 'm.created_at < ?';
    $tipos .= 's';
    $parametros[] = $hasta->modify('+1 day')->format('Y-m-d 00:00:00');
}
$where = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
$ejecutar_preparado = static function (mysqli $conexion, string $sql, string $tipos, array $parametros): mysqli_stmt {
    $stmt = $conexion->prepare($sql);
    if ($tipos !== '') {
        $argumentos = [$tipos];
        foreach ($parametros as $indice => &$valor) {
            $argumentos[] = &$valor;
        }
        call_user_func_array([$stmt, 'bind_param'], $argumentos);
    }
    $stmt->execute();
    return $stmt;
};

$stmt_total = $ejecutar_preparado($conexion, 'SELECT COUNT(*) AS total FROM movimientos_inventario m' . $where, $tipos, $parametros);
$total_movimientos = (int)$stmt_total->get_result()->fetch_assoc()['total'];
$stmt_total->close();
$por_pagina = 30;
$total_paginas = max(1, (int)ceil($total_movimientos / $por_pagina));
$pagina = filter_var($_GET['page'] ?? null, FILTER_VALIDATE_INT);
$pagina = min($total_paginas, max(1, $pagina ?: 1));
$offset = ($pagina - 1) * $por_pagina;
$parametros_lista = $parametros;
$parametros_lista[] = $por_pagina;
$parametros_lista[] = $offset;
$stmt_movimientos = $ejecutar_preparado(
    $conexion,
    "SELECT m.id, m.created_at, m.tipo_movimiento, m.gramos_disponibles_antes, m.gramos_disponibles_despues, m.gramos_reservados_antes, m.gramos_reservados_despues, m.motivo, m.dispensacion_id, i.id AS inventario_id, i.codigo_qr, i.tipo_producto, o.usuario FROM movimientos_inventario m INNER JOIN inventario_derivado i ON i.id = m.inventario_derivado_id LEFT JOIN operadores o ON o.id = m.usuario_id{$where} ORDER BY m.created_at DESC, m.id DESC LIMIT ? OFFSET ?",
    $tipos . 'ii',
    $parametros_lista
);
$movimientos = $stmt_movimientos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_movimientos->close();

$parametros_url = [];
if ($producto_id > 0) $parametros_url['producto'] = $producto_id;
if ($tipo_movimiento !== '') $parametros_url['tipo'] = $tipo_movimiento;
if ($desde) $parametros_url['desde'] = $desde->format('Y-m-d');
if ($hasta) $parametros_url['hasta'] = $hasta->format('Y-m-d');
$url_pagina = static function (int $numero) use ($parametros_url): string {
    return 'movimientos_inventario.php?' . http_build_query($parametros_url + ['page' => $numero]);
};
$parametros_exportacion = ['tipo' => 'movimientos'];
if ($producto_id > 0) $parametros_exportacion['producto'] = $producto_id;
if ($tipo_movimiento !== '') $parametros_exportacion['filtro_tipo'] = $tipo_movimiento;
if ($desde) $parametros_exportacion['desde'] = $desde->format('Y-m-d');
if ($hasta) $parametros_exportacion['hasta'] = $hasta->format('Y-m-d');
$url_exportacion = 'exportar_excel.php?' . http_build_query($parametros_exportacion);
?>
<!DOCTYPE html>
<html lang="es">
<head><?php include 'head.php'; ?></head>
<body class="ui-bg-2">
    <?php include 'loader.php'; ?>
    <?php include 'index_nav.php'; ?>
    <nav class="navbar expand navbar-dark" aria-label="Navegación de movimientos">
        <div class="container-lg px-3">
            <a class="navbar-brand" href="./inventario_derivado.php"><div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver al inventario</div></a>
            <span class="navbar-brand fs-14 text-admin-3 ui-f-700"><i class="fa-light fa-clock-rotate-left me-1"></i>Movimientos de inventario</span>
        </div>
    </nav>
    <main class="container-lg py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <p class="text-admin-3 mb-2">Consulta la trazabilidad de entradas, ajustes, dispensaciones y reintegros.</p>
            <div class="d-flex align-items-center gap-2"><span class="badge bg-secondary"><?= number_format($total_movimientos, 0, ',', '.') ?> movimientos</span><a class="btn btn-sm btn-dark" href="<?= htmlspecialchars($url_exportacion, ENT_QUOTES, 'UTF-8') ?>"><i class="fa-light fa-file-excel me-1"></i>Exportar filtros</a></div>
        </div>

        <form method="get" action="movimientos_inventario.php" class="card p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="filtro-producto">Producto</label>
                    <select class="form-select" name="producto" id="filtro-producto">
                        <option value="">Todos los productos</option>
                        <?php foreach ($productos as $producto): ?>
                            <option value="<?= (int)$producto['id'] ?>" <?= $producto_id === (int)$producto['id'] ? 'selected' : '' ?>><?= htmlspecialchars($producto['codigo_qr'] . ' · ' . $producto['tipo_producto'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="filtro-tipo">Tipo de movimiento</label>
                    <select class="form-select" name="tipo" id="filtro-tipo">
                        <option value="">Todos los movimientos</option>
                        <?php foreach ($tipos_movimiento as $tipo): ?>
                            <option value="<?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>" <?= $tipo_movimiento === $tipo ? 'selected' : '' ?>><?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-xl-2">
                    <label class="form-label" for="filtro-desde">Desde</label>
                    <input class="form-control" type="date" name="desde" id="filtro-desde" value="<?= htmlspecialchars($desde ? $desde->format('Y-m-d') : '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-6 col-xl-2">
                    <label class="form-label" for="filtro-hasta">Hasta</label>
                    <input class="form-control" type="date" name="hasta" id="filtro-hasta" value="<?= htmlspecialchars($hasta ? $hasta->format('Y-m-d') : '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12 col-xl-2 d-flex gap-2">
                    <button class="btn btn-dark flex-grow-1" type="submit"><i class="fa-light fa-filter me-1"></i>Filtrar</button>
                    <a class="btn btn-outline-secondary" href="movimientos_inventario.php" title="Quitar filtros"><i class="fa-light fa-rotate-left"></i></a>
                </div>
            </div>
        </form>

        <div class="table-responsive card p-2">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Producto</th><th>Movimiento</th><th>Disponible (antes → después)</th><th>Reservado (antes → después)</th><th>Operador</th><th>Referencia</th><th>Motivo</th></tr></thead>
                <tbody>
                <?php if (!$movimientos): ?>
                    <tr><td colspan="8" class="text-center py-4">No hay movimientos que coincidan con estos filtros.</td></tr>
                <?php else: foreach ($movimientos as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($m['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><a href="inventario_derivado_editar.php?id=<?= (int)$m['inventario_id'] ?>" class="text-decoration-none"><?= htmlspecialchars($m['codigo_qr'] . ' · ' . $m['tipo_producto'], ENT_QUOTES, 'UTF-8') ?></a></td>
                        <td><?= htmlspecialchars($m['tipo_movimiento'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format((float)$m['gramos_disponibles_antes'], 2, ',', '.') ?> → <?= number_format((float)$m['gramos_disponibles_despues'], 2, ',', '.') ?> g</td>
                        <td><?= number_format((float)$m['gramos_reservados_antes'], 2, ',', '.') ?> → <?= number_format((float)$m['gramos_reservados_despues'], 2, ',', '.') ?> g</td>
                        <td><?= htmlspecialchars($m['usuario'] ?? 'Sistema / migración', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $m['dispensacion_id'] ? '#' . (int)$m['dispensacion_id'] : '—' ?></td>
                        <td><?= htmlspecialchars($m['motivo'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
            <nav class="mt-3" aria-label="Paginación de movimientos">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($url_pagina(max(1, $pagina - 1)), ENT_QUOTES, 'UTF-8') ?>">Anterior</a></li>
                    <li class="page-item disabled"><span class="page-link">Página <?= $pagina ?> de <?= $total_paginas ?></span></li>
                    <li class="page-item <?= $pagina >= $total_paginas ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($url_pagina(min($total_paginas, $pagina + 1)), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a></li>
                </ul>
            </nav>
        <?php endif; ?>
    </main>
    <script>
        (function () {
            function ocultarLoader() {
                document.querySelectorAll('.ui-loader').forEach(function (loader) {
                    loader.style.display = 'none';
                });
                document.querySelectorAll('.ui-loader-bg').forEach(function (fondo) {
                    fondo.classList.add('sale');
                });
                document.body.style.overflow = 'auto';
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    window.setTimeout(ocultarLoader, 250);
                }, { once: true });
            } else {
                window.setTimeout(ocultarLoader, 250);
            }
            window.addEventListener('pageshow', function (evento) {
                if (evento.persisted) ocultarLoader();
            });
        })();
    </script>
</body>
</html>
