<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb($ep){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/');
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>20]);
  $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  $d=json_decode($body,true); return is_array($d)?$d:[];
}
function town($v){$v=trim((string)$v);return $v?:'Unknown';}
function phone($p){$d=preg_replace('/\D+/','',(string)$p);if(strlen($d)==10)return '+1'.$d;if(strlen($d)==11&&$d[0]=='1')return '+'.$d;return $p;}
function src($l){$u=strtolower((string)($l['page_url']??''));$t=strtolower((string)($l['type']??''));if(str_contains($u,'/towns/'))return'Town Pages';if(str_contains($u,'/blog/'))return'Blog';if(str_contains($u,'valuation')||$t==='valuation')return'Home Valuation';return $l['tag']??($l['type']??'Website');}

$future=sb('future_seller_pipeline?select=*&order=next_followup_at.asc&limit=500');
$outcomes=sb('cold_call_outcomes?select=*&order=created_at.desc&limit=500');
$leads=sb('leads?select=*&order=created_at.desc&limit=500');
$calls=sb('call_intelligence?select=*&order=created_at.desc&limit=500');
$events=sb('conversion_events?select=*&order=created_at.desc&limit=1000');

$f30=$f90=$f180=$f365=0;$towns=[];$sources=[];$opps=[];
foreach($future as $f){
  $days=$f['next_followup_at']?floor((strtotime($f['next_followup_at'])-time())/86400):365;
  if($days<=30)$f30++; if($days<=90)$f90++; if($days<=180)$f180++; if($days<=365)$f365++;
  $tw=town($f['town']??''); $score=(int)($f['lead_score']??0);
  $towns[$tw]??=['heat'=>0,'future'=>0,'interested'=>0,'leads'=>0,'valuations'=>0,'traffic'=>0];
  $towns[$tw]['future']++; $towns[$tw]['heat']+=20+min(30,$score/3);
  $s=$f['source']?:'Future Seller'; $sources[$s]??=['leads'=>0,'future'=>0,'interested'=>0,'appointments'=>0]; $sources[$s]['future']++;
  $opps[]=['name'=>$f['name']??'','phone'=>$f['phone']??'','email'=>$f['email']??'','town'=>$tw,'source'=>'Future Seller','score'=>$score,'reason'=>'Future seller follow-up','action'=>$score>=90?'Call personally now':'Queue Jessica follow-up'];
}
foreach($outcomes as $o){
  $tw=town($o['town']??''); $towns[$tw]??=['heat'=>0,'future'=>0,'interested'=>0,'leads'=>0,'valuations'=>0,'traffic'=>0];
  if(in_array($o['outcome']??'', ['interested','appointment'])){$towns[$tw]['interested']++;$towns[$tw]['heat']+=25;}
}
foreach($leads as $l){
  $tw=town($l['town']??'');$score=(int)($l['lead_score']??0);
  $towns[$tw]??=['heat'=>0,'future'=>0,'interested'=>0,'leads'=>0,'valuations'=>0,'traffic'=>0];
  $towns[$tw]['leads']++;$towns[$tw]['heat']+=max(5,min(25,$score/4));
  if(($l['type']??'')==='valuation'||str_contains(strtolower((string)($l['page_url']??'')),'valuation')){$towns[$tw]['valuations']++;$towns[$tw]['heat']+=15;}
  $s=src($l);$sources[$s]??=['leads'=>0,'future'=>0,'interested'=>0,'appointments'=>0];$sources[$s]['leads']++;
  if($score>=75)$opps[]=['name'=>$l['name']??'','phone'=>$l['phone']??'','email'=>$l['email']??'','town'=>$tw,'source'=>$s,'score'=>$score,'reason'=>($l['type']??'Website lead'),'action'=>$score>=90?'Call immediately':'Call today'];
}
foreach($calls as $c){
  $tw=town($c['town']??'');$towns[$tw]??=['heat'=>0,'future'=>0,'interested'=>0,'leads'=>0,'valuations'=>0,'traffic'=>0];
  if(!empty($c['appointment_requested'])||!empty($c['hot_lead']))$towns[$tw]['heat']+=25;
}
foreach($events as $e){$tw=town($e['town']??'');if($tw==='Unknown')continue;$towns[$tw]??=['heat'=>0,'future'=>0,'interested'=>0,'leads'=>0,'valuations'=>0,'traffic'=>0];if(($e['event_type']??'')==='page_view'){$towns[$tw]['traffic']++;$towns[$tw]['heat']+=1;}}
uasort($towns,fn($a,$b)=>$b['heat']<=>$a['heat']);usort($opps,fn($a,$b)=>$b['score']<=>$a['score']);uasort($sources,fn($a,$b)=>(($b['future']*8)+$b['leads'])<=>(($a['future']*8)+$a['leads']));
?>
<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Listing Forecast V6</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.header a{color:#fff}.wrap{max-width:1450px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.l{font-size:11px;text-transform:uppercase;color:#777}.layout{display:grid;grid-template-columns:1fr .75fr;gap:18px}.panel{overflow:hidden;margin-top:18px}.panel h2{font-family:Georgia,serif;margin:0;padding:18px 20px;border-bottom:1px solid #eee}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.badge{border-radius:999px;padding:5px 8px;font-size:11px}.hot{background:#2b2110;color:#ffd36b}.high{background:#fff4d7;color:#8a5a00}.watch{background:#e9f2ff;color:#174ea6}.muted{color:#777;font-size:13px}.bar{height:8px;background:#eee;border-radius:99px;overflow:hidden}.bar span{display:block;height:100%;background:#c8a96e}.btn{display:inline-block;background:#c8a96e;color:#111;padding:8px 10px;border-radius:8px;text-decoration:none;font-weight:800;font-size:12px}@media(max-width:900px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Listing Forecast V6</div><div>Market Intelligence · Future Sellers · Town Heat · Today's Money List · <a href="/dashboard/operations.php">Operations</a></div></div><main class="wrap">
<section class="grid"><div class="kpi"><div class="n"><?=h($f30)?></div><div class="l">30 Days</div></div><div class="kpi"><div class="n"><?=h($f90)?></div><div class="l">90 Days</div></div><div class="kpi"><div class="n"><?=h($f180)?></div><div class="l">180 Days</div></div><div class="kpi"><div class="n"><?=h($f365)?></div><div class="l">365 Days</div></div></section>
<div class="layout"><section class="panel"><h2>Today's Top Listing Opportunities</h2><table><tr><th>Score</th><th>Opportunity</th><th>Town</th><th>Source</th><th>Action</th></tr><?php foreach(array_slice($opps,0,25) as $o):$cls=$o['score']>=90?'hot':($o['score']>=75?'high':'watch');?><tr><td><span class="badge <?=h($cls)?>"><?=h($o['score'])?></span></td><td><strong><?=h($o['name']?:'Unknown')?></strong><div class="muted"><?=h($o['phone'])?> · <?=h($o['email'])?><br><?=h($o['reason'])?></div></td><td><?=h($o['town'])?></td><td><?=h($o['source'])?></td><td><?=h($o['action'])?><br><?php if($o['phone']):?><a class="btn" href="tel:<?=h(phone($o['phone']))?>">Call</a><?php endif;?></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Town Heat Index</h2><table><tr><th>Town</th><th>Heat</th><th>Signals</th></tr><?php foreach(array_slice($towns,0,20,true) as $tw=>$t):$heat=min(100,round($t['heat']));?><tr><td><strong><?=h($tw)?></strong><div class="bar"><span style="width:<?=h($heat)?>%"></span></div></td><td><?=h($heat)?></td><td class="muted">Future <?=h($t['future'])?> · Interested <?=h($t['interested'])?> · Leads <?=h($t['leads'])?> · Valuations <?=h($t['valuations'])?> · Traffic <?=h($t['traffic'])?></td></tr><?php endforeach;?></table></section></div>
<section class="panel"><h2>Source Intelligence</h2><table><tr><th>Source</th><th>Leads</th><th>Future Sellers</th><th>Interested</th><th>Appointments</th></tr><?php foreach($sources as $name=>$s):?><tr><td><strong><?=h($name)?></strong></td><td><?=h($s['leads'])?></td><td><?=h($s['future'])?></td><td><?=h($s['interested'])?></td><td><?=h($s['appointments'])?></td></tr><?php endforeach;?></table></section>
</main></body></html>