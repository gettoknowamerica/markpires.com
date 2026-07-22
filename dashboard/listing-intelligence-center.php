<?php
/**
 * V13.0.1 Listing Intelligence Dashboard — 500 Fix
 * Upload over: /public_html/dashboard/listing-intelligence-center.php
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
require_once __DIR__ . '/includes/goliath-ui.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function money($n){return '$'.number_format((float)$n,0);}
function arr13($v){ if(is_string($v)){ $d=json_decode($v,true); return is_array($d)?$d:[]; } return is_array($v)?$v:[]; }
function sb13d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $d=json_decode($b,true);
  if(!is_array($d)) return [];
  return $d;
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??''; $status=$_POST['status']??'';
  if($id && in_array($status,['active','pursue','defer','won','lost','archived'],true)){
    sb13d('PATCH','listing_intelligence_opportunities?id=eq.'.rawurlencode($id),['status'=>$status,'updated_at'=>date('c')]);
    $msg='Opportunity marked '.$status.'.';
  }
}
$rows=sb13d('GET','listing_intelligence_opportunities?select=*&status=eq.active&order=listing_probability_score.desc,expected_value.desc&limit=300');
$briefs=sb13d('GET','listing_intelligence_briefings?select=*&order=created_at.desc&limit=10');
$brief=$briefs[0]??[];
$motives=arr13($brief['top_motivations']??[]);
$stats=['total'=>count($rows),'aplus'=>0,'a'=>0,'b'=>0,'call'=>0,'commission'=>0];
foreach($rows as $r){ if(($r['priority_tier']??'')==='A+')$stats['aplus']++; if(($r['priority_tier']??'')==='A')$stats['a']++; if(($r['priority_tier']??'')==='B')$stats['b']++; if(!empty($r['call_eligible']))$stats['call']++; $stats['commission']+=(float)($r['estimated_commission']??0); }
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?>
<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath Listing Intelligence</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1700px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:30px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .38fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}.tier{font-size:22px;font-weight:900;color:#c8a96e}.yes{color:#14783c;font-weight:900}.no{color:#9a6400;font-weight:900}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style><link rel="stylesheet" href="/dashboard/assets/goliath-os.css?v=4"><link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=4"><?php goliath_ui_head(); ?></head>
<body><?php goliath_ui_open(); ?><div class="header"><div class="brand">Goliath Listing Intelligence</div><div>Likely listings, revenue per hour, motivations, and next best action</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-listing-intelligence.php?key=<?=h($cronKey)?>">Build Listing Intelligence</a><a class="btn light" href="/dashboard/revenue-forecast.php">Revenue</a><a class="btn light" href="/dashboard/approved-contact-pipeline.php">Contacts</a><a class="btn light" href="/dashboard/jessica-deliverables.php">Deliverables</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Opportunities</div><div class="kpi"><div class="n"><?=h($stats['aplus'])?></div>A+</div><div class="kpi"><div class="n"><?=h($stats['a'])?></div>A</div><div class="kpi"><div class="n"><?=h($stats['b'])?></div>B</div><div class="kpi"><div class="n"><?=h($stats['call'])?></div>Callable</div><div class="kpi"><div class="n"><?=money($stats['commission'])?></div>Commission</div></section>
<div class="layout"><section class="panel"><h2>Top Listing Opportunities</h2><table><tr><th>Tier</th><th>Opportunity</th><th>Scores</th><th>Why This Matters</th><th>Next</th></tr><?php foreach($rows as $r):?><tr><td><div class="tier"><?=h($r['priority_tier']??'')?></div><strong><?=h($r['listing_probability_score']??0)?></strong><div class="muted"><?=money($r['estimated_commission']??0)?></div></td><td><strong><?=h(($r['name']??'')?:(($r['town']??'').' Opportunity'))?></strong><div class="muted"><?=h($r['phone']??'')?><br><?=h($r['email']??'')?><br><?=h($r['address']??'')?><br><?=h($r['town']??'')?> <?=h($r['market']??'')?></div></td><td>Market <?=h($r['market_heat_score']??0)?><br>Contact <?=h($r['contact_quality_score']??0)?><br>Motivation <?=h($r['seller_motivation_score']??0)?><br>Conversation <?=h($r['conversation_score']??0)?><br>Appt <?=h($r['appointment_score']??0)?></td><td><strong><?=h($r['likely_motivation']??'')?></strong><div class="muted"><?=h($r['why_this_matters']??'')?></div><div class="<?=!empty($r['call_eligible'])?'yes':'no'?>">Call eligible: <?=h(!empty($r['call_eligible'])?'yes':'no')?></div></td><td><?=h($r['next_best_action']??'')?><form method="post"><input type="hidden" name="id" value="<?=h($r['id']??'')?>"><button class="btn" name="status" value="pursue">Pursue</button><button class="btn light" name="status" value="defer">Defer</button><button class="btn light" name="status" value="archived">Archive</button></form></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>V13 Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Build Listing Intelligence to create briefing.')?></pre></div><h2>Top Motivations</h2><table><tr><th>Motivation</th><th>Count</th></tr><?php foreach($motives as $m):?><tr><td><?=h($m['motivation']??'')?></td><td><?=h($m['count']??0)?></td></tr><?php endforeach;?></table></section></div></main><?php goliath_ui_close(); ?></body></html>