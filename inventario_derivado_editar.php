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
    header("Location: inventario_derivado.php?page=" . $page);
    exit();
}

$stmt = $conexion->prepare("SELECT * FROM inventario_derivado WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: inventario_derivado.php?page=" . $page);
    exit();
}

$producto = $result->fetch_assoc();
$stmt->close();

// Obtener cosechas
$sql_c = "SELECT c.id, c.codigo_cosecha, v.nombre AS variedad_nombre 
          FROM cosechas c 
          INNER JOIN lotes_cultivo lc ON c.lote_id = lc.id 
          INNER JOIN variedades v ON lc.variedad_id = v.id 
          ORDER BY c.id DESC";
$res_c = $conexion->query($sql_c);
$cosechas = $res_c ? $res_c->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>

    <form action="inventario_derivado_actualizar.php" method="POST">
        <?= campo_token_csrf() ?>
        <nav class="navbar expand navbar-dark" aria-label="Nav">
            <div class="container-lg px-3">
                <a class="navbar-brand" href="inventario_derivado.php">
                    <div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver</div>
                </a>
                <button type="submit" class="btn-nav btn btn-dark ms-1 my-2" id="btn-submit">
                    <span id="btn-text"><i class="fa-solid fa-floppy-disk"></i></span>
                    <i id="btn-spinner" class="fas fa-spinner fa-spin d-none"></i>
                </button>
            </div>
        </nav>

        <div class="container pb-4">
            <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
            <input type="hidden" name="page" value="<?php echo $page; ?>">

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card py-3 px-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="codigo_qr" class="form-label fs-14 mb-0"><small>Código QR / Etiqueta *</small></label>
                                    <input type="text" class="form-control" id="codigo_qr" name="codigo_qr" maxlength="100" required value="<?php echo htmlspecialchars($producto['codigo_qr']); ?>">
                                </div>
                            </div>

                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label for="cosecha_id" class="form-label fs-14 mb-0"><small>Cosecha de Origen *</small></label>
                                    <select class="form-control" id="cosecha_id" name="cosecha_id" required>
                                        <?php foreach ($cosechas as $c): ?>
                                            <option value="<?php echo $c['id']; ?>" <?php echo ($producto['cosecha_id'] == $c['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($c['codigo_cosecha']); ?> - <?php echo htmlspecialchars($c['variedad_nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="tipo_producto" class="form-label fs-14 mb-0"><small>Tipo de Producto *</small></label>
                                    <select class="form-control" id="tipo_producto" name="tipo_producto" required>
                                        <option value="Flor Seca" <?php echo ($producto['tipo_producto'] == 'Flor Seca') ? 'selected' : ''; ?>>Flor Seca</option>
                                        <option value="Aceite/Tintura" <?php echo ($producto['tipo_producto'] == 'Aceite/Tintura') ? 'selected' : ''; ?>>Aceite/Tintura</option>
                                        <option value="Extracto/Rosine" <?php echo ($producto['tipo_producto'] == 'Extracto/Rosine') ? 'selected' : ''; ?>>Extracto / Rosin</option>
                                        <option value="Preroll" <?php echo ($producto['tipo_producto'] == 'Preroll') ? 'selected' : ''; ?>>Pre-roll</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="estado_inventario" class="form-label fs-14 mb-0"><small>Estado de Inventario *</small></label>
                                    <select class="form-control" id="estado_inventario" name="estado_inventario" required>
                                        <option value="En Curado" <?php echo ($producto['estado_inventario'] == 'En Curado') ? 'selected' : ''; ?>>En Curado</option>
                                        <option value="Disponible" <?php echo ($producto['estado_inventario'] == 'Disponible') ? 'selected' : ''; ?>>Disponible</option>
                                        <option value="Agotado" <?php echo ($producto['estado_inventario'] == 'Agotado') ? 'selected' : ''; ?>>Agotado</option>
                                        <option value="Retirado por Calidad" <?php echo ($producto['estado_inventario'] == 'Retirado por Calidad') ? 'selected' : ''; ?>>Retirado por Calidad</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="gramos_disponibles" class="form-label fs-14 mb-0"><small>Gramos Disponibles *</small></label>
                                    <input type="number" step="0.01" class="form-control" id="gramos_disponibles" name="gramos_disponibles" min="0" max="999999.99" required value="<?php echo $producto['gramos_disponibles']; ?>">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="gramos_reservados" class="form-label fs-14 mb-0"><small>Gramos Reservados</small></label>
                                    <input type="number" step="0.01" class="form-control" id="gramos_reservados" name="gramos_reservados" min="0" max="999999.99" value="<?php echo $producto['gramos_reservados']; ?>">
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="motivo_ajuste" class="form-label fs-14 mb-0"><small>Motivo del ajuste de stock</small></label>
                                    <input type="text" class="form-control" id="motivo_ajuste" name="motivo_ajuste" maxlength="255" placeholder="Obligatorio al cambiar gramos disponibles o reservados">
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
