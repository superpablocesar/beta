<?php
require_once __DIR__.'/sesion_segura.php';iniciar_sesion_segura();if(empty($_SESSION['usuario'])){header('Location: ./login.php');exit();}
require_once './conexion.php';require_once './csrf.php';require_once './permisos_operador.php';require_once './alertify_error.php';$db=conexion();$usuario=(string)$_SESSION['usuario'];
if(rol_operador_actual($db,$usuario)!=='administrador'){$db->close();redirigir_error_alertify('Solo un administrador puede gestionar los roles de operadores.',403);}
$operadores=$db->query('SELECT id,usuario,rol FROM operadores ORDER BY usuario')->fetch_all(MYSQLI_ASSOC);$db->close();$roles=['administrador'=>'Administrador','cultivo'=>'Cultivo','inventario'=>'Inventario','dispensacion'=>'Dispensación','cuotas'=>'Cuotas','consulta'=>'Solo consulta'];
?>
<!DOCTYPE html><html lang="es"><head><?php include 'head.php';?></head><body class="ui-bg-2"><?php include 'loader.php';include 'index_nav.php';?><nav class="navbar expand navbar-dark"><div class="container-lg px-3"><a class="navbar-brand" href="./"><div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver al panel</div></a></div></nav><main class="container-lg py-3"><div class="card p-4"><h4>Roles de operadores</h4><p class="text-muted">Los permisos se aplican también al acceder directamente a una dirección. Mantén al menos un administrador.</p><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Operador</th><th>Rol actual</th><th>Asignar rol</th></tr></thead><tbody><?php foreach($operadores as $op):?><tr><td><?= htmlspecialchars($op['usuario'],ENT_QUOTES,'UTF-8') ?><?= $op['usuario']===$usuario?' <span class="badge bg-secondary">Sesión actual</span>':'' ?></td><td><?= htmlspecialchars($roles[$op['rol']]??'Solo consulta',ENT_QUOTES,'UTF-8') ?></td><td><form method="post" action="operadores_permisos_actualizar.php" class="d-flex gap-2"><?= campo_token_csrf() ?><input type="hidden" name="id" value="<?= (int)$op['id'] ?>"><select class="form-select form-select-sm" name="rol" aria-label="Rol para <?= htmlspecialchars($op['usuario'],ENT_QUOTES,'UTF-8') ?>"><?php foreach($roles as $clave=>$rotulo):?><option value="<?= $clave ?>" <?= $op['rol']===$clave?'selected':'' ?>><?= $rotulo ?></option><?php endforeach;?></select><button class="btn btn-sm btn-dark" type="submit">Guardar</button></form></td></tr><?php endforeach;?></tbody></table></div></div></main><script>
        document.body.style.overflow = "hidden";

        $(document).ready(function() {
            function ocultarLoader() {
                $(".ui-loader").fadeOut(function() {
                    $(".ui-loader-bg").fadeOut().addClass("sale");
                    document.body.style.overflow = "auto";
                });
            }

            setTimeout(ocultarLoader, 250);

            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    ocultarLoader();
                    $("a").css("pointer-events", "auto");
                }
            });
        });
    </script></body></html>
