<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once './csrf.php';
require_once './conexion.php';
$conexion = conexion();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$pagina = max(1, (int)($_GET['page'] ?? 1));
if (!$id || $id < 1) { $conexion->close(); header('Location: clones.php?page=' . $pagina); exit(); }
$stmt = $conexion->prepare('SELECT c.*, e.id AS extraccion_id, pm.codigo_planta, pm.variedad_id, v.nombre AS variedad FROM clones_esquejes c INNER JOIN extracciones_madre e ON e.id = c.extraccion_madre_id INNER JOIN plantas_madre pm ON pm.id = e.planta_madre_id INNER JOIN variedades v ON v.id = pm.variedad_id WHERE c.id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$clon = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$clon) { $conexion->close(); header('Location: clones.php?page=' . $pagina); exit(); }
$variedad_id = (int)$clon['variedad_id'];
$lote_actual_id = $clon['lote_cultivo_id'] === null ? 0 : (int)$clon['lote_cultivo_id'];
$stmt = $conexion->prepare("SELECT id, codigo_lote, estado_actual FROM lotes_cultivo WHERE variedad_id = ? AND (estado_actual NOT IN ('Cosechado','Descartado') OR id = ?) ORDER BY id DESC");
$stmt->bind_param('ii', $variedad_id, $lote_actual_id);
$stmt->execute();
$lotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conexion->close();
$estados = ['Cortado','Enraizando','Enraizado','Trasplantado','Descartado'];
?>
<!DOCTYPE html><html lang="es"><head><?php include 'head.php'; ?></head><body class="ui-bg-2">
    <?php include 'loader.php'; include 'index_nav.php'; ?>
    <form action="clones_actualizar.php" method="post">
        <?= campo_token_csrf() ?><input type="hidden" name="id" value="<?= (int)$clon['id'] ?>"><input type="hidden" name="page" value="<?= $pagina ?>">
        <nav class="navbar expand navbar-dark"><div class="container-lg px-3"><a class="navbar-brand" href="clones.php?page=<?= $pagina ?>">Volver a clones</a><button class="btn-nav btn btn-dark" type="submit" title="Guardar"><i class="fa-light fa-floppy-disk"></i></button></div></nav>
        <main class="container pb-4"><div class="row justify-content-center"><div class="col-lg-8"><div class="card p-4">
            <h4><?= htmlspecialchars($clon['codigo_clon'], ENT_QUOTES, 'UTF-8') ?></h4>
            <p class="text-muted">Origen: extracción #<?= (int)$clon['extraccion_id'] ?> · Madre <?= htmlspecialchars($clon['codigo_planta'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($clon['variedad'], ENT_QUOTES, 'UTF-8') ?> · Corte <?= htmlspecialchars($clon['fecha_corte'], ENT_QUOTES, 'UTF-8') ?></p>
            <div class="mb-3"><label class="form-label" for="estado">Etapa</label><select class="form-select" id="estado" name="estado" required><?php foreach ($estados as $estado): ?><option value="<?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>" <?= $clon['estado'] === $estado ? 'selected' : '' ?>><?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
            <div class="mb-3"><label class="form-label" for="fecha_enraizado">Fecha de enraizado</label><input class="form-control" type="date" id="fecha_enraizado" name="fecha_enraizado" value="<?= htmlspecialchars((string)$clon['fecha_enraizado'], ENT_QUOTES, 'UTF-8') ?>" max="<?= date('Y-m-d') ?>"><div class="form-text">Obligatoria al marcar el clon como Enraizado o Trasplantado.</div></div>
            <div class="mb-3"><label class="form-label" for="ubicacion_actual">Ubicación actual</label><input class="form-control" type="text" id="ubicacion_actual" name="ubicacion_actual" maxlength="100" value="<?= htmlspecialchars((string)$clon['ubicacion_actual'], ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="mb-3" id="destino-wrap"><label class="form-label" for="lote_cultivo_id">Lote de destino</label><select class="form-select" id="lote_cultivo_id" name="lote_cultivo_id"><option value="">Seleccionar lote...</option><?php foreach ($lotes as $lote): ?><option value="<?= (int)$lote['id'] ?>" <?= $lote_actual_id === (int)$lote['id'] ? 'selected' : '' ?>><?= htmlspecialchars($lote['codigo_lote'] . ' · ' . $lote['estado_actual'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
            <div class="mb-3"><label class="form-label" for="observaciones">Observaciones</label><textarea class="form-control" id="observaciones" name="observaciones" maxlength="255" rows="3"><?= htmlspecialchars((string)$clon['observaciones'], ENT_QUOTES, 'UTF-8') ?></textarea></div>
        </div></div></div></main>
    </form>
    <script>
        const estadoClon = document.getElementById('estado');
        const destino = document.getElementById('lote_cultivo_id');
        function actualizarDestino() { const mostrar = estadoClon.value === 'Trasplantado'; document.getElementById('destino-wrap').hidden = !mostrar; destino.required = mostrar; }
        estadoClon.addEventListener('change', actualizarDestino); actualizarDestino();
    </script>
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
