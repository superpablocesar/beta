<?php
function json_bitacora(?array $datos): ?string
{
    if ($datos === null) return null;
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) throw new RuntimeException('No se pudieron preparar los datos de auditoría.');
    return $json;
}

function registrar_evento_bitacora(
    mysqli $conexion,
    ?int $operador_id,
    string $modulo,
    string $accion,
    string $entidad,
    ?int $entidad_id,
    ?array $anteriores,
    ?array $nuevos
): void {
    $anteriores_json = json_bitacora($anteriores);
    $nuevos_json = json_bitacora($nuevos);
    $stmt = $conexion->prepare('INSERT INTO bitacora_cambios (operador_id,modulo,accion,entidad,entidad_id,valores_anteriores,valores_nuevos) VALUES (?,?,?,?,?,?,?)');
    $stmt->bind_param('isssiss', $operador_id, $modulo, $accion, $entidad, $entidad_id, $anteriores_json, $nuevos_json);
    $stmt->execute();
    $stmt->close();
}

function operador_id_bitacora(mysqli $conexion, string $usuario): int
{
    $stmt=$conexion->prepare('SELECT id FROM operadores WHERE usuario=? LIMIT 1');
    $stmt->bind_param('s',$usuario);$stmt->execute();$fila=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$fila)throw new InvalidArgumentException('No se pudo identificar al operador para la bitácora.');
    return (int)$fila['id'];
}
