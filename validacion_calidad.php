<?php
function validar_datos_control_calidad(array $post): array
{
    $texto = static fn(string $campo): string => is_scalar($post[$campo] ?? null) ? trim((string)$post[$campo]) : '';
    $cosecha_id = filter_var($post['cosecha_id'] ?? null, FILTER_VALIDATE_INT);
    $producto_raw = $texto('inventario_derivado_id');
    $producto_id = $producto_raw === '' ? null : filter_var($producto_raw, FILTER_VALIDATE_INT);
    $tipo = $texto('tipo_control'); $fecha = $texto('fecha_control'); $resultado = $texto('resultado');
    $valor_raw = $texto('valor'); $unidad = $texto('unidad'); $observaciones = $texto('observaciones');
    $tipos = ['Humedad','Inspección visual','Contaminantes','Análisis de laboratorio','Otro'];
    $resultados = ['Pendiente','Conforme','No conforme'];
    $fecha_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    $valor = null;
    if ($valor_raw !== '') {
        if (!preg_match('/^\d+(?:\.\d{1,3})?$/', $valor_raw) || (float)$valor_raw > 9999999.999) throw new InvalidArgumentException('El valor debe ser numérico y tener hasta tres decimales.');
        $valor = (float)$valor_raw;
    }
    if ($cosecha_id === false || $cosecha_id < 1 || ($producto_raw !== '' && ($producto_id === false || $producto_id < 1))
        || !in_array($tipo, $tipos, true) || !$fecha_obj || $fecha_obj->format('Y-m-d') !== $fecha
        || !in_array($resultado, $resultados, true) || strlen($unidad) > 20 || strlen($observaciones) > 1000) {
        throw new InvalidArgumentException('Revisa la cosecha, el producto, tipo de control, fecha, resultado y observaciones.');
    }
    return ['cosecha_id'=>(int)$cosecha_id,'producto_id'=>$producto_id === false ? null : $producto_id,
        'tipo_control'=>$tipo,'fecha_control'=>$fecha,'resultado'=>$resultado,'valor'=>$valor,
        'unidad'=>$unidad === '' ? null : $unidad,'observaciones'=>$observaciones === '' ? null : $observaciones];
}

function obtener_operador_calidad(mysqli $conexion, string $usuario): int
{
    $stmt=$conexion->prepare('SELECT id FROM operadores WHERE usuario=? LIMIT 1'); $stmt->bind_param('s',$usuario); $stmt->execute();
    $fila=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$fila) throw new InvalidArgumentException('No se pudo identificar al operador de la sesión.');
    return (int)$fila['id'];
}
