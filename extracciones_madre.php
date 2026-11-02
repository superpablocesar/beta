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

$sql_count = "SELECT COUNT(*) AS total FROM extracciones_madre";
$result_count = $conexion->query($sql_count);
$total_rows = $result_count ? $result_count->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_rows / $items_per_page);
// --- Fin Paginador ---

$sql = "SELECT em.id, em.cantidad_esquejes_cortados, em.observaciones, em.created_at,
               pm.codigo_planta, v.nombre AS variedad_nombre, o.usuario AS operador
        FROM extracciones_madre em
        INNER JOIN plantas_madre pm ON em.planta_madre_id = pm.id
        INNER JOIN variedades v ON pm.variedad_id = v.id
        LEFT JOIN operadores o ON em.usuario_id = o.id
        ORDER BY em.id DESC 
        LIMIT $offset, $items_per_page";

$result = $conexion->query($sql);
$extracciones = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>
    
    <?php include 'extracciones_madre_nav.php';?>

    <div class="container-lg">
        <div class="row">
            <?php if (empty($extracciones)): ?>
                <div style="min-height:calc(100vh - 12rem)" class="d-flex justify-content-center align-items-center">
                    <div class="d-flex flex-column justify-content-center align-items-center text-center mb-5">
                        <i class="fa-duotone fa-solid fa-scissors admin-i-no-light opacity-25 mb-4"></i>
                        <h4 class="ui-f-700 mb-1 text-admin-3">No hay extracciones registradas</h4>
                        <p class="text-admin-3 mb-3">Sin cortes de esquejes registrados en el sistema.</p>
                        <a href="extracciones_madre_agregar.php?btn=comenzar<?php echo '&page=' . (isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1); ?>" class="btn btn-dark px-4 py-3 rounded-2">
                            <i class="fa-light fa-square-plus me-1"></i> COMIENZA A REGISTRAR EXTRACCIONES
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($extracciones as $row): ?>
                    <div class="col-12 mb-2">
                        <a href="extracciones_madre_editar.php?id=<?php echo $row['id']; ?>&page=<?php echo $page; ?>" class="card ui-card-list text-decoration-none p-1">
                            <span class="card-body py-2">
                                <div class="row align-items-center">
                                    <div class="col-lg-8">
                                        <span class="fs-5 d-block card-title lh-sm mb-0 clamp-1 ui-f-700">
                                            Madre: <?php echo htmlspecialchars($row['codigo_planta']); ?> 
                                            <span class="badge bg-success ms-2 fs-12 position-relative t--t"><?php echo htmlspecialchars($row['cantidad_esquejes_cortados']); ?> Esquejes</span>
                                        </span>
                                        <span class="fs-14 d-block card-title lh-sm mb-0 clamp-1">
                                            Variedad: <b><?php echo htmlspecialchars($row['variedad_nombre']); ?></b> | 
                                            Fecha: <b><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></b> | 
                                            Operador: <b><?php echo htmlspecialchars($row['operador'] ?? 'No identificado'); ?></b> | 
                                            Obs: <b><?php echo htmlspecialchars($row['observaciones'] ?: 'Sin observaciones'); ?></b>
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
