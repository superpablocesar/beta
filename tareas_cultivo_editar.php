<?php
require_once __DIR__ . '/sesion_segura.php'; iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once './csrf.php'; require_once './conexion.php'; require_once './alertify_error.php';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT); if (!$id || $id < 1) redirigir_error_alertify('El identificador de la tarea no es válido.');
$conexion = conexion(); $stmt = $conexion->prepare('SELECT * FROM tareas_cultivo WHERE id=?'); $stmt->bind_param('i',$id); $stmt->execute(); $tarea = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$tarea) { $conexion->close(); redirigir_error_alertify('La tarea solicitada no existe.',404); }
$stmt = $conexion->prepare("SELECT lc.id,lc.codigo_lote,v.nombre AS variedad,lc.estado_actual FROM lotes_cultivo lc INNER JOIN variedades v ON v.id=lc.variedad_id WHERE lc.estado_actual NOT IN ('Cosechado','Descartado') OR lc.id=? ORDER BY lc.fecha_inicio DESC"); $stmt->bind_param('i',$tarea['lote_id']); $stmt->execute(); $lotes=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
$operadores=$conexion->query('SELECT id,usuario FROM operadores ORDER BY usuario')->fetch_all(MYSQLI_ASSOC); $conexion->close();
$categorias=['Riego','Nutrición','Poda','Trasplante','Control sanitario','Seguimiento','Cosecha','Otro']; $estados=['Pendiente','En curso','Completada','Cancelada']; $prioridades=['Baja','Media','Alta'];
?>
<!DOCTYPE html><html lang="es"><head><?php include 'head.php'; ?></head><body class="ui-bg-2"><?php include 'loader.php'; include 'index_nav.php'; ?>
<form action="tareas_cultivo_actualizar.php" method="post"><?= campo_token_csrf() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><nav class="navbar expand navbar-dark"><div class="container-lg px-3"><a class="navbar-brand" href="tareas_cultivo.php">Volver a agenda</a><button class="btn-nav btn btn-dark" type="submit" title="Guardar cambios"><i class="fa-light fa-floppy-disk"></i></button></div></nav>
<main class="container pb-4"><div class="row justify-content-center"><div class="col-lg-8"><div class="card p-4"><h4 class="mb-3">Editar tarea</h4>
<div class="mb-3"><label class="form-label" for="lote_id">Lote de cultivo</label><select class="form-select" name="lote_id" id="lote_id" required><?php foreach($lotes as $lote): ?><option value="<?= (int)$lote['id'] ?>" <?= (int)$tarea['lote_id']===(int)$lote['id']?'selected':'' ?>><?= htmlspecialchars($lote['codigo_lote'].' · '.$lote['variedad'].' · '.$lote['estado_actual'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div>
<div class="mb-3"><label class="form-label" for="titulo">Tarea</label><input class="form-control" name="titulo" id="titulo" maxlength="120" value="<?= htmlspecialchars($tarea['titulo'],ENT_QUOTES,'UTF-8') ?>" required></div><div class="mb-3"><label class="form-label" for="descripcion">Detalle</label><textarea class="form-control" name="descripcion" id="descripcion" maxlength="500" rows="3"><?= htmlspecialchars((string)$tarea['descripcion'],ENT_QUOTES,'UTF-8') ?></textarea></div>
<div class="row"><div class="col-md-6 mb-3"><label class="form-label" for="categoria">Categoría</label><select class="form-select" name="categoria" id="categoria"><?php foreach($categorias as $op): ?><option <?= $tarea['categoria']===$op?'selected':'' ?>><?= htmlspecialchars($op,ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div><div class="col-md-6 mb-3"><label class="form-label" for="fecha_programada">Fecha programada</label><input type="date" class="form-control" name="fecha_programada" id="fecha_programada" value="<?= htmlspecialchars($tarea['fecha_programada'],ENT_QUOTES,'UTF-8') ?>" required></div></div>
<div class="row"><div class="col-md-4 mb-3"><label class="form-label" for="prioridad">Prioridad</label><select class="form-select" name="prioridad" id="prioridad"><?php foreach($prioridades as $op): ?><option <?= $tarea['prioridad']===$op?'selected':'' ?>><?= $op ?></option><?php endforeach; ?></select></div><div class="col-md-4 mb-3"><label class="form-label" for="responsable_id">Responsable</label><select class="form-select" name="responsable_id" id="responsable_id"><option value="">Sin asignar</option><?php foreach($operadores as $op): ?><option value="<?= (int)$op['id'] ?>" <?= (int)$tarea['responsable_id']===(int)$op['id']?'selected':'' ?>><?= htmlspecialchars($op['usuario'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div><div class="col-md-4 mb-3"><label class="form-label" for="estado">Estado</label><select class="form-select" name="estado" id="estado"><?php foreach($estados as $op): ?><option <?= $tarea['estado']===$op?'selected':'' ?>><?= htmlspecialchars($op,ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div></div><small class="text-muted">Al marcar la tarea como completada se registra la fecha y hora automáticamente.</small></div></div></div></main></form>
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
</body></html>
