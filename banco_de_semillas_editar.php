<?php
// 1. CONFIGURACIÓN, SESIÓN Y CONEXIÓN
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();

if (empty($_SESSION["usuario"])) {
    header("Location: ./login.php");
    exit();
}

// Asignación de la variable para evaluar la condición
$operador = $_SESSION["usuario"];
require_once "./csrf.php";

require_once "./conexion.php";
$conexion = conexion();

// 1. Consulta para obtener las variedades/genéticas
$sql_variedades = "SELECT id, nombre, tipo FROM variedades ORDER BY nombre ASC";
$result_variedades = $conexion->query($sql_variedades);
$variedades = $result_variedades ? $result_variedades->fetch_all(MYSQLI_ASSOC) : [];

// 2. Consulta para obtener las plantas madre (necesarias para la genealogía)
$sql_madres = "SELECT id, codigo_planta, estado FROM plantas_madre ORDER BY codigo_planta ASC";
$result_madres = $conexion->query($sql_madres);
$plantas_madre = $result_madres ? $result_madres->fetch_all(MYSQLI_ASSOC) : [];

// 3. Obtener el ID de la semilla a editar (ej: desde la URL ?id=X)
$id_semilla = isset($_GET['id']) ? intval($_GET['id']) : 0;
$page = max(1, (int)($_GET['page'] ?? 1));

// 4. Consulta para traer los datos de la semilla específica
$stmt_semilla = $conexion->prepare("SELECT * FROM banco_semillas WHERE id = ? LIMIT 1");
$stmt_semilla->bind_param("i", $id_semilla);
$stmt_semilla->execute();
$result_semilla = $stmt_semilla->get_result();
$semilla = ($result_semilla && $result_semilla->num_rows > 0) ? $result_semilla->fetch_assoc() : null;
$stmt_semilla->close();

// Validación por si la semilla no existe
if (!$semilla) {
    header("Location: banco_de_semillas.php"); // Redirige al listado si no se encuentra
    exit();
}
$stmt_lote_asociado = $conexion->prepare('SELECT id FROM lotes_cultivo WHERE banco_semillas_id=? LIMIT 1');
$stmt_lote_asociado->bind_param('i', $id_semilla);
$stmt_lote_asociado->execute();
$lote_asociado = (bool)$stmt_lote_asociado->get_result()->fetch_assoc();
$stmt_lote_asociado->close();
?>

<!DOCTYPE html>
<html lang="es">

