<?php
require_once __DIR__ . '/sesion_segura.php'; iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once './csrf.php'; require_once './conexion.php';
$conexion = conexion();
$lotes = $conexion->query("SELECT lc.id,lc.codigo_lote,v.nombre AS variedad,lc.estado_actual FROM lotes_cultivo lc INNER JOIN variedades v ON v.id=lc.variedad_id WHERE lc.estado_actual NOT IN ('Cosechado','Descartado') ORDER BY lc.fecha_inicio DESC")->fetch_all(MYSQLI_ASSOC);
$operadores = $conexion->query('SELECT id,usuario FROM operadores ORDER BY usuario')->fetch_all(MYSQLI_ASSOC); $conexion->close();
$categorias = ['Riego','Nutrición','Poda','Trasplante','Control sanitario','Seguimiento','Cosecha','Otro']; $hoy = date('Y-m-d');
?>
<!DOCTYPE html><html lang="es"><head><?php include 'head.php'; ?></head><body class="ui-bg-2"><?php include 'loader.php'; include 'index_nav.php'; ?>
<form action="tareas_cultivo_guardar.php" method="post"><?= campo_token_csrf() ?><nav class="navbar expand navbar-dark"><div class="container-lg px-3"><a class="navbar-brand" href="tareas_cultivo.php">Volver a agenda</a><button class="btn-nav btn btn-dark" type="submit" title="Guardar"><i class="fa-light fa-floppy-disk"></i></button></div></nav>
<main class="container pb-4"><div class="row justify-content-center"><div class="col-lg-8"><div class="card p-4"><h4 class="mb-3">Programar tarea de cultivo</h4>
<?php if (!$lotes): ?><div class="alert alert-info">No hay lotes activos a los que asignar una tarea.</div><?php else: ?>
<div class="mb-3"><label class="form-label" for="lote_id">Lote de cultivo</label><select class="form-select" name="lote_id" id="lote_id" required><option value="">Seleccionar lote...</option><?php foreach($lotes as $lote): ?><option value="<?= (int)$lote['id'] ?>"><?= htmlspecialchars($lote['codigo_lote'].' · '.$lote['variedad'].' · '.$lote['estado_actual'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div>
<div class="mb-3"><label class="form-label" for="titulo">Tarea</label><input class="form-control" name="titulo" id="titulo" maxlength="120" required></div>
<div class="mb-3"><label class="form-label" for="descripcion">Detalle</label><textarea class="form-control" name="descripcion" id="descripcion" maxlength="500" rows="3"></textarea></div>
<div class="row"><div class="col-md-6 mb-3"><label class="form-label" for="categoria">Categoría</label><select class="form-select" name="categoria" id="categoria" required><?php foreach($categorias as $categoria): ?><option value="<?= htmlspecialchars($categoria,ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($categoria,ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div><div class="col-md-6 mb-3"><label class="form-label" for="fecha_programada">Fecha programada</label><input type="date" class="form-control" name="fecha_programada" id="fecha_programada" value="<?= $hoy ?>" required></div></div>
<div class="row"><div class="col-md-4 mb-3"><label class="form-label" for="prioridad">Prioridad</label><select class="form-select" name="prioridad" id="prioridad"><option>Baja</option><option selected>Media</option><option>Alta</option></select></div><div class="col-md-4 mb-3"><label class="form-label" for="responsable_id">Responsable</label><select class="form-select" name="responsable_id" id="responsable_id"><option value="">Sin asignar</option><?php foreach($operadores as $operador): ?><option value="<?= (int)$operador['id'] ?>"><?= htmlspecialchars($operador['usuario'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div><div class="col-md-4 mb-3"><label class="form-label" for="estado">Estado</label><select class="form-select" name="estado" id="estado"><option selected>Pendiente</option><option>En curso</option><option>Completada</option><option>Cancelada</option></select></div></div><?php endif; ?></div></div></div></main></form>
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
