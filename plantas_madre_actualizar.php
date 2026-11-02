<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION["usuario"])) {
    header("Location: ./login.php");
    exit();
}

require_once './csrf.php';
require_once "./conexion.php";
require_once __DIR__ . '/bitacora_helper.php';
$conexion = conexion();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validar_token_csrf_post();
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

    if ($id <= 0) {
        header("Location: plantas_madre.php?page=" . $page);
        exit();
    }

    $codigo_planta = is_scalar($_POST['codigo_planta'] ?? null) ? trim((string)$_POST['codigo_planta']) : '';
    $variedad_id = filter_var($_POST['variedad_id'] ?? null, FILTER_VALIDATE_INT);
    $ubicacion_sala = is_scalar($_POST['ubicacion_sala'] ?? null) ? trim((string)$_POST['ubicacion_sala']) : '';
    $estado = is_scalar($_POST['estado'] ?? null) ? trim((string)$_POST['estado']) : '';
    $fecha_raw = is_scalar($_POST['fecha_nacimiento'] ?? null) ? trim((string)$_POST['fecha_nacimiento']) : '';
    $fecha_nacimiento = null;
    $updated_at = date('Y-m-d H:i:s');
    $estados_validos = ['Activa', 'En Descanso', 'Jubilada/Baja'];
    $fecha_valida = $fecha_raw === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $fecha_raw);

    if ($codigo_planta === '' || strlen($codigo_planta) > 50 || !$variedad_id || $variedad_id < 1
        || strlen($ubicacion_sala) > 100 || !in_array($estado, $estados_validos, true)
        || ($fecha_raw !== '' && (!$fecha_valida || $fecha_valida->format('Y-m-d') !== $fecha_raw))) {
        require_once __DIR__ . '/alertify_error.php';
        redirigir_error_alertify('Revisa el código (máximo 50 caracteres), la genética, la ubicación, el estado y la fecha de la planta madre.');
    }
    if ($fecha_valida) {
        $fecha_nacimiento = $fecha_valida->format('Y-m-d');
    }

    try {
        $conexion->begin_transaction();
        $stmt_existente = $conexion->prepare("SELECT id, codigo_planta, variedad_id, ubicacion_sala, estado, fecha_nacimiento FROM plantas_madre WHERE id = ? LIMIT 1 FOR UPDATE");
        $stmt_existente->bind_param("i", $id);
        $stmt_existente->execute();
        $existente = $stmt_existente->get_result()->fetch_assoc();
        $stmt_existente->close();
        if (!$existente) {
            throw new RuntimeException('La planta madre que intentas actualizar ya no existe.');
        }
        $anteriores = $existente;

        $stmt_variedad = $conexion->prepare("SELECT id FROM variedades WHERE id = ? LIMIT 1");
        $stmt_variedad->bind_param("i", $variedad_id);
        $stmt_variedad->execute();
        $variedad = $stmt_variedad->get_result()->fetch_assoc();
        $stmt_variedad->close();
        if (!$variedad) {
            throw new RuntimeException('La genética seleccionada no existe.');
        }

        $stmt_codigo = $conexion->prepare("SELECT id FROM plantas_madre WHERE codigo_planta = ? AND id <> ? LIMIT 1");
        $stmt_codigo->bind_param("si", $codigo_planta, $id);
        $stmt_codigo->execute();
        $duplicada = $stmt_codigo->get_result()->fetch_assoc();
        $stmt_codigo->close();
        if ($duplicada) {
            throw new RuntimeException('Ya existe otra planta madre con ese código.');
        }

        $stmt = $conexion->prepare("UPDATE plantas_madre SET codigo_planta = ?, variedad_id = ?, ubicacion_sala = ?, estado = ?, fecha_nacimiento = ?, updated_at = ? WHERE id = ?");
        $stmt->bind_param("sissssi", $codigo_planta, $variedad_id, $ubicacion_sala, $estado, $fecha_nacimiento, $updated_at, $id);
        
        $stmt->execute();
        $stmt->close();
        $nuevos = ['codigo_planta'=>$codigo_planta, 'variedad_id'=>(int)$variedad_id, 'ubicacion_sala'=>$ubicacion_sala, 'estado'=>$estado, 'fecha_nacimiento'=>$fecha_nacimiento];
        registrar_evento_bitacora($conexion, operador_id_bitacora($conexion, (string)$_SESSION['usuario']), 'plantas_madre', 'Actualizar', 'plantas_madre', (int)$id, $anteriores, $nuevos);
        $conexion->commit();
        $conexion->close();

        header("Location: plantas_madre.php?success=update&page=" . $page);
        exit();

    } catch (Throwable $e) {
        require_once __DIR__ . '/alertify_error.php';
        redirigir_excepcion_alertify($e, 'Error al actualizar la planta madre: ');
    }
} else {
    header("Location: plantas_madre.php");
    exit();
}
?>
