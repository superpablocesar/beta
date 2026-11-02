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
    $cosecha_id = isset($_POST['cosecha_id']) ? (int)$_POST['cosecha_id'] : 0;
    $codigo_qr = is_scalar($_POST['codigo_qr'] ?? null) ? trim((string)$_POST['codigo_qr']) : '';
    $tipo_producto = is_scalar($_POST['tipo_producto'] ?? null) ? trim((string)$_POST['tipo_producto']) : '';
    $disponibles_raw = is_scalar($_POST['gramos_disponibles'] ?? null) ? trim((string)$_POST['gramos_disponibles']) : '';
    $reservados_raw = is_scalar($_POST['gramos_reservados'] ?? null) ? trim((string)$_POST['gramos_reservados']) : '0';
    $gramos_disponibles = is_numeric($disponibles_raw) ? (float)$disponibles_raw : -1;
    $gramos_reservados = is_numeric($reservados_raw) ? (float)$reservados_raw : -1;
    $estado_inventario = is_scalar($_POST['estado_inventario'] ?? null) ? trim((string)$_POST['estado_inventario']) : '';
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

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
    if (($estado_inventario === 'Disponible' && $gramos_disponibles <= 0)
        || ($estado_inventario === 'Agotado' && $gramos_disponibles > 0)) {
        redirigir_error_alertify('Un producto disponible necesita gramos disponibles; un producto agotado debe tener cero gramos disponibles.');
    }

    try {
        $conexion->begin_transaction();
        // El bloqueo de la cosecha serializa altas simultáneas del mismo origen.
        $stmt_cosecha = $conexion->prepare("SELECT id, peso_seco_gramos FROM cosechas WHERE id = ? FOR UPDATE");
        $stmt_cosecha->bind_param("i", $cosecha_id);
        $stmt_cosecha->execute();
        $cosecha = $stmt_cosecha->get_result()->fetch_assoc();
        $stmt_cosecha->close();
        if (!$cosecha) {
            throw new Exception("La cosecha seleccionada no existe.");
        }
        $stmt_total = $conexion->prepare("SELECT COALESCE(SUM(gramos_disponibles + gramos_reservados), 0) AS total FROM inventario_derivado WHERE cosecha_id = ?");
        $stmt_total->bind_param("i", $cosecha_id);
        $stmt_total->execute();
        $total_existente = (float)$stmt_total->get_result()->fetch_assoc()['total'];
        $stmt_total->close();
        $limite_cosecha = (float)$cosecha['peso_seco_gramos'];
        if ($total_existente + $gramos_disponibles + $gramos_reservados > $limite_cosecha + 0.000001) {
            throw new Exception("El inventario derivado superaría el peso seco de la cosecha. Disponible para asignar: " . number_format(max(0, $limite_cosecha - $total_existente), 2, ',', '.') . " g.");
        }
        $stmt_qr = $conexion->prepare("SELECT id FROM inventario_derivado WHERE codigo_qr = ? LIMIT 1");
        $stmt_qr->bind_param("s", $codigo_qr);
        $stmt_qr->execute();
        $qr_existente = $stmt_qr->get_result()->fetch_assoc();
        $stmt_qr->close();
        if ($qr_existente) {
            throw new Exception("Ya existe un producto con ese código QR.");
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

        $stmt = $conexion->prepare("INSERT INTO inventario_derivado (cosecha_id, codigo_qr, tipo_producto, gramos_disponibles, gramos_reservados, estado_inventario) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdds", $cosecha_id, $codigo_qr, $tipo_producto, $gramos_disponibles, $gramos_reservados, $estado_inventario);
        $stmt->execute();
        $inventario_id = $conexion->insert_id;
        $stmt->close();

        $operador_id = (int)$operador['id'];
        $tipo_movimiento = 'Saldo inicial';
        $disponibles_antes = 0.00;
        $reservados_antes = 0.00;
        $dispensacion_id = null;
        $motivo = 'Saldo inicial al crear el producto.';
        $stmt_movimiento = $conexion->prepare("INSERT INTO movimientos_inventario (inventario_derivado_id, usuario_id, tipo_movimiento, gramos_disponibles_antes, gramos_disponibles_despues, gramos_reservados_antes, gramos_reservados_despues, dispensacion_id, motivo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_movimiento->bind_param("iisddddis", $inventario_id, $operador_id, $tipo_movimiento, $disponibles_antes, $gramos_disponibles, $reservados_antes, $gramos_reservados, $dispensacion_id, $motivo);
        $stmt_movimiento->execute();
        $stmt_movimiento->close();

        registrar_evento_bitacora($conexion, $operador_id, 'inventario', 'Crear', 'inventario_derivado', (int)$inventario_id, null, [
            'cosecha_id' => $cosecha_id,
            'codigo_qr' => $codigo_qr,
            'tipo_producto' => $tipo_producto,
            'gramos_disponibles' => $gramos_disponibles,
            'gramos_reservados' => $gramos_reservados,
            'estado_inventario' => $estado_inventario,
        ]);

        $conexion->commit();
        $conexion->close();

        header("Location: inventario_derivado.php?success=add&page=" . $page);
        exit();

    } catch (Throwable $e) {
        $conexion->rollback();
        $conexion->close();
        http_response_code(422);
        require_once __DIR__ . '/alertify_error.php';
        redirigir_excepcion_alertify($e, '<strong>Error al guardar el producto en el inventario:</strong> ');
    }
} else {
    header("Location: inventario_derivado.php");
    exit();
}
?>
