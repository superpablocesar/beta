<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();

if (empty($_SESSION["usuario"])) {
    header("Location: ./login.php");
    exit();
}

require_once "./conexion.php";
$conexion = conexion();

$items_per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;

$sql_count = "SELECT COUNT(*) AS total FROM cuotas_socios";
$result_count = $conexion->query($sql_count);
$total_rows = $result_count ? $result_count->fetch_assoc()['total'] : 0;
$total_pages = max(1, (int)ceil($total_rows / $items_per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $items_per_page;

$sql = "SELECT cs.id, cs.concepto, cs.monto, cs.fecha_pago, cs.metodo_pago, cs.estado,
               s.nombre AS socio_nombre, s.apellido AS socio_apellido, s.dni
        FROM cuotas_socios cs
        INNER JOIN socios s ON cs.socio_id = s.id
        ORDER BY cs.id DESC 
        LIMIT $offset, $items_per_page";

$result = $conexion->query($sql);
$cuotas = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">
    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>
    <?php include 'cuotas_nav.php';?>

    <div class="container-lg">
        <div class="row">
            <?php if (empty($cuotas)): ?>
                <div style="min-height:calc(100vh - 12rem)" class="d-flex justify-content-center align-items-center">
                    <div class="d-flex flex-column justify-content-center align-items-center text-center mb-5">
                        <i class="fa-duotone fa-solid fa-file-invoice-dollar admin-i-no-light opacity-25 mb-4"></i>
                        <h4 class="ui-f-700 mb-1 text-admin-3">No hay cobros registrados</h4>
                        <p class="text-admin-3 mb-3">Sin cuotas o aportes cargados en el sistema.</p>
                        <a href="cuotas_agregar.php?page=<?php echo $page; ?>" class="btn btn-dark px-4 py-3 rounded-2">
                            <i class="fa-light fa-square-plus me-1"></i> REGISTRAR NUEVO COBRO
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($cuotas as $row): ?>
                    <div class="col-12 mb-2">
                        <a href="cuotas_editar.php?id=<?php echo $row['id']; ?>&page=<?php echo $page; ?>" class="card ui-card-list p-1">
                            <div class="card-body py-2">
                                <div class="row align-items-center">
                                    <div class="col-lg-8">
                                        <span class="fs-5 d-block card-title lh-sm mb-0 clamp-1 ui-f-700">
                                            <?php echo htmlspecialchars($row['socio_apellido'] . ', ' . $row['socio_nombre']); ?> 
                                            <?php $monto_clase = $row['estado'] === 'Completado' ? 'bg-success' : ($row['estado'] === 'Anulado' ? 'bg-danger' : 'bg-warning text-dark'); ?>
                                            <span class="badge <?php echo $monto_clase; ?> ms-2 fs-12">$<?php echo number_format($row['monto'], 2, ',', '.'); ?></span>
                                            <span class="badge bg-secondary ms-1 fs-12"><?php echo htmlspecialchars($row['metodo_pago']); ?></span>
                                            <?php $estado_clase = $row['estado'] === 'Completado' ? 'bg-success' : ($row['estado'] === 'Anulado' ? 'bg-danger' : 'bg-warning text-dark'); ?>
                                            <span class="badge <?php echo $estado_clase; ?> ms-1 fs-12"><?php echo htmlspecialchars($row['estado']); ?></span>
                                        </span>
                                        <span class="fs-14 d-block card-title lh-sm mb-0 clamp-1">
                                            Concepto: <b><?php echo htmlspecialchars($row['concepto']); ?></b> | 
                                            DNI: <b><?php echo htmlspecialchars($row['dni']); ?></b> | 
                                            Fecha: <b><?php echo date('d/m/Y', strtotime($row['fecha_pago'])); ?></b>
                                        </span>
                                    </div>
                                    <div class="col-lg-4 d-flex justify-content-end">                                 
                                        <div class="btn-group pt-0" role="group">
                                            <i class="fa-light fa-chevron-right fs-4 py-2"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
