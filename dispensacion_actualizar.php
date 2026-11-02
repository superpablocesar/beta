<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION["usuario"])) {
    header("Location: ./login.php");
    exit();
}

require_once "./conexion.php";
require_once "./csrf.php";
require_once "./bitacora_helper.php";
$conexion = conexion();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validar_token_csrf_post();
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

    if ($id <= 0) {
        header("Location: dispensacion.php?page=" . $page);
        exit();
    }

    $socio_id = isset($_POST['socio_id']) ? (int)$_POST['socio_id'] : 0;
    $inventario_derivado_id = isset($_POST['inventario_derivado_id']) ? (int)$_POST['inventario_derivado_id'] : 0;
    $fecha_dispensacion = !empty($_POST['fecha_dispensacion']) ? trim($_POST['fecha_dispensacion']) : date('Y-m-d');
    $gramos_raw = trim((string)($_POST['gramos_entregados'] ?? ''));
    $gramos_entregados = is_numeric($gramos_raw) ? (float)$gramos_raw : 0.00;
    $observaciones = trim($_POST['observaciones'] ?? '');
    $updated_at = date('Y-m-d H:i:s');

    $fecha_validada = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha_dispensacion);
    if ($socio_id <= 0 || $inventario_derivado_id <= 0 || $gramos_entregados <= 0 || $gramos_entregados > 999999.99
        || !preg_match('/^\d+(?:\.\d{1,2})?$/', $gramos_raw)
        || strlen($observaciones) > 65535
        || !$fecha_validada || $fecha_validada->format('Y-m-d') !== $fecha_dispensacion) {
        http_response_code(422);
        require_once __DIR__ . '/alertify_error.php';
        redirigir_error_alertify('Revisa el socio, el producto, la fecha y los gramos de la dispensación.');
    }

    try {
        $conexion->begin_transaction();

        // Bloquear la entrega y el socio para validar límite sin carreras concurrentes.
        $stmt_actual = $conexion->prepare("SELECT * FROM dispensacion WHERE id = ? FOR UPDATE");
        $stmt_actual->bind_param("i", $id);
        $stmt_actual->execute();
        $actual = $stmt_actual->get_result()->fetch_assoc();
        $stmt_actual->close();
        if (!$actual) {
            throw new Exception("La dispensación seleccionada no existe.");
        }

        $stmt_socio = $conexion->prepare("SELECT limite_gramos_mensual FROM socios WHERE id = ? FOR UPDATE");
        $stmt_socio->bind_param("i", $socio_id);
        $stmt_socio->execute();
        $socio = $stmt_socio->get_result()->fetch_assoc();
        $stmt_socio->close();
        if (!$socio) {
            throw new Exception("El socio seleccionado no existe.");
        }

        $inicio_mes = $fecha_validada->modify('first day of this month')->format('Y-m-d');
        $inicio_mes_siguiente = $fecha_validada->modify('first day of next month')->format('Y-m-d');
        $stmt_sum = $conexion->prepare("SELECT COALESCE(SUM(gramos_entregados), 0) AS total_mes FROM dispensacion WHERE socio_id = ? AND fecha_dispensacion >= ? AND fecha_dispensacion < ? AND id <> ?");
        $stmt_sum->bind_param("issi", $socio_id, $inicio_mes, $inicio_mes_siguiente, $id);
        $stmt_sum->execute();
        $total_mes = (float)$stmt_sum->get_result()->fetch_assoc()['total_mes'];
        $stmt_sum->close();
        $limite_legal = (float)$socio['limite_gramos_mensual'];
        if (($total_mes + $gramos_entregados) > $limite_legal) {
            throw new Exception("El socio supera su límite mensual. Disponible restante: " . max(0, $limite_legal - $total_mes) . " g.");
        }

        // Bloquear ambos productos en orden estable antes de restaurar y descontar stock.
        $inventario_anterior_id = (int)$actual['inventario_derivado_id'];
        $gramos_anteriores = (float)$actual['gramos_entregados'];
        $ids_inventario = array_values(array_unique([$inventario_anterior_id, $inventario_derivado_id]));
        sort($ids_inventario, SORT_NUMERIC);
        $inventarios = [];
        $stmt_inv = $conexion->prepare("SELECT id, gramos_disponibles, gramos_reservados, estado_inventario FROM inventario_derivado WHERE id = ? FOR UPDATE");
        foreach ($ids_inventario as $id_inventario) {
            $stmt_inv->bind_param("i", $id_inventario);
            $stmt_inv->execute();
            $fila = $stmt_inv->get_result()->fetch_assoc();
            if ($fila) {
                $inventarios[(int)$fila['id']] = $fila;
            }
        }
        $stmt_inv->close();

        $inventario_nuevo = $inventarios[$inventario_derivado_id] ?? null;
        $es_mismo_producto = $inventario_anterior_id === $inventario_derivado_id;
        if (!$inventario_nuevo
            || ($es_mismo_producto && !in_array($inventario_nuevo['estado_inventario'], ['Disponible', 'Agotado'], true))
            || (!$es_mismo_producto && $inventario_nuevo['estado_inventario'] !== 'Disponible')) {
            throw new Exception("El producto seleccionado no está disponible.");
        }
        $stock_nuevo = (float)$inventario_nuevo['gramos_disponibles'];
        $movimientos_pendientes = [];
        if ($es_mismo_producto) {
            $stock_antes = (float)$inventario_nuevo['gramos_disponibles'];
            $stock_despues = $stock_antes + $gramos_anteriores - $gramos_entregados;
            $stock_nuevo += $gramos_anteriores;
            if (abs($gramos_anteriores - $gramos_entregados) > 0.000001) {
                $movimientos_pendientes[] = [$inventario_nuevo, 'Corrección de dispensación', $stock_antes, $stock_despues, 'Dispensación corregida.'];
            }
        }
        if ($stock_nuevo < $gramos_entregados) {
            throw new Exception("El producto no tiene stock suficiente.");
        }

        $stmt = $conexion->prepare("UPDATE dispensacion SET socio_id = ?, inventario_derivado_id = ?, fecha_dispensacion = ?, gramos_entregados = ?, observaciones = ?, updated_at = ? WHERE id = ?");
        $stmt->bind_param("iisdssi", $socio_id, $inventario_derivado_id, $fecha_dispensacion, $gramos_entregados, $observaciones, $updated_at, $id);
        $stmt->execute();
        $stmt->close();

        if ($es_mismo_producto) {
            if (abs($gramos_anteriores - $gramos_entregados) > 0.000001) {
                $estado_nuevo_stock = $stock_despues > 0.000001 ? 'Disponible' : 'Agotado';
                $stmt_inv = $conexion->prepare("UPDATE inventario_derivado SET gramos_disponibles = gramos_disponibles + ? - ?, estado_inventario = ? WHERE id = ? AND gramos_disponibles + ? >= ? AND estado_inventario IN ('Disponible','Agotado')");
                $stmt_inv->bind_param("ddsidd", $gramos_anteriores, $gramos_entregados, $estado_nuevo_stock, $inventario_derivado_id, $gramos_anteriores, $gramos_entregados);
                $stmt_inv->execute();
                if ($stmt_inv->affected_rows !== 1) {
                    throw new Exception("No se pudo ajustar el stock del producto.");
                }
                $stmt_inv->close();
            }
        } else {
            $inventario_anterior = $inventarios[$inventario_anterior_id] ?? null;
            if (!$inventario_anterior) {
                throw new Exception("El producto anterior de la dispensación ya no existe.");
            }
            $stock_anterior_antes = (float)$inventario_anterior['gramos_disponibles'];
            $movimientos_pendientes[] = [$inventario_anterior, 'Reintegro por corrección', $stock_anterior_antes, $stock_anterior_antes + $gramos_anteriores, 'Reintegro al corregir la dispensación.'];
            $estado_anterior_repuesto = in_array($inventario_anterior['estado_inventario'], ['Disponible', 'Agotado'], true) ? 'Disponible' : $inventario_anterior['estado_inventario'];
            $stock_nuevo_antes = (float)$inventario_nuevo['gramos_disponibles'];
            $movimientos_pendientes[] = [$inventario_nuevo, 'Dispensación corregida', $stock_nuevo_antes, $stock_nuevo_antes - $gramos_entregados, 'Nueva salida al corregir la dispensación.'];
            $stmt_restore = $conexion->prepare("UPDATE inventario_derivado SET gramos_disponibles = gramos_disponibles + ?, estado_inventario = ? WHERE id = ?");
            $stmt_restore->bind_param("dsi", $gramos_anteriores, $estado_anterior_repuesto, $inventario_anterior_id);
            $stmt_restore->execute();
            if ($stmt_restore->affected_rows !== 1) {
                throw new Exception("No se pudo restaurar el stock del producto anterior.");
            }
            $stmt_restore->close();

            $stmt_inv = $conexion->prepare("UPDATE inventario_derivado SET gramos_disponibles = gramos_disponibles - ?, estado_inventario = CASE WHEN gramos_disponibles - ? <= 0 THEN 'Agotado' ELSE 'Disponible' END WHERE id = ? AND gramos_disponibles >= ? AND estado_inventario = 'Disponible'");
            $stmt_inv->bind_param("ddid", $gramos_entregados, $gramos_entregados, $inventario_derivado_id, $gramos_entregados);
            $stmt_inv->execute();
            if ($stmt_inv->affected_rows !== 1) {
                throw new Exception("No se pudo descontar el stock del producto nuevo.");
            }
            $stmt_inv->close();
        }

        $usuario = $_SESSION['usuario'] ?? '';
        $stmt_usuario = $conexion->prepare("SELECT id FROM operadores WHERE usuario = ? LIMIT 1");
        $stmt_usuario->bind_param("s", $usuario);
        $stmt_usuario->execute();
        $operador = $stmt_usuario->get_result()->fetch_assoc();
        $stmt_usuario->close();
        if (!$operador) {
            throw new Exception("No se pudo identificar al operador de la sesión.");
        }
        $operador_id = (int)$operador['id'];
        $tipo_movimiento = '';
        $motivo = '';
        $dispensacion_id = $id;
        $disponibles_antes = 0.00;
        $disponibles_despues = 0.00;
        $reservados_antes = 0.00;
        $reservados_despues = 0.00;
        $inventario_movimiento_id = 0;
        $stmt_movimiento = $conexion->prepare("INSERT INTO movimientos_inventario (inventario_derivado_id, usuario_id, tipo_movimiento, gramos_disponibles_antes, gramos_disponibles_despues, gramos_reservados_antes, gramos_reservados_despues, dispensacion_id, motivo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($movimientos_pendientes as [$fila_inv, $tipo_movimiento, $disponibles_antes, $disponibles_despues, $motivo]) {
            $inventario_movimiento_id = (int)$fila_inv['id'];
            $reservados_antes = (float)$fila_inv['gramos_reservados'];
            $reservados_despues = $reservados_antes;
            $stmt_movimiento->bind_param("iisddddis", $inventario_movimiento_id, $operador_id, $tipo_movimiento, $disponibles_antes, $disponibles_despues, $reservados_antes, $reservados_despues, $dispensacion_id, $motivo);
            $stmt_movimiento->execute();
        }
        $stmt_movimiento->close();

        $stmt_auditado = $conexion->prepare('SELECT * FROM dispensacion WHERE id=?');
        $stmt_auditado->bind_param('i', $id);
        $stmt_auditado->execute();
        $dispensacion_nueva = $stmt_auditado->get_result()->fetch_assoc();
        $stmt_auditado->close();
        registrar_evento_bitacora($conexion, $operador_id, 'dispensacion', 'Actualizar', 'dispensacion', $id, $actual, $dispensacion_nueva);

        $conexion->commit();
        $conexion->close();

        header("Location: dispensacion.php?success=update&page=" . $page);
        exit();

    } catch (Throwable $e) {
        $conexion->rollback();
        $conexion->close();
        http_response_code(422);
        require_once __DIR__ . '/alertify_error.php';
        redirigir_excepcion_alertify($e, 'Error al actualizar la dispensación: ');
    }
} else {
    header("Location: dispensacion.php");
    exit();
}
?>
