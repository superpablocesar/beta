<?php
function validar_datos_banco_semillas(array $post, bool $es_alta): array
{
    $texto = static function (string $campo) use ($post): string {
        return is_scalar($post[$campo] ?? null) ? trim((string)$post[$campo]) : '';
    };

    $variedad_id = filter_var($post['variedad_id'] ?? null, FILTER_VALIDATE_INT);
    $tipo_material = $texto('tipo_material');
    $origen_tipo = $texto('origen_tipo');
    $cantidad_raw = $texto('cantidad_disponible');
    $cantidad_disponible = filter_var($cantidad_raw, FILTER_VALIDATE_INT);
    $ubicacion_almacen = $texto('ubicacion_almacen');
    $tipos_material = ['Semilla Feminizada', 'Semilla Regular', 'Semilla Automatica', 'Esqueje/Clon'];
    $origenes_validos = ['Compra_Externa', 'Produccion_Propia'];
    $cantidad_minima = $es_alta ? 1 : 0;

    if (!$variedad_id || $variedad_id < 1 || !in_array($tipo_material, $tipos_material, true)
        || !in_array($origen_tipo, $origenes_validos, true)
        || !preg_match('/^\d+$/', $cantidad_raw) || $cantidad_disponible === false
        || $cantidad_disponible < $cantidad_minima || $cantidad_disponible > 2147483647
        || strlen($ubicacion_almacen) > 100) {
        throw new InvalidArgumentException('Revisa la genética, el tipo de material, el origen, la cantidad y la ubicación (máximo 100 caracteres).');
    }

    $origen_proveedor = null;
    $numero_lote_origen = null;
    $planta_madre_id = null;
    $planta_padre_id = null;
    $fecha_polinizacion = null;

    if ($origen_tipo === 'Compra_Externa') {
        $origen_proveedor = $texto('origen_proveedor');
        $numero_lote_origen = $texto('numero_lote_origen');
        if (strlen($origen_proveedor) > 150 || strlen($numero_lote_origen) > 100) {
            throw new InvalidArgumentException('El proveedor admite hasta 150 caracteres y el lote hasta 100.');
        }
        if ($origen_proveedor === '') $origen_proveedor = null;
        if ($numero_lote_origen === '') $numero_lote_origen = null;
    } else {
        foreach (['planta_madre_id' => 'madre', 'planta_padre_id' => 'padre'] as $campo => $nombre) {
            $valor = $texto($campo);
            if ($valor === '') continue;
            $id = filter_var($valor, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                throw new InvalidArgumentException('Selecciona una planta madre y/o padre válidos para la producción propia.');
            }
            if ($nombre === 'madre') $planta_madre_id = $id;
            else $planta_padre_id = $id;
        }

        $fecha_raw = $texto('fecha_polinizacion');
        if ($fecha_raw !== '') {
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha_raw);
            if (!$fecha || $fecha->format('Y-m-d') !== $fecha_raw) {
                throw new InvalidArgumentException('La fecha de polinización no es válida.');
            }
            $fecha_polinizacion = $fecha->format('Y-m-d');
        }
    }

    return [
        'variedad_id' => (int)$variedad_id,
        'tipo_material' => $tipo_material,
        'origen_tipo' => $origen_tipo,
        'origen_proveedor' => $origen_proveedor,
        'numero_lote_origen' => $numero_lote_origen,
        'planta_madre_id' => $planta_madre_id,
        'planta_padre_id' => $planta_padre_id,
        'fecha_polinizacion' => $fecha_polinizacion,
        'cantidad_disponible' => (int)$cantidad_disponible,
        'ubicacion_almacen' => $ubicacion_almacen,
    ];
}

function validar_referencias_banco_semillas(mysqli $conexion, array $datos): void
{
    $stmt = $conexion->prepare('SELECT id FROM variedades WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $datos['variedad_id']);
    $stmt->execute();
    $existe = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$existe) throw new InvalidArgumentException('La genética seleccionada no existe.');

    foreach (['planta_madre_id' => 'madre', 'planta_padre_id' => 'padre'] as $campo => $nombre) {
        if ($datos[$campo] === null) continue;
        $id = (int)$datos[$campo];
        $stmt = $conexion->prepare('SELECT id FROM plantas_madre WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$existe) throw new InvalidArgumentException('La planta ' . $nombre . ' seleccionada ya no existe.');
    }
}
