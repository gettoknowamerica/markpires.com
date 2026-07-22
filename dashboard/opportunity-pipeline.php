<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
require_once __DIR__ . '/includes/goliath-ui.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function money($n){return '$'.number_format((float)$n,0);}
function sb135d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??''; $stage=$_POST['stage']??'';
  $allowed=['new','review','approved_contact','call_queue','contacted','conversation','appointment','listing_opportunity','listing_signed','under_contract','closed','lost','nurture'];
  if($id && in_array($stage,$allowed,true)){
    $prob=['new'=>10,'review'=>5,'approved_contact'=>12,'call_queue'=>18,'contacted'=>22,'conversation'=>30,'appointment'=>45,'listing_opportunity'=>60,'listing_signed'=>80,'under_contract'=>90,'closed'=>100,'lost'=>0,'nurture'=>8][$stage] ?? 10;
    sb135d('PATCH','jessica_opportunity_pipeline?id=eq.'.rawurlencode($id),['pipeline_stage'=>$stage,'probability'=>$prob,'last_activity_at'=>date('c'),'updated_at'=>date('c')]);
    $msg='Pipeline stage updated.';
  }
}
$items=sb135d('GET','jessica_opportunity_pipeline?select=*&status=eq.active&order=stage_score.desc,priority_score.desc,created_at.desc&limit=400');
$briefs=sb135d('GET','jessica_pipeline_briefings?select=*&order=created_at.desc&limit=5');
$brief=$briefs[0]??[];
$stats=['total'=>count($items),'call'=>0,'conversation'=>0,'appointment'=>0,'listing'=>0,'expected'=>0];
foreach($items as $i){$st=$i['pipeline_stage']??''; if($st==='call_queue')$stats['call']++; if($st==='conversation')$stats['conversation']++; if($st==='appointment')$stats['appointment']++; if($st==='listing_opportunity')$stats['listing']++; $stats['expected']+=(float)($i['expected_value']??0);}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V13.5 Opportunity Pipeline</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1700px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:28px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .36fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}.stage{font-weight:900;color:#c8a96e}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style><link rel="stylesheet" href="/dashboard/assets/goliath-os.css?v=4"><link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=4"><?php goliath_ui_head(); ?></head><body><?php goliath_ui_open(); ?><div class="header"><div class="brand">V13.5 Opportunity Pipeline</div><div>New → Call Queue → Conversation → Appointment → Listing Opportunity → Signed → Closed</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-opportunity-pipeline.php?key=<?=h($cronKey)?>">Build Pipeline</a><a class="btn light" href="/dashboard/contact-acquisition-center.php">Acquisition</a><a class="btn light" href="/dashboard/listing-intelligence-center.php">Listings</a><a class="btn light" href="/dashboard/asset-vault.php">Asset Vault</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div><div class="kpi"><div class="n"><?=h($stats['call'])?></div>Call Queue</div><div class="kpi"><div class="n"><?=h($stats['conversation'])?></div>Conversation</div><div class="kpi"><div class="n"><?=h($stats['appointment'])?></div>Appts</div><div class="kpi"><div class="n"><?=h($stats['listing'])?></div>Listings</div><div class="kpi"><div class="n"><?=money($stats['expected'])?></div>Expected</div></section>
<div class="layout"><section class="panel"><h2>Pipeline</h2><table><tr><th>Stage</th><th>Opportunity</th><th>Value</th><th>Next Step</th><th>Move</th></tr><?php foreach($items as $i):?><tr><td><div class="stage"><?=h($i['pipeline_stage'])?></div><div class="muted">Prob <?=h($i['probability'])?>%<br>Score <?=h($i['priority_score'])?></div></td><td><strong><?=h($i['name']?:'Unnamed')?></strong><div class="muted"><?=h($i['phone'])?><br><?=h($i['email'])?><br><?=h($i['address'])?><br><?=h($i['town'])?></div></td><td><?=money($i['estimated_commission'])?><div class="muted">Expected <?=money($i['expected_value'])?></div></td><td><?=h($i['next_step'])?><div class="muted"><?=h($i['notes'])?></div></td><td><form method="post"><input type="hidden" name="id" value="<?=h($i['id'])?>"><button class="btn" name="stage" value="contacted">Contacted</button><button class="btn light" name="stage" value="conversation">Conversation</button><button class="btn light" name="stage" value="appointment">Appointment</button><button class="btn light" name="stage" value="listing_opportunity">Listing Opp</button><button class="btn light" name="stage" value="lost">Lost</button></form></td></tr><?php endforeach;?></table></section><section class="panel"><h2>Pipeline Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Build Pipeline to create briefing.')?></pre></div></section></div>
</main><?php goliath_ui_close(); ?></body></html>