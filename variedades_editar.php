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
    header("Location: variedades.php?page=" . $page);
    exit();
}

$stmt = $conexion->prepare("SELECT id, nombre, tipo, porcentaje_thc_estimado, porcentaje_cbd_estimado, dias_floracion_estimados FROM variedades WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: variedades.php?page=" . $page);
    exit();
}

$variedad = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>

    
    <form action="variedades_actualizar.php" method="POST">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="variedades.php">
                    <div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver</div>
                </a>
                <button type="submit" class="btn-nav btn btn-dark ms-1 my-2" id="btn-submit">
                    <span id="btn-text"><i class="fa-solid fa-floppy-disk"></i></span>
                    <i id="btn-spinner" class="fas fa-spinner fa-spin d-none"></i>
                </button>
            </div>
        </nav>

        <div class="container pb-4">
            <input type="hidden" name="id" value="<?php echo $variedad['id']; ?>">
            <input type="hidden" name="page" value="<?php echo $page; ?>">

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card py-3 px-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="nombre" class="form-label fs-14 mb-0"><small>Nombre de la Variedad *</small></label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" required value="<?php echo htmlspecialchars($variedad['nombre']); ?>">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="tipo" class="form-label fs-14 mb-0"><small>Tipo de Genética *</small></label>
                                    <select class="form-control" id="tipo" name="tipo" required>
                                        <option value="Sativa" <?php echo ($variedad['tipo'] == 'Sativa') ? 'selected' : ''; ?>>Sativa</option>
                                        <option value="Indica" <?php echo ($variedad['tipo'] == 'Indica') ? 'selected' : ''; ?>>Índica</option>
                                        <option value="Hibrida" <?php echo ($variedad['tipo'] == 'Hibrida') ? 'selected' : ''; ?>>Híbrida</option>
                                        <option value="CBD" <?php echo ($variedad['tipo'] == 'CBD') ? 'selected' : ''; ?>>CBD</option>
                                        <option value="Alta en THC" <?php echo ($variedad['tipo'] == 'Alta en THC') ? 'selected' : ''; ?>>Alta en THC</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="dias_floracion_estimados" class="form-label fs-14 mb-0"><small>Días de Floración Estimados</small></label>
                                    <input type="number" class="form-control" id="dias_floracion_estimados" name="dias_floracion_estimados" min="1" max="2147483647" step="1" value="<?php echo $variedad['dias_floracion_estimados']; ?>">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="porcentaje_thc_estimado" class="form-label fs-14 mb-0"><small>% THC Estimado</small></label>
                                    <input type="number" min="0" max="99.99" step="0.01" class="form-control" id="porcentaje_thc_estimado" name="porcentaje_thc_estimado" value="<?php echo $variedad['porcentaje_thc_estimado']; ?>">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="porcentaje_cbd_estimado" class="form-label fs-14 mb-0"><small>% CBD Estimado</small></label>
                                    <input type="number" min="0" max="99.99" step="0.01" class="form-control" id="porcentaje_cbd_estimado" name="porcentaje_cbd_estimado" value="<?php echo $variedad['porcentaje_cbd_estimado']; ?>">
                                </div>
                            </div>
                        </div>   
                    </div>
                </div>  
            </div>
        </div>
    </form>

    <script>
        // Efecto de carga en el botón al enviar el formulario
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
