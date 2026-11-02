<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) {
    header('Location: ./login.php');
    exit();
}

require_once './csrf.php';
require_once './conexion.php';
require_once './validacion_cuotas.php';
require_once './alertify_error.php';
require_once __DIR__ . '/bitacora_helper.php';
$conexion = conexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cuotas.php');
    exit();
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
validar_token_csrf_post();
$page = max(1, (int)($_POST['page'] ?? 1));
if (!$id || $id < 1) {
    redirigir_error_alertify('El identificador del cobro no es válido.');
}

try {
    $datos = validar_datos_cuota($_POST);
    validar_socio_cuota($conexion, $datos['socio_id']);
    $conexion->begin_transaction();

    $stmt_existente = $conexion->prepare('SELECT socio_id, monto, concepto, fecha_pago, metodo_pago, estado, observaciones FROM cuotas_socios WHERE id = ? LIMIT 1 FOR UPDATE');
    $stmt_existente->bind_param('i', $id);
    $stmt_existente->execute();
    $existente = $stmt_existente->get_result()->fetch_assoc();
    $stmt_existente->close();
    if (!$existente) {
        throw new InvalidArgumentException('El cobro que intentas actualizar ya no existe.');
    }
    $anteriores = $existente;

    $socio_id = $datos['socio_id'];
    $monto = $datos['monto'];
    $concepto = $datos['concepto'];
    $fecha_pago = $datos['fecha_pago'];
    $metodo_pago = $datos['metodo_pago'];
    $estado = $datos['estado'];
    $observaciones = $datos['observaciones'];
    $updated_at = date('Y-m-d H:i:s');
    $stmt = $conexion->prepare('UPDATE cuotas_socios SET socio_id = ?, monto = ?, concepto = ?, fecha_pago = ?, metodo_pago = ?, estado = ?, observaciones = ?, updated_at = ? WHERE id = ?');
    $stmt->bind_param('idssssssi', $socio_id, $monto, $concepto, $fecha_pago, $metodo_pago, $estado, $observaciones, $updated_at, $id);
    $stmt->execute();
    $stmt->close();
    registrar_evento_bitacora($conexion, operador_id_bitacora($conexion, (string)$_SESSION['usuario']), 'cuotas', 'Actualizar', 'cuotas_socios', (int)$id, $anteriores, $datos);
    $conexion->commit();
    $conexion->close();

    header('Location: cuotas.php?success=update&page=' . $page);
    exit();
} catch (Throwable $e) {
    $conexion->close();
    redirigir_excepcion_alertify($e, 'No se pudo actualizar el cobro: ');
}
