<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){
 header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-workflow-review-v118-2.php'));exit;
}
function v1182_h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function v1182_one($sql,$p=[]):array{try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];}}
function v1182_all($sql,$p=[]):array{try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}

$missionId=max(0,(int)($_GET['mission_id']??0));
$stageNo=max(0,(int)($_GET['stage']??0));
$embed=(string)($_GET['embed']??'')==='1';
$mission=$missionId?v1182_one("SELECT * FROM goliath_v112_missions WHERE id=? LIMIT 1",[$missionId]):[];
$versions=$missionId?v1182_all(
 "SELECT v.*,s.title stage_title,s.stage_key
  FROM goliath_v118_asset_versions v
  LEFT JOIN goliath_v112_stages s ON s.id=v.stage_id
  WHERE v.mission_id=? ORDER BY v.stage_no ASC,v.id ASC",[$missionId]
):[];
if($stageNo<1&&$versions)$stageNo=(int)end($versions)['stage_no'];
$selected=[];
foreach($versions as $version)if((int)$version['stage_no']===$stageNo)$selected=$version;
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Evolving Asset Review</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#02050b;color:#eef5ff;font-family:Arial,sans-serif}
.shell{display:grid;grid-template-columns:290px minmax(0,1fr);min-height:100vh}.rail{background:#07111f;border-right:1px solid #29445f;overflow:auto}
.railHead{position:sticky;top:0;background:#07111ff5;padding:13px;border-bottom:1px solid #ffffff18}.railHead h2{margin:0;color:#f3d373}
.version{display:block;color:#fff;text-decoration:none;padding:11px;border-bottom:1px solid #ffffff12}.version:hover,.version.active{background:#10213a}
.version b{display:block}.version small{color:#9fb0c7}.version em{float:right;font-style:normal;color:#f3d373}
.main{min-width:0}.head{padding:14px 18px;background:#081322;border-bottom:1px solid #ffffff18}.head h1{margin:0;color:#f3d373}.meta{color:#9fb0c7;margin-top:5px}
.asset{padding:20px;line-height:1.65;font-size:16px;overflow:auto;max-height:calc(100vh - 150px)}.asset img,.asset video,.asset iframe{max-width:100%;height:auto}.asset pre{white-space:pre-wrap;font:inherit}
.changes{margin:0 20px 20px;padding:13px;border:1px solid #415a75;background:#101b2b;border-radius:12px}.changes h3{margin:0 0 8px;color:#f3d373}
.empty{padding:30px;color:#9fb0c7}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:9px}.actions a{background:#805f12;color:#fff;text-decoration:none;padding:8px 11px;border-radius:9px;font-weight:900}
body.embed .shell{grid-template-columns:220px minmax(0,1fr)}body.embed .asset{max-height:calc(100vh - 88px);padding:12px}body.embed .head{padding:9px 12px}
@media(max-width:720px){.shell,body.embed .shell{grid-template-columns:1fr}.rail{max-height:185px;border-right:0;border-bottom:1px solid #29445f}.asset{max-height:none}}
</style></head><body class="<?=$embed?'embed':''?>"><div class="shell">
<aside class="rail"><div class="railHead"><h2><?=v1182_h($mission['title']??'Evolving Asset')?></h2><small>Mission #<?=$missionId?> · every version preserved</small></div>
<?php foreach($versions as $version):?>
<a class="version <?=((int)$version['stage_no']===$stageNo)?'active':''?>" href="/dashboard/goliath-workflow-review-v118-2.php?mission_id=<?=$missionId?>&stage=<?=(int)$version['stage_no']?><?=$embed?'&embed=1':''?>">
<em><?=v1182_h($version['status'])?></em><b><?=v1182_h($version['stage_no'].'. '.ucfirst((string)$version['executive_key']))?></b>
<small><?=v1182_h($version['stage_title']?:$version['artifact_type'])?></small></a>
<?php endforeach;?>
<?php if(!$versions):?><div class="empty">No tangible versions exist yet. The next Executive response must pass the full-artifact gate.</div><?php endif;?>
</aside><main class="main">
<?php if($selected):?><div class="head"><h1><?=v1182_h($selected['title'])?></h1>
<div class="meta"><?=v1182_h(ucfirst((string)$selected['executive_key']))?> · Version <?=$selected['stage_no']?> · <?=v1182_h($selected['artifact_type'])?></div>
<?php if(!$embed):?><div class="actions"><a href="/dashboard/goliath-mission-control.php">Mission Control</a>
<?php if($selected['artifact_url']):?><a target="_blank" href="<?=v1182_h($selected['artifact_url'])?>">Open Output</a><?php endif;?>
<?php if($selected['artifact_path']):?><a target="_blank" href="<?=v1182_h($selected['artifact_path'])?>">Open File</a><?php endif;?></div><?php endif;?></div>
<article class="asset"><?php if(trim((string)$selected['content_html'])!==''): echo $selected['content_html']; else:?><pre><?=v1182_h($selected['content_text'])?></pre><?php endif;?></article>
<?php if(trim((string)$selected['change_note'])!==''):?><section class="changes"><h3>Change applied by <?=v1182_h(ucfirst((string)$selected['executive_key']))?></h3><?=nl2br(v1182_h($selected['change_note']))?></section><?php endif;?>
<?php else:?><div class="empty">Choose a version to inspect the actual work product.</div><?php endif;?>
</main></div></body></html>