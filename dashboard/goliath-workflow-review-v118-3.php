<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-workflow-review-v118-3.php'));exit;}
function v1183_h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function v1183_one($sql,$p=[]):array{try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];}}
function v1183_all($sql,$p=[]):array{try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}

$missionId=max(0,(int)($_GET['mission_id']??0));$stageNo=max(0,(int)($_GET['stage']??0));$compareNo=max(0,(int)($_GET['compare']??0));$embed=(string)($_GET['embed']??'')==='1';
$mission=$missionId?v1183_one("SELECT * FROM goliath_v112_missions WHERE id=? LIMIT 1",[$missionId]):[];
$versions=$missionId?v1183_all("SELECT v.*,s.title stage_title,s.stage_key FROM goliath_v118_asset_versions v LEFT JOIN goliath_v112_stages s ON s.id=v.stage_id WHERE v.mission_id=? ORDER BY v.stage_no ASC,v.id ASC",[$missionId]):[];
$selection=$missionId?v1183_one("SELECT * FROM goliath_v118_asset_selections WHERE mission_id=? AND is_current=1 ORDER BY id DESC LIMIT 1",[$missionId]):[];
if($stageNo<1&&$versions)$stageNo=(int)end($versions)['stage_no'];
$selected=[];$compare=[];
foreach($versions as $v){if((int)$v['stage_no']===$stageNo)$selected=$v;if((int)$v['stage_no']===$compareNo)$compare=$v;}
function render1183(array $v):string{
 if(trim((string)($v['content_html']??''))!=='')return (string)$v['content_html'];
 return '<pre>'.v1183_h($v['content_text']??'').'</pre>';
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Evolving Asset Review</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#02050b;color:#eef5ff;font-family:Arial,sans-serif}.shell{display:grid;grid-template-columns:285px minmax(0,1fr);min-height:100vh}.rail{background:#07111f;border-right:1px solid #29445f;overflow:auto}.railHead{position:sticky;top:0;background:#07111ff5;padding:13px;border-bottom:1px solid #ffffff18;z-index:3}.railHead h2{margin:0;color:#f3d373}.version{display:block;color:#fff;text-decoration:none;padding:11px;border-bottom:1px solid #ffffff12}.version:hover,.version.active{background:#10213a}.version b{display:block}.version small{color:#9fb0c7}.version em{float:right;font-style:normal;color:#f3d373}.chosen{outline:2px solid #2dd46f}.main{min-width:0}.head{padding:13px 17px;background:#081322;border-bottom:1px solid #ffffff18}.head h1{margin:0;color:#f3d373}.meta{color:#9fb0c7;margin-top:5px}.actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:9px}.actions button,.actions a{border:0;background:#805f12;color:#fff;text-decoration:none;padding:8px 11px;border-radius:9px;font-weight:900;cursor:pointer}.actions .green{background:#0b7b45}.actions .blue{background:#125a91}.contentGrid{display:grid;grid-template-columns:1fr;gap:10px;padding:12px}.contentGrid.compare{grid-template-columns:1fr 1fr}.panel{background:#050914;border:1px solid #243a54;border-radius:12px;overflow:hidden}.panelTitle{padding:8px 12px;background:#0b1727;color:#f3d373;font-weight:900}.asset{padding:17px;line-height:1.65;font-size:16px;overflow:auto;max-height:calc(100vh - 185px)}.asset img,.asset video,.asset iframe{max-width:100%;height:auto}.asset pre{white-space:pre-wrap;font:inherit}.changes{margin:0 12px 14px;padding:12px;border:1px solid #415a75;background:#101b2b;border-radius:10px}.changes h3{margin:0 0 7px;color:#f3d373}.empty{padding:30px;color:#9fb0c7}body.embed .shell{grid-template-columns:215px minmax(0,1fr)}body.embed .asset{max-height:calc(100vh - 140px);padding:11px}body.embed .head{padding:8px 11px}@media(max-width:760px){.shell,body.embed .shell{grid-template-columns:1fr}.rail{max-height:190px;border-right:0;border-bottom:1px solid #29445f}.contentGrid.compare{grid-template-columns:1fr}.asset{max-height:none}}
</style></head><body class="<?=$embed?'embed':''?>"><div class="shell">
<aside class="rail"><div class="railHead"><h2><?=v1183_h($mission['title']??'Evolving Asset')?></h2><small>Mission #<?=$missionId?> · inspect or select any version</small></div>
<?php foreach($versions as $v):$isChosen=(int)($selection['version_id']??0)===(int)$v['id'];?>
<a class="version <?=((int)$v['stage_no']===$stageNo)?'active':''?> <?=$isChosen?'chosen':''?>" href="/dashboard/goliath-workflow-review-v118-3.php?mission_id=<?=$missionId?>&stage=<?=(int)$v['stage_no']?><?=$embed?'&embed=1':''?>">
<em><?=v1183_h($v['status'])?></em><b><?=v1183_h($v['stage_no'].'. '.ucfirst((string)$v['executive_key']))?><?=$isChosen?' ★':''?></b><small><?=v1183_h($v['stage_title']?:$v['artifact_type'])?></small></a>
<?php endforeach;?><?php if(!$versions):?><div class="empty">No tangible versions exist yet. Notes-only output is now rejected.</div><?php endif;?></aside>
<main class="main"><?php if($selected):?><div class="head"><h1><?=v1183_h($selected['title'])?></h1><div class="meta"><?=v1183_h(ucfirst($selected['executive_key']))?> · Version <?=$selected['stage_no']?> · <?=v1183_h($selected['artifact_type'])?></div>
<div class="actions"><button class="green" id="selectVersion">Use This Version</button>
<?php foreach($versions as $v): if((int)$v['stage_no']===(int)$selected['stage_no'])continue; ?><a class="blue" href="/dashboard/goliath-workflow-review-v118-3.php?mission_id=<?=$missionId?>&stage=<?=$selected['stage_no']?>&compare=<?=$v['stage_no']?><?=$embed?'&embed=1':''?>">Compare v<?=$v['stage_no']?></a><?php endforeach;?>
<?php if(!$embed):?><a href="/dashboard/goliath-mission-control.php">Mission Control</a><?php endif;?></div></div>
<div class="contentGrid <?=$compare?'compare':''?>"><section class="panel"><div class="panelTitle">Version <?=$selected['stage_no']?> — <?=v1183_h(ucfirst($selected['executive_key']))?></div><article class="asset"><?=render1183($selected)?></article><?php if(trim((string)$selected['change_note'])!==''):?><div class="changes"><h3>Change applied</h3><?=nl2br(v1183_h($selected['change_note']))?></div><?php endif;?></section>
<?php if($compare):?><section class="panel"><div class="panelTitle">Version <?=$compare['stage_no']?> — <?=v1183_h(ucfirst($compare['executive_key']))?></div><article class="asset"><?=render1183($compare)?></article><?php if(trim((string)$compare['change_note'])!==''):?><div class="changes"><h3>Change applied</h3><?=nl2br(v1183_h($compare['change_note']))?></div><?php endif;?></section><?php endif;?></div>
<script>document.getElementById('selectVersion')?.addEventListener('click',async()=>{const reason=prompt('Why should Goliath use this version?','Founder selected the strongest version.');if(reason===null)return;const r=await fetch('/lead-engine/goliath-select-version-v118-3.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({mission_id:<?=$missionId?>,version_id:<?=(int)$selected['id']?>,reason})});const d=await r.json();if(d.ok){alert('Version selected. Goliath will use it as the current source.');location.reload()}else alert(d.error||'Selection failed')});</script>
<?php else:?><div class="empty">Choose a version to inspect the actual work.</div><?php endif;?></main></div></body></html>