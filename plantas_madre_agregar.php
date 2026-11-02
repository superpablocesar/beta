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

// Obtener variedades para el selector
$sql_variedades = "SELECT id, nombre, tipo FROM variedades ORDER BY nombre ASC";
$result_v = $conexion->query($sql_variedades);
$variedades = $result_v ? $result_v->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>
    
    <form action="plantas_madre_guardar.php" method="POST">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="plantas_madre.php">
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
                                    <label for="codigo_planta" class="form-label fs-14 mb-0"><small>Código de Planta / QR (Único) *</small></label>
                                    <input type="text" class="form-control" id="codigo_planta" name="codigo_planta" maxlength="50" required placeholder="Ej. PM-OGKUSH-01">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="variedad_id" class="form-label fs-14 mb-0"><small>Variedad / Genética *</small></label>
                                    <select class="form-control" id="variedad_id" name="variedad_id" required>
                                        <option value="">Seleccionar variedad...</option>
                                        <?php foreach ($variedades as $v): ?>
                                            <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['nombre']); ?> (<?php echo htmlspecialchars($v['tipo']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="ubicacion_sala" class="form-label fs-14 mb-0"><small>Ubicación en Sala / Sector</small></label>
                                    <input type="text" class="form-control" id="ubicacion_sala" name="ubicacion_sala" maxlength="100" placeholder="Ej. Sala Madres 1">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="estado" class="form-label fs-14 mb-0"><small>Estado *</small></label>
                                    <select class="form-control" id="estado" name="estado" required>
                                        <option value="Activa">Activa</option>
                                        <option value="En Descanso">En Descanso</option>
                                        <option value="Jubilada/Baja">Jubilada / Baja</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="fecha_nacimiento" class="form-label fs-14 mb-0"><small>Fecha de Nacimiento / Esquejado</small></label>
                                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento">
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
