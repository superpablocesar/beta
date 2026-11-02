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

$sql_count = "SELECT COUNT(*) AS total FROM historial_lotes";
$result_count = $conexion->query($sql_count);
$total_rows = $result_count ? $result_count->fetch_assoc()['total'] : 0;
$total_pages = max(1, (int)ceil($total_rows / $items_per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $items_per_page;
// --- Fin Paginador ---

$sql = "SELECT hl.id, hl.estado_anterior, hl.estado_nuevo, hl.cantidad_afectada, hl.motivo, hl.created_at,
               lc.codigo_lote, v.nombre AS variedad_nombre, o.usuario AS operador_usuario
        FROM historial_lotes hl
        INNER JOIN lotes_cultivo lc ON hl.lote_id = lc.id
        INNER JOIN variedades v ON lc.variedad_id = v.id
        LEFT JOIN operadores o ON hl.usuario_id = o.id
        ORDER BY hl.id DESC 
        LIMIT $offset, $items_per_page";

$result = $conexion->query($sql);
$historiales = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>
    
    <?php include 'historial_lotes_nav.php';?>

    <div class="container-lg">
        <div class="row">
            <?php if (empty($historiales)): ?>
                <div style="min-height:calc(100vh - 12rem)" class="d-flex justify-content-center align-items-center">
                    <div class="d-flex flex-column justify-content-center align-items-center text-center mb-5">
                        <i class="fa-duotone fa-solid fa-clock-rotate-left admin-i-no-light opacity-25 mb-4"></i>
                        <h4 class="ui-f-700 mb-1 text-admin-3">No hay registros de auditoría</h4>
                        <p class="text-admin-3 mb-3">Sin movimientos o cambios de estado registrados en los lotes.</p>
                        <a href="historial_lotes_agregar.php?btn=comenzar<?php echo '&page=' . (isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1); ?>" class="btn btn-dark px-4 py-3 rounded-2">
                            <i class="fa-light fa-square-plus me-1"></i> COMIENZA A REGISTRAR EVENTO
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($historiales as $row): ?>
                    <div class="col-12 mb-2">
                        <a href="historial_lotes_editar.php?id=<?php echo $row['id']; ?>&page=<?php echo $page; ?>" class="card ui-card-list text-decoration-none p-1">
                            <span class="card-body py-2">
                                <div class="row align-items-center">
                                    <div class="col-lg-8">
                                        <span class="fs-5 d-block card-title lh-sm mb-0 clamp-1 ui-f-700">
                                            Lote: <?php echo htmlspecialchars($row['codigo_lote']); ?> 
                                            <span class="badge bg-secondary ms-2 fs-12 position-relative t--t"><?php echo htmlspecialchars($row['estado_anterior']); ?></span> 
                                            <i class="fa-solid fa-arrow-right fs-12 mx-1"></i> 
                                            <span class="badge bg-success fs-12 position-relative t--t"><?php echo htmlspecialchars($row['estado_nuevo']); ?></span>
                                        </span>
                                        <span class="fs-14 d-block card-title lh-sm mb-0 clamp-1">
                                            Variedad: <b><?php echo htmlspecialchars($row['variedad_nombre']); ?></b> | 
                                            Cant. Afectada: <b><?php echo $row['cantidad_afectada']; ?></b> | 
                                            Operador: <b><?php echo htmlspecialchars($row['operador_usuario'] ?: 'No disponible'); ?></b> | 
                                            Motivo: <b><?php echo htmlspecialchars($row['motivo'] ?: 'Sin especificar'); ?></b> | 
                                            Fecha: <b><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></b>
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
