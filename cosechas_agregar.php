<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION["usuario"])) {
    header("Location: ./login.php");
    exit();
}

require_once './csrf.php';
require_once "./conexion.php";
$conexion = conexion();

// Un lote descartado no puede recibir cosechas. Los cosechados admiten registros adicionales.
$sql_l = "SELECT lc.id, lc.codigo_lote, lc.estado_actual, v.nombre AS variedad_nombre 
          FROM lotes_cultivo lc 
          INNER JOIN variedades v ON lc.variedad_id = v.id 
          WHERE lc.estado_actual <> 'Descartado'
          ORDER BY lc.id DESC";
$res_l = $conexion->query($sql_l);
$lotes = $res_l ? $res_l->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>
    
    <form action="cosechas_guardar.php" method="POST">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="cosechas.php">
                    <div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver</div>
                </a>
                <button type="submit" class="btn-nav btn btn-dark ms-1 my-2" id="btn-submit">
                    <span id="btn-text"><i class="fa-solid fa-floppy-disk"></i></span>
                    <i id="btn-spinner" class="fas fa-spinner fa-spin d-none"></i>
                </button>
            </div>
        </nav>

        <div class="container pb-4">
            <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? (int)$_GET['page'] : 1; ?>">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card py-3 px-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="codigo_cosecha" class="form-label fs-14 mb-0"><small>Código de Cosecha / Rótulo (Único) *</small></label>
                                    <input type="text" class="form-control" id="codigo_cosecha" name="codigo_cosecha" maxlength="50" required placeholder="Ej. COS-202610-001">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="lote_id" class="form-label fs-14 mb-0"><small>Lote de Cultivo Origen *</small></label>
                                    <select class="form-control" id="lote_id" name="lote_id" required>
                                        <option value="">Seleccionar lote...</option>
                                        <?php foreach ($lotes as $l): ?>
                                            <option value="<?php echo $l['id']; ?>">
                                                <?php echo htmlspecialchars($l['codigo_lote']); ?> - <?php echo htmlspecialchars($l['variedad_nombre']); ?> (Estado: <?php echo htmlspecialchars($l['estado_actual']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="fecha_cosecha" class="form-label fs-14 mb-0"><small>Fecha de Cosecha *</small></label>
                                    <input type="date" class="form-control" id="fecha_cosecha" name="fecha_cosecha" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="peso_fresco_gramos" class="form-label fs-14 mb-0"><small>Peso Fresco (Gramos) *</small></label>
                                    <input type="number" step="0.01" class="form-control" id="peso_fresco_gramos" name="peso_fresco_gramos" min="0" max="999999.99" value="0.00" required>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="peso_seco_gramos" class="form-label fs-14 mb-0"><small>Peso Seco (Gramos)</small></label>
                                    <input type="number" step="0.01" class="form-control" id="peso_seco_gramos" name="peso_seco_gramos" min="0" max="999999.99" value="0.00">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="observaciones" class="form-label fs-14 mb-0"><small>Observaciones</small></label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3" placeholder="Detalles de secado, condiciones de sala, tricomas, etc."></textarea>
                                </div>
                            </div>
                        </div>   
                    </div>
                </div>  
            </div>
        </div>
    </form>

    <script>
        document.querySelector("form").addEventListener("submit", function (e) {
            const btn = document.getElementById("btn-submit");
            const text = document.getElementById("btn-text");
            const spinner = document.getElementById("btn-spinner");

            btn.disabled = true;
            if(text) text.style.display = "none";
            if(spinner) spinner.classList.remove("d-none");
        });
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
