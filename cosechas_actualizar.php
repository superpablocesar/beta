<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) {
    header('Location: ./login.php');
    exit();
}

require_once './csrf.php';
require_once './conexion.php';
require_once './validacion_cosechas.php';
require_once './validacion_historial_lotes.php';
require_once './bitacora_helper.php';
require_once './alertify_error.php';
$conexion = conexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cosechas.php');
    exit();
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
validar_token_csrf_post();
$page = max(1, (int)($_POST['page'] ?? 1));
if (!$id || $id < 1) {
    redirigir_error_alertify('El identificador de la cosecha no es válido.');
}

try {
    $conexion->begin_transaction();
    $datos = validar_datos_cosecha($_POST);
    $stmt_existente = $conexion->prepare('SELECT * FROM cosechas WHERE id = ? FOR UPDATE');
    $stmt_existente->bind_param('i', $id);
    $stmt_existente->execute();
    $existente = $stmt_existente->get_result()->fetch_assoc();
    $stmt_existente->close();
    if (!$existente) {
        throw new InvalidArgumentException('La cosecha que intentas actualizar ya no existe.');
    }
    if ((int)$existente['lote_id'] !== $datos['lote_id']) {
        throw new InvalidArgumentException('El lote de origen de una cosecha no se puede cambiar al editarla; así se conserva la trazabilidad.');
    }
    $stmt_stock = $conexion->prepare('SELECT COALESCE(SUM(gramos_disponibles + gramos_reservados), 0) AS total FROM inventario_derivado WHERE cosecha_id = ?');
    $stmt_stock->bind_param('i', $id);
    $stmt_stock->execute();
    $stock_asignado = (float)$stmt_stock->get_result()->fetch_assoc()['total'];
    $stmt_stock->close();
    if ($datos['peso_seco_gramos'] + 0.000001 < $stock_asignado
        && $datos['peso_seco_gramos'] < (float)$existente['peso_seco_gramos'] - 0.000001) {
        throw new InvalidArgumentException('El peso seco no puede reducirse por debajo de los gramos disponibles y reservados en su inventario derivado.');
    }
    validar_referencias_cosecha($conexion, $datos, $id);

    $operador_id = obtener_operador_sesion($conexion, (string)$_SESSION['usuario']);
    cerrar_lote_por_cosecha($conexion, $datos['lote_id'], $operador_id, $datos['codigo_cosecha']);

    $lote_id = $datos['lote_id'];
    $codigo_cosecha = $datos['codigo_cosecha'];
    $fecha_cosecha = $datos['fecha_cosecha'];
    $peso_fresco_gramos = $datos['peso_fresco_gramos'];
    $peso_seco_gramos = $datos['peso_seco_gramos'];
    $observaciones = $datos['observaciones'];
    $updated_at = date('Y-m-d H:i:s');
    $stmt = $conexion->prepare('UPDATE cosechas SET lote_id = ?, codigo_cosecha = ?, fecha_cosecha = ?, peso_fresco_gramos = ?, peso_seco_gramos = ?, observaciones = ?, updated_at = ? WHERE id = ?');
    $stmt->bind_param('issddssi', $lote_id, $codigo_cosecha, $fecha_cosecha, $peso_fresco_gramos, $peso_seco_gramos, $observaciones, $updated_at, $id);
    $stmt->execute();
    $stmt->close();
    $stmt_nueva = $conexion->prepare('SELECT * FROM cosechas WHERE id=?');
    $stmt_nueva->bind_param('i', $id);
    $stmt_nueva->execute();
    $cosecha_nueva = $stmt_nueva->get_result()->fetch_assoc();
    $stmt_nueva->close();
    registrar_evento_bitacora($conexion, $operador_id, 'cosechas', 'Actualizar', 'cosechas', (int)$id, $existente, $cosecha_nueva);
    $conexion->commit();
    $conexion->close();

    header('Location: cosechas.php?success=update&page=' . $page);
    exit();
} catch (Throwable $e) {
    $conexion->rollback();
    $conexion->close();
    redirigir_excepcion_alertify($e, 'No se pudo actualizar la cosecha: ');
}
