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
    $page = max(1, (int)($_POST['page'] ?? 1));

    try {
        $conexion->begin_transaction();
        $datos = validar_datos_variedad($_POST);
        $nombre = $datos['nombre'];
        $tipo = $datos['tipo'];
        $porcentaje_thc_estimado = $datos['porcentaje_thc_estimado'];
        $porcentaje_cbd_estimado = $datos['porcentaje_cbd_estimado'];
        $dias_floracion_estimados = $datos['dias_floracion_estimados'];
        $stmt = $conexion->prepare("INSERT INTO variedades (nombre, tipo, porcentaje_thc_estimado, porcentaje_cbd_estimado, dias_floracion_estimados) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddi", $nombre, $tipo, $porcentaje_thc_estimado, $porcentaje_cbd_estimado, $dias_floracion_estimados);
        
        $stmt->execute();
        $id_variedad = (int)$conexion->insert_id;
        $stmt->close();
        registrar_evento_bitacora($conexion, operador_id_bitacora($conexion, (string)$_SESSION['usuario']), 'variedades', 'Crear', 'variedades', $id_variedad, null, $datos);
        $conexion->commit();
        $conexion->close();

        header("Location: variedades.php?success=add&page=" . $page);
        exit();

    } catch (Throwable $e) {
        $conexion->close();
        redirigir_excepcion_alertify($e, 'Error al guardar la variedad: ');
    }
} else {
    header("Location: variedades.php");
    exit();
}
?>
