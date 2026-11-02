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

require_once "./conexion.php";
$conexion = conexion();

$sql_op = "SELECT id, usuario FROM operadores";
$result_op = $conexion->query($sql_op);
$operadores = $result_op ? $result_op->fetch_all(MYSQLI_ASSOC) : [];

// --- Paginador ---
$items_per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;

$sql_count = "SELECT COUNT(*) AS total FROM banco_semillas";
$result_count = $conexion->query($sql_count);
$total_rows = $result_count ? $result_count->fetch_assoc()['total'] : 0;
$total_pages = max(1, (int)ceil($total_rows / $items_per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $items_per_page;
// --- Fin Paginador ---

// Consulta principal para traer semillas, variedades y plantas madre (padre/madre)
$sql = "SELECT bs.id, bs.tipo_material, bs.origen_tipo, bs.origen_proveedor, bs.numero_lote_origen, 
               bs.fecha_polinizacion, bs.cantidad_disponible, bs.ubicacion_almacen,
               v.nombre AS variedad_nombre, v.tipo AS variedad_tipo,
               pm.codigo_planta AS planta_madre_codigo,
               pp.codigo_planta AS planta_padre_codigo
        FROM banco_semillas bs
        INNER JOIN variedades v ON bs.variedad_id = v.id
        LEFT JOIN plantas_madre pm ON bs.planta_madre_id = pm.id
        LEFT JOIN plantas_madre pp ON bs.planta_padre_id = pp.id
        ORDER BY bs.id DESC 
        LIMIT $offset, $items_per_page";

$result = $conexion->query($sql);
$semillas = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="es">

<?php include 'head.php'; ?>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>

    <?php include 'banco_de_semillas_nav.php';?>

    <div class="container-lg pb-4">
        <div class="row">
            <?php if (empty($semillas)): ?>
                <div style="min-height:calc(100vh - 12rem)" class="d-flex justify-content-center align-items-center">
                    <div class="d-flex flex-column justify-content-center align-items-center text-center mb-5">
                        <i class="fa-duotone fa-solid fa-seedling admin-i-no-light opacity-25 mb-4"></i>

                        <h4 class="ui-f-700 mb-1 text-admin-3">No hay semillas para mostrar</h4>

                        <p class="text-admin-3 mb-3">
                            Sin registros en el banco de semillas o inventario de propagación.
                        </p>

                        <a href="banco_de_semillas_agregar.php?btn=comenzar<?php echo '&page=' . (isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1); ?>" class="btn btn-dark px-4 py-3 rounded-2">
                            <i class="fa-light fa-square-plus me-1"></i>
                            COMIENZA A CARGAR SEMILLAS
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($semillas as $row): ?>
                    <div class="col-12 mb-2">
                        <a href="banco_de_semillas_editar.php?id=<?php echo $row['id']; ?>&page=<?php echo isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1; ?>" class="card ui-card-list text-decoration-none p-1">
                            <span class="card-body py-2">
                                <div class="row align-items-center">
                                    <div class="col-lg-8">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fs-5 d-block card-title lh-sm mb-0 ui-f-700"><?php echo htmlspecialchars($row['variedad_nombre']); ?></span>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($row['variedad_tipo']); ?></span>
                                            <span class="badge bg-success"><?php echo htmlspecialchars($row['tipo_material']); ?></span>
                                        </div>
                                        <span class="fs-14 d-block card-title lh-sm my-1">
                                            <strong>Origen:</strong> <?php echo ($row['origen_tipo'] === 'Compra_Externa') ? 'Compra Externa (Prov: ' . htmlspecialchars($row['origen_proveedor']) . ' - Lote: ' . htmlspecialchars($row['numero_lote_origen']) . ')' : 'Producción Propia (Madre: ' . htmlspecialchars($row['planta_madre_codigo'] ?? 'N/A') . ' / Padre: ' . htmlspecialchars($row['planta_padre_codigo'] ?? 'N/A') . ')'; ?>
                                        </span>
                                        <span class="fs-14 d-block card-title lh-sm mb-0 text-uppercase fw-900">
                                            Stock: <?php echo $row['cantidad_disponible']; ?> un. | Ubicación: <?php echo htmlspecialchars($row['ubicacion_almacen'] ?: 'No especificada'); ?>
                                        </span>
                                    </div>
                                    <div class="col-lg-4 d-flex justify-content-end align-items-center">                                 
                                        <div class="btn-group pt-0" role="group" aria-label="Opciones">
                                            <i class="fa-light fa-chevron-right fs-5 py-2"></i>
                                        </div>
                                    </div>
                                </div>
                            </span>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

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
