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
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

    if ($id <= 0) {
        header("Location: extracciones_madre.php?page=" . $page);
        exit();
    }

    $planta_madre_raw = is_scalar($_POST['planta_madre_id'] ?? null) ? trim((string)$_POST['planta_madre_id']) : '';
    $cantidad_raw = is_scalar($_POST['cantidad_esquejes_cortados'] ?? null) ? trim((string)$_POST['cantidad_esquejes_cortados']) : '';
    $planta_madre_id = filter_var($planta_madre_raw, FILTER_VALIDATE_INT);
    $cantidad_esquejes_cortados = filter_var($cantidad_raw, FILTER_VALIDATE_INT);
    $observaciones = is_scalar($_POST['observaciones'] ?? null) ? trim((string)$_POST['observaciones']) : '';

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
        $stmt_extraccion = $conexion->prepare("SELECT e.id, e.planta_madre_id, e.cantidad_esquejes_cortados, e.observaciones FROM extracciones_madre e WHERE e.id = ? LIMIT 1 FOR UPDATE");
        $stmt_extraccion->bind_param("i", $id);
        $stmt_extraccion->execute();
        $extraccion_existente = $stmt_extraccion->get_result()->fetch_assoc();
        $stmt_extraccion->close();
        if (!$extraccion_existente) {
            throw new RuntimeException('La extracción que intentas actualizar ya no existe.');
        }
        $anteriores = $extraccion_existente;

        $stmt_planta = $conexion->prepare("SELECT id FROM plantas_madre WHERE id = ? LIMIT 1");
        $stmt_planta->bind_param("i", $planta_madre_id);
        $stmt_planta->execute();
        $planta_existente = $stmt_planta->get_result()->fetch_assoc();
        $stmt_planta->close();
        if (!$planta_existente) {
            throw new RuntimeException('La planta madre seleccionada no existe.');
        }

        $stmt = $conexion->prepare("UPDATE extracciones_madre SET planta_madre_id = ?, cantidad_esquejes_cortados = ?, observaciones = ? WHERE id = ?");
        $stmt->bind_param("iisi", $planta_madre_id, $cantidad_esquejes_cortados, $observaciones, $id);
        
        $stmt->execute();
        $stmt->close();
        registrar_evento_bitacora($conexion, operador_id_bitacora($conexion, (string)$_SESSION['usuario']), 'extracciones_madre', 'Actualizar', 'extracciones_madre', (int)$id, $anteriores, ['planta_madre_id'=>(int)$planta_madre_id, 'cantidad_esquejes_cortados'=>(int)$cantidad_esquejes_cortados, 'observaciones'=>$observaciones]);
        $conexion->commit();
        $conexion->close();

        header("Location: extracciones_madre.php?success=update&page=" . $page);
        exit();

    } catch (Throwable $e) {
        require_once __DIR__ . '/alertify_error.php';
        redirigir_excepcion_alertify($e, 'Error al actualizar la extracción: ');
    }
} else {
    header("Location: extracciones_madre.php");
    exit();
}
?>
