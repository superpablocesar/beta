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
    header("Location: historial_lotes.php?page=" . $page);
    exit();
}

$stmt = $conexion->prepare("SELECT * FROM historial_lotes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: historial_lotes.php?page=" . $page);
    exit();
}

$historial = $result->fetch_assoc();
$stmt->close();

// Obtener lotes
$sql_l = "SELECT lc.id, lc.codigo_lote, v.nombre AS variedad_nombre 
          FROM lotes_cultivo lc 
          INNER JOIN variedades v ON lc.variedad_id = v.id 
          ORDER BY lc.id DESC";
$res_l = $conexion->query($sql_l);
$lotes = $res_l ? $res_l->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>

    <form action="historial_lotes_actualizar.php" method="POST">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="historial_lotes.php">
                    <div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver</div>
                </a>
                <button type="submit" class="btn-nav btn btn-dark ms-1 my-2" id="btn-submit">
                    <span id="btn-text"><i class="fa-solid fa-floppy-disk"></i></span>
                    <i id="btn-spinner" class="fas fa-spinner fa-spin d-none"></i>
                </button>
            </div>
        </nav>

        <div class="container pb-4">
            <input type="hidden" name="id" value="<?php echo $historial['id']; ?>">
            <input type="hidden" name="page" value="<?php echo $page; ?>">

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card py-3 px-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="lote_id" class="form-label fs-14 mb-0"><small>Lote de Cultivo *</small></label>
                                    <select class="form-control" id="lote_id" name="lote_id" required>
                                        <?php foreach ($lotes as $l): ?>
                                            <option value="<?php echo $l['id']; ?>" <?php echo ($historial['lote_id'] == $l['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($l['codigo_lote']); ?> - <?php echo htmlspecialchars($l['variedad_nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="estado_anterior" class="form-label fs-14 mb-0"><small>Estado Anterior *</small></label>
                                    <select class="form-control" id="estado_anterior" name="estado_anterior" required>
                                        <option value="Germinacion" <?php echo ($historial['estado_anterior'] == 'Germinacion') ? 'selected' : ''; ?>>Germinación</option>
                                        <option value="Vegetativo" <?php echo ($historial['estado_anterior'] == 'Vegetativo') ? 'selected' : ''; ?>>Vegetativo</option>
                                        <option value="Floracion" <?php echo ($historial['estado_anterior'] == 'Floracion') ? 'selected' : ''; ?>>Floración</option>
                                        <option value="Cosechado" <?php echo ($historial['estado_anterior'] == 'Cosechado') ? 'selected' : ''; ?>>Cosechado</option>
                                        <option value="Descartado" <?php echo ($historial['estado_anterior'] == 'Descartado') ? 'selected' : ''; ?>>Descartado</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="estado_nuevo" class="form-label fs-14 mb-0"><small>Estado Nuevo *</small></label>
                                    <select class="form-control" id="estado_nuevo" name="estado_nuevo" required>
                                        <option value="Germinacion" <?php echo ($historial['estado_nuevo'] == 'Germinacion') ? 'selected' : ''; ?>>Germinación</option>
                                        <option value="Vegetativo" <?php echo ($historial['estado_nuevo'] == 'Vegetativo') ? 'selected' : ''; ?>>Vegetativo</option>
                                        <option value="Floracion" <?php echo ($historial['estado_nuevo'] == 'Floracion') ? 'selected' : ''; ?>>Floración</option>
                                        <option value="Cosechado" <?php echo ($historial['estado_nuevo'] == 'Cosechado') ? 'selected' : ''; ?>>Cosechado</option>
                                        <option value="Descartado" <?php echo ($historial['estado_nuevo'] == 'Descartado') ? 'selected' : ''; ?>>Descartado</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="cantidad_afectada" class="form-label fs-14 mb-0"><small>Cantidad Afectada / Mermas *</small></label>
                                    <input type="number" class="form-control" id="cantidad_afectada" name="cantidad_afectada" min="-2147483648" max="2147483647" required value="<?php echo $historial['cantidad_afectada']; ?>">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="motivo" class="form-label fs-14 mb-0"><small>Motivo del Cambio / Observación</small></label>
                                    <input type="text" class="form-control" id="motivo" name="motivo" maxlength="255" value="<?php echo htmlspecialchars($historial['motivo']); ?>">
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
