<?php
require_once __DIR__ . '/sesion_segura.php'; iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once __DIR__ . '/csrf.php'; require_once __DIR__ . '/conexion.php'; require_once __DIR__ . '/permisos_operador.php'; require_once __DIR__ . '/operadores_helper.php'; require_once __DIR__ . '/bitacora_helper.php'; require_once __DIR__ . '/alertify_error.php';
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { header('Location: operadores_permisos.php'); exit(); }
validar_token_csrf_post(); $db=conexion(); $actor=(string)$_SESSION['usuario']; exigir_administrador_operadores($db,$actor);
 $transaccion=false;
try {
    $datos=validar_datos_operador($_POST,true); $asignados=modulos_recibidos_operador($_POST,$datos['rol']);
    $stmt=$db->prepare('SELECT id FROM operadores WHERE usuario=? LIMIT 1'); $stmt->bind_param('s',$datos['usuario']); $stmt->execute(); $existe=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if($existe) throw new InvalidArgumentException('Ya existe un operador con ese usuario.');
    $db->begin_transaction(); $transaccion=true; $hash=password_hash($datos['clave'],PASSWORD_DEFAULT); $stmt=$db->prepare('INSERT INTO operadores (usuario,clave,rol) VALUES (?,?,?)'); $stmt->bind_param('sss',$datos['usuario'],$hash,$datos['rol']); $stmt->execute(); $id=(int)$db->insert_id; $stmt->close();
    guardar_permisos_individuales($db,$id,$asignados); $actor_id=operador_id_bitacora($db,$actor);
    registrar_evento_bitacora($db,$actor_id,'operadores','Crear','operadores',$id,null,['usuario'=>$datos['usuario'],'rol'=>$datos['rol'],'modulos'=>nombre_modulos_asignados($asignados)]);
    $db->commit(); $transaccion=false; $db->close(); header('Location: operadores_permisos.php?success=add'); exit();
} catch(Throwable $e) { if($transaccion) $db->rollback(); $db->close(); redirigir_excepcion_alertify($e,'No se pudo crear el operador.'); }
