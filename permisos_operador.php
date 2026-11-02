<?php
function permisos_por_rol_operador(): array
{
    return [
        'administrador' => ['*'],
        'cultivo' => ['variedades','plantas_madre','clones','banco_semillas','lotes','historial_lotes','cosechas','calidad','tareas','reportes'],
        'inventario' => ['inventario','movimientos_inventario','banco_semillas','reportes'],
        'dispensacion' => ['socios','dispensacion','inventario','movimientos_inventario','reportes'],
        'cuotas' => ['socios','cuotas','reportes'],
        'consulta' => ['variedades','plantas_madre','clones','banco_semillas','lotes','historial_lotes','cosechas','calidad','tareas','inventario','movimientos_inventario','socios','dispensacion','cuotas','reportes'],
    ];
}

function rol_operador_actual(mysqli $conexion, string $usuario): string
{
    $stmt=$conexion->prepare('SELECT rol FROM operadores WHERE usuario=? LIMIT 1');
    $stmt->bind_param('s',$usuario);$stmt->execute();$fila=$stmt->get_result()->fetch_assoc();$stmt->close();
    return $fila && isset(permisos_por_rol_operador()[$fila['rol']]) ? (string)$fila['rol'] : 'consulta';
}

function operador_puede_consultar(string $rol, string $modulo): bool
{
    return permiso_operador_en_bd($rol, $modulo, false);
}

function operador_puede_modificar(string $rol, string $modulo): bool
{
    return permiso_operador_en_bd($rol, $modulo, true);
}

function permiso_operador_en_bd(string $rol, string $modulo, bool $modificar, ?mysqli $conexion_actual = null): bool
{
    if (!isset(permisos_por_rol_operador()[$rol])) return false;
    if ($conexion_actual === null) {
        require_once __DIR__ . '/conexion.php';
        $conexion_actual = conexion();
        $cerrar = true;
    } else {
        $cerrar = false;
    }
    $columna = $modificar ? 'puede_modificar' : 'puede_consultar';
    $stmt = $conexion_actual->prepare('SELECT ' . $columna . ' AS permitido FROM permisos_roles WHERE rol=? AND modulo=? LIMIT 1');
    $stmt->bind_param('ss', $rol, $modulo);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($cerrar) $conexion_actual->close();
    return $fila && (int)$fila['permitido'] === 1;
}

function modulo_para_ruta_actual(): ?string
{
    $pagina = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $prefijos = [
        'variedades_' => 'variedades', 'variedades.php' => 'variedades',
        'plantas_madre' => 'plantas_madre', 'extracciones_madre' => 'plantas_madre', 'visor.php' => 'plantas_madre',
        'clones' => 'clones', 'banco_de_semillas' => 'banco_semillas', 'movimientos_banco_semillas.php' => 'banco_semillas', 'registro_movimientos_banco.php' => 'banco_semillas',
        'lotes_cultivo' => 'lotes', 'historial_lotes' => 'historial_lotes', 'cosechas' => 'cosechas',
        'control_calidad' => 'calidad', 'tareas_cultivo' => 'tareas', 'inventario_derivado' => 'inventario',
        'movimientos_inventario.php' => 'movimientos_inventario', 'socios' => 'socios', 'dispensacion' => 'dispensacion',
        'cuotas' => 'cuotas', 'reportes.php' => 'reportes', 'operadores_permisos' => 'operadores', 'bitacora.php' => 'bitacora',
        'plantas_madre_qr_pdf.php' => 'plantas_madre'
    ];
    foreach ($prefijos as $prefijo => $modulo) {
        if ($pagina === $prefijo || strpos($pagina, $prefijo) === 0) return $modulo;
    }
    if ($pagina === 'exportar_excel.php') {
        $tipo = is_scalar($_GET['tipo'] ?? null) ? (string)$_GET['tipo'] : '';
        switch ($tipo) {
            case 'socios': return 'socios';
            case 'inventario': return 'inventario';
            case 'banco_semillas': return 'banco_semillas';
            case 'cosechas': return 'cosechas';
            case 'rendimiento_genetica': return 'reportes';
            case 'actividad_madres_clones': return 'reportes';
            case 'alertas_vencimientos': return 'reportes';
            case 'dispensacion': case 'dispensacion_limites': return 'dispensacion';
            case 'movimientos': case 'conciliacion_inventario': return 'movimientos_inventario';
            case 'cuotas': case 'cuotas_periodo': return 'cuotas';
            case 'movimientos_banco_semillas': return 'banco_semillas';
            case 'clones': return 'clones';
            case 'control_calidad': return 'calidad';
            case 'trazabilidad_producto': return 'reportes';
            case 'bitacora': return 'bitacora';
            default: return 'reportes';
        }
    }
    return null;
}

function autorizar_ruta_actual(string $usuario): void
{
    $modulo = modulo_para_ruta_actual();
    require_once __DIR__ . '/conexion.php';
    $db = conexion();
    $rol = rol_operador_actual($db, $usuario);
    $_SESSION['rol'] = $rol;
    if ($modulo === null) {
        $db->close();
        return;
    }
    $pagina = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $requiere_edicion = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        || preg_match('/_(?:agregar|editar|guardar|actualizar)\.php$/', $pagina) === 1
        || $pagina === 'operadores_permisos.php';
    $permitido = permiso_operador_en_bd($rol, $modulo, $requiere_edicion, $db);
    $db->close();
    if (!$permitido) {
        http_response_code(403);
        require_once __DIR__ . '/alertify_error.php';
        redirigir_error_alertify('Tu rol no tiene permiso para ' . ($requiere_edicion ? 'modificar este módulo.' : 'consultar este módulo.'), 403);
    }
}
