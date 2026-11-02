<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION["usuario"])) {
    header("Location: ./login.php");
    exit();
}

require_once "./conexion.php";
require_once "./csrf.php";
$conexion = conexion();

// Obtener socios activos
$sql_s = "SELECT id, nombre, apellido, dni, limite_gramos_mensual FROM socios WHERE estado_membresia = 'Activo' AND reprocann_vencimiento >= CURRENT_DATE() ORDER BY apellido ASC";
$res_s = $conexion->query($sql_s);
$socios = $res_s ? $res_s->fetch_all(MYSQLI_ASSOC) : [];

// Obtener inventario derivado disponible
$sql_idv = "SELECT id, codigo_qr, tipo_producto, gramos_disponibles FROM inventario_derivado WHERE gramos_disponibles > 0 AND estado_inventario = 'Disponible' ORDER BY id DESC";
$res_idv = $conexion->query($sql_idv);
$inventario = $res_idv ? $res_idv->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>
    
    <form action="dispensacion_guardar.php" method="POST">
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
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card py-3 px-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="socio_id" class="form-label fs-14 mb-0"><small>Socio / Paciente REPROCANN *</small></label>
                                    <select class="form-control" id="socio_id" name="socio_id" required>
                                        <option value="">Seleccionar socio...</option>
                                        <?php foreach ($socios as $s): ?>
                                            <option value="<?php echo $s['id']; ?>">
                                                <?php echo htmlspecialchars($s['apellido'] . ', ' . $s['nombre']); ?> (DNI: <?php echo htmlspecialchars($s['dni']); ?> - Límite: <?php echo $s['limite_gramos_mensual']; ?>g)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="inventario_derivado_id" class="form-label fs-14 mb-0"><small>Producto del Inventario (Frasco / Lote) *</small></label>
                                    <select class="form-control" id="inventario_derivado_id" name="inventario_derivado_id" required>
                                        <option value="">Seleccionar producto...</option>
                                        <?php foreach ($inventario as $inv): ?>
                                            <option value="<?php echo $inv['id']; ?>">
                                                [<?php echo htmlspecialchars($inv['tipo_producto']); ?>] QR: <?php echo htmlspecialchars($inv['codigo_qr']); ?> (Disp: <?php echo $inv['gramos_disponibles']; ?> g)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="fecha_dispensacion" class="form-label fs-14 mb-0"><small>Fecha de Entrega *</small></label>
                                    <input type="date" class="form-control" id="fecha_dispensacion" name="fecha_dispensacion" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="gramos_entregados" class="form-label fs-14 mb-0"><small>Gramos a Entregar *</small></label>
                                    <input type="number" step="0.01" class="form-control" id="gramos_entregados" name="gramos_entregados" min="0.01" max="999999.99" value="10.00" required>
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="observaciones" class="form-label fs-14 mb-0"><small>Observaciones</small></label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3" maxlength="65535" placeholder="Detalles de la entrega o retiro..."></textarea>
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
