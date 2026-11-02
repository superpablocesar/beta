<?php
require_once __DIR__ . '/sesion_segura.php'; iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once __DIR__ . '/csrf.php'; require_once __DIR__ . '/conexion.php'; require_once __DIR__ . '/permisos_operador.php'; require_once __DIR__ . '/operadores_helper.php'; require_once __DIR__ . '/bitacora_helper.php'; require_once __DIR__ . '/alertify_error.php';
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { header('Location: operadores_permisos.php'); exit(); }
validar_token_csrf_post(); $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT); if(!$id||$id<1) redirigir_error_alertify('El identificador del operador no es válido.');
$db=conexion(); $actor=(string)$_SESSION['usuario']; exigir_administrador_operadores($db,$actor);
 $transaccion=false;
try {
    $datos=validar_datos_operador($_POST,false); $asignados=modulos_recibidos_operador($_POST,$datos['rol']); $db->begin_transaction(); $transaccion=true;
    $stmt=$db->prepare('SELECT id,usuario,rol FROM operadores WHERE id=? FOR UPDATE'); $stmt->bind_param('i',$id); $stmt->execute(); $anterior=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if(!$anterior) throw new InvalidArgumentException('El operador seleccionado ya no existe.');
    $modulos_anteriores=modulos_asignados_operador($db,(int)$id,(string)$anterior['rol']);
    $stmt=$db->prepare('SELECT id FROM operadores WHERE usuario=? AND id<>? LIMIT 1'); $stmt->bind_param('si',$datos['usuario'],$id); $stmt->execute(); $duplicado=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if($duplicado) throw new InvalidArgumentException('Ya existe otro operador con ese usuario.');
    if($anterior['rol']==='administrador' && $datos['rol']!=='administrador') { $admins=$db->query("SELECT id FROM operadores WHERE rol='administrador' FOR UPDATE"); if($admins->num_rows<=1) throw new InvalidArgumentException('No se puede quitar el rol al último administrador.'); }
    $actor_id=operador_id_bitacora($db,$actor);
    if($datos['clave']!=='') { $hash=password_hash($datos['clave'],PASSWORD_DEFAULT); $stmt=$db->prepare('UPDATE operadores SET usuario=?,rol=?,clave=? WHERE id=?'); $stmt->bind_param('sssi',$datos['usuario'],$datos['rol'],$hash,$id); }
    else { $stmt=$db->prepare('UPDATE operadores SET usuario=?,rol=? WHERE id=?'); $stmt->bind_param('ssi',$datos['usuario'],$datos['rol'],$id); }
    $stmt->execute(); $stmt->close(); guardar_permisos_individuales($db,(int)$id,$asignados);
    registrar_evento_bitacora($db,$actor_id,'operadores','Actualizar','operadores',(int)$id,['usuario'=>$anterior['usuario'],'rol'=>$anterior['rol'],'modulos'=>nombre_modulos_asignados($modulos_anteriores)],['usuario'=>$datos['usuario'],'rol'=>$datos['rol'],'modulos'=>nombre_modulos_asignados($asignados),'clave_cambiada'=>$datos['clave']!=='']);
    $db->commit(); $transaccion=false;
    if($anterior['usuario']===$actor) { $_SESSION['usuario']=$datos['usuario']; $_SESSION['rol']=$datos['rol']; }
    $db->close(); header('Location: operadores_permisos.php?success=update'); exit();
} catch(Throwable $e) { if($transaccion) $db->rollback(); $db->close(); redirigir_excepcion_alertify($e,'No se pudo actualizar el operador.'); }
