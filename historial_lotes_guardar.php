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
validar_token_csrf_post();
$page = max(1, (int)($_POST['page'] ?? 1));
try {
    $conexion->begin_transaction();
    $datos = validar_datos_historial_lote($_POST);
    validar_referencia_lote_historial($conexion, $datos['lote_id']);
    $usuario_id = obtener_operador_sesion($conexion, (string)$_SESSION['usuario']);
    $stmt = $conexion->prepare('INSERT INTO historial_lotes (lote_id, usuario_id, estado_anterior, estado_nuevo, cantidad_afectada, motivo) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iissis', $datos['lote_id'], $usuario_id, $datos['estado_anterior'], $datos['estado_nuevo'], $datos['cantidad_afectada'], $datos['motivo']);
    $stmt->execute(); $historial_id=(int)$conexion->insert_id; $stmt->close();
    registrar_evento_bitacora($conexion,$usuario_id,'historial_lotes','Crear','historial_lotes',$historial_id,null,$datos);
    $conexion->commit(); $conexion->close();
    header('Location: historial_lotes.php?success=add&page=' . $page); exit();
} catch (Throwable $e) {
    $conexion->rollback();
    $conexion->close();
    redirigir_excepcion_alertify($e, 'Error al guardar el registro en el historial: ');
}
