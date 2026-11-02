<?php
// Establecer la sesión de instancia
require_once "./conexion.php";
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
require_once './csrf.php';
require_once './permisos_operador.php';
require_once './bitacora_helper.php';
$_SESSION['instancia'] = $instancia;

// Desactivar reCAPTCHA solo cuando el host y el cliente sean loopback.
$host_solicitud = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
$host_solicitud = trim($host_solicitud, '[]');
$ip_cliente = $_SERVER['REMOTE_ADDR'] ?? '';
$host_local = in_array($host_solicitud, ['localhost', '127.0.0.1', '::1'], true);
$ip_local = $ip_cliente === '::1' || $ip_cliente === '::ffff:127.0.0.1'
    || (filter_var($ip_cliente, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        && (int) explode('.', $ip_cliente)[0] === 127);
$es_entorno_local = $host_local && $ip_local;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validar_token_csrf_post();
    $conexion = conexion();
    $usuario = trim($_POST['usuario'] ?? '');
    $password = (string)($_POST['pass'] ?? '');

    if (!$es_entorno_local) {
        // Verifica que se haya recibido un token de reCAPTCHA
        if (empty($_POST['g-recaptcha-response'])) {
            require_once __DIR__ . '/alertify_error.php';
            redirigir_error_alertify('No se pudo validar reCAPTCHA.', 422);
        }

        // Validar reCAPTCHA con cURL
        $recaptchaResponse = $_POST['g-recaptcha-response'];
        $recaptchaUrl = "https://www.google.com/recaptcha/api/siteverify";
        $data = [
            'secret'   => $secretKey,
            'response' => $recaptchaResponse,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $recaptchaUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $respuesta_recaptcha = curl_exec($ch);
        $error_recaptcha = curl_error($ch);
        $estado_http_recaptcha = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($respuesta_recaptcha === false || $estado_http_recaptcha < 200 || $estado_http_recaptcha >= 300) {
            error_log('[FLOWHER] Falló la consulta de reCAPTCHA: ' . ($error_recaptcha ?: 'respuesta HTTP no válida'));
            require_once __DIR__ . '/alertify_error.php';
            redirigir_error_alertify('No se pudo comprobar reCAPTCHA. Inténtalo de nuevo más tarde.', 503);
        }

        $recaptchaVerify = json_decode($respuesta_recaptcha);
        if (!is_object($recaptchaVerify)) {
            error_log('[FLOWHER] reCAPTCHA devolvió una respuesta no válida.');
            require_once __DIR__ . '/alertify_error.php';
            redirigir_error_alertify('No se pudo comprobar reCAPTCHA. Inténtalo de nuevo más tarde.', 503);
        }

        $recaptchaErrorCodes = is_array($recaptchaVerify->{'error-codes'} ?? null)
            ? array_map(static function ($code) {
                return preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$code);
            }, $recaptchaVerify->{'error-codes'})
            : [];
        $recaptchaHostname = preg_replace('/[^a-zA-Z0-9.-]/', '', (string)($recaptchaVerify->hostname ?? ''));
        $recaptchaAction = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($recaptchaVerify->action ?? ''));
        $recaptchaScore = is_numeric($recaptchaVerify->score ?? null)
            ? (string)$recaptchaVerify->score
            : 'sin_puntuacion';

        if (empty($recaptchaVerify->success) || ($recaptchaVerify->score ?? 0) < 0.5) {
            error_log('[FLOWHER] reCAPTCHA rechazado; códigos=' . implode(',', $recaptchaErrorCodes)
                . '; hostname=' . ($recaptchaHostname ?: 'desconocido')
                . '; action=' . ($recaptchaAction ?: 'desconocida')
                . '; score=' . $recaptchaScore
                . '; fuente_claves=' . ($recaptchaConfigSource ?? 'desconocida'));
            header("Location: ./login.php?error=2");
            exit();
        }
    }

    $sql = "SELECT id, clave, rol FROM operadores WHERE usuario = ? LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $operador = $result->fetch_assoc();
    $stmt->close();

    $autenticado = false;
    $actualizar_hash = false;
    if ($operador) {
        $hash_guardado = $operador['clave'];
        $autenticado = password_verify($password, $hash_guardado);

        // Compatibilidad temporal: migrar hashes MD5 al validar la contraseña.
        if (!$autenticado && preg_match('/^[a-f0-9]{32}$/i', $hash_guardado)
            && hash_equals(strtolower($hash_guardado), md5($password))) {
            $autenticado = true;
            $actualizar_hash = true;
        }
    }

    if ($autenticado) {
        if ($actualizar_hash) {
            $nuevo_hash = password_hash($password, PASSWORD_DEFAULT);
            if ($nuevo_hash === false) {
                $conexion->close();
                http_response_code(500);
                require_once __DIR__ . '/alertify_error.php';
                redirigir_error_alertify('No se pudo actualizar la protección de la contraseña.');
            }
            $operador_id = (int)$operador['id'];
            $conexion->begin_transaction();
            $stmt_hash = $conexion->prepare("UPDATE operadores SET clave = ? WHERE id = ?");
            $stmt_hash->bind_param("si", $nuevo_hash, $operador_id);
            $stmt_hash->execute();
            $stmt_hash->close();
            registrar_evento_bitacora($conexion, $operador_id, 'operadores', 'Actualizar', 'operadores', $operador_id, ['formato_clave'=>'MD5'], ['formato_clave'=>'password_hash']);
            $conexion->commit();
        }

        session_regenerate_id(true);
        $_SESSION['usuario'] = $usuario;
        $_SESSION['operador_id'] = (int)$operador['id'];
        $_SESSION['rol'] = isset(permisos_por_rol_operador()[$operador['rol']]) ? $operador['rol'] : 'consulta';
        $conexion->close();
        header("Location: ./");
        exit();
    } else {
        $conexion->close();
        header("Location: ./login.php?error=1");
        exit();
    }
}

