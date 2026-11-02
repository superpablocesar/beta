<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }

require_once './conexion.php';
require_once './csrf.php';
require_once './validacion_lotes_cultivo.php';
require_once './bitacora_helper.php';
require_once './alertify_error.php';
$conexion = conexion();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: lotes_cultivo.php'); exit(); }
validar_token_csrf_post();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$page = max(1, (int)($_POST['page'] ?? 1));
if (!$id || $id < 1) redirigir_error_alertify('El identificador del lote no es válido.');
$updated_at = date('Y-m-d H:i:s');

try {
    $datos = validar_datos_lote_cultivo($_POST, false);
    $conexion->begin_transaction();

    $stmt_anterior = $conexion->prepare('SELECT * FROM lotes_cultivo WHERE id=? FOR UPDATE');
    $stmt_anterior->bind_param('i', $id);
    $stmt_anterior->execute();
    $lote_anterior = $stmt_anterior->get_result()->fetch_assoc();
    $stmt_anterior->close();
    if (!$lote_anterior) throw new InvalidArgumentException('El lote seleccionado no existe.');

    $stmt_cosecha_existente = $conexion->prepare('SELECT id FROM cosechas WHERE lote_id = ? LIMIT 1');
    $stmt_cosecha_existente->bind_param('i', $id);
    $stmt_cosecha_existente->execute();
    $tiene_cosecha = (bool)$stmt_cosecha_existente->get_result()->fetch_assoc();
    $stmt_cosecha_existente->close();
    if ($tiene_cosecha && !in_array($datos['estado_actual'], ['Cosechado', 'Descartado'], true)) {
        throw new InvalidArgumentException('Este lote ya tiene una cosecha registrada y no puede volver a una etapa de cultivo abierta.');
    }

    validar_referencias_lote_cultivo($conexion, $datos, $id, true);
    $banco_anterior_id = $lote_anterior['banco_semillas_id'] === null ? null : (int)$lote_anterior['banco_semillas_id'];
    $banco_nuevo_id = $datos['banco_semillas_id'];

    $madre_anterior_id = $lote_anterior['planta_madre_id'] === null ? null : (int)$lote_anterior['planta_madre_id'];
    if ((int)$lote_anterior['variedad_id'] !== $datos['variedad_id']
        || $lote_anterior['origen_tipo'] !== $datos['origen_tipo']
        || $banco_anterior_id !== $banco_nuevo_id
        || $madre_anterior_id !== $datos['planta_madre_id']) {
        throw new InvalidArgumentException('La genética y el origen del lote quedan fijos para preservar su trazabilidad. Registra un lote nuevo para usar otro origen.');
    }

    $usuario = (string)($_SESSION['usuario'] ?? '');
    $stmt_operador = $conexion->prepare('SELECT id FROM operadores WHERE usuario=? LIMIT 1');
    $stmt_operador->bind_param('s', $usuario);
    $stmt_operador->execute();
    $operador = $stmt_operador->get_result()->fetch_assoc();
    $stmt_operador->close();
    if (!$operador) throw new RuntimeException('No se pudo identificar al operador de la sesión.');
    $operador_id = (int)$operador['id'];

    $stmt = $conexion->prepare('UPDATE lotes_cultivo SET codigo_lote=?, variedad_id=?, origen_tipo=?, banco_semillas_id=?, planta_madre_id=?, ubicacion_actual=?, estado_actual=?, cantidad_actual=?, fecha_inicio=?, fecha_estimada_cosecha=?, updated_at=? WHERE id=?');
    $stmt->bind_param('sisiississsi', $datos['codigo_lote'], $datos['variedad_id'], $datos['origen_tipo'], $banco_nuevo_id, $datos['planta_madre_id'], $datos['ubicacion_actual'], $datos['estado_actual'], $datos['cantidad'], $datos['fecha_inicio'], $datos['fecha_estimada_cosecha'], $updated_at, $id);
    $stmt->execute();
    $stmt->close();

    $estado_anterior = $lote_anterior['estado_actual'];
    $cantidad_anterior = (int)$lote_anterior['cantidad_actual'];
    if ($estado_anterior !== $datos['estado_actual'] || $cantidad_anterior !== $datos['cantidad']) {
        $cantidad_afectada = $cantidad_anterior - $datos['cantidad'];
        $motivo = 'Cambio registrado automáticamente al editar el lote.';
        $stmt_historial = $conexion->prepare('INSERT INTO historial_lotes (lote_id, usuario_id, estado_anterior, estado_nuevo, cantidad_afectada, motivo) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt_historial->bind_param('iissis', $id, $operador_id, $estado_anterior, $datos['estado_actual'], $cantidad_afectada, $motivo);
        $stmt_historial->execute();
        $stmt_historial->close();
    }

    $stmt_nuevo = $conexion->prepare('SELECT * FROM lotes_cultivo WHERE id=?');
    $stmt_nuevo->bind_param('i', $id);
    $stmt_nuevo->execute();
    $lote_nuevo = $stmt_nuevo->get_result()->fetch_assoc();
    $stmt_nuevo->close();
    registrar_evento_bitacora($conexion, $operador_id, 'lotes', 'Actualizar', 'lotes_cultivo', (int)$id, $lote_anterior, $lote_nuevo);

    $conexion->commit();
    $conexion->close();
    header('Location: lotes_cultivo.php?success=update&page=' . $page);
    exit();
} catch (Throwable $e) {
    $conexion->rollback();
    $conexion->close();
    redirigir_excepcion_alertify($e, 'Error al actualizar el lote de cultivo: ');
}
