<?php
function validar_datos_variedad(array $post): array
{
    $texto = static function (string $campo) use ($post): string {
        return is_scalar($post[$campo] ?? null) ? trim((string)$post[$campo]) : '';
    };

    $nombre = $texto('nombre');
    $tipo = $texto('tipo');
    $thc_raw = $texto('porcentaje_thc_estimado');
    $cbd_raw = $texto('porcentaje_cbd_estimado');
    $dias_raw = $texto('dias_floracion_estimados');
    $tipos_validos = ['Sativa', 'Indica', 'Hibrida', 'CBD', 'Alta en THC'];
    $validar_porcentaje = static function (string $valor): ?float {
        if ($valor === '') return null;
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $valor) || (float)$valor > 99.99) {
            throw new InvalidArgumentException('Los porcentajes THC y CBD deben estar entre 0 y 99,99, con hasta dos decimales.');
        }
        return (float)$valor;
    };

    if ($nombre === '' || strlen($nombre) > 100 || !in_array($tipo, $tipos_validos, true)) {
        throw new InvalidArgumentException('El nombre es obligatorio (máximo 100 caracteres) y debes elegir un tipo de genética válido.');
    }

    $thc = $validar_porcentaje($thc_raw);
    $cbd = $validar_porcentaje($cbd_raw);
    $dias = null;
    if ($dias_raw !== '') {
        $dias_validos = filter_var($dias_raw, FILTER_VALIDATE_INT);
        if (!preg_match('/^\d+$/', $dias_raw) || $dias_validos === false || $dias_validos < 1) {
            throw new InvalidArgumentException('Los días estimados de floración deben ser un entero mayor que cero.');
        }
        $dias = (int)$dias_validos;
    }

    return [
        'nombre' => $nombre,
        'tipo' => $tipo,
        'porcentaje_thc_estimado' => $thc,
        'porcentaje_cbd_estimado' => $cbd,
        'dias_floracion_estimados' => $dias,
    ];
}
