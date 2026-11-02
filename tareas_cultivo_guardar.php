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
$conexion = conexion();
try {
    $conexion->begin_transaction();
    $datos = validar_datos_tarea_cultivo($_POST);
    $operador_id = obtener_operador_tarea($conexion, (string)$_SESSION['usuario']);
    $stmt = $conexion->prepare("SELECT id FROM lotes_cultivo WHERE id=? AND estado_actual NOT IN ('Cosechado','Descartado')");
    $stmt->bind_param('i', $datos['lote_id']);
    $stmt->execute();
    $lote = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$lote) throw new InvalidArgumentException('Selecciona un lote de cultivo activo.');
    if ($datos['responsable_id'] !== null) {
        $stmt = $conexion->prepare('SELECT id FROM operadores WHERE id=?');
        $stmt->bind_param('i', $datos['responsable_id']);
        $stmt->execute();
        $responsable = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$responsable) throw new InvalidArgumentException('El responsable seleccionado no existe.');
    }
    $completada = $datos['estado'] === 'Completada' ? date('Y-m-d H:i:s') : null;
    $stmt = $conexion->prepare('INSERT INTO tareas_cultivo (lote_id,titulo,descripcion,categoria,fecha_programada,estado,prioridad,responsable_id,completada_en,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->bind_param('issssssisi', $datos['lote_id'], $datos['titulo'], $datos['descripcion'], $datos['categoria'], $datos['fecha_programada'], $datos['estado'], $datos['prioridad'], $datos['responsable_id'], $completada, $operador_id);
    $stmt->execute();
    $tarea_id = (int)$conexion->insert_id;
    $stmt->close();
    registrar_evento_bitacora($conexion, $operador_id, 'tareas', 'Crear', 'tareas_cultivo', $tarea_id, null, $datos);
    $conexion->commit();
    $conexion->close();
    header('Location: tareas_cultivo.php?success=add');
    exit();
} catch (Throwable $e) {
    $conexion->rollback();
    $conexion->close();
    redirigir_excepcion_alertify($e, 'No se pudo guardar la tarea.');
}
