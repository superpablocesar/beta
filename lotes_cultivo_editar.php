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
    header("Location: lotes_cultivo.php?page=" . $page);
    exit();
}

$stmt = $conexion->prepare("SELECT * FROM lotes_cultivo WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: lotes_cultivo.php?page=" . $page);
    exit();
}

$lote = $result->fetch_assoc();
$stmt->close();

// Obtener variedades
$sql_v = "SELECT id, nombre, tipo FROM variedades ORDER BY nombre ASC";
$res_v = $conexion->query($sql_v);
$variedades = $res_v ? $res_v->fetch_all(MYSQLI_ASSOC) : [];

// Obtener banco de semillas
$sql_bs = "SELECT bs.id, bs.variedad_id, bs.tipo_material, bs.cantidad_disponible, v.nombre AS variedad_nombre 
           FROM banco_semillas bs 
           INNER JOIN variedades v ON bs.variedad_id = v.id 
           ORDER BY bs.id DESC";
$res_bs = $conexion->query($sql_bs);
$banco_semillas = $res_bs ? $res_bs->fetch_all(MYSQLI_ASSOC) : [];

// Obtener plantas madre
$sql_pm = "SELECT id, variedad_id, codigo_planta FROM plantas_madre ORDER BY codigo_planta ASC";
$res_pm = $conexion->query($sql_pm);
$plantas_madre = $res_pm ? $res_pm->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>

    <form action="lotes_cultivo_actualizar.php" method="POST">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="lotes_cultivo.php">
                    <div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver</div>
                </a>
                <button type="submit" class="btn-nav btn btn-dark ms-1 my-2" id="btn-submit">
                    <span id="btn-text"><i class="fa-solid fa-floppy-disk"></i></span>
                    <i id="btn-spinner" class="fas fa-spinner fa-spin d-none"></i>
                </button>
            </div>
        </nav>

        <div class="container pb-4">
            <input type="hidden" name="id" value="<?php echo $lote['id']; ?>">
            <input type="hidden" name="page" value="<?php echo $page; ?>">
            <input type="hidden" name="variedad_id" value="<?php echo (int)$lote['variedad_id']; ?>">
            <input type="hidden" name="origen_tipo" value="<?php echo htmlspecialchars($lote['origen_tipo'], ENT_QUOTES); ?>">
            <input type="hidden" name="banco_semillas_id" value="<?php echo $lote['banco_semillas_id'] === null ? '' : (int)$lote['banco_semillas_id']; ?>">
            <input type="hidden" name="planta_madre_id" value="<?php echo $lote['planta_madre_id'] === null ? '' : (int)$lote['planta_madre_id']; ?>">
            <div class="col-12 mb-3"><div class="alert alert-info py-2 mb-0">La genética y el origen quedan fijos para conservar la trazabilidad del material utilizado.</div></div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card py-3 px-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="codigo_lote" class="form-label fs-14 mb-0"><small>Código del Lote *</small></label>
                                    <input type="text" class="form-control" id="codigo_lote" name="codigo_lote" maxlength="50" required value="<?php echo htmlspecialchars($lote['codigo_lote']); ?>">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="variedad_id" class="form-label fs-14 mb-0"><small>Variedad / Genética *</small></label>
                                    <select class="form-control" id="variedad_id" disabled required>
                                        <?php foreach ($variedades as $v): ?>
                                            <option value="<?php echo $v['id']; ?>" <?php echo ($lote['variedad_id'] == $v['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($v['nombre']); ?> (<?php echo htmlspecialchars($v['tipo']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="cantidad_actual" class="form-label fs-14 mb-0"><small>Cantidad Actual (Plantas) *</small></label>
                                    <input type="number" class="form-control" id="cantidad_actual" name="cantidad_actual" min="0" max="2147483647" required value="<?php echo $lote['cantidad_actual']; ?>">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="estado_actual" class="form-label fs-14 mb-0"><small>Estado Actual *</small></label>
                                    <select class="form-control" id="estado_actual" name="estado_actual" required>
                                        <option value="Germinacion" <?php echo ($lote['estado_actual'] == 'Germinacion') ? 'selected' : ''; ?>>Germinación</option>
                                        <option value="Vegetativo" <?php echo ($lote['estado_actual'] == 'Vegetativo') ? 'selected' : ''; ?>>Vegetativo</option>
                                        <option value="Floracion" <?php echo ($lote['estado_actual'] == 'Floracion') ? 'selected' : ''; ?>>Floración</option>
                                        <option value="Cosechado" <?php echo ($lote['estado_actual'] == 'Cosechado') ? 'selected' : ''; ?>>Cosechado</option>
                                        <option value="Descartado" <?php echo ($lote['estado_actual'] == 'Descartado') ? 'selected' : ''; ?>>Descartado</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="ubicacion_actual" class="form-label fs-14 mb-0"><small>Ubicación Actual / Sala</small></label>
                                    <input type="text" class="form-control" id="ubicacion_actual" name="ubicacion_actual" maxlength="100" value="<?php echo htmlspecialchars($lote['ubicacion_actual']); ?>">
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
                                    <label for="origen_tipo" class="form-label fs-14 mb-0"><small>Tipo de Origen *</small></label>
                                    <select class="form-control" id="origen_tipo" disabled required>
                                        <option value="Desde_Semilla" <?php echo ($lote['origen_tipo'] == 'Desde_Semilla') ? 'selected' : ''; ?>>Desde Semilla</option>
                                        <option value="Desde_Esqueje_Madre" <?php echo ($lote['origen_tipo'] == 'Desde_Esqueje_Madre') ? 'selected' : ''; ?>>Desde Esqueje de Planta Madre</option>
                                        <option value="Desde_Esqueje_Externo" <?php echo ($lote['origen_tipo'] == 'Desde_Esqueje_Externo') ? 'selected' : ''; ?>>Desde Esqueje Externo</option>
                                    </select>
                                </div>
                            </div>

                            <div id="seccion_banco" class="col-lg-12">
                                <div class="mb-3">
                                    <label for="banco_semillas_id" class="form-label fs-14 mb-0"><small>Ítem del Banco de Semillas / Stock</small></label>
                                    <select class="form-control" id="banco_semillas_id" disabled>
                                        <option value="">Seleccionar del banco...</option>
                                        <?php foreach ($banco_semillas as $bs): ?>
                                            <option value="<?php echo $bs['id']; ?>" data-material="<?php echo htmlspecialchars($bs['tipo_material'], ENT_QUOTES); ?>" data-variedad="<?php echo (int)$bs['variedad_id']; ?>" <?php echo ($lote['banco_semillas_id'] == $bs['id']) ? 'selected' : ''; ?>>
                                                [<?php echo htmlspecialchars($bs['tipo_material']); ?>] <?php echo htmlspecialchars($bs['variedad_nombre']); ?> (Stock: <?php echo (int)$bs['cantidad_disponible']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div id="seccion_madre" class="col-lg-12 d-none">
                                <div class="mb-3">
                                    <label for="planta_madre_id" class="form-label fs-14 mb-0"><small>Planta Madre Donante</small></label>
                                    <select class="form-control" id="planta_madre_id" disabled>
                                        <option value="">Seleccionar madre...</option>
                                        <?php foreach ($plantas_madre as $pm): ?>
                                            <option value="<?php echo $pm['id']; ?>" data-variedad="<?php echo (int)$pm['variedad_id']; ?>" <?php echo ($lote['planta_madre_id'] == $pm['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($pm['codigo_planta']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="fecha_inicio" class="form-label fs-14 mb-0"><small>Fecha de Inicio *</small></label>
                                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required value="<?php echo $lote['fecha_inicio'] ? date('Y-m-d', strtotime($lote['fecha_inicio'])) : ''; ?>">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="fecha_estimada_cosecha" class="form-label fs-14 mb-0"><small>Fecha Estimada de Cosecha</small></label>
                                    <input type="date" class="form-control" id="fecha_estimada_cosecha" name="fecha_estimada_cosecha" value="<?php echo $lote['fecha_estimada_cosecha'] ? date('Y-m-d', strtotime($lote['fecha_estimada_cosecha'])) : ''; ?>">
                                </div>
                            </div>
                        </div>   
                    </div>
                </div>  
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const origenSelect = document.getElementById('origen_tipo');
            const seccionBanco = document.getElementById('seccion_banco');
            const seccionMadre = document.getElementById('seccion_madre');

            function toggleOrigen() {
                const val = origenSelect.value;
                if (val === 'Desde_Semilla' || val === 'Desde_Esqueje_Externo') {
                    seccionBanco.classList.remove('d-none');
                    seccionMadre.classList.add('d-none');
                } else {
                    seccionBanco.classList.add('d-none');
                    seccionMadre.classList.remove('d-none');
                }
            }

            toggleOrigen();
        });
    </script>

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
