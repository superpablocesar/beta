<?php
function validar_datos_cuota(array $post): array
{
    $texto = static function (string $campo) use ($post): string {
        return is_scalar($post[$campo] ?? null) ? trim((string)$post[$campo]) : '';
    };

    $socio_id = filter_var($post['socio_id'] ?? null, FILTER_VALIDATE_INT);
    $monto_raw = $texto('monto');
    $concepto = $texto('concepto');
    $fecha_raw = $texto('fecha_pago');
    $metodo_pago = $texto('metodo_pago');
    $estado = $texto('estado');
    $observaciones = $texto('observaciones');
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha_raw);

    if (!$socio_id || $socio_id < 1
        || !preg_match('/^\d+(?:\.\d{1,2})?$/', $monto_raw)
        || (float)$monto_raw > 99999999.99
        || $concepto === '' || strlen($concepto) > 150
        || !$fecha || $fecha->format('Y-m-d') !== $fecha_raw
        || !in_array($metodo_pago, ['Efectivo', 'Transferencia', 'MercadoPago', 'Otro'], true)
        || !in_array($estado, ['Pagado Pendiente', 'Completado', 'Anulado'], true)
        || strlen($observaciones) > 65535) {
        throw new InvalidArgumentException('Revisa el socio, el monto (hasta 99.999.999,99), el concepto, la fecha, el método y el estado del cobro.');
    }

    return [
        'socio_id' => (int)$socio_id,
        'monto' => (float)$monto_raw,
        'concepto' => $concepto,
        'fecha_pago' => $fecha->format('Y-m-d'),
        'metodo_pago' => $metodo_pago,
        'estado' => $estado,
        'observaciones' => $observaciones === '' ? null : $observaciones,
    ];
}

function validar_socio_cuota(mysqli $conexion, int $socio_id): void
{
    $stmt = $conexion->prepare('SELECT id FROM socios WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $socio_id);
    $stmt->execute();
    $socio = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$socio) throw new InvalidArgumentException('El socio seleccionado no existe.');
}
