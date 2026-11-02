<?php
require_once __DIR__ . '/sesion_segura.php'; iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once __DIR__ . '/conexion.php'; require_once __DIR__ . '/csrf.php'; require_once __DIR__ . '/permisos_operador.php'; require_once __DIR__ . '/operadores_helper.php'; require_once __DIR__ . '/alertify_error.php';
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT); if(!$id||$id<1) redirigir_error_alertify('El identificador del operador no es válido.');
$db=conexion(); exigir_administrador_operadores($db,(string)$_SESSION['usuario']); $stmt=$db->prepare('SELECT id,usuario,rol FROM operadores WHERE id=? LIMIT 1'); $stmt->bind_param('i',$id); $stmt->execute(); $operador=$stmt->get_result()->fetch_assoc(); $stmt->close(); if(!$operador){$db->close();redirigir_error_alertify('El operador solicitado no existe.',404);}
$marcados=modulos_asignados_operador($db,(int)$id,(string)$operador['rol']); $db->close();
$es_edicion=true; $roles=['administrador'=>'Administrador','cultivo'=>'Cultivo','inventario'=>'Inventario','dispensacion'=>'Dispensación','cuotas'=>'Cuotas','consulta'=>'Personalizado / consulta']; define('FLOWHER_CARGA_FORM_OPERADORES',true); include __DIR__.'/operadores_formulario.php';
