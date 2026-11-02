<?php
function obtener_operador_clones_sesion(mysqli $conexion, string $usuario): int
{
    $stmt = $conexion->prepare('SELECT id FROM operadores WHERE usuario = ? LIMIT 1');
    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fila) {
        throw new InvalidArgumentException('No se pudo identificar al operador de la sesión.');
    }
    return (int)$fila['id'];
}

function validar_fecha_clon(string $fecha): bool
{
    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $objeto !== false && $objeto->format('Y-m-d') === $fecha
        && $fecha <= date('Y-m-d');
}
