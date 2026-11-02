<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION["usuario"])) {
    header("Location: ./login.php");
    exit();
}

require_once "./conexion.php";
$conexion = conexion();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($id <= 0) {
    header("Location: plantas_madre.php?page=" . $page);
    exit();
}

// Consulta uniendo la tabla variedades para extraer todos sus atributos
$stmt = $conexion->prepare("SELECT pm.id, pm.codigo_planta, pm.ubicacion_sala, pm.estado, pm.fecha_nacimiento, 
                                   v.nombre AS variedad_nombre, v.tipo AS variedad_tipo, 
                                   v.porcentaje_thc_estimado, v.porcentaje_cbd_estimado, v.dias_floracion_estimados
                            FROM plantas_madre pm 
                            INNER JOIN variedades v ON pm.variedad_id = v.id 
                            WHERE pm.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: plantas_madre.php?page=" . $page);
    exit();
}

$planta = $result->fetch_assoc();
$stmt->close();

$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$url_qr_destino = "{$protocolo}://{$host}/visor.php?id=" . $planta['id'];
$api_qr = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=15&data=" . urlencode($url_qr_destino);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'head.php'; ?>
</head>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>

    <nav class="navbar expand navbar-dark" aria-label="Nav">
        <div class="container-lg px-3">
            <a class="navbar-brand" href="plantas_madre.php?page=<?php echo $page; ?>">
                <div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver al Listado</div>
            </a>
            <div class="navbar-brand">
                <div class="fs-14 text-admin-3 ui-f-700 mb-0">Vista Pública / Visor de Planta</div>
            </div>
        </div>
    </nav>

    <div class="container pb-4 pt-3">
        <div class="row justify-content-center">
            <!-- Tarjeta con el Volcado de Datos -->
            <div class="col-lg-8">
                <div class="card py-3 px-4 mb-3">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="mb-3 border-bottom pb-2">
                                <span class="d-block fs-12 text-muted uppercase">Código de Planta / QR</span>
                                <span class="fs-5 ui-f-700 text-admin-3"><?php echo htmlspecialchars($planta['codigo_planta']); ?></span>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="mb-3 border-bottom pb-2">
                                <span class="d-block fs-12 text-muted uppercase">Variedad / Genética</span>
                                <span class="fs-16 ui-f-700 text-admin-3">
                                    <?php echo htmlspecialchars($planta['variedad_nombre']); ?> 
                                    <span class="badge bg-secondary ms-1 fs-12"><?php echo htmlspecialchars($planta['variedad_tipo']); ?></span>
                                </span>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="mb-3 border-bottom pb-2">
                                <span class="d-block fs-12 text-muted uppercase">THC</span>
                                <span class="fs-15 text-admin-3"><?php echo $planta['porcentaje_thc_estimado'] !== null ? $planta['porcentaje_thc_estimado'] . '%' : 'N/D'; ?></span>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="mb-3 border-bottom pb-2">
                                <span class="d-block fs-12 text-muted uppercase">CBD</span>
                                <span class="fs-15 text-admin-3"><?php echo $planta['porcentaje_cbd_estimado'] !== null ? $planta['porcentaje_cbd_estimado'] . '%' : 'N/D'; ?></span>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="mb-3 border-bottom pb-2">
                                <span class="d-block fs-12 text-muted uppercase">Floración</span>
                                <span class="fs-15 text-admin-3"><?php echo $planta['dias_floracion_estimados'] !== null ? $planta['dias_floracion_estimados'] . ' días' : 'N/D'; ?></span>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="mb-3 border-bottom pb-2">
                                <span class="d-block fs-12 text-muted uppercase">Ubicación en Sala</span>
                                <span class="fs-15 text-admin-3"><?php echo htmlspecialchars($planta['ubicacion_sala'] ?: 'No asignada'); ?></span>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="mb-3 border-bottom pb-2">
                                <span class="d-block fs-12 text-muted uppercase">Estado Actual</span>
                                <span class="badge bg-success fs-13 mt-1"><?php echo htmlspecialchars($planta['estado']); ?></span>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="mb-3 border-bottom pb-2">
                                <span class="d-block fs-12 text-muted uppercase">Fecha de Nacimiento / Inicio</span>
                                <span class="fs-15 text-admin-3"><?php echo $planta['fecha_nacimiento'] ? date('d/m/Y', strtotime($planta['fecha_nacimiento'])) : 'No registrada'; ?></span>
                            </div>
                        </div>
                    </div>   
                </div>
            </div>  

            <!-- Tarjeta de Visualización del Código QR -->
            <div class="col-lg-4">
                <div class="card py-3 px-4 text-center">
                    <h6 class="ui-f-700 text-admin-3 mb-2"><small>QR de Acceso Directo</small></h6>
                    <div class="my-2">
                        <img src="<?php echo $api_qr; ?>" alt="Código QR Planta Madre" class="img-fluid bg-white p-2 rounded border" style="max-width: 100%;">
                    </div>
                    <small class="text-muted d-block mt-2 text-break" style="font-size: 11px;"><?php echo htmlspecialchars($url_qr_destino); ?></small>
                    <a href="<?php echo $api_qr; ?>" target="_blank" download="QR_Planta_<?php echo htmlspecialchars($planta['codigo_planta']); ?>.png" class="btn btn-sm btn-outline-secondary mt-3">
                        <i class="fa-light fa-download me-1"></i> Descargar QR
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://buho.pm/admin/js/jquery.min.js"></script>
    <script>
        document.body.style.overflow = "hidden";
        $(document).ready(function() {
            setTimeout(function() {
                $(".ui-loader").fadeOut(function() {
                    $(".ui-loader-bg").fadeOut().addClass("sale");
                    document.body.style.overflow = "auto";
                });
            }, 250);
        });
    </script>
</body>
</html>