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
    header("Location: cosechas.php?page=" . $page);
    exit();
}

$stmt = $conexion->prepare("SELECT * FROM cosechas WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: cosechas.php?page=" . $page);
    exit();
}

$cosecha = $result->fetch_assoc();
$stmt->close();

$stmt_lote = $conexion->prepare('SELECT lc.codigo_lote, v.nombre AS variedad_nombre FROM lotes_cultivo lc INNER JOIN variedades v ON v.id = lc.variedad_id WHERE lc.id = ?');
$stmt_lote->bind_param('i', $cosecha['lote_id']);
$stmt_lote->execute();
$lote_origen = $stmt_lote->get_result()->fetch_assoc();
$stmt_lote->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>

    <form action="cosechas_actualizar.php" method="POST">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="cosechas.php">
                    <div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver</div>
                </a>
                <button type="submit" class="btn-nav btn btn-dark ms-1 my-2" id="btn-submit">
                    <span id="btn-text"><i class="fa-solid fa-floppy-disk"></i></span>
                    <i id="btn-spinner" class="fas fa-spinner fa-spin d-none"></i>
                </button>
            </div>
        </nav>

        <div class="container pb-4">
            <input type="hidden" name="id" value="<?php echo $cosecha['id']; ?>">
            <input type="hidden" name="page" value="<?php echo $page; ?>">
            <input type="hidden" name="lote_id" value="<?php echo (int)$cosecha['lote_id']; ?>">

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card py-3 px-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="codigo_cosecha" class="form-label fs-14 mb-0"><small>Código de Cosecha *</small></label>
                                    <input type="text" class="form-control" id="codigo_cosecha" name="codigo_cosecha" maxlength="50" required value="<?php echo htmlspecialchars($cosecha['codigo_cosecha']); ?>">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label class="form-label fs-14 mb-0"><small>Lote de Cultivo Origen</small></label>
                                    <input class="form-control" type="text" value="<?php echo htmlspecialchars(($lote_origen['codigo_lote'] ?? 'Lote no disponible') . ' - ' . ($lote_origen['variedad_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                    <small class="text-muted">El origen de una cosecha es fijo para conservar la trazabilidad.</small>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="fecha_cosecha" class="form-label fs-14 mb-0"><small>Fecha de Cosecha *</small></label>
                                    <input type="date" class="form-control" id="fecha_cosecha" name="fecha_cosecha" required value="<?php echo $cosecha['fecha_cosecha'] ? date('Y-m-d', strtotime($cosecha['fecha_cosecha'])) : ''; ?>">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="peso_fresco_gramos" class="form-label fs-14 mb-0"><small>Peso Fresco (Gramos) *</small></label>
                                    <input type="number" step="0.01" class="form-control" id="peso_fresco_gramos" name="peso_fresco_gramos" min="0" max="999999.99" required value="<?php echo $cosecha['peso_fresco_gramos']; ?>">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="peso_seco_gramos" class="form-label fs-14 mb-0"><small>Peso Seco (Gramos)</small></label>
                                    <input type="number" step="0.01" class="form-control" id="peso_seco_gramos" name="peso_seco_gramos" min="0" max="999999.99" value="<?php echo $cosecha['peso_seco_gramos']; ?>">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="observaciones" class="form-label fs-14 mb-0"><small>Observaciones</small></label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?php echo htmlspecialchars($cosecha['observaciones']); ?></textarea>
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
