<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once './csrf.php';
require_once './conexion.php';
require_once './validacion_tareas_cultivo.php';
require_once './bitacora_helper.php';
require_once './alertify_error.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: tareas_cultivo.php'); exit(); }
validar_token_csrf_post();
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) redirigir_error_alertify('El identificador de la tarea no es válido.');
$conexion = conexion();
try {
    $conexion->begin_transaction();
    $datos = validar_datos_tarea_cultivo($_POST);
    $operador_id = obtener_operador_tarea($conexion, (string)$_SESSION['usuario']);
    $stmt = $conexion->prepare('SELECT lote_id,titulo,descripcion,categoria,fecha_programada,estado,prioridad,responsable_id,completada_en,created_by,updated_by FROM tareas_cultivo WHERE id=? FOR UPDATE');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $anterior = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$anterior) throw new InvalidArgumentException('La tarea ya no existe.');
    $stmt = $conexion->prepare('SELECT id FROM lotes_cultivo WHERE id=?');
    $stmt->bind_param('i', $datos['lote_id']);
    $stmt->execute();
    $lote = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$lote) throw new InvalidArgumentException('El lote seleccionado no existe.');
    if ($datos['responsable_id'] !== null) {
        $stmt = $conexion->prepare('SELECT id FROM operadores WHERE id=?');
        $stmt->bind_param('i', $datos['responsable_id']);
        $stmt->execute();
        $responsable = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$responsable) throw new InvalidArgumentException('El responsable seleccionado no existe.');
    }
    $stmt = $conexion->prepare("UPDATE tareas_cultivo SET lote_id=?,titulo=?,descripcion=?,categoria=?,fecha_programada=?,estado=?,prioridad=?,responsable_id=?,completada_en=CASE WHEN ?='Completada' THEN COALESCE(completada_en,NOW()) ELSE NULL END,updated_by=? WHERE id=?");
    $stmt->bind_param('issssssisii', $datos['lote_id'], $datos['titulo'], $datos['descripcion'], $datos['categoria'], $datos['fecha_programada'], $datos['estado'], $datos['prioridad'], $datos['responsable_id'], $datos['estado'], $operador_id, $id);
    $stmt->execute();
    $stmt->close();
    $stmt = $conexion->prepare('SELECT lote_id,titulo,descripcion,categoria,fecha_programada,estado,prioridad,responsable_id,completada_en,updated_by FROM tareas_cultivo WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $nueva = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    registrar_evento_bitacora($conexion, $operador_id, 'tareas', 'Actualizar', 'tareas_cultivo', $id, $anterior, $nueva);
    $conexion->commit();
    $conexion->close();
    header('Location: tareas_cultivo.php?success=update');
    exit();
} catch (Throwable $e) {
    $conexion->rollback();
    $conexion->close();
    redirigir_excepcion_alertify($e, 'No se pudo actualizar la tarea.');
}
