<?php
require_once __DIR__.'/sesion_segura.php';iniciar_sesion_segura();if(empty($_SESSION['usuario'])){header('Location: ./login.php');exit();}require_once './conexion.php';$db=conexion();
$modulos=['operadores'=>'Operadores','tareas'=>'Tareas','calidad'=>'Calidad','inventario'=>'Inventario','dispensacion'=>'Dispensación','cuotas'=>'Cuotas','lotes'=>'Lotes','cosechas'=>'Cosechas','banco_semillas'=>'Banco de semillas','socios'=>'Socios','variedades'=>'Genéticas','plantas_madre'=>'Plantas madre','clones'=>'Clones'];
$modulo=is_scalar($_GET['modulo']??null)?trim((string)$_GET['modulo']):'';if(!isset($modulos[$modulo]))$modulo='';$desde=is_scalar($_GET['desde']??null)?trim((string)$_GET['desde']):'';$hasta=is_scalar($_GET['hasta']??null)?trim((string)$_GET['hasta']):'';
$validar_fecha=static function(string $fecha):bool{$obj=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);return $obj!==false&&$obj->format('Y-m-d')===$fecha;};if($desde!==''&&!$validar_fecha($desde))$desde='';if($hasta!==''&&!$validar_fecha($hasta))$hasta='';
$condiciones=[];$parametros=[];$tipos='';if($modulo!==''){$condiciones[]='b.modulo=?';$parametros[]=$modulo;$tipos.='s';}if($desde!==''){$condiciones[]='b.created_at>=?';$parametros[]=$desde.' 00:00:00';$tipos.='s';}if($hasta!==''){$fin=(new DateTimeImmutable($hasta))->modify('+1 day')->format('Y-m-d 00:00:00');$condiciones[]='b.created_at<?';$parametros[]=$fin;$tipos.='s';}$where=$condiciones?' WHERE '.implode(' AND ',$condiciones):'';
$stmt=$db->prepare('SELECT COUNT(*) AS total FROM bitacora_cambios b'.$where);if($parametros)$stmt->bind_param($tipos,...$parametros);$stmt->execute();$total=(int)$stmt->get_result()->fetch_assoc()['total'];$stmt->close();$por_pagina=30;$pagina=max(1,(int)($_GET['page']??1));$paginas=max(1,(int)ceil($total/$por_pagina));$pagina=min($pagina,$paginas);$offset=($pagina-1)*$por_pagina;
$sql='SELECT b.id,b.created_at,b.modulo,b.accion,b.entidad,b.entidad_id,b.valores_anteriores,b.valores_nuevos,o.usuario FROM bitacora_cambios b LEFT JOIN operadores o ON o.id=b.operador_id'.$where.' ORDER BY b.id DESC LIMIT ? OFFSET ?';$parametros[]=$por_pagina;$parametros[]=$offset;$tipos.='ii';$stmt=$db->prepare($sql);$stmt->bind_param($tipos,...$parametros);$stmt->execute();$eventos=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();$db->close();
?>
<!DOCTYPE html><html lang="es"><head><?php include 'head.php';?></head><body class="ui-bg-2"><?php include 'loader.php';include 'index_nav.php';?><nav class="navbar expand navbar-dark"><div class="container-lg px-3"><a class="navbar-brand" href="./"><div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Bitácora de cambios</div></a><a class="btn btn-sm btn-dark" href="exportar_excel.php?tipo=bitacora&amp;modulo=<?= rawurlencode($modulo) ?>&amp;desde=<?= rawurlencode($desde) ?>&amp;hasta=<?= rawurlencode($hasta) ?>">Exportar CSV</a></div></nav>
<main class="container-lg py-3"><form class="card p-3 mb-3" method="get"><div class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label" for="modulo">Módulo</label><select class="form-select" name="modulo" id="modulo"><option value="">Todos</option><?php foreach($modulos as $clave=>$nombre):?><option value="<?= $clave ?>" <?= $modulo===$clave?'selected':'' ?>><?= htmlspecialchars($nombre,ENT_QUOTES,'UTF-8') ?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label" for="desde">Desde</label><input class="form-control" type="date" name="desde" id="desde" value="<?= htmlspecialchars($desde,ENT_QUOTES,'UTF-8') ?>"></div><div class="col-md-3"><label class="form-label" for="hasta">Hasta</label><input class="form-control" type="date" name="hasta" id="hasta" value="<?= htmlspecialchars($hasta,ENT_QUOTES,'UTF-8') ?>"></div><div class="col-md-2"><button class="btn btn-outline-dark w-100" type="submit">Filtrar</button></div></div></form>
<div class="card p-3"><div class="small text-muted mb-2">Eventos encontrados: <?= $total ?> · Los registros de bitácora no se editan desde esta pantalla.</div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Fecha</th><th>Operador</th><th>Módulo / acción</th><th>Registro</th><th>Cambios</th></tr></thead><tbody><?php if(!$eventos):?><tr><td colspan="5" class="text-muted">Todavía no hay eventos en la bitácora.</td></tr><?php else:foreach($eventos as $evento):?><tr><td><?= htmlspecialchars($evento['created_at'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars($evento['usuario']??'Operador eliminado',ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars($evento['modulo'].' · '.$evento['accion'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars($evento['entidad'].' #'.(string)$evento['entidad_id'],ENT_QUOTES,'UTF-8') ?></td><td><details><summary>Ver valores</summary><div class="row mt-2"><div class="col-lg-6"><strong>Antes</strong><pre class="small text-wrap"><?= htmlspecialchars((string)$evento['valores_anteriores'],ENT_QUOTES,'UTF-8') ?></pre></div><div class="col-lg-6"><strong>Después</strong><pre class="small text-wrap"><?= htmlspecialchars((string)$evento['valores_nuevos'],ENT_QUOTES,'UTF-8') ?></pre></div></div></details></td></tr><?php endforeach;endif;?></tbody></table></div>
<?php if($paginas>1):?><nav><ul class="pagination justify-content-center mb-0"><?php for($i=1;$i<=$paginas;$i++):?><li class="page-item <?= $i===$pagina?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&amp;modulo=<?= rawurlencode($modulo) ?>&amp;desde=<?= rawurlencode($desde) ?>&amp;hasta=<?= rawurlencode($hasta) ?>"><?= $i ?></a></li><?php endfor;?></ul></nav><?php endif;?></div></main><script>
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
