<?php
function exigir_administrador_operadores(mysqli $conexion, string $usuario): void
{
    if (rol_operador_actual($conexion, $usuario) !== 'administrador') {
        require_once __DIR__ . '/alertify_error.php';
        redirigir_error_alertify('Solo un administrador puede gestionar operadores y permisos.', 403);
    }
}

function modulos_asignados_operador(mysqli $conexion, int $operador_id, string $rol): array
{
    if ($rol === 'administrador') return array_keys(modulos_operador());
    $stmt = $conexion->prepare('SELECT modulo, puede_consultar FROM permisos_operadores WHERE operador_id=?');
    $stmt->bind_param('i', $operador_id);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if ($filas) {
        $asignados = [];
        foreach ($filas as $fila) if ((int)$fila['puede_consultar'] === 1 && $fila['modulo'] !== 'operadores') $asignados[] = (string)$fila['modulo'];
        return $asignados;
    }
    return array_values(array_intersect(permisos_por_rol_operador()[$rol] ?? [], array_keys(modulos_operador())));
}

function modulos_recibidos_operador(array $post, string $rol): array
{
    if ($rol === 'administrador') return array_keys(modulos_operador());
    $recibidos = $post['modulos'] ?? [];
    if (!is_array($recibidos)) $recibidos = [];
    $validos = [];
    foreach ($recibidos as $modulo) if (is_string($modulo) || is_int($modulo)) $validos[] = (string)$modulo;
    return array_values(array_diff(array_intersect(array_unique($validos), array_keys(modulos_operador())), ['operadores']));
}

function guardar_permisos_individuales(mysqli $conexion, int $operador_id, array $asignados): void
{
    $borrar = $conexion->prepare('DELETE FROM permisos_operadores WHERE operador_id=?');
    $borrar->bind_param('i', $operador_id);
    $borrar->execute();
    $borrar->close();
    $stmt = $conexion->prepare('INSERT INTO permisos_operadores (operador_id, modulo, puede_consultar, puede_modificar) VALUES (?, ?, ?, ?)');
    foreach (modulos_operador() as $modulo => $nombre) {
        $permitido = in_array($modulo, $asignados, true) ? 1 : 0;
        $stmt->bind_param('isii', $operador_id, $modulo, $permitido, $permitido);
        $stmt->execute();
    }
    $stmt->close();
}

function nombre_modulos_asignados(array $asignados): array
{
    $nombres = [];
    foreach (modulos_operador() as $modulo => $nombre) if (in_array($modulo, $asignados, true)) $nombres[] = $nombre;
    return $nombres;
}

function validar_datos_operador(array $post, bool $crear): array
{
    $usuario = is_scalar($post['usuario'] ?? null) ? trim((string)$post['usuario']) : '';
    $rol = is_scalar($post['rol'] ?? null) ? (string)$post['rol'] : '';
    $clave = is_scalar($post['clave'] ?? null) ? (string)$post['clave'] : '';
    $confirmacion = is_scalar($post['clave_confirmacion'] ?? null) ? (string)$post['clave_confirmacion'] : '';
    $roles = array_keys(permisos_por_rol_operador());
    $longitud = function_exists('mb_strlen') ? mb_strlen($usuario, 'UTF-8') : strlen($usuario);
    if ($longitud < 3 || $longitud > 50 || !in_array($rol, $roles, true)) {
        throw new InvalidArgumentException('Revisa el usuario (3 a 50 caracteres) y el rol seleccionado.');
    }
    if (($crear && strlen($clave) < 8) || (!$crear && $clave !== '' && strlen($clave) < 8)) {
        throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
    }
    if (!$crear && (($clave === '') !== ($confirmacion === ''))) {
        throw new InvalidArgumentException('Completa ambos campos para cambiar la contraseña.');
    }
    if (($crear || $clave !== '') && !hash_equals($clave, $confirmacion)) {
        throw new InvalidArgumentException('La confirmación de contraseña no coincide.');
    }
    return ['usuario' => $usuario, 'rol' => $rol, 'clave' => $clave];
}
