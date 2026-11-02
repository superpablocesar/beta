<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION["usuario"])) {
    header("Location: ./login.php");
    exit();
}

require_once './csrf.php';
require_once "./conexion.php";
require_once "./validacion_variedades.php";
require_once "./alertify_error.php";
require_once __DIR__ . '/bitacora_helper.php';
$conexion = conexion();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validar_token_csrf_post();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $page = max(1, (int)($_POST['page'] ?? 1));

    if (!$id || $id < 1) {
        redirigir_error_alertify('El identificador de la genética no es válido.');
    }

    try {
        $conexion->begin_transaction();
        $datos = validar_datos_variedad($_POST);
        $stmt_existente = $conexion->prepare('SELECT id, nombre, tipo, porcentaje_thc_estimado, porcentaje_cbd_estimado, dias_floracion_estimados FROM variedades WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt_existente->bind_param('i', $id);
        $stmt_existente->execute();
        $existente = $stmt_existente->get_result()->fetch_assoc();
        $stmt_existente->close();
        if (!$existente) {
            throw new InvalidArgumentException('La genética que intentas actualizar ya no existe.');
        }
        $anteriores = $existente;

        $nombre = $datos['nombre'];
        $tipo = $datos['tipo'];
        $porcentaje_thc_estimado = $datos['porcentaje_thc_estimado'];
        $porcentaje_cbd_estimado = $datos['porcentaje_cbd_estimado'];
        $dias_floracion_estimados = $datos['dias_floracion_estimados'];
        $updated_at = date('Y-m-d H:i:s');
        $stmt = $conexion->prepare("UPDATE variedades SET nombre = ?, tipo = ?, porcentaje_thc_estimado = ?, porcentaje_cbd_estimado = ?, dias_floracion_estimados = ?, updated_at = ? WHERE id = ?");
        $stmt->bind_param("ssddisi", $nombre, $tipo, $porcentaje_thc_estimado, $porcentaje_cbd_estimado, $dias_floracion_estimados, $updated_at, $id);
        
        $stmt->execute();
        $stmt->close();
        registrar_evento_bitacora($conexion, operador_id_bitacora($conexion, (string)$_SESSION['usuario']), 'variedades', 'Actualizar', 'variedades', (int)$id, $anteriores, $datos);
        $conexion->commit();
        $conexion->close();

        header("Location: variedades.php?success=update&page=" . $page);
        exit();

    } catch (Throwable $e) {
        $conexion->close();
        redirigir_excepcion_alertify($e, 'Error al actualizar la variedad: ');
    }
} else {
    header("Location: variedades.php");
    exit();
}
?>
