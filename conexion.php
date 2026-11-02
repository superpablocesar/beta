<?php
set_exception_handler(static function (Throwable $e): void {
    require_once __DIR__ . '/alertify_error.php';
    redirigir_excepcion_alertify($e, 'Ocurrió un error inesperado. Inténtalo de nuevo o contacta con administración.', 500);
});

    function flowher_instancia_actual(): string
    {
        $host = strtolower((string)parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
        $host = trim($host, '[]');
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) return 'beta';

        $sufijo = '.buho.pm';
        if (strlen($host) > strlen($sufijo) && substr($host, -strlen($sufijo)) === $sufijo) {
            $subdominio = substr($host, 0, -strlen($sufijo));
            if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $subdominio)) return $subdominio;
        }

        $instanciaConfigurada = getenv('FLOWHER_INSTANCE');
        if ($instanciaConfigurada !== false && preg_match('/^[a-z0-9][a-z0-9-]*$/i', $instanciaConfigurada)) {
            return strtolower($instanciaConfigurada);
        }
        return '';
    }

    $instancia = flowher_instancia_actual();
    function flowher_configuracion(string $nombre): ?string
    {
        $valor = getenv($nombre);
        if ($valor !== false && trim((string)$valor) !== '') return trim((string)$valor);
        foreach ([$_SERVER, $_ENV] as $fuente) {
            if (isset($fuente[$nombre]) && is_scalar($fuente[$nombre]) && trim((string)$fuente[$nombre]) !== '') return trim((string)$fuente[$nombre]);
        }
        if (function_exists('apache_getenv')) {
            $valor_apache = apache_getenv($nombre, true);
            if ($valor_apache !== false && trim((string)$valor_apache) !== '') return trim((string)$valor_apache);
        }
        return null;
    }

    function flowher_configuracion_instancia(string $campo): ?string
    {
        global $instancia;
        $campo = strtoupper($campo);
        if ($instancia !== '') {
            foreach ([
                "FLOWHER_{$instancia}_{$campo}",
                "{$instancia}_{$campo}",
                "FLOWHER_{$campo}_{$instancia}",
                "{$campo}_{$instancia}",
            ] as $nombre) {
                $valor = flowher_configuracion($nombre);
                if ($valor !== null) return $valor;
            }
        }
        return flowher_configuracion("FLOWHER_{$campo}");
    }

    // Las claves pública y privada deben provenir siempre de la misma pareja.
    $siteKeyConfigurada = flowher_configuracion_instancia('RECAPTCHA_SITE_KEY');
    $secretKeyConfigurada = flowher_configuracion_instancia('RECAPTCHA_SECRET_KEY');
    if (($siteKeyConfigurada === null) !== ($secretKeyConfigurada === null)) {
        error_log('[FLOWHER] Configuración parcial de reCAPTCHA; se usa la pareja heredada completa.');
    }
    if ($siteKeyConfigurada !== null && $secretKeyConfigurada !== null) {
        $siteKey = $siteKeyConfigurada;
        $secretKey = $secretKeyConfigurada;
        $recaptchaConfigSource = 'entorno:' . ($instancia !== '' ? $instancia : 'sin_instancia');
    } else {
        $siteKey = '6LdIKAgrAAAAAIduIBo0sizBRVFp_JAIiQervnIL';
        $secretKey = '6LdIKAgrAAAAAKLibKNFYQnJI1IW7QkvmyCEV-nm';
        $recaptchaConfigSource = 'respaldo:' . ($instancia !== '' ? $instancia : 'sin_instancia');
    }

    function conexion(){
    $servername = flowher_configuracion_instancia('DB_HOST') ?: 'localhost';
    $username = flowher_configuracion_instancia('DB_USER') ?: 'buhopm_beta';
    $password = flowher_configuracion_instancia('DB_PASSWORD') ?: 'S0ym1ll0n4r10';
    $dbname = flowher_configuracion_instancia('DB_NAME') ?: 'buhopm_beta';

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
