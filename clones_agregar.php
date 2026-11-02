<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once './csrf.php';
require_once './conexion.php';
$conexion = conexion();
$sql = "SELECT e.id, e.created_at, e.cantidad_esquejes_cortados, pm.codigo_planta, v.nombre AS variedad,
               e.cantidad_esquejes_cortados - COALESCE(registrados.total, 0) AS restantes
        FROM extracciones_madre e
        INNER JOIN plantas_madre pm ON pm.id = e.planta_madre_id
        INNER JOIN variedades v ON v.id = pm.variedad_id
        LEFT JOIN (SELECT extraccion_madre_id, COUNT(*) AS total FROM clones_esquejes GROUP BY extraccion_madre_id) registrados
            ON registrados.extraccion_madre_id = e.id
        WHERE e.cantidad_esquejes_cortados > COALESCE(registrados.total, 0)
        ORDER BY e.created_at DESC, e.id DESC";
$extracciones = $conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
$conexion->close();
$fecha_hoy = date('Y-m-d');
?>
<!DOCTYPE html><html lang="es"><head><?php include 'head.php'; ?></head><body class="ui-bg-2">
<?php include 'loader.php'; include 'index_nav.php'; ?>
    <form action="clones_guardar.php" method="post">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark"><div class="container-lg px-3"><a class="navbar-brand" href="clones.php">Volver a clones</a><button class="btn-nav btn btn-dark" type="submit" title="Registrar"><i class="fa-light fa-floppy-disk"></i></button></div></nav>
        <main class="container pb-4"><div class="row justify-content-center"><div class="col-lg-8"><div class="card p-4">
            <h4 class="mb-3">Registrar esquejes individuales</h4>
            <p class="text-muted">Cada esqueje registrado tendrá un código y un estado propios. Solo se puede registrar la cantidad pendiente de una extracción.</p>
            <?php if (!$extracciones): ?><div class="alert alert-info">No hay extracciones con esquejes pendientes de registrar.</div><?php else: ?>
            <div class="mb-3"><label class="form-label" for="extraccion_id">Extracción de origen</label><select class="form-select" id="extraccion_id" name="extraccion_id" required>
                <option value="">Seleccionar extracción...</option><?php foreach ($extracciones as $e): ?><option value="<?= (int)$e['id'] ?>" data-fecha="<?= htmlspecialchars(date('Y-m-d', strtotime($e['created_at'])), ENT_QUOTES, 'UTF-8') ?>" data-restantes="<?= (int)$e['restantes'] ?>"><?= htmlspecialchars($e['codigo_planta'] . ' · ' . $e['variedad'] . ' · ' . date('d/m/Y', strtotime($e['created_at'])) . ' · ' . $e['restantes'] . ' pendientes', ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select></div>
            <div class="mb-3"><label class="form-label" for="cantidad">Cantidad de clones a crear</label><input class="form-control" type="number" id="cantidad" name="cantidad" min="1" max="200" value="1" required><div class="form-text" id="restantes">Selecciona una extracción para ver el saldo.</div></div>
            <div class="mb-3"><label class="form-label" for="fecha_corte">Fecha de corte</label><input class="form-control" type="date" id="fecha_corte" name="fecha_corte" value="<?= htmlspecialchars($fecha_hoy, ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars($fecha_hoy, ENT_QUOTES, 'UTF-8') ?>" required></div>
            <div class="mb-3"><label class="form-label" for="ubicacion_actual">Ubicación inicial</label><input class="form-control" type="text" id="ubicacion_actual" name="ubicacion_actual" maxlength="100"></div>
            <div class="mb-3"><label class="form-label" for="observaciones">Observaciones</label><textarea class="form-control" id="observaciones" name="observaciones" maxlength="255" rows="3"></textarea></div>
            <?php endif; ?>
        </div></div></div></main>
    </form>
    <script>
    const extraccion = document.getElementById('extraccion_id');
    if (extraccion) {
        const cantidad = document.getElementById('cantidad');
        const fecha = document.getElementById('fecha_corte');
        const restantes = document.getElementById('restantes');
        extraccion.addEventListener('change', function () {
            const opcion = this.options[this.selectedIndex];
            const saldo = Number(opcion.dataset.restantes || 0);
            cantidad.max = Math.min(200, saldo);
            if (Number(cantidad.value) > Number(cantidad.max)) cantidad.value = cantidad.max || 1;
            if (opcion.dataset.fecha) fecha.value = opcion.dataset.fecha;
            restantes.textContent = 'Esquejes pendientes de registrar: ' + saldo;
        });
    }
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
