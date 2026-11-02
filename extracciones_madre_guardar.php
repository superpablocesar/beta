<?php
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
    $usuario = (string)$_SESSION['usuario'];
    $planta_madre_raw = is_scalar($_POST['planta_madre_id'] ?? null) ? trim((string)$_POST['planta_madre_id']) : '';
    $cantidad_raw = is_scalar($_POST['cantidad_esquejes_cortados'] ?? null) ? trim((string)$_POST['cantidad_esquejes_cortados']) : '';
    $planta_madre_id = filter_var($planta_madre_raw, FILTER_VALIDATE_INT);
    $cantidad_esquejes_cortados = filter_var($cantidad_raw, FILTER_VALIDATE_INT);
    $observaciones = is_scalar($_POST['observaciones'] ?? null) ? trim((string)$_POST['observaciones']) : '';
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

    if (!$planta_madre_id || $planta_madre_id < 1 || !$cantidad_esquejes_cortados
        || $cantidad_esquejes_cortados < 1 || $cantidad_esquejes_cortados > 2147483647) {
        require_once __DIR__ . '/alertify_error.php';
        redirigir_error_alertify('Selecciona una planta madre válida e indica al menos un esqueje.');
    }
    if (strlen($observaciones) > 255) {
        require_once __DIR__ . '/alertify_error.php';
        redirigir_error_alertify('Las observaciones no pueden superar los 255 caracteres.');
    }

    try {
        $conexion->begin_transaction();
        $stmt_operador = $conexion->prepare("SELECT id FROM operadores WHERE usuario = ? LIMIT 1");
        $stmt_operador->bind_param("s", $usuario);
        $stmt_operador->execute();
        $operador = $stmt_operador->get_result()->fetch_assoc();
        $stmt_operador->close();
        if (!$operador) {
            throw new RuntimeException('No se pudo identificar al operador de la sesión.');
        }
        $usuario_id = (int)$operador['id'];

        $stmt_planta = $conexion->prepare("SELECT id FROM plantas_madre WHERE id = ? AND estado = 'Activa' LIMIT 1 FOR UPDATE");
        $stmt_planta->bind_param("i", $planta_madre_id);
        $stmt_planta->execute();
        $planta_activa = $stmt_planta->get_result()->fetch_assoc();
        $stmt_planta->close();
        if (!$planta_activa) {
            throw new RuntimeException('La planta madre no existe o no está activa.');
        }

        $stmt = $conexion->prepare("INSERT INTO extracciones_madre (planta_madre_id, usuario_id, cantidad_esquejes_cortados, observaciones) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $planta_madre_id, $usuario_id, $cantidad_esquejes_cortados, $observaciones);
        
        $stmt->execute();
        $extraccion_id = (int)$conexion->insert_id;
        $stmt->close();
        registrar_evento_bitacora($conexion, $usuario_id, 'extracciones_madre', 'Crear', 'extracciones_madre', $extraccion_id, null, ['planta_madre_id'=>(int)$planta_madre_id, 'cantidad_esquejes_cortados'=>(int)$cantidad_esquejes_cortados, 'observaciones'=>$observaciones]);
        $conexion->commit();
        $conexion->close();

        header("Location: extracciones_madre.php?success=add&page=" . $page);
        exit();

    } catch (Throwable $e) {
        require_once __DIR__ . '/alertify_error.php';
        redirigir_excepcion_alertify($e, 'Error al guardar la extracción: ');
    }
} else {
    header("Location: extracciones_madre.php");
    exit();
}
?>
