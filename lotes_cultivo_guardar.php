<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }

require_once './conexion.php';
require_once './csrf.php';
require_once './validacion_lotes_cultivo.php';
require_once './registro_movimientos_banco.php';
require_once './bitacora_helper.php';
require_once './alertify_error.php';
$conexion = conexion();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: lotes_cultivo.php'); exit(); }
validar_token_csrf_post();

$page = max(1, (int)($_POST['page'] ?? 1));
try {
    $datos = validar_datos_lote_cultivo($_POST, true);
    $conexion->begin_transaction();
    $operador_id = obtener_operador_banco_sesion($conexion, (string)$_SESSION['usuario']);
    validar_referencias_lote_cultivo($conexion, $datos);

    $banco_semillas_id = $datos['banco_semillas_id'];
    $cantidad_banco_antes = null;
    if ($banco_semillas_id !== null) {
        $banco = bloquear_banco_para_lote($conexion, $banco_semillas_id);
        if ((int)$banco['variedad_id'] !== $datos['variedad_id']) {
            throw new InvalidArgumentException('La variedad del ítem del banco no coincide con la del lote.');
        }
        if ((int)$banco['cantidad_disponible'] < $datos['cantidad']) {
            throw new InvalidArgumentException('El banco no tiene unidades suficientes para crear este lote.');
        }
        $cantidad_banco_antes = (int)$banco['cantidad_disponible'];
        descontar_stock_banco($conexion, $banco_semillas_id, $datos['cantidad']);
    }

    $cantidad_inicial = $datos['cantidad'];
    $stmt = $conexion->prepare('INSERT INTO lotes_cultivo (codigo_lote, variedad_id, origen_tipo, banco_semillas_id, planta_madre_id, ubicacion_actual, estado_actual, cantidad_inicial, cantidad_actual, fecha_inicio, fecha_estimada_cosecha) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sisiissiiss', $datos['codigo_lote'], $datos['variedad_id'], $datos['origen_tipo'], $banco_semillas_id, $datos['planta_madre_id'], $datos['ubicacion_actual'], $datos['estado_actual'], $cantidad_inicial, $cantidad_inicial, $datos['fecha_inicio'], $datos['fecha_estimada_cosecha']);
    $stmt->execute();
    $lote_id = $conexion->insert_id;
    $stmt->close();
    if ($banco_semillas_id !== null) {
        registrar_movimiento_banco_semillas($conexion, $banco_semillas_id, $operador_id, 'Consumo por lote', $cantidad_banco_antes, $cantidad_banco_antes - $cantidad_inicial, $lote_id, 'Consumo de unidades para el lote ' . $datos['codigo_lote'] . '.');
    }

    registrar_evento_bitacora($conexion, $operador_id, 'lotes', 'Crear', 'lotes_cultivo', (int)$lote_id, null, $datos);

    $conexion->commit();
    $conexion->close();
    header('Location: lotes_cultivo.php?success=add&page=' . $page);
    exit();
} catch (Throwable $e) {
    $conexion->rollback();
    $conexion->close();
    redirigir_excepcion_alertify($e, 'Error al guardar el lote de cultivo: ');
}
