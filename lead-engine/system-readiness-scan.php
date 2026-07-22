<?php
/**
 * V12.6 System Readiness Scanner
 * Upload: /public_html/lead-engine/system-readiness-scan.php
 *
 * Run:
 * /lead-engine/system-readiness-scan.php?key=YOUR_KEY
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403);
  echo json_encode([
    'success'=>false,
    'error'=>'Invalid key',
    'hint'=>'Use the exact AFTER_HOURS_CRON_KEY value from config.php'
  ],JSON_PRETTY_PRINT);
  exit;
}

function sb126($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=[
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json'
  ];
  $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>35]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function add_check126(&$checks,$name,$group,$status,$score,$detail,$raw=[]){
  $checks[]=[
    'check_name'=>$name,
    'check_group'=>$group,
    'status'=>$status,
    'score'=>$score,
    'detail'=>$detail,
    'raw_payload'=>$raw,
    'created_at'=>date('c')
  ];
}
function table_count126($table){
  $r=sb126('GET',$table.'?select=id&limit=1');
  return $r;
}

$checks=[];

/* Config checks */
add_check126($checks,'Supabase URL','config',defined('SUPABASE_URL')&&SUPABASE_URL?'ok':'error',defined('SUPABASE_URL')&&SUPABASE_URL?100:0,defined('SUPABASE_URL')&&SUPABASE_URL?'Supabase URL configured.':'Missing SUPABASE_URL.');
add_check126($checks,'Supabase Service Role','config',defined('SUPABASE_SERVICE_ROLE_KEY')&&SUPABASE_SERVICE_ROLE_KEY?'ok':'error',defined('SUPABASE_SERVICE_ROLE_KEY')&&SUPABASE_SERVICE_ROLE_KEY?100:0,defined('SUPABASE_SERVICE_ROLE_KEY')&&SUPABASE_SERVICE_ROLE_KEY?'Supabase service key configured.':'Missing SUPABASE_SERVICE_ROLE_KEY.');
add_check126($checks,'Cron Key','config',defined('AFTER_HOURS_CRON_KEY')&&strlen(AFTER_HOURS_CRON_KEY)>=10?'ok':'error',defined('AFTER_HOURS_CRON_KEY')?100:0,'Cron key length: '.(defined('AFTER_HOURS_CRON_KEY')?strlen(AFTER_HOURS_CRON_KEY):0));
add_check126($checks,'Google Calendar URL','config',defined('GOOGLE_CALENDAR_WEBHOOK_URL')&&GOOGLE_CALENDAR_WEBHOOK_URL?'ok':'warning',defined('GOOGLE_CALENDAR_WEBHOOK_URL')&&GOOGLE_CALENDAR_WEBHOOK_URL?100:50,defined('GOOGLE_CALENDAR_WEBHOOK_URL')?'Calendar URL present.':'Calendar URL missing.');
add_check126($checks,'Google Calendar Secret','config',defined('GOOGLE_CALENDAR_SECRET')&&GOOGLE_CALENDAR_SECRET?'ok':'warning',defined('GOOGLE_CALENDAR_SECRET')&&GOOGLE_CALENDAR_SECRET?100:50,defined('GOOGLE_CALENDAR_SECRET')?'Calendar secret present.':'Calendar secret missing.');
add_check126($checks,'Resend Email','config',defined('RESEND_API_KEY')&&RESEND_API_KEY?'ok':'warning',defined('RESEND_API_KEY')&&RESEND_API_KEY?100:50,defined('RESEND_API_KEY')?'Resend configured.':'Resend missing.');
add_check126($checks,'Retell','config',defined('RETELL_API_KEY')&&RETELL_API_KEY?'ok':'warning',defined('RETELL_API_KEY')&&RETELL_API_KEY?100:50,defined('RETELL_API_KEY')?'Retell key present.':'Retell key missing or not named RETELL_API_KEY.');

/* Table checks */
$tables=[
  'leads','mark_action_queue','appointment_requests','google_calendar_sync_logs',
  'overnight_research_missions','discovery_intelligence_sources','discovery_opportunity_queue',
  'compliant_lead_imports','first_campaign_plan','campaign_launch_assets',
  'builder_pipeline','builder_forecasts','builder_daily_briefings'
];
foreach($tables as $t){
  $r=table_count126($t);
  add_check126($checks,$t,'tables',$r['ok']?'ok':'error',$r['ok']?100:0,$r['ok']?'Table reachable.':'Table not reachable: '.$r['body'],['http'=>$r['http']]);
}

/* File checks */
$files=[
  'cron-master.php','build-overnight-research.php','build-discovery-intelligence.php',
  'build-compliant-import-queue.php','run-launch-control.php','build-first-ad-campaigns.php',
  'appointment-automation.php','test-google-calendar.php'
];
foreach($files as $f){
  $exists=file_exists(__DIR__.'/'.$f);
  add_check126($checks,$f,'files',$exists?'ok':'error',$exists?100:0,$exists?'File exists.':'File missing.');
}

/* Data checks */
$disc=sb126('GET','discovery_opportunity_queue?select=id&limit=5');
$camp=sb126('GET','first_campaign_plan?select=id&limit=5');
$imports=sb126('GET','compliant_lead_imports?select=id&limit=5');
add_check126($checks,'Discovery Queue Data','data',!empty($disc['data'])?'ok':'warning',!empty($disc['data'])?100:60,!empty($disc['data'])?'Discovery queue has records.':'No discovery records yet. Run V12 Discovery.');
add_check126($checks,'Campaign Draft Data','data',!empty($camp['data'])?'ok':'warning',!empty($camp['data'])?100:60,!empty($camp['data'])?'Campaign drafts exist.':'No campaign drafts yet. Run Launch Control/First Ad Campaigns.');
add_check126($checks,'Compliant Import Data','data',!empty($imports['data'])?'ok':'warning',!empty($imports['data'])?100:60,!empty($imports['data'])?'Compliant import queue has records.':'No compliant imports yet.');

sb126('POST','system_readiness_checks',$checks);

$ok=0;$warn=0;$err=0;$totalScore=0;
foreach($checks as $c){
  if($c['status']==='ok')$ok++;
  elseif($c['status']==='warning')$warn++;
  elseif($c['status']==='error')$err++;
  $totalScore+=(int)$c['score'];
}
$overall=count($checks)?round($totalScore/count($checks)):0;

echo json_encode([
  'success'=>$err===0,
  'overall_score'=>$overall,
  'ok'=>$ok,
  'warnings'=>$warn,
  'errors'=>$err,
  'checks'=>$checks
],JSON_PRETTY_PRINT);
?>