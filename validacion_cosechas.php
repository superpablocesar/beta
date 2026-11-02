<?php
require_once __DIR__ . '/bitacora_helper.php';

function validar_datos_cosecha(array $post): array
{
    $texto = static function (string $campo) use ($post): string {
        return is_scalar($post[$campo] ?? null) ? trim((string)$post[$campo]) : '';
    };
    $lote_id = filter_var($post['lote_id'] ?? null, FILTER_VALIDATE_INT);
    $codigo = $texto('codigo_cosecha');
    $fecha_raw = $texto('fecha_cosecha');
    $fresco_raw = $texto('peso_fresco_gramos');
    $seco_raw = $texto('peso_seco_gramos');
    if ($seco_raw === '') $seco_raw = '0';
    $observaciones = $texto('observaciones');
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha_raw);
    $validar_peso = static function (string $valor): bool {
        return preg_match('/^\d+(?:\.\d{1,2})?$/', $valor) === 1 && (float)$valor <= 999999.99;
    };

    if (!$lote_id || $lote_id < 1 || $codigo === '' || strlen($codigo) > 50
        || !$fecha || $fecha->format('Y-m-d') !== $fecha_raw
        || !$validar_peso($fresco_raw) || !$validar_peso($seco_raw)
        || (float)$seco_raw > (float)$fresco_raw
        || strlen($observaciones) > 65535) {
        throw new InvalidArgumentException('Revisa el lote, el código (máximo 50 caracteres), la fecha y los pesos. El peso seco no puede superar al fresco.');
    }

    return [
        'lote_id' => (int)$lote_id,
        'codigo_cosecha' => $codigo,
        'fecha_cosecha' => $fecha->format('Y-m-d'),
        'peso_fresco_gramos' => (float)$fresco_raw,
        'peso_seco_gramos' => (float)$seco_raw,
        'observaciones' => $observaciones === '' ? null : $observaciones,
    ];
}

function validar_referencias_cosecha(mysqli $conexion, array $datos, int $id_excluir = 0): void
{
    $stmt = $conexion->prepare('SELECT id FROM lotes_cultivo WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $datos['lote_id']);
    $stmt->execute();
    $lote = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$lote) throw new InvalidArgumentException('El lote seleccionado no existe.');

    $stmt = $conexion->prepare('SELECT id FROM cosechas WHERE codigo_cosecha = ? AND id <> ? LIMIT 1');
    $stmt->bind_param('si', $datos['codigo_cosecha'], $id_excluir);
    $stmt->execute();
    $duplicada = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($duplicada) throw new InvalidArgumentException('Ya existe una cosecha con ese código.');
}

function cerrar_lote_por_cosecha(mysqli $conexion, int $lote_id, int $operador_id, string $codigo_cosecha): void
{
    $stmt = $conexion->prepare('SELECT estado_actual FROM lotes_cultivo WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $lote_id);
    $stmt->execute();
    $lote = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$lote) throw new InvalidArgumentException('El lote asociado a la cosecha ya no existe.');
    if ($lote['estado_actual'] === 'Descartado') {
        throw new InvalidArgumentException('No se puede registrar una cosecha para un lote descartado.');
    }
    if ($lote['estado_actual'] === 'Cosechado') return;

    $estado_anterior = (string)$lote['estado_actual'];
    $estado_nuevo = 'Cosechado';
    $stmt = $conexion->prepare("UPDATE lotes_cultivo SET estado_actual = 'Cosechado', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $lote_id);
    $stmt->execute();
    $stmt->close();

    $cantidad_afectada = 0;
    $motivo = 'Cierre automático por cosecha ' . $codigo_cosecha . '.';
    $stmt = $conexion->prepare('INSERT INTO historial_lotes (lote_id, usuario_id, estado_anterior, estado_nuevo, cantidad_afectada, motivo) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iissis', $lote_id, $operador_id, $estado_anterior, $estado_nuevo, $cantidad_afectada, $motivo);
    $stmt->execute();
    $stmt->close();
    registrar_evento_bitacora($conexion, $operador_id, 'lotes', 'Actualizar', 'lotes_cultivo', $lote_id,
        ['estado_actual' => $estado_anterior], ['estado_actual' => $estado_nuevo]);
}