<?php include 'head.php'; ?>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>

    <form action="banco_de_semillas_actualizar.php" method="POST" enctype="multipart/form-data">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="banco_de_semillas.php">
                    <div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver</div>
                </a>
                <button type="submit" class="btn-nav btn btn-dark ms-1 my-2" id="btn-submit">
                    <span id="btn-text"><i class="fa-solid fa-floppy-disk"></i></span>
                    <i id="btn-spinner" class="fas fa-spinner fa-spin d-none"></i>
                </button>
            </div>
        </nav>
        <div class="container pb-4">
            <input type="hidden" name="id" value="<?php echo $semilla['id']; ?>">
            <input type="hidden" name="page" value="<?php echo $page; ?>">
            <?php if ($lote_asociado): ?>
                <div class="alert alert-info py-2">Este material ya está vinculado a un lote. La genética y su origen quedan fijos para conservar la trazabilidad; puedes ajustar stock y ubicación.</div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card py-3 px-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="variedad_id" class="form-label fs-14 mb-0"><small>Variedad / Genética</small></label>
                                    <select class="form-control" id="variedad_id" name="variedad_id" required>
                                        <?php foreach ($variedades as$v): ?>
                                            <option value="<?php echo $v['id']; ?>" <?php echo ($semilla['variedad_id'] ==$v['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($v['nombre']); ?> (<?php echo htmlspecialchars($v['tipo']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="tipo_material" class="form-label fs-14 mb-0"><small>Tipo de Material</small></label>
                                    <select class="form-control" id="tipo_material" name="tipo_material" required>
                                        <option value="Semilla Feminizada" <?php echo ($semilla['tipo_material'] == 'Semilla Feminizada') ? 'selected' : ''; ?>>Semilla Feminizada</option>
                                        <option value="Semilla Regular" <?php echo ($semilla['tipo_material'] == 'Semilla Regular') ? 'selected' : ''; ?>>Semilla Regular</option>
                                        <option value="Semilla Automatica" <?php echo ($semilla['tipo_material'] == 'Semilla Automatica') ? 'selected' : ''; ?>>Semilla Automática</option>
                                        <option value="Esqueje/Clon" <?php echo ($semilla['tipo_material'] == 'Esqueje/Clon') ? 'selected' : ''; ?>>Esqueje / Clon</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="cantidad_disponible" class="form-label fs-14 mb-0"><small>Cantidad Disponible</small></label>
                                    <input type="number" class="form-control" id="cantidad_disponible" name="cantidad_disponible" value="<?php echo $semilla['cantidad_disponible']; ?>" min="0" max="2147483647" step="1" required>
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="motivo_ajuste" class="form-label fs-14 mb-0"><small>Motivo del ajuste de stock</small></label>
                                    <input type="text" class="form-control" id="motivo_ajuste" name="motivo_ajuste" maxlength="255" placeholder="Obligatorio al cambiar la cantidad disponible">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="ubicacion_almacen" class="form-label fs-14 mb-0"><small>Ubicación en Almacén</small></label>
                                    <input type="text" class="form-control" id="ubicacion_almacen" name="ubicacion_almacen" maxlength="100" value="<?php echo htmlspecialchars($semilla['ubicacion_almacen']); ?>">
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
                                    <label for="origen_tipo" class="form-label fs-14 mb-0"><small>Tipo de Origen</small></label>
                                    <select class="form-control" id="origen_tipo" name="origen_tipo" required>
                                        <option value="Compra_Externa" <?php echo ($semilla['origen_tipo'] == 'Compra_Externa') ? 'selected' : ''; ?>>Compra Externa</option>
                                        <option value="Produccion_Propia" <?php echo ($semilla['origen_tipo'] == 'Produccion_Propia') ? 'selected' : ''; ?>>Producción Propia (Cruza)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Campos para Compra Externa -->
                            <div id="seccion_externa" class="col-lg-12">
                                <div class="mb-3">
                                    <label for="origen_proveedor" class="form-label fs-14 mb-0"><small>Proveedor / Banco de Semillas</small></label>
                                    <input type="text" class="form-control" id="origen_proveedor" name="origen_proveedor" maxlength="150" value="<?php echo htmlspecialchars($semilla['origen_proveedor']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="numero_lote_origen" class="form-label fs-14 mb-0"><small>Número de Lote / Rótulo</small></label>
                                    <input type="text" class="form-control" id="numero_lote_origen" name="numero_lote_origen" maxlength="100" value="<?php echo htmlspecialchars($semilla['numero_lote_origen']); ?>">
                                </div>
                            </div>

                            <!-- Campos para Producción Propia (Genealogía) -->
                            <div id="seccion_propia" class="col-lg-12 d-none">
                                <div class="mb-3">
                                    <label for="planta_madre_id" class="form-label fs-14 mb-0"><small>Planta Madre (Madre)</small></label>
                                    <select class="form-control" id="planta_madre_id" name="planta_madre_id">
                                        <option value="">Seleccionar madre...</option>
                                        <?php foreach ($plantas_madre as$pm): ?>
                                            <option value="<?php echo $pm['id']; ?>" <?php echo ($semilla['planta_madre_id'] == $pm['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pm['codigo_planta'] . ($pm['estado'] === 'Activa' ? '' : ' · ' . $pm['estado'])); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="planta_padre_id" class="form-label fs-14 mb-0"><small>Planta Padre / Donante de Polen (Padre)</small></label>
                                    <select class="form-control" id="planta_padre_id" name="planta_padre_id">
                                        <option value="">Seleccionar padre...</option>
                                        <?php foreach ($plantas_madre as$pm): ?>
                                            <option value="<?php echo $pm['id']; ?>" <?php echo ($semilla['planta_padre_id'] == $pm['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pm['codigo_planta'] . ($pm['estado'] === 'Activa' ? '' : ' · ' . $pm['estado'])); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="fecha_polinizacion" class="form-label fs-14 mb-0"><small>Fecha de Polinización</small></label>
                                    <input type="date" class="form-control" id="fecha_polinizacion" name="fecha_polinizacion" value="<?php echo $semilla['fecha_polinizacion'] ? date('Y-m-d', strtotime($semilla['fecha_polinizacion'])) : ''; ?>">
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
            const seccionExterna = document.getElementById('seccion_externa');
            const seccionPropia = document.getElementById('seccion_propia');

            function toggleOrigen() {
                if (origenSelect.value === 'Compra_Externa') {
                    seccionExterna.classList.remove('d-none');
                    seccionPropia.classList.add('d-none');
                } else {
                    seccionExterna.classList.add('d-none');
                    seccionPropia.classList.remove('d-none');
                }
            }

            origenSelect.addEventListener('change', toggleOrigen);
            toggleOrigen();
        });
    </script>

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
