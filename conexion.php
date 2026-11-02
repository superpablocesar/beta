<?php
set_exception_handler(static function (Throwable $e): void {
    require_once __DIR__ . '/alertify_error.php';
    redirigir_excepcion_alertify($e, 'Ocurrió un error inesperado. Inténtalo de nuevo o contacta con administración.', 500);
});

    $instancia = 'beta';
    $siteKey = getenv('FLOWHER_RECAPTCHA_SITE_KEY') ?: '';
    $secretKey = getenv('FLOWHER_RECAPTCHA_SECRET_KEY') ?: '';

    function conexion(){
        $servername = getenv('FLOWHER_DB_HOST') ?: 'localhost';
        $username = getenv('FLOWHER_DB_USER');
        $password = getenv('FLOWHER_DB_PASSWORD');
        $dbname = getenv('FLOWHER_DB_NAME');

        // Usar credenciales de desarrollo únicamente para solicitudes loopback.
        $host_solicitud = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
        $host_solicitud = trim($host_solicitud, '[]');
        $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? '';
        $host_local = in_array($host_solicitud, ['localhost', '127.0.0.1', '::1'], true);
        $ip_local = $ip_cliente === '::1' || $ip_cliente === '::ffff:127.0.0.1'
            || (filter_var($ip_cliente, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                && (int) explode('.', $ip_cliente)[0] === 127);

        if ($host_local && $ip_local) {
            $username = 'root';
            $password = '';
            $dbname = 'beta';
        } elseif ($username === false || $password === false || $dbname === false
            || trim((string)$username) === '' || trim((string)$dbname) === '') {
            error_log('[FLOWHER] Faltan variables de entorno de conexión a producción.');
            require_once __DIR__ . '/alertify_error.php';
            redirigir_error_alertify('La conexión con la base de datos no está configurada. Contacta con administración.', 503);
        }

        $conexion = new mysqli($servername, $username, $password, $dbname);
        if ($conexion->connect_errno) {
            require_once __DIR__ . '/alertify_error.php';
            error_log('[FLOWHER] Conexión MySQL fallida: ' . mysqli_connect_error());
            redirigir_error_alertify('No se pudo conectar con la base de datos. Inténtalo de nuevo más tarde.', 500);
        }
        $conexion->set_charset('utf8mb4');
        return $conexion;
    }
?>
