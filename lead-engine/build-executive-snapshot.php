<?php
/**
 * V12.7 Executive Snapshot Builder
 * Upload: /public_html/lead-engine/build-executive-snapshot.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb127($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function rows127($table,$query='select=*&limit=1000'){
  $r=sb127('GET',$table.'?'.$query);
  return $r['ok'] ? $r['data'] : [];
}
function count127($table,$query='select=id&limit=1000'){ return count(rows127($table,$query)); }

$today=date('Y-m-d');
$monthStart=date('Y-m-01');

$leads=rows127('leads','select=*&created_at=gte.'.rawurlencode($monthStart).'&limit=5000');
$todayLeads=0;$hot=0;$sources=[];
foreach($leads as $l){
  if(substr((string)($l['created_at']??''),0,10)===$today)$todayLeads++;
  if((int)($l['lead_score']??0)>=80 || ($l['route']??'')==='mark_priority')$hot++;
  $src=$l['source']??($l['type']??'unknown');
  $sources[$src]=($sources[$src]??0)+1;
}

$appts=rows127('appointment_requests','select=*&limit=5000');
$apptConfirmed=0;$calendarCreated=0;
foreach($appts as $a){
  if(in_array(($a['status']??''),['confirmed','mark_confirmed'],true))$apptConfirmed++;
  if(($a['calendar_status']??'')==='created')$calendarCreated++;
}

$campaigns=rows127('first_campaign_plan','select=*&limit=1000');
$campaignDrafts=0;$campaignApproved=0;
foreach($campaigns as $c){ if(($c['status']??'')==='draft')$campaignDrafts++; if(!empty($c['approved_for_launch'])||($c['status']??'')==='approved')$campaignApproved++; }

$imports=rows127('compliant_lead_imports','select=*&limit=5000');
$callEligible=0;
foreach($imports as $i){ if(!empty($i['call_eligible']))$callEligible++; }

$builderForecasts=rows127('builder_forecasts','select=*&limit=5000');
$expectedBuilder=0;
foreach($builderForecasts as $b){ $expectedBuilder+=(float)($b['expected_referral_value']??0); }

$months=[];
for($i=11;$i>=0;$i--){
  $m=date('Y-m',strtotime("-$i months"));
  $months[$m]=['month'=>$m,'leads'=>0,'appointments'=>0,'campaigns'=>0,'imports'=>0];
}
foreach($leads as $l){$m=substr((string)($l['created_at']??''),0,7);if(isset($months[$m]))$months[$m]['leads']++;}
foreach($appts as $a){$m=substr((string)($a['created_at']??''),0,7);if(isset($months[$m]))$months[$m]['appointments']++;}
foreach($campaigns as $c){$m=substr((string)($c['created_at']??''),0,7);if(isset($months[$m]))$months[$m]['campaigns']++;}
foreach($imports as $i){$m=substr((string)($i['created_at']??''),0,7);if(isset($months[$m]))$months[$m]['imports']++;}

arsort($sources);
$sourceSummary=[];
foreach(array_slice($sources,0,12,true) as $k=>$v)$sourceSummary[]=['source'=>$k,'count'=>$v];

$activity=[
  'overnight_missions'=>count127('overnight_research_missions'),
  'discovery_sources'=>count127('discovery_intelligence_sources'),
  'campaign_assets'=>count127('campaign_launch_assets'),
  'launch_runs'=>count127('launch_control_runs'),
  'cron_audits'=>count127('cron_run_audit')
];

$alerts=[];
if($hot>0)$alerts[]=['type'=>'lead','message'=>$hot.' hot leads need attention.'];
if($apptConfirmed>$calendarCreated)$alerts[]=['type'=>'calendar','message'=>'Confirmed appointments not yet on calendar.'];
if($campaignDrafts>0)$alerts[]=['type'=>'campaign','message'=>$campaignDrafts.' campaign drafts waiting for review.'];
if($callEligible>0)$alerts[]=['type'=>'calls','message'=>$callEligible.' compliant imports are call eligible.'];

$payload=[[
  'snapshot_date'=>$today,
  'total_leads'=>count($leads),
  'hot_leads'=>$hot,
  'new_leads_today'=>$todayLeads,
  'appointments_total'=>count($appts),
  'appointments_confirmed'=>$apptConfirmed,
  'calendar_created'=>$calendarCreated,
  'campaign_drafts'=>$campaignDrafts,
  'approved_campaigns'=>$campaignApproved,
  'discovery_opportunities'=>count127('discovery_opportunity_queue'),
  'compliant_imports'=>count($imports),
  'call_eligible_imports'=>$callEligible,
  'builder_pipeline'=>count127('builder_pipeline'),
  'builder_forecasts'=>count($builderForecasts),
  'expected_builder_referral'=>$expectedBuilder,
  'action_queue_open'=>count127('mark_action_queue','select=id&status=in.(open,pending)&limit=5000'),
  'monthly_rollup'=>array_values($months),
  'source_summary'=>$sourceSummary,
  'jessica_activity'=>$activity,
  'priority_alerts'=>$alerts,
  'created_at'=>date('c')
]];

$res=sb127('POST','executive_daily_snapshots',$payload);

echo json_encode(['success'=>$res['ok'],'snapshot'=>$payload[0],'supabase_http'=>$res['http'],'body'=>$res['ok']?'':$res['body']],JSON_PRETTY_PRINT);
?>