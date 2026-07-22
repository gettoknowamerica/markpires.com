<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-workflow-review-v119-2.php'));exit;}
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function one($sql,$p=[]):array{try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];}}
function all($sql,$p=[]):array{try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}

$missionId=max(0,(int)($_GET['mission_id']??0));$stageNo=(int)($_GET['stage']??-1);$compareNo=(int)($_GET['compare']??-999);$embed=(string)($_GET['embed']??'')==='1';
$mission=$missionId?one("SELECT * FROM goliath_v112_missions WHERE id=? LIMIT 1",[$missionId]):[];
$stages=$missionId?all("SELECT * FROM goliath_v112_stages WHERE mission_id=? ORDER BY stage_no",[$missionId]):[];
$versions=$missionId?all("SELECT * FROM goliath_v118_asset_versions WHERE mission_id=? ORDER BY stage_no,id",[$missionId]):[];
$byStage=[];foreach($versions as $v)$byStage[(int)$v['stage_no']]=$v;
if($stageNo<0&&$versions)$stageNo=(int)end($versions)['stage_no'];
$selected=$byStage[$stageNo]??[];$compare=$byStage[$compareNo]??[];
function renderAsset(array $v):string{
 if(trim((string)($v['content_html']??''))!=='')return (string)$v['content_html'];
 return '<pre>'.h($v['content_text']??'').'</pre>';
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath Work Evolution</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#02050b;color:#eef5ff;font-family:Arial,sans-serif}.shell{display:grid;grid-template-columns:265px minmax(0,1fr);min-height:100vh}.rail{background:#07111f;border-right:1px solid #29445f;overflow:auto}.railHead{position:sticky;top:0;background:#07111ff5;padding:12px;z-index:4;border-bottom:1px solid #ffffff18}.railHead h1{margin:0;color:#f3d373;font-size:18px}.version{display:block;color:#fff;text-decoration:none;padding:10px;border-bottom:1px solid #ffffff12}.version:hover,.version.active{background:#10213a}.version b{display:block}.version small{color:#9fb0c7}.version em{float:right;font-style:normal;color:#2dd46f}.waiting em{color:#8b9ab0}.main{min-width:0}.head{padding:11px 15px;background:#081322;border-bottom:1px solid #ffffff18}.head h2{margin:0;color:#f3d373}.meta{color:#9fb0c7;margin-top:4px}.toolbar{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px}.toolbar a{background:#805f12;color:#fff;text-decoration:none;padding:7px 10px;border-radius:8px;font-weight:900;font-size:12px}.content{display:grid;grid-template-columns:1fr;gap:10px;padding:10px}.content.compare{grid-template-columns:1fr 1fr}.panel{background:#fff;color:#172033;border-radius:12px;overflow:hidden}.panelTitle{background:#0e1a2a;color:#f3d373;padding:8px 11px;font-weight:900}.asset{padding:20px;line-height:1.65;min-height:500px;overflow:auto;max-height:calc(100vh - 115px)}.asset img,.asset video,.asset iframe{max-width:100%;height:auto}.asset pre{white-space:pre-wrap;font:inherit}.changeDetails{margin:0 10px 12px;background:#0f1b2c;color:#dbe8f8;border-radius:9px;padding:9px}.changeDetails summary{cursor:pointer;color:#f3d373;font-weight:900}.empty{padding:40px;text-align:center;color:#94a3b8}body.embed .shell{grid-template-columns:190px minmax(0,1fr)}body.embed .asset{padding:12px;max-height:calc(100vh - 82px)}body.embed .head{padding:7px 10px}@media(max-width:760px){.shell,body.embed .shell{grid-template-columns:1fr}.rail{max-height:190px;border-right:0;border-bottom:1px solid #29445f}.content.compare{grid-template-columns:1fr}.asset{max-height:none;min-height:350px}}
</style></head><body class="<?=$embed?'embed':''?>"><div class="shell"><aside class="rail"><div class="railHead"><h1>Actual Work Evolution</h1><small><?=h($mission['title']??'Mission')?> · #<?=$missionId?></small></div>
<?php if(isset($byStage[0])):$v=$byStage[0];?><a class="version <?=$stageNo===0?'active':''?>" href="?mission_id=<?=$missionId?>&stage=0<?=$embed?'&embed=1':''?>"><em>WORK</em><b>0. Imported Source</b><small>Exact starting file</small></a><?php endif;?>
<?php foreach($stages as $s):$v=$byStage[(int)$s['stage_no']]??[];?>
<a class="version <?=((int)$s['stage_no']===$stageNo)?'active':''?> <?=empty($v)?'waiting':''?>" href="?mission_id=<?=$missionId?>&stage=<?=(int)$s['stage_no']?><?=$embed?'&embed=1':''?>">
<em><?=empty($v)?'WAIT':'WORK'?></em><b><?=h($s['stage_no'].'. '.ucfirst((string)$s['executive_key']))?></b><small><?=h($s['title'])?></small></a>
<?php endforeach;?></aside><main class="main">
<?php if($selected):?><div class="head"><h2><?=h($selected['title'])?></h2><div class="meta"><?=h(ucfirst((string)$selected['executive_key']))?> · Complete Version <?=$selected['stage_no']?> · <?=number_format(mb_strlen(strip_tags((string)($selected['content_html']?:$selected['content_text']))))?> visible characters</div>
<div class="toolbar"><?php foreach($versions as $v): if((int)$v['stage_no']===(int)$selected['stage_no']) continue; ?><a href="?mission_id=<?=$missionId?>&stage=<?=$selected['stage_no']?>&compare=<?=$v['stage_no']?><?=$embed?'&embed=1':''?>">Compare v<?=$v['stage_no']?></a><?php endforeach;?><?php if(!$embed):?><a href="/dashboard/goliath-mission-control.php">Mission Control</a><?php endif;?></div></div>
<div class="content <?=$compare?'compare':''?>"><section class="panel"><div class="panelTitle">Complete Version <?=$selected['stage_no']?> — <?=h(ucfirst((string)$selected['executive_key']))?></div><article class="asset"><?=renderAsset($selected)?></article><?php if(trim((string)$selected['change_note'])!==''):?><details class="changeDetails"><summary>Optional change record</summary><?=nl2br(h($selected['change_note']))?></details><?php endif;?></section>
<?php if($compare):?><section class="panel"><div class="panelTitle">Complete Version <?=$compare['stage_no']?> — <?=h(ucfirst((string)$compare['executive_key']))?></div><article class="asset"><?=renderAsset($compare)?></article></section><?php endif;?></div>
<?php else:?><div class="empty">No complete work exists at this stage yet. Notes and executive briefs are never displayed as deliverables.</div><?php endif;?></main></div></body></html>