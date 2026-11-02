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
    $socio_id = isset($_POST['socio_id']) ? (int)$_POST['socio_id'] : 0;
    $inventario_derivado_id = isset($_POST['inventario_derivado_id']) ? (int)$_POST['inventario_derivado_id'] : 0;
    $fecha_dispensacion = !empty($_POST['fecha_dispensacion']) ? trim($_POST['fecha_dispensacion']) : date('Y-m-d');
    $gramos_raw = trim((string)($_POST['gramos_entregados'] ?? ''));
    $gramos_entregados = is_numeric($gramos_raw) ? (float)$gramos_raw : 0.00;
    $observaciones = trim($_POST['observaciones'] ?? '');
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

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

        // Obtener límite mensual del socio.
        $stmt_socio = $conexion->prepare("SELECT limite_gramos_mensual, estado_membresia, reprocann_vencimiento FROM socios WHERE id = ? FOR UPDATE");
        $stmt_socio->bind_param("i", $socio_id);
        $stmt_socio->execute();
        $res_socio = $stmt_socio->get_result();
        if ($res_socio->num_rows === 0) {
            throw new Exception("El socio seleccionado no existe.");
        }
        $socio_data = $res_socio->fetch_assoc();
        if ($socio_data['estado_membresia'] !== 'Activo') {
            throw new Exception("El socio no tiene la membresía activa.");
        }
        if ($socio_data['reprocann_vencimiento'] < date('Y-m-d')
            || $socio_data['reprocann_vencimiento'] < $fecha_dispensacion) {
            throw new Exception("El permiso REPROCANN está vencido para la fecha de dispensación.");
        }
        $limite_legal = (float)$socio_data['limite_gramos_mensual'];
        $stmt_socio->close();

        // Sumar las entregas del mismo mes y año que la fecha registrada.
        $inicio_mes = $fecha_validada->modify('first day of this month')->format('Y-m-d');
        $inicio_mes_siguiente = $fecha_validada->modify('first day of next month')->format('Y-m-d');
        $stmt_sum = $conexion->prepare("SELECT COALESCE(SUM(gramos_entregados), 0) AS total_mes FROM dispensacion WHERE socio_id = ? AND fecha_dispensacion >= ? AND fecha_dispensacion < ?");
        $stmt_sum->bind_param("iss", $socio_id, $inicio_mes, $inicio_mes_siguiente);
        $stmt_sum->execute();
        $res_sum = $stmt_sum->get_result();
        $sum_data = $res_sum->fetch_assoc();
        $total_mes_actual = (float)($sum_data['total_mes'] ?? 0);
        $stmt_sum->close();

        if (($total_mes_actual + $gramos_entregados) > $limite_legal) {
            $disponible_restante = max(0, $limite_legal - $total_mes_actual);
            throw new Exception("El socio supera su límite mensual. Disponible restante: {$disponible_restante} g.");
        }

        // Bloquear y validar el inventario para evitar descontar stock insuficiente.
        $stmt_inv = $conexion->prepare("SELECT gramos_disponibles, gramos_reservados, estado_inventario FROM inventario_derivado WHERE id = ? FOR UPDATE");
        $stmt_inv->bind_param("i", $inventario_derivado_id);
        $stmt_inv->execute();
        $res_inv = $stmt_inv->get_result();
        $inventario = $res_inv->fetch_assoc();
        $stmt_inv->close();
        if (!$inventario || $inventario['estado_inventario'] !== 'Disponible'
            || (float)$inventario['gramos_disponibles'] < $gramos_entregados) {
            throw new Exception("El producto no está disponible o no tiene stock suficiente.");
        }

        $stock_antes = (float)$inventario['gramos_disponibles'];
        $reservados = (float)$inventario['gramos_reservados'];
        $stmt = $conexion->prepare("INSERT INTO dispensacion (socio_id, inventario_derivado_id, fecha_dispensacion, gramos_entregados, observaciones) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisds", $socio_id, $inventario_derivado_id, $fecha_dispensacion, $gramos_entregados, $observaciones);
        $stmt->execute();
        $dispensacion_id = $conexion->insert_id;
        $stmt->close();

        $stmt_inv = $conexion->prepare("UPDATE inventario_derivado SET gramos_disponibles = gramos_disponibles - ?, estado_inventario = CASE WHEN gramos_disponibles - ? <= 0 THEN 'Agotado' ELSE 'Disponible' END WHERE id = ? AND gramos_disponibles >= ? AND estado_inventario = 'Disponible'");
        $stmt_inv->bind_param("ddid", $gramos_entregados, $gramos_entregados, $inventario_derivado_id, $gramos_entregados);
        $stmt_inv->execute();
        if ($stmt_inv->affected_rows !== 1) {
            throw new Exception("No se pudo descontar el stock del producto.");
        }
        $stmt_inv->close();

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
        $tipo_movimiento = 'Dispensación';
        $stock_despues = $stock_antes - $gramos_entregados;
        $motivo = 'Dispensación registrada.';
        $stmt_movimiento = $conexion->prepare("INSERT INTO movimientos_inventario (inventario_derivado_id, usuario_id, tipo_movimiento, gramos_disponibles_antes, gramos_disponibles_despues, gramos_reservados_antes, gramos_reservados_despues, dispensacion_id, motivo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_movimiento->bind_param("iisddddis", $inventario_derivado_id, $operador_id, $tipo_movimiento, $stock_antes, $stock_despues, $reservados, $reservados, $dispensacion_id, $motivo);
        $stmt_movimiento->execute();
        $stmt_movimiento->close();

        registrar_evento_bitacora($conexion, $operador_id, 'dispensacion', 'Crear', 'dispensacion', (int)$dispensacion_id, null, [
            'socio_id' => $socio_id,
            'inventario_derivado_id' => $inventario_derivado_id,
            'fecha_dispensacion' => $fecha_dispensacion,
            'gramos_entregados' => $gramos_entregados,
            'observaciones' => $observaciones,
        ]);

        $conexion->commit();
        $conexion->close();

        header("Location: dispensacion.php?success=add&page=" . $page);
        exit();

    } catch (Throwable $e) {
        $conexion->rollback();
        $conexion->close();
        http_response_code(422);
        require_once __DIR__ . '/alertify_error.php';
        redirigir_excepcion_alertify($e, 'Error en la dispensación: ');
    }
} else {
    header("Location: dispensacion.php");
    exit();
}
?>
