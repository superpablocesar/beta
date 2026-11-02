<?php
require_once __DIR__ . '/sesion_segura.php'; iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once __DIR__ . '/conexion.php'; require_once __DIR__ . '/csrf.php'; require_once __DIR__ . '/permisos_operador.php'; require_once __DIR__ . '/operadores_helper.php'; require_once __DIR__ . '/alertify_error.php';
$db=conexion(); exigir_administrador_operadores($db,(string)$_SESSION['usuario']); $db->close();
$es_edicion=false; $operador=['usuario'=>'','rol'=>'consulta']; $roles=['administrador'=>'Administrador','cultivo'=>'Cultivo','inventario'=>'Inventario','dispensacion'=>'Dispensación','cuotas'=>'Cuotas','consulta'=>'Personalizado / consulta'];
$marcados=[]; define('FLOWHER_CARGA_FORM_OPERADORES',true); include __DIR__.'/operadores_formulario.php';
