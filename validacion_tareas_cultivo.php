<?php
function validar_datos_tarea_cultivo(array $post): array
{
    $texto = static function (string $campo): string {
        return is_scalar($_POST[$campo] ?? null) ? trim((string)$_POST[$campo]) : '';
    };
    $lote_id = filter_var($post['lote_id'] ?? null, FILTER_VALIDATE_INT);
    $responsable = $texto('responsable_id');
    $responsable_id = $responsable === '' ? null : filter_var($responsable, FILTER_VALIDATE_INT);
    $titulo = $texto('titulo');
    $descripcion = $texto('descripcion');
    $categoria = $texto('categoria');
    $fecha = $texto('fecha_programada');
    $estado = $texto('estado');
    $prioridad = $texto('prioridad');
    $categorias = ['Riego','Nutrición','Poda','Trasplante','Control sanitario','Seguimiento','Cosecha','Otro'];
    $estados = ['Pendiente','En curso','Completada','Cancelada'];
    $prioridades = ['Baja','Media','Alta'];
    $fecha_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    if ($lote_id === false || $lote_id < 1 || ($responsable !== '' && ($responsable_id === false || $responsable_id < 1))
        || $titulo === '' || strlen($titulo) > 120 || strlen($descripcion) > 500
        || !in_array($categoria, $categorias, true) || !$fecha_obj || $fecha_obj->format('Y-m-d') !== $fecha
        || !in_array($estado, $estados, true) || !in_array($prioridad, $prioridades, true)) {
        throw new InvalidArgumentException('Revisa lote, título, categoría, fecha, estado, prioridad y responsable de la tarea.');
    }
    return ['lote_id'=>(int)$lote_id, 'responsable_id'=>$responsable_id === false ? null : $responsable_id,
        'titulo'=>$titulo, 'descripcion'=>$descripcion === '' ? null : $descripcion, 'categoria'=>$categoria,
        'fecha_programada'=>$fecha, 'estado'=>$estado, 'prioridad'=>$prioridad];
}

function obtener_operador_tarea(mysqli $conexion, string $usuario): int
{
    $stmt = $conexion->prepare('SELECT id FROM operadores WHERE usuario = ? LIMIT 1');
    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fila) throw new InvalidArgumentException('No se pudo identificar al operador de la sesión.');
    return (int)$fila['id'];
}
