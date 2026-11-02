<?php
function validar_datos_lote_cultivo(array $post, bool $nuevo): array
{
    $texto = static fn(string $campo): string => is_scalar($post[$campo] ?? null) ? trim((string)$post[$campo]) : '';
    $id_int = static function ($valor): ?int {
        $validado = filter_var($valor, FILTER_VALIDATE_INT);
        return $validado === false ? null : (int)$validado;
    };
    $codigo = $texto('codigo_lote');
    $variedad = $id_int($post['variedad_id'] ?? null);
    $origen = $texto('origen_tipo');
    $estado = $texto('estado_actual');
    $cantidad = $id_int($post[$nuevo ? 'cantidad_inicial' : 'cantidad_actual'] ?? null);
    $ubicacion = $texto('ubicacion_actual');
    $inicio = $texto('fecha_inicio');
    $estimada = $texto('fecha_estimada_cosecha');
    $fecha_valida = static function (string $f): bool {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $f);
        return $d && $d->format('Y-m-d') === $f;
    };
    if ($inicio === '') $inicio = date('Y-m-d');
    if ($codigo === '' || strlen($codigo) > 50 || !$variedad || $variedad < 1
        || !in_array($origen, ['Desde_Semilla','Desde_Esqueje_Madre','Desde_Esqueje_Externo'], true)
        || !in_array($estado, ['Germinacion','Vegetativo','Floracion','Cosechado','Descartado'], true)
        || $cantidad === null || $cantidad < ($nuevo ? 1 : 0)
        || strlen($ubicacion) > 100 || !$fecha_valida($inicio)
        || ($estimada !== '' && (!$fecha_valida($estimada) || $estimada < $inicio))) {
        throw new InvalidArgumentException('Revisa código, variedad, origen, estado, cantidad y fechas del lote.');
    }
    $banco = $id_int($post['banco_semillas_id'] ?? null);
    $madre = $id_int($post['planta_madre_id'] ?? null);
    if ($banco !== null && $banco < 1) throw new InvalidArgumentException('El ítem de semillas no es válido.');
    if ($madre !== null && $madre < 1) throw new InvalidArgumentException('La planta madre no es válida.');
    if (in_array($origen, ['Desde_Semilla', 'Desde_Esqueje_Externo'], true) && $banco === null) {
        throw new InvalidArgumentException('Selecciona el material del banco que se utilizará para el lote.');
    }
    if ($origen === 'Desde_Esqueje_Madre' && $madre === null) {
        throw new InvalidArgumentException('Selecciona la planta madre donante del lote.');
    }
    return ['codigo_lote'=>$codigo,'variedad_id'=>$variedad,'origen_tipo'=>$origen,
        'estado_actual'=>$estado,'cantidad'=>$cantidad,'ubicacion_actual'=>$ubicacion,
        'fecha_inicio'=>$inicio,'fecha_estimada_cosecha'=>$estimada === '' ? null : $estimada,
        'banco_semillas_id'=>in_array($origen,['Desde_Semilla','Desde_Esqueje_Externo'],true)?$banco:null,
        'planta_madre_id'=>$origen==='Desde_Esqueje_Madre'?$madre:null];
}

function validar_referencias_lote_cultivo(mysqli $conexion, array $datos, int $id_excluir = 0, bool $permitir_tipo_heredado = false): void
{
    $checks = [
        ['SELECT id FROM variedades WHERE id=? LIMIT 1', $datos['variedad_id'], 'La variedad seleccionada no existe.'],
    ];
    if ($datos['banco_semillas_id'] !== null) {
        $stmt = $conexion->prepare('SELECT variedad_id, tipo_material FROM banco_semillas WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $datos['banco_semillas_id']); $stmt->execute(); $banco = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$banco) throw new InvalidArgumentException('El ítem del banco de semillas no existe.');
        if ((int)$banco['variedad_id'] !== $datos['variedad_id']) throw new InvalidArgumentException('La variedad del lote debe coincidir con la variedad del ítem del banco.');
        $material_valido = $datos['origen_tipo'] === 'Desde_Semilla'
            ? strpos($banco['tipo_material'], 'Semilla ') === 0
            : $banco['tipo_material'] === 'Esqueje/Clon';
        if (!$material_valido) {
            $heredado = false;
            if ($permitir_tipo_heredado && $id_excluir > 0) {
                $stmt = $conexion->prepare('SELECT id FROM lotes_cultivo WHERE id=? AND variedad_id=? AND origen_tipo=? AND banco_semillas_id=? LIMIT 1');
                $stmt->bind_param('iisi', $id_excluir, $datos['variedad_id'], $datos['origen_tipo'], $datos['banco_semillas_id']);
                $stmt->execute(); $heredado = (bool)$stmt->get_result()->fetch_assoc(); $stmt->close();
            }
            if (!$heredado) throw new InvalidArgumentException('El tipo de material del banco no coincide con el origen seleccionado.');
        }
    }
    if ($datos['planta_madre_id'] !== null) {
        $stmt = $conexion->prepare('SELECT variedad_id FROM plantas_madre WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $datos['planta_madre_id']); $stmt->execute(); $madre = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$madre) throw new InvalidArgumentException('La planta madre seleccionada no existe.');
        if ((int)$madre['variedad_id'] !== $datos['variedad_id']) throw new InvalidArgumentException('La variedad del lote debe coincidir con la de la planta madre.');
    }
    foreach ($checks as [$sql,$ref,$mensaje]) {
        $stmt=$conexion->prepare($sql); $stmt->bind_param('i',$ref); $stmt->execute(); $ok=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$ok) throw new InvalidArgumentException($mensaje);
    }
    $stmt=$conexion->prepare('SELECT id FROM lotes_cultivo WHERE codigo_lote=? AND id<>? LIMIT 1');
    $stmt->bind_param('si',$datos['codigo_lote'],$id_excluir); $stmt->execute(); $duplicate=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($duplicate) throw new InvalidArgumentException('Ya existe un lote con ese código.');
}

function bloquear_banco_para_lote(mysqli $conexion, int $banco_id): array
{
    $stmt = $conexion->prepare('SELECT id, variedad_id, tipo_material, cantidad_disponible FROM banco_semillas WHERE id=? FOR UPDATE');
    $stmt->bind_param('i', $banco_id); $stmt->execute(); $banco = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$banco) throw new InvalidArgumentException('El ítem del banco de semillas ya no existe.');
    return $banco;
}

function descontar_stock_banco(mysqli $conexion, int $banco_id, int $cantidad): void
{
    $stmt = $conexion->prepare('UPDATE banco_semillas SET cantidad_disponible = cantidad_disponible - ? WHERE id=? AND cantidad_disponible >= ?');
    $stmt->bind_param('iii', $cantidad, $banco_id, $cantidad); $stmt->execute(); $actualizado = $stmt->affected_rows; $stmt->close();
    if ($actualizado !== 1) throw new InvalidArgumentException('El banco no tiene unidades suficientes para crear este lote.');
}
