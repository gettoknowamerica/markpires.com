<?php
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/shakespeare-authority-center.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function rows($s,$p=[]){try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [];}}
function one($s,$p=[]){try{return gdb_one($s,$p)?:[];}catch(Throwable $e){return [];}}
$missionId=(int)($_GET['v112_mission']??0);
if(!$missionId){$m=one("SELECT id FROM goliath_v112_missions WHERE originator_key='shakespeare' ORDER BY id DESC LIMIT 1");$missionId=(int)($m['id']??0);}
$mission=$missionId?one("SELECT * FROM goliath_v112_missions WHERE id=?",[$missionId]):[];
$artifacts=$missionId?rows("SELECT a.*,s.stage_no,s.stage_key,s.title stage_title FROM goliath_v112_artifacts a LEFT JOIN goliath_v112_stages s ON s.id=a.stage_id WHERE a.mission_id=? ORDER BY s.stage_no DESC,a.id DESC",[$missionId]):[];
$stages=$missionId?rows("SELECT * FROM goliath_v112_stages WHERE mission_id=? ORDER BY stage_no",[$missionId]):[];
$latest=$artifacts[0]??[];
$missions=rows("SELECT * FROM goliath_v112_missions WHERE originator_key='shakespeare' ORDER BY priority DESC,id DESC LIMIT 50");
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Shakespeare Workshop</title><link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33"><style>
body{background:repeating-linear-gradient(45deg,#070b12 0,#070b12 8px,#0a1019 8px,#0a1019 16px);color:#fff}.hero{background:linear-gradient(135deg,#4a0d0d,#07111f);border:1px solid #7f1d1d;border-radius:20px;padding:16px;margin-bottom:12px}.layout{display:grid;grid-template-columns:minmax(0,1fr) 350px;gap:14px}.viewer,.rail{background:#07111f;border:1px solid #ffffff18;border-radius:18px;padding:15px}.viewerBody{background:#fff;color:#111;border-radius:14px;padding:28px;min-height:620px;max-height:78vh;overflow:auto;line-height:1.65}.rail{max-height:86vh;overflow:auto}.item{display:block;color:#fff;text-decoration:none;border-bottom:1px solid #ffffff14;padding:11px 2px}.item b{color:#f6d679}.stage{display:grid;grid-template-columns:28px 1fr auto;gap:8px;padding:7px 0;border-bottom:1px solid #ffffff10}.status{font-size:10px;border-radius:999px;padding:3px 6px;background:#172033;color:#93c5fd}.actions{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}.btnx{background:#7f1d1d;color:#fff;border:1px solid #ef444466;border-radius:10px;padding:9px 11px;text-decoration:none;font-weight:900}@media(max-width:950px){.layout{grid-template-columns:1fr}}
</style></head><body><div class="shell"><?php @require __DIR__.'/includes/goliath-sidebar-v33.php'; ?><main class="main">
<section class="hero"><h1>✒️ Shakespeare Workshop</h1><p>Actual article on the left. Current production queue on the right. No executive brief pages.</p><div class="actions"><a class="btnx" href="/dashboard/goliath-mission-control.php">← Mission Control</a><?php if(!empty($mission['delivered_url'])):?><a class="btnx" href="<?=h($mission['delivered_url'])?>">Open Published Article</a><?php endif;?></div></section>
<section class="layout"><main class="viewer"><h2><?=h($mission['title']??'No active Shakespeare mission')?></h2><p>Status: <b><?=h($mission['status']??'none')?></b> · Finished assets: <b><?=!empty($mission['delivered_at'])?'1':'0'?></b></p><div class="viewerBody"><?php
$body=(string)($latest['content_html']??'');
if($body!=='')echo $body;
elseif(!empty($latest['content_text']))echo '<pre style="white-space:pre-wrap">'.h($latest['content_text']).'</pre>';
else echo '<h2>Production has not created the first tangible draft yet.</h2><p>Shakespeare will begin with OpenClaw research. This remains zero until a real artifact is produced.</p>';
?></div><h3>Production Funnel</h3><?php foreach($stages as $s): ?><div class="stage"><span><?=h($s['stage_no'])?></span><span><b><?=h(ucfirst($s['executive_key']))?></b> — <?=h($s['title'])?></span><span class="status"><?=h($s['status'])?></span></div><?php endforeach;?></main>
<aside class="rail"><h2>Shakespeare Content</h2><?php foreach($missions as $m): ?><a class="item" href="/dashboard/shakespeare-authority-center.php?v112_mission=<?=h($m['id'])?>"><b><?=h($m['title'])?></b><br><small><?=h($m['status'])?> · finished <?=!empty($m['delivered_at'])?'1':'0'?></small></a><?php endforeach;?><?php if(!$missions):?><p>No Shakespeare missions.</p><?php endif;?></aside></section>
</main></div></body></html>