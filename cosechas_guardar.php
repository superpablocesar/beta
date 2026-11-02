<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) {
    header('Location: ./login.php');
    exit();
}

require_once './csrf.php';
require_once './conexion.php';
require_once './validacion_cosechas.php';
require_once './validacion_historial_lotes.php';
require_once './bitacora_helper.php';
require_once './alertify_error.php';
$conexion = conexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cosechas.php');
    exit();
}

validar_token_csrf_post();
$page = max(1, (int)($_POST['page'] ?? 1));

try {
    $conexion->begin_transaction();
    $datos = validar_datos_cosecha($_POST);
    validar_referencias_cosecha($conexion, $datos);

    $operador_id = obtener_operador_sesion($conexion, (string)$_SESSION['usuario']);
    cerrar_lote_por_cosecha($conexion, $datos['lote_id'], $operador_id, $datos['codigo_cosecha']);

    $lote_id = $datos['lote_id'];
    $codigo_cosecha = $datos['codigo_cosecha'];
    $fecha_cosecha = $datos['fecha_cosecha'];
    $peso_fresco_gramos = $datos['peso_fresco_gramos'];
    $peso_seco_gramos = $datos['peso_seco_gramos'];
    $observaciones = $datos['observaciones'];
    $stmt = $conexion->prepare('INSERT INTO cosechas (lote_id, codigo_cosecha, fecha_cosecha, peso_fresco_gramos, peso_seco_gramos, observaciones) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('issdds', $lote_id, $codigo_cosecha, $fecha_cosecha, $peso_fresco_gramos, $peso_seco_gramos, $observaciones);
    $stmt->execute();
    $cosecha_id = (int)$conexion->insert_id;
    $stmt->close();
    registrar_evento_bitacora($conexion, $operador_id, 'cosechas', 'Crear', 'cosechas', $cosecha_id, null, $datos);
    $conexion->commit();
    $conexion->close();

    header('Location: cosechas.php?success=add&page=' . $page);
    exit();
} catch (Throwable $e) {
    $conexion->rollback();
    $conexion->close();
    redirigir_excepcion_alertify($e, 'No se pudo registrar la cosecha: ');
}
