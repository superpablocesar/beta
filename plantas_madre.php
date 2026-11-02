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
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

$sql_count = "SELECT COUNT(*) AS total FROM plantas_madre";
$result_count = $conexion->query($sql_count);
$total_rows = $result_count ? $result_count->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_rows / $items_per_page);
// --- Fin Paginador ---

$sql = "SELECT pm.id, pm.codigo_planta, pm.ubicacion_sala, pm.estado, pm.fecha_nacimiento, pm.created_at,
               v.nombre AS variedad_nombre, v.tipo AS variedad_tipo
        FROM plantas_madre pm
        INNER JOIN variedades v ON pm.variedad_id = v.id
        ORDER BY pm.codigo_planta ASC 
        LIMIT $offset, $items_per_page";

$result = $conexion->query($sql);
$plantas_madre = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>
    
    <?php include 'plantas_madre_nav.php';?>

    <div class="container-lg">
        <div class="row">
            <?php if (empty($plantas_madre)): ?>
                <div style="min-height:calc(100vh - 12rem)" class="d-flex justify-content-center align-items-center">
                    <div class="d-flex flex-column justify-content-center align-items-center text-center mb-5">
                        <i class="fa-duotone fa-solid fa-seedling admin-i-no-light opacity-25 mb-4"></i>
                        <h4 class="ui-f-700 mb-1 text-admin-3">No hay plantas madre para mostrar</h4>
                        <p class="text-admin-3 mb-3">Sin genéticas madre registradas en el sistema.</p>
                        <a href="plantas_madre_agregar.php?btn=comenzar<?php echo '&page=' . (isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1); ?>" class="btn btn-dark px-4 py-3 rounded-2">
                            <i class="fa-light fa-square-plus me-1"></i> COMIENZA A AGREGAR PLANTAS MADRE
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($plantas_madre as $row): ?>
                    <div class="col-12 mb-2">
                        <a href="plantas_madre_editar.php?id=<?php echo $row['id']; ?>&page=<?php echo $page; ?>" class="card ui-card-list text-decoration-none p-1">
                            <span class="card-body py-2">
                                <div class="row align-items-center">
                                    <div class="col-lg-8">
                                        <span class="fs-5 d-block card-title lh-sm mb-0 clamp-1 ui-f-700">
                                            <?php echo htmlspecialchars($row['codigo_planta']); ?> 
                                            <span class="badge bg-success ms-2 fs-12 position-relative t--t"><?php echo htmlspecialchars($row['estado']); ?></span>
                                        </span>
                                        <span class="fs-14 d-block card-title lh-sm mb-0 clamp-1">
                                            Variedad: <b><?php echo htmlspecialchars($row['variedad_nombre']); ?></b> (<?php echo htmlspecialchars($row['variedad_tipo']); ?>) | 
                                            Sala: <b><?php echo htmlspecialchars($row['ubicacion_sala'] ?: 'No asignada'); ?></b> | 
                                            Nacimiento: <b><?php echo $row['fecha_nacimiento'] ? date('d/m/Y', strtotime($row['fecha_nacimiento'])) : 'N/D'; ?></b>
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