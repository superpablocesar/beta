<?php
require_once __DIR__ . '/sesion_segura.php';
/** Guarda el error y vuelve al formulario para que Alertify lo muestre allí. */
function redirigir_error_alertify(string $mensaje, ?int $codigo_http = null): never
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        iniciar_sesion_segura();
    }
    $_SESSION['flash_alertify_error'] = strip_tags($mensaje);

    $actual = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $destino = null;
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $referer_url = $referer !== '' ? parse_url($referer) : false;
    $host_actual = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if (is_array($referer_url)
        && isset($referer_url['host'], $referer_url['path'])
        && strtolower($referer_url['host']) === strtolower((string)parse_url('http://' . $host_actual, PHP_URL_HOST))
        && preg_match('/_(?:editar|agregar)\.php$/i', basename($referer_url['path']))) {
        $destino = $referer_url['path'] . (isset($referer_url['query']) ? '?' . $referer_url['query'] : '');
    }

    if ($destino === null && preg_match('/_(guardar|actualizar)\.php$/i', $actual, $coincidencia)) {
        $sufijo = strtolower($coincidencia[1]) === 'guardar' ? 'agregar' : 'editar';
        $destino = preg_replace('/_(?:guardar|actualizar)\.php$/i', '_' . $sufijo . '.php', $actual);
        if (!is_file(__DIR__ . DIRECTORY_SEPARATOR . $destino)) {
            $destino = null;
        }
    }

    if ($destino === null && $actual === 'login.php') {
        $destino = 'login.php';
    }

    if ($destino !== null && !headers_sent()) {
        header('Location: ' . $destino, true, 303);
        exit;
    }

    // Si falla la carga del propio formulario o no existe un destino seguro,
    // presenta una página mínima con una notificación no bloqueante.
    if ($codigo_http !== null && !headers_sent()) {
        http_response_code($codigo_http);
    }
    $mensaje_js = json_encode(
        htmlspecialchars($_SESSION['flash_alertify_error'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    );
    unset($_SESSION['flash_alertify_error']);
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Error · FLOWHER</title>
        <link rel="stylesheet" href="https://buho.pm/admin/css/alertify.css">
        <script src="https://buho.pm/admin/js/alertify.js"></script>
        <script src="./validacion_alertify.js"></script>
    </head>
    <body>
        <script>
            const mensaje = <?= $mensaje_js ?: '"Se produjo un error."' ?>;
            window.mostrarNotificacionError(mensaje, 8);
        </script>
        <p><a href="./">Volver al panel</a></p>
    </body>
    </html>
    <?php
    exit;
}

/** Muestra validaciones esperadas y oculta detalles técnicos de excepciones. */
function redirigir_excepcion_alertify(Throwable $error, string $mensaje_generico, ?int $codigo_http = null): never
{
    if ($error instanceof InvalidArgumentException || $error instanceof DomainException) {
        redirigir_error_alertify($error->getMessage(), $codigo_http);
    }

    error_log('[FLOWHER] ' . get_class($error) . ': ' . $error->getMessage());
    redirigir_error_alertify($mensaje_generico, $codigo_http);
}

/** Emite una notificación de error al cargar una pantalla de alta o edición. */
function mostrar_error_alertify_flash_en_formulario(): void
{
    $pagina = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!preg_match('/_(?:editar|agregar)\.php$/i', $pagina) && $pagina !== 'login.php') {
        return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        iniciar_sesion_segura();
    }
    if (empty($_SESSION['flash_alertify_error'])) {
        return;
    }

    $mensaje = json_encode(
        htmlspecialchars((string)$_SESSION['flash_alertify_error'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    );
    unset($_SESSION['flash_alertify_error']);
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mensaje = <?= $mensaje ?: '"Se produjo un error."' ?>;
            window.mostrarNotificacionError(mensaje, 8);
        });
    </script>
    <?php
}
