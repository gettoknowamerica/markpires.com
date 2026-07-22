<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-v119-blog-proof.php'));exit;}
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function one($sql,$p=[]):array{try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];}}
function all($sql,$p=[]):array{try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}

$missionId=(int)($_GET['mission_id']??0);
$stageNo=(int)($_GET['stage']??0);
$mission=$missionId?one("SELECT * FROM goliath_v112_missions WHERE id=? LIMIT 1",[$missionId]):[];
$stages=$missionId?all("SELECT * FROM goliath_v112_stages WHERE mission_id=? ORDER BY stage_no",[$missionId]):[];
$versions=$missionId?all("SELECT * FROM goliath_v118_asset_versions WHERE mission_id=? ORDER BY stage_no,id",[$missionId]):[];
$byStage=[];foreach($versions as $v)$byStage[(int)$v['stage_no']]=$v;
if($stageNo<1&&$versions)$stageNo=(int)end($versions)['stage_no'];
$selected=$byStage[$stageNo]??[];
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>V119 Blog Proof</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#03060d;color:#eef4ff;font-family:Arial,sans-serif}.wrap{display:grid;grid-template-columns:300px minmax(0,1fr);min-height:100vh}.rail{background:#07111f;border-right:1px solid #29445f;overflow:auto}.railHead{position:sticky;top:0;background:#07111ff5;padding:14px;z-index:3;border-bottom:1px solid #ffffff18}.railHead h1{margin:0;color:#f3d373;font-size:20px}.stage{display:block;color:#fff;text-decoration:none;padding:11px 12px;border-bottom:1px solid #ffffff12}.stage:hover,.stage.active{background:#10213a}.stage b{display:block}.stage small{color:#9fb0c7}.stage em{float:right;font-style:normal;color:#f3d373}.pass{color:#2dd46f!important}.main{min-width:0}.top{padding:14px 18px;background:#081322;border-bottom:1px solid #ffffff18}.top h2{margin:0;color:#f3d373}.metrics{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}.metric{background:#111c2d;border:1px solid #334a66;padding:8px 10px;border-radius:10px;font-weight:900}.asset{padding:22px;line-height:1.65;font-size:16px;max-width:1050px;margin:auto}.asset img,.asset iframe,.asset video{max-width:100%;height:auto}.asset pre{white-space:pre-wrap;font:inherit}.empty{padding:40px;text-align:center;color:#95a5ba}.changes{margin:0 auto 25px;max-width:1050px;background:#101b2b;border:1px solid #415a75;border-radius:12px;padding:13px}.changes h3{margin:0 0 8px;color:#f3d373}.toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.toolbar a{background:#805f12;color:#fff;text-decoration:none;padding:8px 11px;border-radius:9px;font-weight:900}@media(max-width:760px){.wrap{grid-template-columns:1fr}.rail{max-height:220px;border-right:0;border-bottom:1px solid #29445f}.asset{padding:14px}}
</style></head><body><div class="wrap"><aside class="rail"><div class="railHead"><h1>V119 Blog Proof</h1><small>Mission #<?=$missionId?> · actual work only</small></div>
<?php foreach($stages as $s):$v=$byStage[(int)$s['stage_no']]??[];?>
<a class="stage <?=((int)$s['stage_no']===$stageNo)?'active':''?>" href="?mission_id=<?=$missionId?>&stage=<?=(int)$s['stage_no']?>">
<em class="<?=!empty($v)?'pass':''?>"><?=!empty($v)?'VERSION':'WAIT'?></em><b><?=h($s['stage_no'].'. '.ucfirst((string)$s['executive_key']))?></b><small><?=h($s['title'])?> · <?=h($s['status'])?></small></a>
<?php endforeach;?></aside><main class="main"><div class="top"><h2><?=h($mission['title']??'Blog Proof Mission')?></h2><div class="metrics"><span class="metric">Mission: <?=h($mission['status']??'unknown')?></span><span class="metric">Versions: <?=count($versions)?> / <?=count($stages)?></span><span class="metric">Current stage: <?=h($mission['current_stage_no']??0)?></span></div><div class="toolbar"><a href="/dashboard/goliath-mission-control.php">Mission Control</a><a href="/dashboard/goliath-workflow-review-v118-3.php?mission_id=<?=$missionId?>">Compare / Select Version</a></div></div>
<?php if($selected):?><article class="asset"><?php if(trim((string)$selected['content_html'])!==''): echo $selected['content_html']; else:?><pre><?=h($selected['content_text'])?></pre><?php endif;?></article><?php if(trim((string)$selected['change_note'])!==''):?><section class="changes"><h3>Applied by <?=h(ucfirst((string)$selected['executive_key']))?></h3><?=nl2br(h($selected['change_note']))?></section><?php endif;?>
<?php else:?><div class="empty">This Executive has not produced a tangible version yet. Notes-only output will not appear here.</div><?php endif;?></main></div>
<script>setTimeout(()=>location.reload(),15000)</script></body></html>