<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbg($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>20]);
  $body=curl_exec($ch);curl_close($ch);$d=json_decode($body,true);return is_array($d)?$d:[];
}
$rules=sbg('adaptive_intelligence_rules?select=*&order=score_adjustment.desc&limit=300');
$homeowners=sbg('homeowner_intelligence?select=*&order=adaptive_score.desc&limit=50');
$leads=sbg('leads?select=*&order=adaptive_score.desc&limit=50');
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
$pos=0;$neg=0;$high=0;foreach($rules as $r){if((int)$r['score_adjustment']>0)$pos++;if((int)$r['score_adjustment']<0)$neg++;if(($r['confidence']??'')==='high')$high++;}
?>
<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Adaptive Intelligence V7</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.badge{border-radius:999px;padding:5px 8px;font-size:11px}.plus{background:#e6f7ec;color:#14783c}.minus{background:#ffeaea;color:#9b1c1c}.zero{background:#eee;color:#555}.btn{display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:10px 12px;border-radius:9px;font-weight:900}.layout{display:grid;grid-template-columns:1fr 1fr;gap:18px}@media(max-width:900px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Adaptive Intelligence V7</div><div>Jessica learns from towns, sources, calls, outcomes, and future sellers · <a style="color:#fff" href="/dashboard/operations.php">Operations</a></div></div><main class="wrap">
<p><a class="btn" href="/lead-engine/build-adaptive-intelligence.php?key=<?=h($cronKey)?>" target="_blank">Build Adaptive Intelligence Now</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h(count($rules))?></div>Rules</div><div class="kpi"><div class="n"><?=h($pos)?></div>Positive Boosts</div><div class="kpi"><div class="n"><?=h($neg)?></div>Negative Adjustments</div><div class="kpi"><div class="n"><?=h($high)?></div>High Confidence</div></section>

<section class="panel"><h2>Adaptive Rules</h2><table><tr><th>Type</th><th>Key</th><th>Samples</th><th>Conversion</th><th>Adjustment</th><th>Confidence</th><th>Recommendation</th></tr>
<?php foreach($rules as $r):$adj=(int)$r['score_adjustment'];$cls=$adj>0?'plus':($adj<0?'minus':'zero');?><tr><td><?=h($r['rule_type'])?></td><td><strong><?=h($r['rule_key'])?></strong></td><td><?=h($r['sample_size'])?></td><td><?=h(round(((float)$r['conversion_rate'])*100,1))?>%</td><td><span class="badge <?=h($cls)?>"><?=h($adj>0?'+'.$adj:$adj)?></span></td><td><?=h($r['confidence'])?></td><td><?=h($r['recommendation'])?></td></tr><?php endforeach;?>
</table></section>

<div class="layout">
<section class="panel"><h2>Top Adaptive Homeowners</h2><table><tr><th>Adaptive</th><th>Base</th><th>Owner</th><th>Town</th><th>Reason</th></tr>
<?php foreach($homeowners as $h):?><tr><td><strong><?=h($h['adaptive_score']??$h['lead_score'])?></strong></td><td><?=h($h['lead_score'])?></td><td><?=h($h['owner_name'])?></td><td><?=h($h['town'])?></td><td><?=h($h['adaptive_reason'])?></td></tr><?php endforeach;?>
</table></section>
<section class="panel"><h2>Top Adaptive Leads</h2><table><tr><th>Adaptive</th><th>Base</th><th>Lead</th><th>Town</th><th>Reason</th></tr>
<?php foreach($leads as $l):?><tr><td><strong><?=h($l['adaptive_score']??$l['lead_score'])?></strong></td><td><?=h($l['lead_score'])?></td><td><?=h($l['name'])?></td><td><?=h($l['town'])?></td><td><?=h($l['adaptive_reason'])?></td></tr><?php endforeach;?>
</table></section>
</div>
</main></body></html>
