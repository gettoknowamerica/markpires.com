<?php
/**
 * V12.8 ROI Attribution Snapshot Builder
 * Upload: /public_html/lead-engine/build-roi-attribution.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb128($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function rows128($table,$query='select=*&limit=5000'){
  $r=sb128('GET',$table.'?'.$query);
  return $r['ok']?$r['data']:[];
}

$monthStart=date('Y-m-01');
$spend=rows128('marketing_spend_log','select=*&spend_date=gte.'.rawurlencode($monthStart).'&limit=5000');
$leads=rows128('leads','select=*&created_at=gte.'.rawurlencode($monthStart).'&limit=5000');
$appts=rows128('appointment_requests','select=*&created_at=gte.'.rawurlencode($monthStart).'&limit=5000');

$sources=[];$campaigns=[];
$totalSpend=0;
foreach($spend as $s){
  $src=$s['platform']?:'Unknown';
  $camp=$s['campaign_name']?:'Unassigned';
  if(!isset($sources[$src]))$sources[$src]=['source'=>$src,'spend'=>0,'leads'=>0,'appointments'=>0,'clicks'=>0,'impressions'=>0,'cpl'=>0,'cpa'=>0];
  if(!isset($campaigns[$camp]))$campaigns[$camp]=['campaign'=>$camp,'source'=>$src,'spend'=>0,'leads'=>0,'appointments'=>0,'cpl'=>0,'cpa'=>0];
  $sources[$src]['spend']+=(float)($s['spend']??0);
  $sources[$src]['clicks']+=(int)($s['clicks']??0);
  $sources[$src]['impressions']+=(int)($s['impressions']??0);
  $campaigns[$camp]['spend']+=(float)($s['spend']??0);
  $totalSpend+=(float)($s['spend']??0);
}

foreach($leads as $l){
  $src=$l['utm_source'] ?: ($l['source'] ?: 'Unknown');
  $camp=$l['utm_campaign'] ?: ($l['campaign_name'] ?: 'Unassigned');
  if(!isset($sources[$src]))$sources[$src]=['source'=>$src,'spend'=>0,'leads'=>0,'appointments'=>0,'clicks'=>0,'impressions'=>0,'cpl'=>0,'cpa'=>0];
  if(!isset($campaigns[$camp]))$campaigns[$camp]=['campaign'=>$camp,'source'=>$src,'spend'=>0,'leads'=>0,'appointments'=>0,'cpl'=>0,'cpa'=>0];
  $sources[$src]['leads']++;
  $campaigns[$camp]['leads']++;
}

foreach($appts as $a){
  $src=$a['utm_source'] ?: ($a['source'] ?: 'Unknown');
  $camp=$a['utm_campaign'] ?: ($a['campaign_name'] ?: 'Unassigned');
  if(!isset($sources[$src]))$sources[$src]=['source'=>$src,'spend'=>0,'leads'=>0,'appointments'=>0,'clicks'=>0,'impressions'=>0,'cpl'=>0,'cpa'=>0];
  if(!isset($campaigns[$camp]))$campaigns[$camp]=['campaign'=>$camp,'source'=>$src,'spend'=>0,'leads'=>0,'appointments'=>0,'cpl'=>0,'cpa'=>0];
  if(in_array(($a['status']??''),['confirmed','mark_confirmed','offered'],true)){
    $sources[$src]['appointments']++;
    $campaigns[$camp]['appointments']++;
  }
}

foreach($sources as &$x){
  $x['cpl']=$x['leads']>0?round($x['spend']/$x['leads'],2):0;
  $x['cpa']=$x['appointments']>0?round($x['spend']/$x['appointments'],2):0;
}
unset($x);
foreach($campaigns as &$x){
  $x['cpl']=$x['leads']>0?round($x['spend']/$x['leads'],2):0;
  $x['cpa']=$x['appointments']>0?round($x['spend']/$x['appointments'],2):0;
}
unset($x);

$sourceList=array_values($sources);
usort($sourceList,function($a,$b){
  $as=($a['leads']*10)+($a['appointments']*30)-($a['cpl']);
  $bs=($b['leads']*10)+($b['appointments']*30)-($b['cpl']);
  return $bs<=>$as;
});
$campaignList=array_values($campaigns);
usort($campaignList,function($a,$b){
  $as=($a['leads']*10)+($a['appointments']*30)-($a['cpl']);
  $bs=($b['leads']*10)+($b['appointments']*30)-($b['cpl']);
  return $bs<=>$as;
});

$totalLeads=count($leads);
$totalAppts=0;
foreach($appts as $a){ if(in_array(($a['status']??''),['confirmed','mark_confirmed','offered'],true))$totalAppts++; }

$recs=[];
if(!empty($sourceList[0]))$recs[]=['type'=>'scale','message'=>'Best source so far: '.$sourceList[0]['source'].'. Consider shifting budget here if lead quality is good.'];
if(!empty($sourceList[count($sourceList)-1]) && count($sourceList)>1)$recs[]=['type'=>'watch','message'=>'Weakest source so far: '.$sourceList[count($sourceList)-1]['source'].'. Watch CPL/appointments before increasing spend.'];
if($totalLeads===0)$recs[]=['type'=>'launch','message'=>'No tracked leads yet. Launch first home value or relocation campaign and verify UTM capture.'];
if($totalSpend>0 && $totalAppts===0)$recs[]=['type'=>'conversion','message'=>'Spend exists but no appointments yet. Improve landing page CTA or Jessica response speed.'];

$payload=[[
  'snapshot_date'=>date('Y-m-d'),
  'total_spend'=>$totalSpend,
  'total_leads'=>$totalLeads,
  'total_appointments'=>$totalAppts,
  'cost_per_lead'=>$totalLeads>0?round($totalSpend/$totalLeads,2):0,
  'cost_per_appointment'=>$totalAppts>0?round($totalSpend/$totalAppts,2):0,
  'best_source'=>$sourceList[0]['source']??'',
  'worst_source'=>$sourceList[count($sourceList)-1]['source']??'',
  'source_rollup'=>$sourceList,
  'campaign_rollup'=>$campaignList,
  'recommendations'=>$recs,
  'created_at'=>date('c')
]];
$res=sb128('POST','roi_attribution_snapshots',$payload);
echo json_encode(['success'=>$res['ok'],'snapshot'=>$payload[0],'supabase_http'=>$res['http'],'body'=>$res['ok']?'':$res['body']],JSON_PRETTY_PRINT);
?>