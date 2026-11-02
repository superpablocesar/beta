<?php
/** Inicia la sesión con una cookie protegida y aislada entre sitios. */
function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $parametros_cookie = session_get_cookie_params();
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    $https = $https || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    $parametros_cookie['secure'] = $https;
    $parametros_cookie['httponly'] = true;
    $parametros_cookie['samesite'] = 'Lax';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params($parametros_cookie);
    session_start();

    if (!empty($_SESSION['usuario'])) {
        require_once __DIR__ . '/permisos_operador.php';
        autorizar_ruta_actual((string)$_SESSION['usuario']);
    }
}
