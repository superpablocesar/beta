<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once './conexion.php';
$conexion = conexion();
$estados = ['Cortado','Enraizando','Enraizado','Trasplantado','Descartado'];
$estado = is_scalar($_GET['estado'] ?? null) ? trim((string)$_GET['estado']) : '';
if (!in_array($estado, $estados, true)) $estado = '';
$pagina = max(1, (int)($_GET['page'] ?? 1));
$por_pagina = 20;
$where = $estado === '' ? '' : ' WHERE c.estado = ?';
$stmt = $conexion->prepare('SELECT COUNT(*) AS total FROM clones_esquejes c' . $where);
if ($estado !== '') $stmt->bind_param('s', $estado);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$paginas = max(1, (int)ceil($total / $por_pagina));
$pagina = min($pagina, $paginas);
$offset = ($pagina - 1) * $por_pagina;
$sql = 'SELECT c.id, c.codigo_clon, c.fecha_corte, c.fecha_enraizado, c.estado, c.ubicacion_actual,
               c.observaciones, e.id AS extraccion_id, pm.codigo_planta, v.nombre AS variedad,
               lc.codigo_lote
        FROM clones_esquejes c
        INNER JOIN extracciones_madre e ON e.id = c.extraccion_madre_id
        INNER JOIN plantas_madre pm ON pm.id = e.planta_madre_id
        INNER JOIN variedades v ON v.id = pm.variedad_id
        LEFT JOIN lotes_cultivo lc ON lc.id = c.lote_cultivo_id' . $where . '
        ORDER BY c.id DESC LIMIT ? OFFSET ?';
$stmt = $conexion->prepare($sql);
if ($estado !== '') $stmt->bind_param('sii', $estado, $por_pagina, $offset);
else $stmt->bind_param('ii', $por_pagina, $offset);
$stmt->execute();
$clones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head><?php include 'head.php'; ?></head>
<body class="ui-bg-2">
    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>

    <?php include 'clones_nav.php';?>

    <main class="container-lg py-3">
        <form method="get" class="card p-3 mb-3"><label for="estado" class="form-label">Filtrar por etapa</label>
            <div class="d-flex gap-2"><select class="form-select" id="estado" name="estado">
                <option value="">Todas</option><?php foreach ($estados as $opcion): ?><option value="<?= htmlspecialchars($opcion, ENT_QUOTES, 'UTF-8') ?>" <?= $estado === $opcion ? 'selected' : '' ?>><?= htmlspecialchars($opcion, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select><button class="btn btn-dark" type="submit">Filtrar</button></div>
        </form>
        <?php if (!$clones): ?><div class="card p-4 text-center"><h4 class="mb-1">No hay clones registrados</h4><p class="mb-3">Registra esquejes a partir de una extracción existente para seguir su evolución individual.</p><a class="btn btn-dark align-self-center" href="clones_agregar.php">Registrar esquejes</a></div>
        <?php else: foreach ($clones as $clon): ?><a class="card ui-card-list text-decoration-none p-3 mb-2" href="clones_editar.php?id=<?= (int)$clon['id'] ?>&page=<?= $pagina ?><?= $estado !== '' ? '&estado=' . rawurlencode($estado) : '' ?>">
            <div class="d-flex justify-content-between gap-3"><div><strong><?= htmlspecialchars($clon['codigo_clon'], ENT_QUOTES, 'UTF-8') ?></strong> <span class="badge bg-secondary"><?= htmlspecialchars($clon['estado'], ENT_QUOTES, 'UTF-8') ?></span>
                <div class="small mt-1">Madre: <b><?= htmlspecialchars($clon['codigo_planta'], ENT_QUOTES, 'UTF-8') ?></b> · Genética: <b><?= htmlspecialchars($clon['variedad'], ENT_QUOTES, 'UTF-8') ?></b></div>
                <div class="small">Corte: <?= htmlspecialchars($clon['fecha_corte'], ENT_QUOTES, 'UTF-8') ?><?= $clon['fecha_enraizado'] ? ' · Enraizado: ' . htmlspecialchars($clon['fecha_enraizado'], ENT_QUOTES, 'UTF-8') : '' ?><?= $clon['codigo_lote'] ? ' · Lote: ' . htmlspecialchars($clon['codigo_lote'], ENT_QUOTES, 'UTF-8') : '' ?></div>
                <?php if ($clon['ubicacion_actual']): ?><div class="small">Ubicación: <?= htmlspecialchars($clon['ubicacion_actual'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            </div><i class="fa-light fa-chevron-right fs-4 align-self-center"></i></div>
        </a><?php endforeach; ?><?php endif; ?>
        <?php if ($paginas > 1): ?><nav aria-label="Paginación"><ul class="pagination justify-content-center"><?php for ($i=1; $i <= $paginas; $i++): ?><li class="page-item <?= $i === $pagina ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?><?= $estado !== '' ? '&estado=' . rawurlencode($estado) : '' ?>"><?= $i ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
    </main>

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
