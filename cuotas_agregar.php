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

// Obtener socios activos para el selector
$sql_s = "SELECT id, nombre, apellido, dni FROM socios WHERE estado_membresia = 'Activo' ORDER BY apellido ASC";
$res_s = $conexion->query($sql_s);
$socios = $res_s ? $res_s->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>
    
    <form action="cuotas_guardar.php" method="POST">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="cuotas.php?page=<?php echo isset($_GET['page']) ? (int)$_GET['page'] : 1; ?>">
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
                                    <label for="socio_id" class="form-label fs-14 mb-0"><small>Socio / Paciente *</small></label>
                                    <select class="form-control" id="socio_id" name="socio_id" required>
                                        <option value="">Seleccionar socio...</option>
                                        <?php foreach ($socios as $s): ?>
                                            <option value="<?php echo $s['id']; ?>">
                                                <?php echo htmlspecialchars($s['apellido'] . ', ' . $s['nombre']); ?> (DNI: <?php echo htmlspecialchars($s['dni']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="monto" class="form-label fs-14 mb-0"><small>Monto ($) *</small></label>
                                    <input type="number" step="0.01" class="form-control" id="monto" name="monto" min="0" max="99999999.99" value="0.00" required>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="fecha_pago" class="form-label fs-14 mb-0"><small>Fecha de Pago *</small></label>
                                    <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="concepto" class="form-label fs-14 mb-0"><small>Concepto *</small></label>
                                    <input type="text" class="form-control" id="concepto" name="concepto" maxlength="150" required placeholder="Ej. Cuota Social Octubre 2026 / Fondo Cultivo">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="metodo_pago" class="form-label fs-14 mb-0"><small>Método de Pago *</small></label>
                                    <select class="form-control" id="metodo_pago" name="metodo_pago" required>
                                        <option value="Efectivo">Efectivo</option>
                                        <option value="Transferencia">Transferencia</option>
                                        <option value="MercadoPago">MercadoPago</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="estado" class="form-label fs-14 mb-0"><small>Estado *</small></label>
                                    <select class="form-control" id="estado" name="estado" required>
                                        <option value="Completado">Completado</option>
                                        <option value="Pagado Pendiente">Pagado Pendiente</option>
                                        <option value="Anulado">Anulado</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="observaciones" class="form-label fs-14 mb-0"><small>Observaciones</small></label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3" placeholder="Detalles adicionales del cobro..."></textarea>
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