// Verifica la clave secreta solo cuando reCAPTCHA es obligatorio.
if (!$es_entorno_local && (empty($siteKey) || empty($secretKey))) {
    require_once __DIR__ . '/alertify_error.php';
    redirigir_error_alertify('La configuración de reCAPTCHA no está completa. Contacta con administración.', 500);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Administrador de contenidos">
    <title>Buho</title>
    <link href="https://buho.pm/admin/css/alertify.css" rel="stylesheet">
    <script src="https://buho.pm/admin/js/alertify.js"></script>
    <script src="./validacion_alertify.js" defer></script>
    
    <link rel="preload" href="./iconos/css/all.min.css?v=1.1" as="style" onload="this.rel='stylesheet'">

    <!-- Bootstrap -->
    <link href="https://buho.pm/admin/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://buho.pm/admin/js/bootstrap.bundle.min.js"></script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&display=swap" rel="stylesheet">

    <?php if (!$es_entorno_local): ?>
        <!-- Google reCAPTCHA v3 -->
        <script src="https://www.google.com/recaptcha/api.js?render=<?= $siteKey ?>"></script>
    <?php endif; ?>

    <script>
        function generateRecaptchaToken(event) {
            <?php if ($es_entorno_local): ?>
            return true;
            <?php else: ?>
            event.preventDefault(); // Detenemos el envío inmediato

            // 1. ACTIVAR LOADER (Estética de salida)
            document.body.style.overflow = "hidden";
            $(".ui-loader-bg").fadeIn(function() {
                $(".ui-loader-bg").removeClass("sale");
                $(".ui-loader").fadeIn();
            });

            // 2. Ejecutar reCAPTCHA
            grecaptcha.ready(function() {
                grecaptcha.execute("<?= $siteKey ?>", {action: "login"}).then(function(token) {
                    if (typeof token !== "string" || token.length < 100) {
                        throw new Error("Token de reCAPTCHA vacío o incompleto");
                    }
                    document.getElementById("g-recaptcha-response").value = token;
                    document.getElementById("loginForm").submit();
                }).catch(function(error) {
                    console.error("No se pudo generar el token de reCAPTCHA", error);
                    if (window.alertify && typeof window.alertify.error === "function") {
                        window.alertify.error("No se pudo validar reCAPTCHA. Recarga la página e inténtalo nuevamente.");
                    }
                    document.body.style.overflow = "auto";
                    $(".ui-loader").fadeOut();
                    $(".ui-loader-bg").fadeOut().addClass("sale");
                });
            });
            <?php endif; ?>
        }
    </script>

    <link rel="icon" type="image/png" href="https://buho.pm/img/favicon.png">
    <link rel="shortcut icon" type="image/png" href="https://buho.pm/img/favicon.png">
    <?php include './estilos.css.php';?>
</head>
<body class="ui-bg-1" id="morph">

    <?php include 'loader.php';?>

    <div class="container-lg">
        <div class="row">
            <div class="col-12">
                <div class="d-flex flex-column mh-100vh align-items-center justify-content-center pb-5">
                    <div class="mb-3 animate-in-up">
                        <div class="card-title text-admin-6 ui-f-400 mb-2 fs-1"><i class="fa-light fa-cannabis me-1"></i><span style="text-shadow: 0 5px 5px rgba(0,0,0,0.25), 0 10px 10px #63fc2d"><span class="ui-f-700">FLOW</span>HER</span></div>
                        <div class="card p-4 shadow">
                            <form id="loginForm" action="login.php" method="post" onsubmit="generateRecaptchaToken(event)">
                                <?= campo_token_csrf() ?>
                                <div class="mb-2">
                                    <label for="UsuarioAdmin" class="fs-14"><small>Usuario</small></label>
                                    <input name="usuario" type="text" class="form-control form-control-lg" id="UsuarioAdmin" required>
                                </div>
                                <div class="mb-3">
                                    <label for="PassAdmin" class="fs-14"><small>Contraseña</small></label>
                                    <div class="input-group">
                                        <input name="pass" type="password" class="form-control form-control-lg border-end-0" id="PassAdmin" required>
                                        <button class="btn passeye border-start-0" type="button" id="togglePassword" style="min-width:50px">
                                            <i id="togglePasswordIcon" class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Mueve el script al final del body -->
                                <script>
                                    document.addEventListener("DOMContentLoaded", function () {
                                        document.getElementById("togglePassword").addEventListener("click", function () {
                                            var passwordField = document.getElementById("PassAdmin");
                                            var toggleIcon = document.getElementById("togglePasswordIcon");

                                            if (passwordField.type === "password") {
                                                passwordField.type = "text";
                                                toggleIcon.classList.remove("fa-eye");
                                                toggleIcon.classList.add("fa-eye-slash");
                                            } else {
                                                passwordField.type = "password";
                                                toggleIcon.classList.remove("fa-eye-slash");
                                                toggleIcon.classList.add("fa-eye");
                                            }
                                        });
                                    });
                                </script>


                                <?php if (!$es_entorno_local): ?>
                                    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
                                <?php else: ?>
                                    <div class="text-muted text-center mb-3 fs-12">reCAPTCHA desactivado en localhost</div>
                                <?php endif; ?>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-lg btn-dark fs-6 btn-iniciar-sesion"><small>INICIAR SESIÓN</small></button>
                                </div>
                                <?php
                                if (isset($_GET["error"])) {
                                    if ($_GET["error"] == 1) {
                                        echo "<div class='text-danger w-100 text-center mt-4 fs-12'>ERROR DE USUARIO O CONTRASEÑA</div>";
                                    } elseif ($_GET["error"] == 2) {
                                        echo "<div class='text-danger w-100 text-center mt-4 fs-12'>ERROR EN reCAPTCHA</div>";
                                    }
                                }
                                ?>
                            </form>
                        </div>
                    </div>
                    <?php if ((isset($_GET['tit'])) && (isset($_GET['msg']))): ?>
                        <div class="pt-3 px-3">
                            <div class="ui-f-900 text-center logout-tit"><?php echo htmlspecialchars($_GET['tit']); ?></div>
                            <div class="small text-center logout-msg"><?php echo htmlspecialchars($_GET['msg']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://buho.pm/admin/js/jquery.min.js"></script>
    <script>
        document.body.style.overflow = "hidden";

        $(document).ready(function() {
            // 1. Desvanecer loader al cargar la página (o al volver atrás)
            function ocultarLoader() {
                $(".ui-loader").fadeOut(function() {
                    $(".ui-loader-bg").fadeOut().addClass("sale");
                    document.body.style.overflow = "auto";
                });
            }

            // Ejecución inicial al cargar
            setTimeout(ocultarLoader, 250);

            // 3. SOLUCIÓN BOTÓN ATRÁS (BFCache)
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    // Si el usuario vuelve atrás, ocultamos el loader y reactivamos clics
                    ocultarLoader();
                    $("a").css("pointer-events", "auto");
                }
            });
        });
    </script>

    <?php if (isset($_GET['error']) && in_array((string)$_GET['error'], ['1', '2'], true)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const mensajes = {
                    '1': 'Usuario o contraseña incorrectos.',
                    '2': 'No se pudo validar reCAPTCHA. Inténtalo nuevamente.'
                };
                const mensaje = mensajes[<?= json_encode((string)$_GET['error']) ?>];
                if (mensaje) window.mostrarNotificacionError(mensaje, 8);
            });
        </script>
    <?php endif; ?>
    <?php
    require_once __DIR__ . '/alertify_error.php';
    mostrar_error_alertify_flash_en_formulario();
    ?>
</body>
</html>
