<?php
require_once __DIR__ . '/sesion_segura.php';
function obtener_token_csrf(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) iniciar_sesion_segura();
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function campo_token_csrf(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(obtener_token_csrf(), ENT_QUOTES, 'UTF-8') . '">';
}

function validar_token_csrf_post(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) iniciar_sesion_segura();
    $proporcionado = is_scalar($_POST['_csrf_token'] ?? null) ? (string)$_POST['_csrf_token'] : '';
    $esperado = is_string($_SESSION['_csrf_token'] ?? null) ? $_SESSION['_csrf_token'] : '';
    if ($esperado === '' || $proporcionado === '' || !hash_equals($esperado, $proporcionado)) {
        require_once __DIR__ . '/alertify_error.php';
        redirigir_error_alertify('La sesión del formulario venció o no es válida. Recarga la pantalla e inténtalo de nuevo.', 403);
    }
}
