<?php
function registrar_movimiento_banco_semillas(
    mysqli $conexion,
    int $banco_id,
    ?int $usuario_id,
    string $tipo,
    int $cantidad_antes,
    int $cantidad_despues,
    ?int $lote_id,
    string $motivo
): void {
    if (strlen($tipo) > 40 || trim($motivo) === '' || strlen($motivo) > 255) {
        throw new InvalidArgumentException('El tipo del movimiento o su motivo no son válidos.');
    }
    $stmt = $conexion->prepare('INSERT INTO movimientos_banco_semillas (banco_semillas_id, usuario_id, tipo_movimiento, cantidad_antes, cantidad_despues, lote_cultivo_id, motivo) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iisiiis', $banco_id, $usuario_id, $tipo, $cantidad_antes, $cantidad_despues, $lote_id, $motivo);
    $stmt->execute();
    $stmt->close();
}

function obtener_operador_banco_sesion(mysqli $conexion, string $usuario): int
{
    $stmt = $conexion->prepare('SELECT id FROM operadores WHERE usuario=? LIMIT 1');
    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fila) throw new InvalidArgumentException('No se pudo identificar al operador de la sesión.');
    return (int)$fila['id'];
}
