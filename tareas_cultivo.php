<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once './conexion.php';
$conexion = conexion();
$estados = ['Pendiente','En curso','Completada','Cancelada'];
$estado = is_scalar($_GET['estado'] ?? null) ? trim((string)$_GET['estado']) : '';
if (!in_array($estado, $estados, true)) $estado = '';
$periodo = is_scalar($_GET['periodo'] ?? null) ? trim((string)$_GET['periodo']) : '';
if ($periodo !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) $periodo = '';
$where = [];
$params = [];
$types = '';
if ($estado !== '') { $where[] = 't.estado = ?'; $params[] = $estado; $types .= 's'; }
if ($periodo !== '') { $where[] = 't.fecha_programada >= ? AND t.fecha_programada < ?'; $inicio = $periodo . '-01'; $fin = DateTimeImmutable::createFromFormat('!Y-m-d', $inicio)->modify('+1 month')->format('Y-m-d'); $params[] = $inicio; $params[] = $fin; $types .= 'ss'; }
$condicion = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$stmt = $conexion->prepare('SELECT COUNT(*) AS total FROM tareas_cultivo t' . $condicion);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute(); $total = (int)$stmt->get_result()->fetch_assoc()['total']; $stmt->close();
$por_pagina = 20; $pagina = max(1, (int)($_GET['page'] ?? 1)); $paginas = max(1, (int)ceil($total / $por_pagina)); $pagina = min($pagina, $paginas); $offset = ($pagina - 1) * $por_pagina;
$sql = 'SELECT t.id,t.titulo,t.categoria,t.fecha_programada,t.estado,t.prioridad,t.descripcion,lc.codigo_lote,v.nombre AS variedad,COALESCE(o.usuario,\'Sin asignar\') AS responsable FROM tareas_cultivo t INNER JOIN lotes_cultivo lc ON lc.id=t.lote_id INNER JOIN variedades v ON v.id=lc.variedad_id LEFT JOIN operadores o ON o.id=t.responsable_id' . $condicion . ' ORDER BY FIELD(t.estado,\'Pendiente\',\'En curso\',\'Completada\',\'Cancelada\'),t.fecha_programada,t.id DESC LIMIT ? OFFSET ?';
$params[] = $por_pagina; $params[] = $offset; $types .= 'ii'; $stmt = $conexion->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $tareas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); $conexion->close();
?>
<!DOCTYPE html><html lang="es"><head><?php include 'head.php'; ?></head><body class="ui-bg-2">
<?php include 'loader.php'; include 'index_nav.php'; include 'tareas_cultivo_nav.php'; ?>
<main class="container-lg py-3">
    <form method="get" class="card p-3 mb-3"><div class="row g-2 align-items-end"><div class="col-md-5"><label class="form-label" for="estado">Estado</label><select class="form-select" id="estado" name="estado"><option value="">Todos</option><?php foreach ($estados as $opcion): ?><option value="<?= htmlspecialchars($opcion, ENT_QUOTES, 'UTF-8') ?>" <?= $estado === $opcion ? 'selected' : '' ?>><?= htmlspecialchars($opcion, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div><div class="col-md-5"><label class="form-label" for="periodo">Mes</label><input class="form-control" type="month" id="periodo" name="periodo" value="<?= htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8') ?>"></div><div class="col-md-2"><button class="btn btn-outline-dark w-100" type="submit">Filtrar</button></div></div></form>
    <?php if (!$tareas): ?><div class="card p-4 text-center"><h4 class="mb-1">No hay tareas para mostrar</h4><p class="mb-3">Programa seguimientos vinculados a un lote de cultivo.</p><a class="btn btn-dark align-self-center" href="tareas_cultivo_agregar.php">Programar tarea</a></div>
    <?php else: foreach ($tareas as $tarea): $badge = $tarea['estado'] === 'Completada' ? 'bg-success' : ($tarea['estado'] === 'Cancelada' ? 'bg-secondary' : ($tarea['prioridad'] === 'Alta' ? 'bg-danger' : 'bg-warning text-dark')); ?><a class="card ui-card-list text-decoration-none p-3 mb-2" href="tareas_cultivo_editar.php?id=<?= (int)$tarea['id'] ?>"><div class="d-flex justify-content-between gap-3"><div><strong><?= htmlspecialchars($tarea['titulo'], ENT_QUOTES, 'UTF-8') ?></strong> <span class="badge <?= $badge ?>"><?= htmlspecialchars($tarea['estado'], ENT_QUOTES, 'UTF-8') ?></span><div class="small mt-1"><?= htmlspecialchars($tarea['categoria'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($tarea['prioridad'], ENT_QUOTES, 'UTF-8') ?> · <?= date('d/m/Y', strtotime($tarea['fecha_programada'])) ?></div><div class="small">Lote: <b><?= htmlspecialchars($tarea['codigo_lote'], ENT_QUOTES, 'UTF-8') ?></b> · <?= htmlspecialchars($tarea['variedad'], ENT_QUOTES, 'UTF-8') ?> · Responsable: <?= htmlspecialchars($tarea['responsable'], ENT_QUOTES, 'UTF-8') ?></div><?php if ($tarea['descripcion']): ?><div class="small text-muted mt-1"><?= htmlspecialchars($tarea['descripcion'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?></div><i class="fa-light fa-chevron-right fs-4 align-self-center"></i></div></a><?php endforeach; endif; ?>
    <?php if ($paginas > 1): ?><nav aria-label="Paginación"><ul class="pagination justify-content-center"><?php for ($i=1;$i<=$paginas;$i++): ?><li class="page-item <?= $i===$pagina?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?><?= $estado!==''?'&estado='.rawurlencode($estado):'' ?><?= $periodo!==''?'&periodo='.rawurlencode($periodo):'' ?>"><?= $i ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
</main><script>document.body.style.overflow="hidden";$(document).ready(function(){function ocultarLoader(){$(".ui-loader").fadeOut(function(){$(".ui-loader-bg").fadeOut().addClass("sale");document.body.style.overflow="auto";});}setTimeout(ocultarLoader,250);window.addEventListener('pageshow',function(event){if(event.persisted){ocultarLoader();$("a").css("pointer-events","auto");}});});</script></body></html>
