<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION["usuario"])) {
    header("Location: ./login.php");
    exit();
}

require_once './csrf.php';
require_once "./conexion.php";
require_once __DIR__ . '/bitacora_helper.php';
$conexion = conexion();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validar_token_csrf_post();
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $telefono = !empty($_POST['telefono']) ? trim($_POST['telefono']) : null;
    $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
    $reprocann_codigo = trim($_POST['reprocann_codigo'] ?? '');
    
    $reprocann_vencimiento = trim($_POST['reprocann_vencimiento'] ?? '');

    $limite_gramos_mensual = isset($_POST['limite_gramos_mensual']) ? (float)$_POST['limite_gramos_mensual'] : 40.00;
    $estado_membresia = $_POST['estado_membresia'] ?? 'Activo';
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

    if ($reprocann_vencimiento === '') {
        http_response_code(422);
        require_once __DIR__ . '/alertify_error.php';
        redirigir_error_alertify('La fecha de vencimiento REPROCANN es obligatoria.');
    }

    try {
        $conexion->begin_transaction();
        $stmt = $conexion->prepare("INSERT INTO socios (nombre, apellido, dni, telefono, email, reprocann_codigo, reprocann_vencimiento, limite_gramos_mensual, estado_membresia) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssds", $nombre, $apellido, $dni, $telefono, $email, $reprocann_codigo, $reprocann_vencimiento, $limite_gramos_mensual, $estado_membresia);
        $stmt->execute();
        $socio_id = (int)$conexion->insert_id;
        $stmt->close();
        registrar_evento_bitacora($conexion, operador_id_bitacora($conexion, (string)$_SESSION['usuario']), 'socios', 'Crear', 'socios', $socio_id, null, ['limite_gramos_mensual'=>$limite_gramos_mensual, 'estado_membresia'=>$estado_membresia]);
        $conexion->commit();

        $conexion->close();

        header("Location: socios.php?success=add&page=" . $page);
        exit();

    } catch (Exception $e) {
        require_once __DIR__ . '/alertify_error.php';
        redirigir_excepcion_alertify($e, '<strong>Error al guardar el socio:</strong> ');
        echo "</div>";
    }
} else {
    header("Location: socios.php");
    exit();
}
?>
