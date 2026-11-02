<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION["usuario"])) {
    header("Location: ./login.php");
    exit();
}

require_once "./conexion.php";
$conexion = conexion();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: plantas_madre.php");
    exit();
}

$stmt = $conexion->prepare("SELECT pm.id, pm.codigo_planta, pm.ubicacion_sala, pm.estado, pm.fecha_nacimiento, 
                                   v.nombre AS variedad_nombre, v.tipo AS variedad_tipo 
                            FROM plantas_madre pm 
                            INNER JOIN variedades v ON pm.variedad_id = v.id 
                            WHERE pm.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: plantas_madre.php");
    exit();
}

$planta = $result->fetch_assoc();
$stmt->close();

$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$url_qr_destino = "{$protocolo}://{$host}/visor.php?id=" . $planta['id'];
$api_qr = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=15&data=" . urlencode($url_qr_destino);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>QR Planta - <?php echo htmlspecialchars($planta['codigo_planta']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            background-color: #fff;
            color: #000;
            margin: 0;
            padding: 20px;
        }
        .etiqueta-container {
            border: 2px dashed #333;
            border-radius: 10px;
            padding: 20px;
            max-width: 320px;
            margin: 0 auto;
        }
        h2 {
            margin: 0 0 5px 0;
            font-size: 22px;
        }
        p {
            margin: 5px 0;
            font-size: 14px;
        }
        .qr-img {
            width: 220px;
            height: 220px;
            margin: 10px 0;
        }
        .info {
            font-size: 12px;
            color: #555;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
            .etiqueta-container {
                border: none;
            }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="etiqueta-container">
        <h2><?php echo htmlspecialchars($planta['codigo_planta']); ?></h2>
        <p><strong>Genética:</strong> <?php echo htmlspecialchars($planta['variedad_nombre']); ?> (<?php echo htmlspecialchars($planta['variedad_tipo']); ?>)</p>
        <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($planta['ubicacion_sala'] ?: 'N/D'); ?></p>
        
        <div>
            <img src="<?php echo $api_qr; ?>" alt="QR Code" class="qr-img">
        </div>
        
        <div class="info">
            Estado: <?php echo htmlspecialchars($planta['estado']); ?>
        </div>
    </div>

    <div class="no-print" style="margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">Imprimir / Guardar PDF</button>
        <br><br>
        <a href="javascript:window.close();" style="color: #000; text-decoration: underline;">Cerrar ventana</a>
    </div>

</body>
</html>