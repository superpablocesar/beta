<?php
require_once __DIR__ . '/sesion_segura.php'; iniciar_sesion_segura();
if (empty($_SESSION['usuario'])) { header('Location: ./login.php'); exit(); }
require_once __DIR__ . '/csrf.php'; require_once __DIR__ . '/alertify_error.php';
if (($_SERVER['REQUEST_METHOD']??'')==='POST') validar_token_csrf_post();
$id=filter_var($_POST['id']??$_GET['id']??null,FILTER_VALIDATE_INT);
if(!$id||$id<1) { header('Location: operadores_permisos.php'); exit(); }
header('Location: operadores_editar.php?id='.(int)$id); exit();
