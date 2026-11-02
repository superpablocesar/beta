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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($id <= 0) {
    header("Location: cuotas.php?page=" . $page);
    exit();
}

$stmt = $conexion->prepare("SELECT * FROM cuotas_socios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: cuotas.php?page=" . $page);
    exit();
}

$cuota = $result->fetch_assoc();
$stmt->close();

// Obtener socios
$sql_s = "SELECT id, nombre, apellido, dni FROM socios ORDER BY apellido ASC";
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

    <form action="cuotas_actualizar.php" method="POST">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="cuotas.php?page=<?php echo $page; ?>">
                    <div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver</div>
                </a>
                <button type="submit" class="btn-nav btn btn-dark ms-1 my-2" id="btn-submit">
                    <span id="btn-text"><i class="fa-solid fa-floppy-disk"></i></span>
                    <i id="btn-spinner" class="fas fa-spinner fa-spin d-none"></i>
                </button>
            </div>
        </nav>

        <div class="container pb-4">
            <input type="hidden" name="id" value="<?php echo $cuota['id']; ?>">
            <input type="hidden" name="page" value="<?php echo $page; ?>">

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card py-3 px-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="socio_id" class="form-label fs-14 mb-0"><small>Socio / Paciente *</small></label>
                                    <select class="form-control" id="socio_id" name="socio_id" required>
                                        <?php foreach ($socios as $s): ?>
                                            <option value="<?php echo $s['id']; ?>" <?php echo ($cuota['socio_id'] == $s['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($s['apellido'] . ', ' . $s['nombre']); ?> (DNI: <?php echo htmlspecialchars($s['dni']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="monto" class="form-label fs-14 mb-0"><small>Monto ($) *</small></label>
                                    <input type="number" step="0.01" class="form-control" id="monto" name="monto" min="0" max="99999999.99" required value="<?php echo $cuota['monto']; ?>">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="fecha_pago" class="form-label fs-14 mb-0"><small>Fecha de Pago *</small></label>
                                    <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" required value="<?php echo date('Y-m-d', strtotime($cuota['fecha_pago'])); ?>">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="concepto" class="form-label fs-14 mb-0"><small>Concepto *</small></label>
                                    <input type="text" class="form-control" id="concepto" name="concepto" maxlength="150" required value="<?php echo htmlspecialchars($cuota['concepto']); ?>">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="metodo_pago" class="form-label fs-14 mb-0"><small>Método de Pago *</small></label>
                                    <select class="form-control" id="metodo_pago" name="metodo_pago" required>
                                        <option value="Efectivo" <?php echo ($cuota['metodo_pago'] == 'Efectivo') ? 'selected' : ''; ?>>Efectivo</option>
                                        <option value="Transferencia" <?php echo ($cuota['metodo_pago'] == 'Transferencia') ? 'selected' : ''; ?>>Transferencia</option>
                                        <option value="MercadoPago" <?php echo ($cuota['metodo_pago'] == 'MercadoPago') ? 'selected' : ''; ?>>MercadoPago</option>
                                        <option value="Otro" <?php echo ($cuota['metodo_pago'] == 'Otro') ? 'selected' : ''; ?>>Otro</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="estado" class="form-label fs-14 mb-0"><small>Estado *</small></label>
                                    <select class="form-control" id="estado" name="estado" required>
                                        <option value="Completado" <?php echo ($cuota['estado'] == 'Completado') ? 'selected' : ''; ?>>Completado</option>
                                        <option value="Pagado Pendiente" <?php echo ($cuota['estado'] == 'Pagado Pendiente') ? 'selected' : ''; ?>>Pagado Pendiente</option>
                                        <option value="Anulado" <?php echo ($cuota['estado'] == 'Anulado') ? 'selected' : ''; ?>>Anulado</option>
                                    </select>
                                    <small class="form-text text-muted">“Pagado Pendiente” significa que el pago está registrado y falta confirmarlo; no representa una deuda calculada.</small>
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="observaciones" class="form-label fs-14 mb-0"><small>Observaciones</small></label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?php echo htmlspecialchars($cuota['observaciones']); ?></textarea>
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
