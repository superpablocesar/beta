<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
$parametros_cookie = session_get_cookie_params();
session_unset();
session_destroy();

if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $parametros_cookie['path'],
        'domain' => $parametros_cookie['domain'],
        'secure' => $parametros_cookie['secure'],
        'httponly' => $parametros_cookie['httponly'],
        'samesite' => $parametros_cookie['samesite'] ?? 'Lax',
    ]);
}

header("Location: ./login.php");
exit();
?>
