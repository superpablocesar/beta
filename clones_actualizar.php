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
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$pagina = max(1, (int)($_POST['page'] ?? 1));
$estado = is_scalar($_POST['estado'] ?? null) ? trim((string)$_POST['estado']) : '';
$fecha_enraizado = is_scalar($_POST['fecha_enraizado'] ?? null) ? trim((string)$_POST['fecha_enraizado']) : '';
$ubicacion = is_scalar($_POST['ubicacion_actual'] ?? null) ? trim((string)$_POST['ubicacion_actual']) : '';
$observaciones = is_scalar($_POST['observaciones'] ?? null) ? trim((string)$_POST['observaciones']) : '';
$estados = ['Cortado','Enraizando','Enraizado','Trasplantado','Descartado'];
$lote_id = filter_var($_POST['lote_cultivo_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1 || !in_array($estado, $estados, true) || strlen($ubicacion) > 100 || strlen($observaciones) > 255
    || ($fecha_enraizado !== '' && !validar_fecha_clon($fecha_enraizado))) {
    $conexion->close();
    redirigir_error_alertify('Revisa la etapa, la fecha de enraizado y los textos del formulario.');
}
if (in_array($estado, ['Enraizado','Trasplantado'], true) && $fecha_enraizado === '') {
    $conexion->close();
    redirigir_error_alertify('Indica la fecha en que enraizó el clon.');
}
if ($fecha_enraizado !== '' && $fecha_enraizado > date('Y-m-d')) {
    $conexion->close();
    redirigir_error_alertify('La fecha de enraizado no puede estar en el futuro.');
}
$ubicacion = $ubicacion === '' ? null : $ubicacion;
$observaciones = $observaciones === '' ? null : $observaciones;
try {
    $conexion->begin_transaction();
    $stmt = $conexion->prepare('SELECT c.id, c.extraccion_madre_id, c.codigo_clon, c.fecha_corte, c.fecha_enraizado, c.estado, c.ubicacion_actual, c.lote_cultivo_id, c.observaciones, pm.variedad_id FROM clones_esquejes c INNER JOIN extracciones_madre e ON e.id = c.extraccion_madre_id INNER JOIN plantas_madre pm ON pm.id = e.planta_madre_id WHERE c.id = ? FOR UPDATE');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $clon = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$clon) throw new InvalidArgumentException('El clon que intentas actualizar ya no existe.');
    $anteriores = $clon;
    if ($fecha_enraizado !== '' && $fecha_enraizado < $clon['fecha_corte']) {
        throw new InvalidArgumentException('La fecha de enraizado no puede ser anterior al corte.');
    }
    $destino_id = null;
    if ($estado === 'Trasplantado') {
        if (!$lote_id || $lote_id < 1) throw new InvalidArgumentException('Selecciona un lote de destino para el clon trasplantado.');
        $variedad_id = (int)$clon['variedad_id'];
        $lote_actual = $clon['lote_cultivo_id'] === null ? 0 : (int)$clon['lote_cultivo_id'];
        $stmt = $conexion->prepare("SELECT id FROM lotes_cultivo WHERE id = ? AND variedad_id = ? AND (estado_actual NOT IN ('Cosechado','Descartado') OR id = ?) FOR UPDATE");
        $stmt->bind_param('iii', $lote_id, $variedad_id, $lote_actual);
        $stmt->execute();
        $destino = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$destino) throw new InvalidArgumentException('El lote debe tener la misma genética y estar activo.');
        $destino_id = (int)$lote_id;
    }
    $operador_id = obtener_operador_clones_sesion($conexion, (string)$_SESSION['usuario']);
    $fecha_enraizado_db = $fecha_enraizado === '' ? null : $fecha_enraizado;
    $stmt = $conexion->prepare('UPDATE clones_esquejes SET fecha_enraizado = ?, estado = ?, ubicacion_actual = ?, lote_cultivo_id = ?, observaciones = ?, updated_by = ? WHERE id = ?');
    $stmt->bind_param('sssisii', $fecha_enraizado_db, $estado, $ubicacion, $destino_id, $observaciones, $operador_id, $id);
    $stmt->execute();
    $stmt->close();
    $nuevos = ['fecha_enraizado'=>$fecha_enraizado_db, 'estado'=>$estado, 'ubicacion_actual'=>$ubicacion, 'lote_cultivo_id'=>$destino_id, 'observaciones'=>$observaciones];
    registrar_evento_bitacora($conexion, $operador_id, 'clones', 'Actualizar', 'clones_esquejes', (int)$id, $anteriores, $nuevos);
    $conexion->commit();
    $conexion->close();
    header('Location: clones.php?page=' . $pagina);
    exit();
} catch (Throwable $e) {
    $conexion->rollback();
    $conexion->close();
    redirigir_excepcion_alertify($e, 'No se pudo actualizar el seguimiento del clon.');
}
