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

validar_token_csrf_post();
$page = max(1, (int)($_POST['page'] ?? 1));

try {
    $datos = validar_datos_cuota($_POST);
    validar_socio_cuota($conexion, $datos['socio_id']);
    $conexion->begin_transaction();

    $socio_id = $datos['socio_id'];
    $monto = $datos['monto'];
    $concepto = $datos['concepto'];
    $fecha_pago = $datos['fecha_pago'];
    $metodo_pago = $datos['metodo_pago'];
    $estado = $datos['estado'];
    $observaciones = $datos['observaciones'];
    $stmt = $conexion->prepare('INSERT INTO cuotas_socios (socio_id, monto, concepto, fecha_pago, metodo_pago, estado, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('idsssss', $socio_id, $monto, $concepto, $fecha_pago, $metodo_pago, $estado, $observaciones);
    $stmt->execute();
    $cuota_id = (int)$conexion->insert_id;
    $stmt->close();
    registrar_evento_bitacora($conexion, operador_id_bitacora($conexion, (string)$_SESSION['usuario']), 'cuotas', 'Crear', 'cuotas_socios', $cuota_id, null, $datos);
    $conexion->commit();
    $conexion->close();

    header('Location: cuotas.php?success=add&page=' . $page);
    exit();
} catch (Throwable $e) {
    $conexion->close();
    redirigir_excepcion_alertify($e, 'No se pudo registrar el cobro: ');
}
