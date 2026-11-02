<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once './csrf.php';
require_once './conexion.php';
require_once './validacion_clones.php';
require_once './alertify_error.php';
require_once __DIR__ . '/bitacora_helper.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: clones.php'); exit(); }
validar_token_csrf_post();
$conexion = conexion();
$extraccion_id = filter_var($_POST['extraccion_id'] ?? null, FILTER_VALIDATE_INT);
$cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT);
$fecha_corte = is_scalar($_POST['fecha_corte'] ?? null) ? trim((string)$_POST['fecha_corte']) : '';
$ubicacion = is_scalar($_POST['ubicacion_actual'] ?? null) ? trim((string)$_POST['ubicacion_actual']) : '';
$observaciones = is_scalar($_POST['observaciones'] ?? null) ? trim((string)$_POST['observaciones']) : '';
if (!$extraccion_id || $extraccion_id < 1 || !$cantidad || $cantidad < 1 || $cantidad > 200
    || !validar_fecha_clon($fecha_corte) || strlen($ubicacion) > 100 || strlen($observaciones) > 255) {
    $conexion->close();
    redirigir_error_alertify('Revisa la extracción, la cantidad (máximo 200), la fecha y los textos del formulario.');
}
$ubicacion = $ubicacion === '' ? null : $ubicacion;
$observaciones = $observaciones === '' ? null : $observaciones;

try {
    $conexion->begin_transaction();
    $stmt = $conexion->prepare('SELECT e.cantidad_esquejes_cortados FROM extracciones_madre e WHERE e.id = ? FOR UPDATE');
    $stmt->bind_param('i', $extraccion_id);
    $stmt->execute();
    $extraccion = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$extraccion) throw new InvalidArgumentException('La extracción seleccionada no existe.');

    $stmt = $conexion->prepare('SELECT COUNT(*) AS registrados FROM clones_esquejes WHERE extraccion_madre_id = ?');
    $stmt->bind_param('i', $extraccion_id);
    $stmt->execute();
    $registrados = (int)$stmt->get_result()->fetch_assoc()['registrados'];
    $stmt->close();
    $pendientes = (int)$extraccion['cantidad_esquejes_cortados'] - $registrados;
    if ($cantidad > $pendientes) throw new InvalidArgumentException('La cantidad supera los esquejes pendientes de esta extracción.');

    $operador_id = obtener_operador_clones_sesion($conexion, (string)$_SESSION['usuario']);
    $estado = 'Cortado';
    $stmt = $conexion->prepare('INSERT INTO clones_esquejes (extraccion_madre_id, codigo_clon, fecha_corte, estado, ubicacion_actual, observaciones, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $clones_creados = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $codigo = 'CL-' . $extraccion_id . '-' . strtoupper(bin2hex(random_bytes(6)));
        $stmt->bind_param('isssssi', $extraccion_id, $codigo, $fecha_corte, $estado, $ubicacion, $observaciones, $operador_id);
        $stmt->execute();
        $clones_creados[] = ['id'=>(int)$conexion->insert_id, 'codigo_clon'=>$codigo];
    }
    $stmt->close();
    registrar_evento_bitacora($conexion, $operador_id, 'clones', 'Crear', 'clones_esquejes_lote', null, null, ['extraccion_madre_id'=>(int)$extraccion_id, 'cantidad'=>$cantidad, 'fecha_corte'=>$fecha_corte, 'estado'=>$estado, 'clones'=>$clones_creados]);
    $conexion->commit();
    $conexion->close();
    header('Location: clones.php?success=add');
    exit();
} catch (Throwable $e) {
    $conexion->rollback();
    $conexion->close();
    redirigir_excepcion_alertify($e, 'No se pudieron registrar los clones.');
}
