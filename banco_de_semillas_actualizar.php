<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }

require_once './conexion.php';
require_once './csrf.php';
require_once './validacion_banco_semillas.php';
require_once './registro_movimientos_banco.php';
require_once './alertify_error.php';
require_once __DIR__ . '/bitacora_helper.php';
$conexion = conexion();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: banco_de_semillas.php'); exit(); }
validar_token_csrf_post();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$page = max(1, (int)($_POST['page'] ?? 1));
if (!$id || $id < 1) redirigir_error_alertify('El identificador del registro no es válido.');

try {
    $datos = validar_datos_banco_semillas($_POST, false);
    $motivo_ajuste = is_scalar($_POST['motivo_ajuste'] ?? null) ? trim((string)$_POST['motivo_ajuste']) : '';
    if (strlen($motivo_ajuste) > 255) throw new InvalidArgumentException('El motivo del ajuste admite hasta 255 caracteres.');
    $conexion->begin_transaction();

    $stmt_actual = $conexion->prepare('SELECT cantidad_disponible, variedad_id, tipo_material, origen_tipo, origen_proveedor, numero_lote_origen, planta_madre_id, planta_padre_id, fecha_polinizacion FROM banco_semillas WHERE id=? FOR UPDATE');
    $stmt_actual->bind_param('i', $id);
    $stmt_actual->execute();
    $actual = $stmt_actual->get_result()->fetch_assoc();
    $stmt_actual->close();
    if (!$actual) throw new InvalidArgumentException('El registro de semillas ya no existe.');
    $anteriores = $actual;
    $cantidad_antes = (int)$actual['cantidad_disponible'];
    $cantidad_despues = (int)$datos['cantidad_disponible'];
    $hay_ajuste_stock = $cantidad_antes !== $cantidad_despues;
    if ($hay_ajuste_stock && $motivo_ajuste === '') {
        throw new InvalidArgumentException('Indica el motivo al cambiar la cantidad disponible.');
    }

    validar_referencias_banco_semillas($conexion, $datos);
    $stmt_lote = $conexion->prepare('SELECT id FROM lotes_cultivo WHERE banco_semillas_id=? LIMIT 1');
    $stmt_lote->bind_param('i', $id);
    $stmt_lote->execute();
    $lote_asociado = (bool)$stmt_lote->get_result()->fetch_assoc();
    $stmt_lote->close();

    if ($lote_asociado) {
        $campos_origen = ['variedad_id', 'tipo_material', 'origen_tipo', 'origen_proveedor', 'numero_lote_origen', 'planta_madre_id', 'planta_padre_id', 'fecha_polinizacion'];
        foreach ($campos_origen as $campo) {
            $anterior = $actual[$campo] === null ? null : (string)$actual[$campo];
            $nuevo = $datos[$campo] === null ? null : (string)$datos[$campo];
            if ($anterior !== $nuevo) {
                throw new InvalidArgumentException('Este material ya está vinculado a un lote. Su genética, tipo y origen quedan fijos; puedes ajustar stock y ubicación.');
            }
        }
    }

    $updated_at = date('Y-m-d H:i:s');
    $stmt = $conexion->prepare('UPDATE banco_semillas SET variedad_id=?, tipo_material=?, origen_tipo=?, origen_proveedor=?, numero_lote_origen=?, planta_madre_id=?, planta_padre_id=?, fecha_polinizacion=?, cantidad_disponible=?, ubicacion_almacen=?, updated_at=? WHERE id=?');
    $stmt->bind_param('issssiisissi', $datos['variedad_id'], $datos['tipo_material'], $datos['origen_tipo'], $datos['origen_proveedor'], $datos['numero_lote_origen'], $datos['planta_madre_id'], $datos['planta_padre_id'], $datos['fecha_polinizacion'], $datos['cantidad_disponible'], $datos['ubicacion_almacen'], $updated_at, $id);
    $stmt->execute();
    $stmt->close();
    if ($hay_ajuste_stock) {
        $usuario_id = obtener_operador_banco_sesion($conexion, (string)$_SESSION['usuario']);
        registrar_movimiento_banco_semillas($conexion, $id, $usuario_id, 'Ajuste', $cantidad_antes, $cantidad_despues, null, $motivo_ajuste);
    }
    $nuevos = $datos;
    if ($hay_ajuste_stock) $nuevos['motivo_ajuste'] = $motivo_ajuste;
    registrar_evento_bitacora($conexion, operador_id_bitacora($conexion, (string)$_SESSION['usuario']), 'banco_semillas', 'Actualizar', 'banco_semillas', (int)$id, $anteriores, $nuevos);
    $conexion->commit();
    $conexion->close();

    header('Location: banco_de_semillas.php?success=update&page=' . $page);
    exit();
} catch (Throwable $e) {
    $conexion->rollback();
    $conexion->close();
    redirigir_excepcion_alertify($e, 'No se pudo actualizar el banco de semillas: ');
}
