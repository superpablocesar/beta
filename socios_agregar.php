<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION["usuario"])) {
    header("Location: ./login.php");
    exit();
}
require_once './csrf.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>
    
    <form action="socios_guardar.php" method="POST">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="javascript:history.back()">
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
            <div class="row">
                <div class="col-lg-6">
                    <div class="card py-3 px-4">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="nombre" class="form-label fs-14 mb-0"><small>Nombre *</small></label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required placeholder="Nombre del socio">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="apellido" class="form-label fs-14 mb-0"><small>Apellido *</small></label>
                                    <input type="text" class="form-control" id="apellido" name="apellido" required placeholder="Apellido del socio">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="dni" class="form-label fs-14 mb-0"><small>DNI *</small></label>
                                    <input type="text" class="form-control" id="dni" name="dni" required placeholder="Número de documento">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="telefono" class="form-label fs-14 mb-0"><small>Teléfono</small></label>
                                    <input type="text" class="form-control" id="telefono" name="telefono" placeholder="Celular de contacto">
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="email" class="form-label fs-14 mb-0"><small>Correo Electrónico</small></label>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="correo@ejemplo.com">
                                </div>
                            </div>
                        </div>   
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card py-3 px-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="reprocann_codigo" class="form-label fs-14 mb-0"><small>Código de Vinculación REPROCANN *</small></label>
                                    <input type="text" class="form-control" id="reprocann_codigo" name="reprocann_codigo" required placeholder="Código o número de trámite oficial">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="reprocann_vencimiento" class="form-label fs-14 mb-0"><small>Vencimiento Autorización *</small></label>
                                    <input type="date" class="form-control" id="reprocann_vencimiento" name="reprocann_vencimiento" required>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="limite_gramos_mensual" class="form-label fs-14 mb-0"><small>Límite Legal (Gramos/Mes) *</small></label>
                                    <input type="number" step="0.01" class="form-control" id="limite_gramos_mensual" name="limite_gramos_mensual" value="40.00" min="0" required>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="estado_membresia" class="form-label fs-14 mb-0"><small>Estado de Membresía *</small></label>
                                    <select class="form-control" id="estado_membresia" name="estado_membresia" required>
                                        <option value="Activo">Activo</option>
                                        <option value="Pendiente REPROCANN">Pendiente REPROCANN</option>
                                        <option value="Vencido">Vencido</option>
                                        <option value="Suspendido">Suspendido</option>
                                    </select>
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