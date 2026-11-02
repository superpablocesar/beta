<?php
require_once __DIR__ . '/sesion_segura.php';
iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/permisos_operador.php';
require_once __DIR__ . '/operadores_helper.php';
require_once __DIR__ . '/alertify_error.php';
$db = conexion();
exigir_administrador_operadores($db, (string)$_SESSION['usuario']);
$sql = 'SELECT o.id,o.usuario,o.rol,COUNT(CASE WHEN po.puede_consultar=1 THEN 1 END) AS modulos_asignados FROM operadores o LEFT JOIN permisos_operadores po ON po.operador_id=o.id GROUP BY o.id,o.usuario,o.rol ORDER BY o.usuario';
$operadores = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
$db->close();
$roles = ['administrador'=>'Administrador','cultivo'=>'Cultivo','inventario'=>'Inventario','dispensacion'=>'Dispensación','cuotas'=>'Cuotas','consulta'=>'Personalizado / consulta'];
?>
<!DOCTYPE html><html lang="es"><head><?php include 'head.php'; ?></head><body class="ui-bg-2"><?php include 'loader.php'; include 'index_nav.php'; ?>
<nav class="navbar expand navbar-dark"><div class="container-lg px-3"><a class="navbar-brand" href="./"><div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Volver al panel</div></a><a class="btn btn-dark btn-sm" href="operadores_agregar.php"><i class="fa-light fa-user-plus me-1"></i>Agregar operador</a></div></nav>
<main class="container-lg py-3"><div class="card p-4"><h4 class="mb-1">Operadores y permisos</h4><p class="text-muted">Edita cada operador y marca los módulos a los que puede acceder. Cada módulo marcado permite consultar y modificar.</p>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Operador</th><th>Rol</th><th>Módulos habilitados</th><th></th></tr></thead><tbody>
<?php if (!$operadores): ?><tr><td colspan="4" class="text-muted">No hay operadores registrados.</td></tr><?php else: foreach ($operadores as $op): ?>
<tr><td><?= htmlspecialchars($op['usuario'],ENT_QUOTES,'UTF-8') ?><?= $op['usuario']===(string)$_SESSION['usuario']?' <span class="badge bg-secondary">Sesión actual</span>':'' ?></td><td><?= htmlspecialchars($roles[$op['rol']]??$op['rol'],ENT_QUOTES,'UTF-8') ?></td><td><?= $op['rol']==='administrador'?'Todos los módulos':((int)$op['modulos_asignados']>0?(int)$op['modulos_asignados'].' de '.count(modulos_operador()):'Permisos por rol (pendiente personalizar)') ?></td><td class="text-end"><a class="btn btn-sm btn-outline-dark" href="operadores_editar.php?id=<?= (int)$op['id'] ?>"><i class="fa-light fa-pen-to-square me-1"></i>Editar</a></td></tr>
<?php endforeach; endif; ?></tbody></table></div></div></main>
<script>document.body.style.overflow="hidden";$(document).ready(function(){function ocultarLoader(){$(".ui-loader").fadeOut(function(){$(".ui-loader-bg").fadeOut().addClass("sale");document.body.style.overflow="auto";});}setTimeout(ocultarLoader,250);window.addEventListener('pageshow',function(e){if(e.persisted)ocultarLoader();});});</script></body></html>
