<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once './conexion.php';
$conexion = conexion();

$productos = $conexion->query("SELECT b.id, b.tipo_material, v.nombre AS variedad FROM banco_semillas b INNER JOIN variedades v ON v.id=b.variedad_id ORDER BY v.nombre,b.id")->fetch_all(MYSQLI_ASSOC);
$producto_id = filter_var($_GET['producto'] ?? null, FILTER_VALIDATE_INT);
$producto_id = $producto_id && $producto_id > 0 ? (int)$producto_id : 0;
$condicion = $producto_id > 0 ? ' WHERE m.banco_semillas_id=?' : '';
$stmt_total = $conexion->prepare('SELECT COUNT(*) AS total FROM movimientos_banco_semillas m' . $condicion);
if ($producto_id > 0) $stmt_total->bind_param('i', $producto_id);
$stmt_total->execute();
$total = (int)$stmt_total->get_result()->fetch_assoc()['total'];
$stmt_total->close();
$por_pagina = 30;
$total_paginas = max(1, (int)ceil($total / $por_pagina));
$pagina = filter_var($_GET['page'] ?? null, FILTER_VALIDATE_INT);
$pagina = min($total_paginas, max(1, $pagina ?: 1));
$offset = ($pagina - 1) * $por_pagina;
$sql = 'SELECT m.id,m.created_at,m.tipo_movimiento,m.cantidad_antes,m.cantidad_despues,m.motivo,b.id AS banco_id,b.tipo_material,v.nombre AS variedad,b.cantidad_disponible,lc.codigo_lote,o.usuario FROM movimientos_banco_semillas m INNER JOIN banco_semillas b ON b.id=m.banco_semillas_id INNER JOIN variedades v ON v.id=b.variedad_id LEFT JOIN lotes_cultivo lc ON lc.id=m.lote_cultivo_id LEFT JOIN operadores o ON o.id=m.usuario_id' . $condicion . ' ORDER BY m.created_at DESC,m.id DESC LIMIT ? OFFSET ?';
$stmt = $conexion->prepare($sql);
if ($producto_id > 0) $stmt->bind_param('iii', $producto_id, $por_pagina, $offset);
else $stmt->bind_param('ii', $por_pagina, $offset);
$stmt->execute();
$movimientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$url_exportacion = 'exportar_excel.php?' . http_build_query(['tipo' => 'movimientos_banco_semillas'] + ($producto_id > 0 ? ['producto' => $producto_id] : []));
?>
<!DOCTYPE html>
<html lang="es">
<head><?php include 'head.php'; ?></head>
<body class="ui-bg-2">
    <?php include 'loader.php'; ?>
    <?php include 'index_nav.php'; ?>
    <nav class="navbar expand navbar-dark" aria-label="Navegación de movimientos del banco">
        <div class="container-lg px-3">
            <a class="navbar-brand" href="./banco_de_semillas.php"><div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver al banco</div></a>
            <span class="navbar-brand fs-14 text-admin-3 ui-f-700"><i class="fa-light fa-clock-rotate-left me-1"></i>Movimientos del banco de semillas</span>
        </div>
    </nav>
    <main class="container-lg py-4">
        <form method="get" action="movimientos_banco_semillas.php" class="card p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-8">
                    <label for="producto" class="form-label">Material</label>
                    <select class="form-select" name="producto" id="producto">
                        <option value="">Todos los materiales</option>
                        <?php foreach ($productos as $producto): ?>
                            <option value="<?php echo (int)$producto['id']; ?>" <?php echo $producto_id === (int)$producto['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($producto['variedad'] . ' · ' . $producto['tipo_material'] . ' (#' . $producto['id'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button class="btn btn-dark flex-grow-1" type="submit"><i class="fa-light fa-filter me-1"></i>Filtrar</button>
                    <a class="btn btn-outline-secondary" href="movimientos_banco_semillas.php" title="Quitar filtro"><i class="fa-light fa-rotate-left"></i></a>
                </div>
            </div>
        </form>
        <div class="d-flex justify-content-end mb-3"><a class="btn btn-sm btn-dark" href="<?php echo htmlspecialchars($url_exportacion, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-light fa-file-excel me-1"></i>Exportar movimientos</a></div>
        <div class="table-responsive card p-2">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Material</th><th>Movimiento</th><th>Unidades (antes → después)</th><th>Lote de cultivo</th><th>Operador</th><th>Motivo</th></tr></thead>
                <tbody>
                <?php if (!$movimientos): ?>
                    <tr><td colspan="7" class="text-center py-4">No hay movimientos para este filtro.</td></tr>
                <?php else: foreach ($movimientos as $movimiento): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($movimiento['created_at']))); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['variedad'] . ' · ' . $movimiento['tipo_material'] . ' (#' . $movimiento['banco_id'] . ')'); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['tipo_movimiento']); ?></td>
                        <td><?php echo number_format((int)$movimiento['cantidad_antes'], 0, ',', '.'); ?> → <?php echo number_format((int)$movimiento['cantidad_despues'], 0, ',', '.'); ?></td>
                        <td><?php echo $movimiento['codigo_lote'] ? htmlspecialchars($movimiento['codigo_lote']) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($movimiento['usuario'] ?? 'Sistema / migración'); ?></td>
                        <td><?php echo htmlspecialchars($movimiento['motivo']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_paginas > 1): ?>
            <nav class="mt-3" aria-label="Paginación">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="?<?php echo http_build_query(array_filter(['producto'=>$producto_id ?: null,'page'=>max(1,$pagina-1)])); ?>">Anterior</a></li>
                    <li class="page-item disabled"><span class="page-link">Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?></span></li>
                    <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>"><a class="page-link" href="?<?php echo http_build_query(array_filter(['producto'=>$producto_id ?: null,'page'=>min($total_paginas,$pagina+1)])); ?>">Siguiente</a></li>
                </ul>
            </nav>
        <?php endif; ?>
    </main>
    <script>
        function ocultarLoader(){document.querySelectorAll('.ui-loader').forEach(function(e){e.style.display='none';});document.querySelectorAll('.ui-loader-bg').forEach(function(e){e.classList.add('sale');});document.body.style.overflow='auto';}
        window.addEventListener('DOMContentLoaded',function(){setTimeout(ocultarLoader,250);});
        window.addEventListener('pageshow',function(e){if(e.persisted)ocultarLoader();});
    </script>
</body>
</html>
