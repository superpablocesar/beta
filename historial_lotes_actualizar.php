<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once './csrf.php';
require_once './conexion.php';
require_once './validacion_historial_lotes.php';
require_once './bitacora_helper.php';
require_once './alertify_error.php';
$conexion = conexion();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: historial_lotes.php'); exit(); }
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
validar_token_csrf_post();
$page = max(1, (int)($_POST['page'] ?? 1));
if (!$id || $id < 1) redirigir_error_alertify('El identificador del historial no es válido.');
try {
    $conexion->begin_transaction();
    $datos = validar_datos_historial_lote($_POST);
    validar_referencia_lote_historial($conexion, $datos['lote_id']);
    $existe = $conexion->prepare('SELECT * FROM historial_lotes WHERE id=? FOR UPDATE');
    $existe->bind_param('i', $id); $existe->execute(); $fila = $existe->get_result()->fetch_assoc(); $existe->close();
    if (!$fila) throw new InvalidArgumentException('El evento de historial ya no existe.');
    $stmt = $conexion->prepare('UPDATE historial_lotes SET lote_id=?, estado_anterior=?, estado_nuevo=?, cantidad_afectada=?, motivo=? WHERE id=?');
    $stmt->bind_param('issisi', $datos['lote_id'], $datos['estado_anterior'], $datos['estado_nuevo'], $datos['cantidad_afectada'], $datos['motivo'], $id);
    $stmt->execute(); $stmt->close();
    $nuevo = $conexion->prepare('SELECT * FROM historial_lotes WHERE id=?');
    $nuevo->bind_param('i', $id); $nuevo->execute(); $fila_nueva = $nuevo->get_result()->fetch_assoc(); $nuevo->close();
    $operador_id = obtener_operador_sesion($conexion, (string)$_SESSION['usuario']);
    registrar_evento_bitacora($conexion,$operador_id,'historial_lotes','Actualizar','historial_lotes',(int)$id,$fila,$fila_nueva);
    $conexion->commit(); $conexion->close();
    header('Location: historial_lotes.php?success=update&page=' . $page); exit();
} catch (Throwable $e) {
    $conexion->rollback();
    $conexion->close();
    redirigir_excepcion_alertify($e, 'Error al actualizar el registro del historial: ');
}
