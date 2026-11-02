<?php
require_once __DIR__.'/sesion_segura.php'; iniciar_sesion_segura(); if(empty($_SESSION['usuario'])){header('Location: ./login.php');exit();}
require_once './conexion.php';require_once './validacion_calidad.php'; $db=conexion(); $estados=['Pendiente','Conforme','No conforme']; $estado=is_scalar($_GET['resultado']??null)?trim((string)$_GET['resultado']):'';if(!in_array($estado,$estados,true))$estado='';
$where=$estado!==''?' WHERE q.resultado=?':'';$stmt=$db->prepare('SELECT COUNT(*) AS total FROM controles_calidad q'.$where);if($estado!=='')$stmt->bind_param('s',$estado);$stmt->execute();$total=(int)$stmt->get_result()->fetch_assoc()['total'];$stmt->close();
$per=20;$page=max(1,(int)($_GET['page']??1));$pages=max(1,(int)ceil($total/$per));$page=min($page,$pages);$off=($page-1)*$per;$sql='SELECT q.id,q.tipo_control,q.fecha_control,q.resultado,q.valor,q.unidad,q.observaciones,c.codigo_cosecha,COALESCE(i.codigo_qr,\'Cosecha completa\') AS producto FROM controles_calidad q INNER JOIN cosechas c ON c.id=q.cosecha_id LEFT JOIN inventario_derivado i ON i.id=q.inventario_derivado_id'.$where.' ORDER BY q.fecha_control DESC,q.id DESC LIMIT ? OFFSET ?';$stmt=$db->prepare($sql);if($estado!=='')$stmt->bind_param('sii',$estado,$per,$off);else$stmt->bind_param('ii',$per,$off);$stmt->execute();$controles=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();$db->close();
?>
<!DOCTYPE html><html lang="es"><head><?php include 'head.php'; ?></head><body class="ui-bg-2"><?php include 'loader.php';include 'index_nav.php';include 'control_calidad_nav.php';?><main class="container-lg py-3"><form method="get" class="card p-3 mb-3"><label class="form-label" for="resultado">Resultado</label><div class="d-flex gap-2"><select class="form-select" id="resultado" name="resultado"><option value="">Todos</option><?php foreach($estados as $op):?><option <?= $estado===$op?'selected':'' ?>><?= htmlspecialchars($op,ENT_QUOTES,'UTF-8') ?></option><?php endforeach;?></select><button class="btn btn-outline-dark" type="submit">Filtrar</button></div></form>
<?php if(!$controles):?><div class="card p-4 text-center"><h4>Sin controles de calidad</h4><p class="mb-3">Registra inspecciones por cosecha o por producto derivado.</p><a class="btn btn-dark align-self-center" href="control_calidad_agregar.php">Registrar control</a></div><?php else:foreach($controles as $q):?><a class="card ui-card-list text-decoration-none p-3 mb-2" href="control_calidad_editar.php?id=<?= (int)$q['id'] ?>"><div class="d-flex justify-content-between gap-3"><div><strong><?= htmlspecialchars($q['tipo_control'],ENT_QUOTES,'UTF-8') ?></strong><span class="badge <?= $q['resultado']==='Conforme'?'bg-success':($q['resultado']==='No conforme'?'bg-danger':'bg-warning text-dark') ?> ms-2"><?= htmlspecialchars($q['resultado'],ENT_QUOTES,'UTF-8') ?></span><div class="small mt-1">Cosecha <?= htmlspecialchars($q['codigo_cosecha'],ENT_QUOTES,'UTF-8') ?> · <?= htmlspecialchars($q['producto'],ENT_QUOTES,'UTF-8') ?> · <?= date('d/m/Y',strtotime($q['fecha_control'])) ?></div><?php if($q['valor']!==null):?><div class="small">Medición: <?= htmlspecialchars(formatear_valor_calidad($q['valor']),ENT_QUOTES,'UTF-8') ?> <?= htmlspecialchars((string)$q['unidad'],ENT_QUOTES,'UTF-8') ?></div><?php endif;?><?php if($q['observaciones']):?><div class="small text-muted mt-1"><?= htmlspecialchars($q['observaciones'],ENT_QUOTES,'UTF-8') ?></div><?php endif;?></div><i class="fa-light fa-chevron-right fs-4 align-self-center"></i></div></a><?php endforeach;?><?php endif;?>
<?php if($pages>1):?><nav><ul class="pagination justify-content-center"><?php for($i=1;$i<=$pages;$i++):?><li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?><?= $estado!==''?'&resultado='.rawurlencode($estado):'' ?>"><?= $i ?></a></li><?php endfor;?></ul></nav><?php endif;?></main><script>
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
