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
    header("Location: plantas_madre.php?page=" . $page);
    exit();
}

$stmt = $conexion->prepare("SELECT id, codigo_planta, variedad_id, ubicacion_sala, estado, fecha_nacimiento FROM plantas_madre WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: plantas_madre.php?page=" . $page);
    exit();
}

$planta = $result->fetch_assoc();
$stmt->close();

// Obtener variedades para el selector
$sql_variedades = "SELECT id, nombre, tipo FROM variedades ORDER BY nombre ASC";
$result_v = $conexion->query($sql_variedades);
$variedades = $result_v ? $result_v->fetch_all(MYSQLI_ASSOC) : [];

// Construir la URL absoluta o relativa para el código QR (Ajusta el dominio según tu entorno)
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$url_qr_destino = "{$protocolo}://{$host}/visor.php?id=" . $planta['id'];
$api_qr = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=15&data=" . urlencode($url_qr_destino);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>

    <form action="plantas_madre_actualizar.php" method="POST">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="plantas_madre.php?page=<?php echo $page; ?>">
                    <div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver</div>
                </a>
                <button type="submit" class="btn-nav btn btn-dark ms-1 my-2" id="btn-submit">
                    <span id="btn-text"><i class="fa-solid fa-floppy-disk"></i></span>
                    <i id="btn-spinner" class="fas fa-spinner fa-spin d-none"></i>
                </button>
            </div>
        </nav>

        <div class="container pb-4">
            <input type="hidden" name="id" value="<?php echo $planta['id']; ?>">
            <input type="hidden" name="page" value="<?php echo $page; ?>">

            <div class="row justify-content-center">
                <!-- Formulario de Edición -->
                <div class="col-lg-8">
                    <div class="card py-3 px-4 mb-3">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="codigo_planta" class="form-label fs-14 mb-0"><small>Código de Planta / QR *</small></label>
                                    <input type="text" class="form-control" id="codigo_planta" name="codigo_planta" maxlength="50" required value="<?php echo htmlspecialchars($planta['codigo_planta']); ?>">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="variedad_id" class="form-label fs-14 mb-0"><small>Variedad / Genética *</small></label>
                                    <select class="form-control" id="variedad_id" name="variedad_id" required>
                                        <?php foreach ($variedades as $v): ?>
                                            <option value="<?php echo $v['id']; ?>" <?php echo ($planta['variedad_id'] == $v['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($v['nombre']); ?> (<?php echo htmlspecialchars($v['tipo']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="ubicacion_sala" class="form-label fs-14 mb-0"><small>Ubicación en Sala</small></label>
                                    <input type="text" class="form-control" id="ubicacion_sala" name="ubicacion_sala" maxlength="100" value="<?php echo htmlspecialchars($planta['ubicacion_sala']); ?>">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="estado" class="form-label fs-14 mb-0"><small>Estado *</small></label>
                                    <select class="form-control" id="estado" name="estado" required>
                                        <option value="Activa" <?php echo ($planta['estado'] == 'Activa') ? 'selected' : ''; ?>>Activa</option>
                                        <option value="En Descanso" <?php echo ($planta['estado'] == 'En Descanso') ? 'selected' : ''; ?>>En Descanso</option>
                                        <option value="Jubilada/Baja" <?php echo ($planta['estado'] == 'Jubilada/Baja') ? 'selected' : ''; ?>>Jubilada / Baja</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="fecha_nacimiento" class="form-label fs-14 mb-0"><small>Fecha de Nacimiento</small></label>
                                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo $planta['fecha_nacimiento'] ? date('Y-m-d', strtotime($planta['fecha_nacimiento'])) : ''; ?>">
                                </div>
                            </div>
                        </div>   
                    </div>
                </div>  

                <!-- Tarjeta de Visualización del Código QR -->
                <div class="col-lg-4">
                    <div class="card py-3 px-4 text-center">
                        <h6 class="ui-f-700 text-admin-3 mb-2"><small>QR de Acceso Directo</small></h6>
                        <div class="my-2">
                            <img src="<?php echo $api_qr; ?>" alt="Código QR Planta Madre" class="img-fluid bg-white p-2 rounded border" style="max-width: 100%;">
                        </div>
                        <small class="text-muted d-block mt-2 text-break" style="font-size: 11px;"><?php echo htmlspecialchars($url_qr_destino); ?></small>
                        <div class="d-grid gap-2 mt-3">
                            <a href="<?php echo $api_qr; ?>" target="_blank" download="QR_Planta_<?php echo htmlspecialchars($planta['codigo_planta']); ?>.png" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-light fa-download me-1"></i> Descargar QR
                            </a>
                            <a href="plantas_madre_qr_pdf.php?id=<?php echo $planta['id']; ?>" target="_blank" class="btn btn-sm btn-dark">
                                <i class="fa-light fa-print me-1"></i> Imprimir / PDF
                            </a>
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
            function ocultarLoader() {
                $(".ui-loader").fadeOut(function() {
                    $(".ui-loader-bg").fadeOut().addClass("sale");
                    document.body.style.overflow = "auto";
                });
            }

            setTimeout(ocultarLoader, 250);

            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    ocultarLoader();
                    $("a").css("pointer-events", "auto");
                }
            });
        });
    </script>

</body>
</html>
