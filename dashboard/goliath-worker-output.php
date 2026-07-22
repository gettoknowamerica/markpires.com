<?php
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-worker-output.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function rows($s,$p=[]){try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [];}}
$assets=rows("SELECT a.*,m.title mission_title,m.originator_key FROM goliath_v112_artifacts a JOIN goliath_v112_missions m ON m.id=a.mission_id WHERE a.delivered_by_goliath=1 AND a.status='delivered' ORDER BY a.delivered_at DESC,a.id DESC LIMIT 200");
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Finished Assets</title><link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33"><style>
body{background:#030712;color:#fff}.hero,.asset{background:#07111f;border:1px solid #ffffff18;border-radius:18px;padding:16px;margin-bottom:12px}.hero b{font-size:46px;color:#d4af37}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:12px}.asset h3{color:#f6d679;margin:0 0 7px}.meta{color:#94a3b8;font-size:12px}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.btnx{background:#14532d;color:#dcfce7;border:1px solid #22c55e66;border-radius:10px;padding:9px 11px;text-decoration:none;font-weight:900}
</style></head><body><div class="shell"><?php @require __DIR__.'/includes/goliath-sidebar-v33.php'; ?><main class="main">
<section class="hero"><h1>Finished Assets</h1><b><?=count($assets)?></b><p>Actual delivered assets only. Internal stages, reviews, placeholders and handoffs are not counted.</p><a class="btnx" href="/dashboard/goliath-mission-control.php">← Mission Control</a></section>
<section class="grid"><?php foreach($assets as $a): ?><article class="asset"><h3><?=h($a['title']?:$a['mission_title'])?></h3><div class="meta">Delivered by Goliath · <?=h($a['delivered_at'])?> · Originator: <?=h($a['originator_key'])?></div><p><?=h(mb_substr(strip_tags((string)($a['content_text']?:$a['content_html'])),0,280))?></p><div class="actions"><?php if($a['artifact_url']): ?><a class="btnx" href="<?=h($a['artifact_url'])?>">Open Finished Asset</a><?php endif; ?></div></article><?php endforeach; ?><?php if(!$assets): ?><article class="asset"><h3>0 finished assets</h3><p>This is correct until Goliath publishes or delivers the first tangible result.</p></article><?php endif; ?></section>
</main></div></body></html>