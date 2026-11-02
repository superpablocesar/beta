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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($id <= 0) {
    header("Location: dispensacion.php?page=" . $page);
    exit();
}

$stmt = $conexion->prepare("SELECT * FROM dispensacion WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: dispensacion.php?page=" . $page);
    exit();
}

$dispensacion = $result->fetch_assoc();
$stmt->close();

// Obtener socios
$sql_s = "SELECT id, nombre, apellido, dni FROM socios ORDER BY apellido ASC";
$res_s = $conexion->query($sql_s);
$socios = $res_s ? $res_s->fetch_all(MYSQLI_ASSOC) : [];

// Obtener inventario
$sql_idv = "SELECT id, codigo_qr, tipo_producto FROM inventario_derivado ORDER BY id DESC";
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

    <form action="dispensacion_actualizar.php" method="POST">
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
            <input type="hidden" name="id" value="<?php echo $dispensacion['id']; ?>">
            <input type="hidden" name="page" value="<?php echo $page; ?>">

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card py-3 px-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="socio_id" class="form-label fs-14 mb-0"><small>Socio / Paciente REPROCANN *</small></label>
                                    <select class="form-control" id="socio_id" name="socio_id" required>
                                        <?php foreach ($socios as $s): ?>
                                            <option value="<?php echo $s['id']; ?>" <?php echo ($dispensacion['socio_id'] == $s['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($s['apellido'] . ', ' . $s['nombre']); ?> (DNI: <?php echo htmlspecialchars($s['dni']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="inventario_derivado_id" class="form-label fs-14 mb-0"><small>Producto del Inventario *</small></label>
                                    <select class="form-control" id="inventario_derivado_id" name="inventario_derivado_id" required>
                                        <?php foreach ($inventario as $inv): ?>
                                            <option value="<?php echo $inv['id']; ?>" <?php echo ($dispensacion['inventario_derivado_id'] == $inv['id']) ? 'selected' : ''; ?>>
                                                [<?php echo htmlspecialchars($inv['tipo_producto']); ?>] QR: <?php echo htmlspecialchars($inv['codigo_qr']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="fecha_dispensacion" class="form-label fs-14 mb-0"><small>Fecha de Entrega *</small></label>
                                    <input type="date" class="form-control" id="fecha_dispensacion" name="fecha_dispensacion" required value="<?php echo $dispensacion['fecha_dispensacion'] ? date('Y-m-d', strtotime($dispensacion['fecha_dispensacion'])) : ''; ?>">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="gramos_entregados" class="form-label fs-14 mb-0"><small>Gramos Entregados *</small></label>
                                    <input type="number" step="0.01" class="form-control" id="gramos_entregados" name="gramos_entregados" min="0.01" max="999999.99" required value="<?php echo $dispensacion['gramos_entregados']; ?>">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="observaciones" class="form-label fs-14 mb-0"><small>Observaciones</small></label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3" maxlength="65535"><?php echo htmlspecialchars($dispensacion['observaciones']); ?></textarea>
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
