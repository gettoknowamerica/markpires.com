<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb114d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??'';
  if($id){
    $payload=[
      'pipeline_stage'=>$_POST['pipeline_stage']??'new',
      'deal_probability'=>(int)($_POST['deal_probability']??10),
      'estimated_deal_value'=>$_POST['estimated_deal_value']!==''?(float)$_POST['estimated_deal_value']:null,
      'referral_potential'=>$_POST['referral_potential']!==''?(float)$_POST['referral_potential']:null,
      'next_step'=>$_POST['next_step']??'',
      'next_followup_at'=>$_POST['next_followup_at']?:null,
      'notes'=>$_POST['notes']??'',
      'updated_at'=>date('c')
    ];
    $r=sb114d('PATCH','builder_pipeline?id=eq.'.rawurlencode($id),$payload);
    $msg=$r['ok']?'Pipeline updated.':'Update failed: '.$r['body'];
  }
}
$stage=$_GET['stage']??'active';
if($stage==='active')$ep='builder_pipeline?select=*&pipeline_stage=not.in.(closed,dead)&order=deal_probability.desc,next_followup_at.asc&limit=300';
elseif($stage==='all')$ep='builder_pipeline?select=*&order=updated_at.desc&limit=300';
else $ep='builder_pipeline?select=*&pipeline_stage=eq.'.rawurlencode($stage).'&order=deal_probability.desc&limit=300';
$rows=sb114d('GET',$ep)['data'];
$all=sb114d('GET','builder_pipeline?select=pipeline_stage,deal_probability,referral_potential,opportunity_town&limit=1000')['data'];
$stages=[];$pipelineValue=0;$hot=0;$followups=0;
foreach($all as $r){$s=$r['pipeline_stage']?:'new';$stages[$s]=($stages[$s]??0)+1;$pipelineValue+=(float)($r['referral_potential']??0);if((int)($r['deal_probability']??0)>=50)$hot++;}
foreach($rows as $r){if(!empty($r['next_followup_at'])&&strtotime($r['next_followup_at'])<=strtotime('+7 days'))$followups++;}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Builder Pipeline V11.4</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{display:inline-block;border:0;background:#10101a;color:#fff;text-decoration:none;padding:8px 10px;border-radius:8px;font-size:12px;font-weight:800;margin:2px;cursor:pointer}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}input,select,textarea{padding:8px;border:1px solid #ddd;border-radius:8px;margin:3px;max-width:190px}.muted{color:#777;font-size:13px}.layout{display:grid;grid-template-columns:1fr .3fr;gap:18px}@media(max-width:900px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style></head><body><div class="header"><div class="brand">Builder Pipeline V11.4</div><div>Track builder/developer opportunities from intro to deal</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn gold" target="_blank" href="/lead-engine/build-builder-pipeline.php?key=<?=h($cronKey)?>">Build Pipeline</a><a class="btn light" href="?stage=active">Active</a><a class="btn light" href="?stage=site_visit">Site Visits</a><a class="btn light" href="?stage=offer_possible">Offer Possible</a><a class="btn light" href="?stage=all">All</a><a class="btn light" href="/dashboard/builder-intro-outreach.php">Intro Outreach</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h(count($all))?></div>Total Pipeline</div><div class="kpi"><div class="n"><?=h($hot)?></div>50%+ Probability</div><div class="kpi"><div class="n"><?=h($followups)?></div>7-Day Followups</div><div class="kpi"><div class="n">$<?=h(number_format($pipelineValue))?></div>Referral Potential</div></section>
<div class="layout"><section class="panel"><h2>Pipeline</h2><table><tr><th>Deal</th><th>Builder</th><th>Stage</th><th>Next Step</th><th>Update</th></tr><?php foreach($rows as $r):?><tr><td><strong><?=h($r['opportunity_address'])?></strong><div class="muted"><?=h($r['opportunity_town'])?> · <?=h($r['opportunity_type'])?><br>Value $<?=h(number_format((float)$r['estimated_deal_value']))?><br>Referral $<?=h(number_format((float)$r['referral_potential']))?></div></td><td><strong><?=h($r['builder_name'])?></strong><div class="muted"><?=h($r['company'])?><br><?=h($r['phone'])?><br><?=h($r['email'])?></div></td><td><?=h($r['pipeline_stage'])?><br><strong><?=h($r['deal_probability'])?>%</strong></td><td><?=h($r['next_step'])?><div class="muted">Follow-up: <?=h($r['next_followup_at'])?><br><?=h($r['notes'])?></div></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><select name="pipeline_stage"><option><?=h($r['pipeline_stage'])?></option><option value="interested">interested</option><option value="reviewing">reviewing</option><option value="site_visit">site_visit</option><option value="offer_possible">offer_possible</option><option value="offer_made">offer_made</option><option value="under_contract">under_contract</option><option value="closed">closed</option><option value="dead">dead</option></select><input type="number" name="deal_probability" value="<?=h($r['deal_probability'])?>"><input type="number" name="estimated_deal_value" value="<?=h($r['estimated_deal_value'])?>" placeholder="Deal value"><input type="number" name="referral_potential" value="<?=h($r['referral_potential'])?>" placeholder="Referral potential"><input type="datetime-local" name="next_followup_at" value=""><textarea name="next_step" placeholder="Next step"><?=h($r['next_step'])?></textarea><textarea name="notes" placeholder="Notes"><?=h($r['notes'])?></textarea><button class="btn gold">Save</button></form></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Stages</h2><table><tr><th>Stage</th><th>Count</th></tr><?php foreach($stages as $k=>$v):?><tr><td><?=h($k)?></td><td><?=h($v)?></td></tr><?php endforeach;?></table></section></div>
</main></body></html>