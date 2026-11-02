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
        header("Location: inventario_derivado.php?page=" . $page);
        exit();
    }

    $cosecha_id = isset($_POST['cosecha_id']) ? (int)$_POST['cosecha_id'] : 0;
    $codigo_qr = is_scalar($_POST['codigo_qr'] ?? null) ? trim((string)$_POST['codigo_qr']) : '';
    $tipo_producto = is_scalar($_POST['tipo_producto'] ?? null) ? trim((string)$_POST['tipo_producto']) : '';
    $disponibles_raw = is_scalar($_POST['gramos_disponibles'] ?? null) ? trim((string)$_POST['gramos_disponibles']) : '';
    $reservados_raw = is_scalar($_POST['gramos_reservados'] ?? null) ? trim((string)$_POST['gramos_reservados']) : '0';
    $gramos_disponibles = is_numeric($disponibles_raw) ? (float)$disponibles_raw : -1;
    $gramos_reservados = is_numeric($reservados_raw) ? (float)$reservados_raw : -1;
    $estado_inventario = is_scalar($_POST['estado_inventario'] ?? null) ? trim((string)$_POST['estado_inventario']) : '';
    $motivo_ajuste = is_scalar($_POST['motivo_ajuste'] ?? null) ? trim((string)$_POST['motivo_ajuste']) : '';

    $tipos_producto = ['Flor Seca', 'Aceite/Tintura', 'Extracto/Rosine', 'Preroll'];
    $estados_inventario = ['En Curado', 'Disponible', 'Agotado', 'Retirado por Calidad'];
    $formato_gramos_valido = static fn($valor) => preg_match('/^\d+(?:\.\d{1,2})?$/', $valor) === 1;
    if ($cosecha_id <= 0 || $codigo_qr === '' || strlen($codigo_qr) > 100
        || $gramos_disponibles > 999999.99 || $gramos_reservados > 999999.99
        || !in_array($tipo_producto, $tipos_producto, true)
        || !$formato_gramos_valido($disponibles_raw) || !$formato_gramos_valido($reservados_raw)
        || !in_array($estado_inventario, $estados_inventario, true)) {
        http_response_code(422);
        require_once __DIR__ . '/alertify_error.php';
        redirigir_error_alertify('Revisa el código, la cosecha, el tipo, el estado y las cantidades del producto.');
    }

    if (strlen($motivo_ajuste) > 255) {
        http_response_code(422);
        require_once __DIR__ . '/alertify_error.php';
        redirigir_error_alertify('El motivo del ajuste no puede superar los 255 caracteres.');
    }
    if (($estado_inventario === 'Disponible' && $gramos_disponibles <= 0)
        || ($estado_inventario === 'Agotado' && $gramos_disponibles > 0)) {
        redirigir_error_alertify('Un producto disponible necesita gramos disponibles; un producto agotado debe tener cero gramos disponibles.');
    }

    try {
        $conexion->begin_transaction();
        $stmt_actual = $conexion->prepare("SELECT * FROM inventario_derivado WHERE id = ? FOR UPDATE");
        $stmt_actual->bind_param("i", $id);
        $stmt_actual->execute();
        $actual = $stmt_actual->get_result()->fetch_assoc();
        $stmt_actual->close();
        if (!$actual) {
            throw new Exception("El producto seleccionado no existe.");
        }

        // Bloquear cosechas en orden estable para evitar exceder el rendimiento con ediciones simultáneas.
        $ids_cosecha = array_values(array_unique([(int)$actual['cosecha_id'], $cosecha_id]));
        sort($ids_cosecha, SORT_NUMERIC);
        $cosechas_bloqueadas = [];
        $stmt_cosecha = $conexion->prepare("SELECT id, peso_seco_gramos FROM cosechas WHERE id = ? FOR UPDATE");
        foreach ($ids_cosecha as $id_cosecha) {
            $stmt_cosecha->bind_param("i", $id_cosecha);
            $stmt_cosecha->execute();
            $fila_cosecha = $stmt_cosecha->get_result()->fetch_assoc();
            if ($fila_cosecha) {
                $cosechas_bloqueadas[(int)$fila_cosecha['id']] = $fila_cosecha;
            }
        }
        $stmt_cosecha->close();
        $cosecha = $cosechas_bloqueadas[$cosecha_id] ?? null;
        if (!$cosecha) {
            throw new Exception("La cosecha seleccionada no existe.");
        }
        $stmt_total = $conexion->prepare("SELECT COALESCE(SUM(gramos_disponibles + gramos_reservados), 0) AS total FROM inventario_derivado WHERE cosecha_id = ? AND id <> ?");
        $stmt_total->bind_param("ii", $cosecha_id, $id);
        $stmt_total->execute();
        $total_otros = (float)$stmt_total->get_result()->fetch_assoc()['total'];
        $stmt_total->close();
        $total_propuesto = $total_otros + $gramos_disponibles + $gramos_reservados;
        $limite_cosecha = (float)$cosecha['peso_seco_gramos'];
        $total_actual_producto = (float)$actual['gramos_disponibles'] + (float)$actual['gramos_reservados'];
        $es_reduccion_de_exceso_existente = (int)$actual['cosecha_id'] === $cosecha_id
            && $gramos_disponibles + $gramos_reservados <= $total_actual_producto + 0.000001;
        if ($total_propuesto > $limite_cosecha + 0.000001 && !$es_reduccion_de_exceso_existente) {
            throw new Exception("El inventario derivado superaría el peso seco de la cosecha. Disponible para asignar: " . number_format(max(0, $limite_cosecha - $total_otros - $total_actual_producto), 2, ',', '.') . " g.");
        }
        $stmt_qr = $conexion->prepare("SELECT id FROM inventario_derivado WHERE codigo_qr = ? AND id <> ? LIMIT 1");
        $stmt_qr->bind_param("si", $codigo_qr, $id);
        $stmt_qr->execute();
        $qr_existente = $stmt_qr->get_result()->fetch_assoc();
        $stmt_qr->close();
        if ($qr_existente) {
            throw new Exception("Ya existe otro producto con ese código QR.");
        }

        $disponibles_antes = (float)$actual['gramos_disponibles'];
        $reservados_antes = (float)$actual['gramos_reservados'];
        $hay_cambio_stock = abs($disponibles_antes - $gramos_disponibles) > 0.000001
            || abs($reservados_antes - $gramos_reservados) > 0.000001;
        if ($hay_cambio_stock && $motivo_ajuste === '') {
            throw new Exception("Indica el motivo para ajustar las cantidades del producto.");
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

        $stmt = $conexion->prepare("UPDATE inventario_derivado SET cosecha_id = ?, codigo_qr = ?, tipo_producto = ?, gramos_disponibles = ?, gramos_reservados = ?, estado_inventario = ? WHERE id = ?");
        $stmt->bind_param("issddsi", $cosecha_id, $codigo_qr, $tipo_producto, $gramos_disponibles, $gramos_reservados, $estado_inventario, $id);
        $stmt->execute();
        $stmt->close();

        if ($hay_cambio_stock) {
            $tipo_movimiento = 'Ajuste';
            $dispensacion_id = null;
            $stmt_movimiento = $conexion->prepare("INSERT INTO movimientos_inventario (inventario_derivado_id, usuario_id, tipo_movimiento, gramos_disponibles_antes, gramos_disponibles_despues, gramos_reservados_antes, gramos_reservados_despues, dispensacion_id, motivo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_movimiento->bind_param("iisddddis", $id, $operador_id, $tipo_movimiento, $disponibles_antes, $gramos_disponibles, $reservados_antes, $gramos_reservados, $dispensacion_id, $motivo_ajuste);
            $stmt_movimiento->execute();
            $stmt_movimiento->close();
        }

        $stmt_auditado = $conexion->prepare('SELECT * FROM inventario_derivado WHERE id=?');
        $stmt_auditado->bind_param('i', $id);
        $stmt_auditado->execute();
        $inventario_nuevo = $stmt_auditado->get_result()->fetch_assoc();
        $stmt_auditado->close();
        registrar_evento_bitacora($conexion, $operador_id, 'inventario', 'Actualizar', 'inventario_derivado', $id, $actual, $inventario_nuevo);

        $conexion->commit();
        $conexion->close();

        header("Location: inventario_derivado.php?success=update&page=" . $page);
        exit();

    } catch (Throwable $e) {
        $conexion->rollback();
        $conexion->close();
        http_response_code(422);
        require_once __DIR__ . '/alertify_error.php';
        redirigir_excepcion_alertify($e, '<strong>Error al actualizar el producto en el inventario:</strong> ');
    }
} else {
    header("Location: inventario_derivado.php");
    exit();
}
?>
