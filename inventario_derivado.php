<?php
// 1. CONFIGURACIÓN, SESIÓN Y CONEXIÓN
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();

if (empty($_SESSION["usuario"])) {
    header("Location: ./login.php");
    exit();
}

require_once "./conexion.php";
$conexion = conexion();

// --- Paginador ---
$items_per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

$sql_count = "SELECT COUNT(*) AS total FROM inventario_derivado";
$result_count = $conexion->query($sql_count);
$total_rows = $result_count ? $result_count->fetch_assoc()['total'] : 0;
$total_pages = max(1, (int)ceil($total_rows / $items_per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $items_per_page;
// --- Fin Paginador ---

$sql = "SELECT id.id, id.codigo_qr, id.tipo_producto, id.gramos_disponibles, id.gramos_reservados, id.estado_inventario, id.created_at,
               c.codigo_cosecha, v.nombre AS variedad_nombre
        FROM inventario_derivado id
        INNER JOIN cosechas c ON id.cosecha_id = c.id
        INNER JOIN lotes_cultivo lc ON c.lote_id = lc.id
        INNER JOIN variedades v ON lc.variedad_id = v.id
        ORDER BY id.id DESC 
        LIMIT $offset, $items_per_page";

$result = $conexion->query($sql);
$inventario = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>
    
    <?php include 'inventario_derivado_nav.php';?>

    <div class="container-lg">
        <div class="row">
            <?php if (empty($inventario)): ?>
                <div style="min-height:calc(100vh - 12rem)" class="d-flex justify-content-center align-items-center">
                    <div class="d-flex flex-column justify-content-center align-items-center text-center mb-5">
                        <i class="fa-duotone fa-solid fa-jar-wheat admin-i-no-light opacity-25 mb-4"></i>
                        <h4 class="ui-f-700 mb-1 text-admin-3">No hay productos en inventario derivado</h4>
                        <p class="text-admin-3 mb-3">Sin productos finales o frascos registrados en el sistema.</p>
                        <a href="inventario_derivado_agregar.php?btn=comenzar<?php echo '&page=' . (isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1); ?>" class="btn btn-dark px-4 py-3 rounded-2">
                            <i class="fa-light fa-square-plus me-1"></i> COMIENZA A CREAR PRODUCTOS
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($inventario as $row): ?>
                    <div class="col-12 mb-2">
                        <a href="inventario_derivado_editar.php?id=<?php echo $row['id']; ?>&page=<?php echo $page; ?>" class="card ui-card-list text-decoration-none p-1">
                            <span class="card-body py-2">
                                <div class="row align-items-center">
                                    <div class="col-lg-8">
                                        <span class="fs-5 d-block card-title lh-sm mb-0 clamp-1 ui-f-700">
                                            <?php echo htmlspecialchars($row['codigo_qr']); ?> 
                                            <span class="badge bg-success ms-2 fs-12 position-relative t--t"><?php echo htmlspecialchars($row['estado_inventario']); ?></span>
                                            <span class="badge bg-secondary ms-1 fs-12 position-relative t--t"><?php echo htmlspecialchars($row['tipo_producto']); ?></span>
                                        </span>
                                        <span class="fs-14 d-block card-title lh-sm mb-0 clamp-1">
                                            Variedad: <b><?php echo htmlspecialchars($row['variedad_nombre']); ?></b> | 
                                            Cosecha: <b><?php echo htmlspecialchars($row['codigo_cosecha']); ?></b> | 
                                            Disponible: <b><?php echo $row['gramos_disponibles']; ?> g</b> | 
                                            Reservado: <b><?php echo $row['gramos_reservados']; ?> g</b>
                                        </span>
                                    </div>
                                    <div class="col-lg-4 d-flex justify-content-end">                                 
                                        <div class="btn-group pt-0" role="group" aria-label="Opciones">
                                            <i class="fa-light fa-chevron-right fs-4 py-2"></i>
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
