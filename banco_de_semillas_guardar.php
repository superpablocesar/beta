<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) {
    header('Location: ./login.php');
    exit();
}

require_once './conexion.php';
require_once './csrf.php';
require_once './validacion_banco_semillas.php';
require_once './registro_movimientos_banco.php';
require_once './alertify_error.php';
require_once __DIR__ . '/bitacora_helper.php';
$conexion = conexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: banco_de_semillas.php');
    exit();
}
validar_token_csrf_post();

$page = max(1, (int)($_POST['page'] ?? 1));

try {
    $datos = validar_datos_banco_semillas($_POST, true);
    $conexion->begin_transaction();
    validar_referencias_banco_semillas($conexion, $datos);
    $usuario_id = obtener_operador_banco_sesion($conexion, (string)$_SESSION['usuario']);

    $variedad_id = $datos['variedad_id'];
    $tipo_material = $datos['tipo_material'];
    $origen_tipo = $datos['origen_tipo'];
    $origen_proveedor = $datos['origen_proveedor'];
    $numero_lote_origen = $datos['numero_lote_origen'];
    $planta_madre_id = $datos['planta_madre_id'];
    $planta_padre_id = $datos['planta_padre_id'];
    $fecha_polinizacion = $datos['fecha_polinizacion'];
    $cantidad_disponible = $datos['cantidad_disponible'];
    $ubicacion_almacen = $datos['ubicacion_almacen'];

    $stmt = $conexion->prepare("INSERT INTO banco_semillas (variedad_id, tipo_material, origen_tipo, origen_proveedor, numero_lote_origen, planta_madre_id, planta_padre_id, fecha_polinizacion, cantidad_disponible, ubicacion_almacen, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param(
        'issssiisis',
        $variedad_id,
        $tipo_material,
        $origen_tipo,
        $origen_proveedor,
        $numero_lote_origen,
        $planta_madre_id,
        $planta_padre_id,
        $fecha_polinizacion,
        $cantidad_disponible,
        $ubicacion_almacen
    );
    $stmt->execute();
    $banco_id = $conexion->insert_id;
    $stmt->close();
    registrar_movimiento_banco_semillas($conexion, $banco_id, $usuario_id, 'Alta inicial', 0, $cantidad_disponible, null, 'Stock inicial al registrar el material.');
    registrar_evento_bitacora($conexion, $usuario_id, 'banco_semillas', 'Crear', 'banco_semillas', $banco_id, null, $datos);
    $conexion->commit();
    $conexion->close();

    header('Location: banco_de_semillas.php?success=add&page=' . $page);
    exit();
} catch (Throwable $e) {
    $conexion->rollback();
    $conexion->close();
    redirigir_excepcion_alertify($e, 'No se pudo guardar en el banco de semillas: ');
}
