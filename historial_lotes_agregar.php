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

// Obtener lotes activos
$sql_l = "SELECT lc.id, lc.codigo_lote, lc.estado_actual, v.nombre AS variedad_nombre 
          FROM lotes_cultivo lc 
          INNER JOIN variedades v ON lc.variedad_id = v.id 
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
    
    <form action="historial_lotes_guardar.php" method="POST">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="historial_lotes.php">
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
                                    <label for="lote_id" class="form-label fs-14 mb-0"><small>Lote de Cultivo *</small></label>
                                    <select class="form-control" id="lote_id" name="lote_id" required>
                                        <option value="">Seleccionar lote...</option>
                                        <?php foreach ($lotes as $l): ?>
                                            <option value="<?php echo $l['id']; ?>">
                                                <?php echo htmlspecialchars($l['codigo_lote']); ?> - <?php echo htmlspecialchars($l['variedad_nombre']); ?> (Estado actual: <?php echo htmlspecialchars($l['estado_actual']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="estado_anterior" class="form-label fs-14 mb-0"><small>Estado Anterior *</small></label>
                                    <select class="form-control" id="estado_anterior" name="estado_anterior" required>
                                        <option value="Germinacion">Germinación</option>
                                        <option value="Vegetativo">Vegetativo</option>
                                        <option value="Floracion">Floración</option>
                                        <option value="Cosechado">Cosechado</option>
                                        <option value="Descartado">Descartado</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="estado_nuevo" class="form-label fs-14 mb-0"><small>Estado Nuevo *</small></label>
                                    <select class="form-control" id="estado_nuevo" name="estado_nuevo" required>
                                        <option value="Vegetativo">Vegetativo</option>
                                        <option value="Floracion">Floración</option>
                                        <option value="Cosechado">Cosechado</option>
                                        <option value="Descartado">Descartado</option>
                                        <option value="Germinacion">Germinación</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="cantidad_afectada" class="form-label fs-14 mb-0"><small>Cantidad Afectada / Mermas *</small></label>
                                    <input type="number" class="form-control" id="cantidad_afectada" name="cantidad_afectada" value="0" min="-2147483648" max="2147483647" required>
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="motivo" class="form-label fs-14 mb-0"><small>Motivo del Cambio / Observación</small></label>
                                    <input type="text" class="form-control" id="motivo" name="motivo" maxlength="255" placeholder="Ej. Paso a Floración, Baja por hongo, etc.">
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
