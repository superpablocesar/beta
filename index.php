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

$sql = "SELECT id, usuario FROM operadores";
$result = $conexion->query($sql);
$operadores = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<?php include 'head.php'; ?>
<body class="ui-bg-2">

    <?php include 'loader.php';?>
    <?php include 'index_nav.php';?>
    
    <div class="container-lg">
        <div class="row mt-4 pt-1">

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./variedades.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-chart-network my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-microscope my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Genéticas</span></div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Listado de variantes</small></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./plantas_madre.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-cannabis my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-clipboard-list my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Plantas Madre</span></div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Registro de ejemplares</small></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./clones.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-eye-dropper my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-leaf my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Clones</span></div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Seguimiento de esquejes</small></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./banco_de_semillas.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-seedling my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-building-columns my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Banco de semillas</span></div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Registro y seguimiento</small></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./lotes_cultivo.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-trees my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-square-dashed my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Lotes de cultivo</span></div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Registro y seguimiento</small></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./historial_lotes.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-clipboard-list my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-clock-rotate-left my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Historial de lotes</span></div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Listado de registros</small></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./cosechas.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-flower-tulip my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-scissors my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Cosechas realizadas</span></div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Registros de eventos</small></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./inventario_derivado.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-flower my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-boxes my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Inventario derivado</span></div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Listado de tenencias</small></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./movimientos_inventario.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-clock-rotate-left my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span><i class="fad fa-arrow-right-arrow-left my-2 ui-text"></i></span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Movimientos</span></div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Trazabilidad del inventario</small></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./socios.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-address-card my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-circle-user my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Beneficiarios</span></div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Listado de socios suscriptos</small></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./dispensacion.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-badge-check my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-bag-seedling my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Dipensario</span></div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Registro de entregas</small></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./cuotas.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-memo-circle-check my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-money-check-dollar my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Cuotas</span></div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Registro de cumplimiento</small></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./control_calidad.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-clipboard-check my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-medal my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2"><div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Control de calidad</span></div><div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Inspecciones de cosechas y productos</small></div></div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./tareas_cultivo.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-calendar-days my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-clipboard-list my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0">
                                <span>Tareas de cultivo</span>
                            </div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2">
                                <small>Agenda y seguimiento por lote</small>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./reportes.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-table my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-file-xls my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0"><span>Reportes</span></div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2"><small>Descargas en excel</small></div>
                        </div>
                    </div>
                </a>
            </div>

            <?php if (($_SESSION['rol'] ?? '') === 'administrador'): ?>
            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./operadores_permisos.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-users-gear my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-cogs my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0">
                                <span>Operadores y permisos</span>
                            </div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2">
                                <small>Asignar roles de acceso</small>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3 position-relative">
                <a class="card ui-card" href="./bitacora.php">
                    <div class="card-body px-4 py-3">
                        <i class="fad fa-clipboard-list my-2 ui-text ui-card-i-fnd"></i>
                        <div>
                            <span>
                                <i class="fad fa-rectangle-history-circle-user my-2 ui-text"></i>
                            </span>
                        </div>
                        <div class="lh-sm pt-2">
                            <div class="card-title fs-5 ui-text ui-f-500 mb-0">
                                <span>Bitácora</span>
                            </div>
                            <div class="card-text fs-6 ui-text ui-f-700 mb-2">
                                <small>Auditoría de cambios y operadores</small>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
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
