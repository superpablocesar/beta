<?php
function validar_datos_historial_lote(array $post): array
{
    $lote = filter_var($post['lote_id'] ?? null, FILTER_VALIDATE_INT);
    $cantidad = filter_var($post['cantidad_afectada'] ?? null, FILTER_VALIDATE_INT);
    $anterior = is_scalar($post['estado_anterior'] ?? null) ? trim((string)$post['estado_anterior']) : '';
    $nuevo = is_scalar($post['estado_nuevo'] ?? null) ? trim((string)$post['estado_nuevo']) : '';
    $motivo = is_scalar($post['motivo'] ?? null) ? trim((string)$post['motivo']) : '';
    $estados = ['Germinacion','Vegetativo','Floracion','Cosechado','Descartado'];
    if ($lote === false || $lote < 1 || $cantidad === false
        || !in_array($anterior, $estados, true) || !in_array($nuevo, $estados, true)
        || strlen($motivo) > 255) {
        throw new InvalidArgumentException('Revisa el lote, los estados y la cantidad afectada (entero con signo). El motivo admite hasta 255 caracteres.');
    }
    return ['lote_id'=>(int)$lote,'cantidad_afectada'=>(int)$cantidad,'estado_anterior'=>$anterior,
        'estado_nuevo'=>$nuevo,'motivo'=>$motivo === '' ? null : $motivo];
}

function validar_referencia_lote_historial(mysqli $conexion, int $lote_id): void
{
    $stmt=$conexion->prepare('SELECT id FROM lotes_cultivo WHERE id=? LIMIT 1');
    $stmt->bind_param('i',$lote_id); $stmt->execute(); $ok=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$ok) throw new InvalidArgumentException('El lote seleccionado no existe.');
}

function obtener_operador_sesion(mysqli $conexion, string $usuario): int
{
    $stmt=$conexion->prepare('SELECT id FROM operadores WHERE usuario=? LIMIT 1');
    $stmt->bind_param('s',$usuario); $stmt->execute(); $fila=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$fila) throw new InvalidArgumentException('No se pudo identificar al operador de la sesión.');
    return (int)$fila['id'];
}
