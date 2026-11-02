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
    header("Location: extracciones_madre.php?page=" . $page);
    exit();
}

$stmt = $conexion->prepare("SELECT id, planta_madre_id, cantidad_esquejes_cortados, observaciones FROM extracciones_madre WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: extracciones_madre.php?page=" . $page);
    exit();
}

$extraccion = $result->fetch_assoc();
$stmt->close();

// Obtener plantas madre para el selector
$sql_madres = "SELECT pm.id, pm.codigo_planta, v.nombre AS variedad_nombre 
               FROM plantas_madre pm 
               INNER JOIN variedades v ON pm.variedad_id = v.id 
               ORDER BY pm.codigo_planta ASC";
$result_m = $conexion->query($sql_madres);
$plantas_madre = $result_m ? $result_m->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>

    <form action="extracciones_madre_actualizar.php" method="POST">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="extracciones_madre.php">
                    <div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver</div>
                </a>
                <button type="submit" class="btn-nav btn btn-dark ms-1 my-2" id="btn-submit">
                    <span id="btn-text"><i class="fa-solid fa-floppy-disk"></i></span>
                    <i id="btn-spinner" class="fas fa-spinner fa-spin d-none"></i>
                </button>
            </div>
        </nav>

        <div class="container pb-4">
            <input type="hidden" name="id" value="<?php echo $extraccion['id']; ?>">
            <input type="hidden" name="page" value="<?php echo $page; ?>">

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card py-3 px-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="planta_madre_id" class="form-label fs-14 mb-0"><small>Planta Madre *</small></label>
                                    <select class="form-control" id="planta_madre_id" name="planta_madre_id" required>
                                        <?php foreach ($plantas_madre as $pm): ?>
                                            <option value="<?php echo $pm['id']; ?>" <?php echo ($extraccion['planta_madre_id'] == $pm['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($pm['codigo_planta']); ?> (Genética: <?php echo htmlspecialchars($pm['variedad_nombre']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="cantidad_esquejes_cortados" class="form-label fs-14 mb-0"><small>Cantidad de Esquejes Cortados *</small></label>
                                    <input type="number" class="form-control" id="cantidad_esquejes_cortados" name="cantidad_esquejes_cortados" min="1" max="2147483647" step="1" required value="<?php echo $extraccion['cantidad_esquejes_cortados']; ?>">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="observaciones" class="form-label fs-14 mb-0"><small>Observaciones</small></label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3" maxlength="255"><?php echo htmlspecialchars($extraccion['observaciones']); ?></textarea>
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
