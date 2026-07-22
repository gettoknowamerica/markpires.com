<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function ph143($p){$p=preg_replace('/[^0-9]/','',(string)$p);if(strlen($p)===11&&substr($p,0,1)==='1')$p=substr($p,1);return $p;}
function sb143d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>45]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
function parse_csv143($path){
  $rows=[]; if(($h=fopen($path,'r'))!==false){$headers=fgetcsv($h); if(!$headers)return []; while(($d=fgetcsv($h))!==false){$r=[];foreach($headers as $i=>$k){$r[trim($k)]=$d[$i]??'';}$rows[]=$r;}fclose($h);} return $rows;
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['csv_file'])){
  if(is_uploaded_file($_FILES['csv_file']['tmp_name'])){
    $rows=parse_csv143($_FILES['csv_file']['tmp_name']);
    $payload=[]; $created=0; $errors=[];
    foreach($rows as $r){
      $full=$r['full_name']??$r['FULL_NAME']??$r['name']??'';
      $parts=array_values(array_filter(explode(' ',trim($full))));
      $payload[]=[
        'full_name'=>$full,'first_name'=>$parts[0]??'','last_name'=>count($parts)>1?$parts[count($parts)-1]:'',
        'userid'=>$r['userid']??$r['USERID']??'','office_name'=>$r['office_name']??$r['OFFICENAME']??'',
        'office_city'=>$r['office_city']??$r['OFFICE_CITY']??'','office_phone'=>ph143($r['office_phone']??$r['OPHONE']??''),
        'mobile_phone'=>ph143($r['mobile_phone']??$r['MPHONE']??''),'email'=>strtolower($r['email']??$r['EMAIL']??''),
        'mls_user_types'=>$r['mls_user_types']??$r['MLS_USER_TYPES']??'','mls_agent_type'=>$r['mls_agent_type']??$r['MLS_AGENT_TYPE']??'',
        'source'=>'connectmls_csv','status'=>'active','raw_payload'=>$r,'created_at'=>date('c'),'updated_at'=>date('c')
      ];
    }
    foreach(array_chunk($payload,100) as $chunk){$res=sb143d('POST','agent_master_list',$chunk); if(is_array($res))$created+=count($chunk);}
    $msg='Imported '.$created.' agent records.';
  }
}
$agents=sb143d('GET','agent_master_list?select=*&status=eq.active&order=full_name.asc&limit=300');
$matches=sb143d('GET','realtor_exclusion_matches?select=*&status=eq.active&order=created_at.desc&limit=300');
$briefs=sb143d('GET','realtor_exclusion_briefings?select=*&order=created_at.desc&limit=5');
$brief=$briefs[0]??[];
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V14.3 Realtor Exclusion</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1700px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:28px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .38fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}input{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;margin:4px 0}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">V14.3 Realtor Exclusion Engine</div><div>Removes agents/Realtors before Jessica calls homeowner prospects</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-realtor-exclusion.php?key=<?=h($cronKey)?>">Run Realtor Exclusion</a><a class="btn light" href="/dashboard/contact-enrichment-center.php">Enrichment</a><a class="btn light" href="/dashboard/seller-opportunity-engine.php">Seller Engine</a><a class="btn light" href="/dashboard/opportunity-pipeline.php">Pipeline</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h(count($agents))?>+</div>Agents Loaded</div><div class="kpi"><div class="n"><?=h(count($matches))?></div>Recent Matches</div><div class="kpi"><div class="n"><?=h($brief['matches_found']??0)?></div>Matched Today</div><div class="kpi"><div class="n"><?=h($brief['approved_pool_flagged']??0)?></div>Pool Flagged</div></section>
<div class="layout"><section class="panel"><h2>Recent Realtor Matches</h2><table><tr><th>Source</th><th>Prospect</th><th>Matched Agent</th><th>Confidence</th></tr><?php foreach($matches as $m):?><tr><td><?=h($m['source_table'])?><div class="muted"><?=h($m['source_town'])?></div></td><td><?=h($m['source_name']?:$m['source_phone'])?><div class="muted"><?=h($m['source_email'])?><br><?=h($m['source_address'])?></div></td><td><strong><?=h($m['agent_name'])?></strong><div class="muted"><?=h($m['office_name'])?><br><?=h($m['match_type'])?></div></td><td><?=h($m['match_confidence'])?>%</td></tr><?php endforeach;?></table><h2>Agent Master Sample</h2><table><tr><th>Name</th><th>Office</th><th>Phone</th></tr><?php foreach($agents as $a):?><tr><td><?=h($a['full_name'])?></td><td><?=h($a['office_name'])?><div class="muted"><?=h($a['office_city'])?></div></td><td><?=h($a['office_phone'])?></td></tr><?php endforeach;?></table></section><section class="panel"><h2>Import Agent CSV</h2><div style="padding:16px"><form method="post" enctype="multipart/form-data"><input type="file" name="csv_file" accept=".csv" required><button class="btn">Import CSV</button></form><p class="muted">Use included file: data/agent_master_list_import.csv</p></div><h2>Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Import agents, then run Realtor Exclusion.')?></pre></div></section></div>
</main></body></html>