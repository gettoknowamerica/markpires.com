<?php
/**
 * V12.3.1 Launch Control Diagnostic Patch
 * Upload over: /public_html/lead-engine/run-launch-control.php
 *
 * IMPORTANT:
 * Use AFTER_HOURS_CRON_KEY from config.php, not GOOGLE_CALENDAR_SECRET.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';

  if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY) {
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'error' => 'AFTER_HOURS_CRON_KEY missing in config.php',
      'hint' => 'Add or confirm define("AFTER_HOURS_CRON_KEY", "...") in /lead-engine/config.php'
    ], JSON_PRETTY_PRINT);
    exit;
  }

  if (!hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403);
    echo json_encode([
      'success' => false,
      'error' => 'Invalid key',
      'hint' => 'Use the cron/master key from AFTER_HOURS_CRON_KEY, not GOOGLE_CALENDAR_SECRET.',
      'received_key_length' => strlen($key),
      'expected_key_length' => strlen(AFTER_HOURS_CRON_KEY)
    ], JSON_PRETTY_PRINT);
    exit;
  }

  function sb1231($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json'
    ];
    $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    $d=json_decode($b,true);
    return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }

  function call_local1231($file,$params=[]){
    $host=$_SERVER['HTTP_HOST']??'markpires.com';
    $params=array_merge(['key'=>AFTER_HOURS_CRON_KEY],$params);
    $url='https://'.$host.'/lead-engine/'.$file.'?'.http_build_query($params);

    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_TIMEOUT=>60,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3]);
    $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    $d=json_decode($body,true);

    return[
      'file'=>$file,
      'url'=>$url,
      'ok'=>$http>=200&&$http<300&&is_array($d)&&($d['success']??true)!==false,
      'http'=>$http,
      'error'=>$err,
      'data'=>is_array($d)?$d:null,
      'raw'=>is_array($d)?null:substr((string)$body,0,500)
    ];
  }

  $steps=[
    ['name'=>'Overnight Research','file'=>'build-overnight-research.php'],
    ['name'=>'Discovery Intelligence','file'=>'build-discovery-intelligence.php'],
    ['name'=>'Compliant Import Queue','file'=>'build-compliant-import-queue.php'],
    ['name'=>'Builder Forecasts','file'=>'build-builder-forecasts.php'],
    ['name'=>'Builder Briefing','file'=>'build-builder-briefing.php'],
    ['name'=>'Appointment Automation','file'=>'appointment-automation.php'],
    ['name'=>'First Ad Campaigns','file'=>'build-first-ad-campaigns.php']
  ];

  $results=[];$ok=0;$fail=0;
  foreach($steps as $s){
    $r=call_local1231($s['file']);
    $r['name']=$s['name'];
    $results[]=$r;
    if($r['ok'])$ok++; else $fail++;
  }

  $top=sb1231('GET','discovery_opportunity_queue?select=*&order=priority_score.desc&limit=20')['data'];
  $campaigns=[];

  foreach($top as $o){
    if(!is_array($o)) continue;
    $type=$o['opportunity_type']??'seller';
    $town=$o['town']??'Fairfield County';
    $campaignName=$town.' Opportunity Campaign';
    $headline='Fairfield County Opportunity';
    $body='Jessica is organizing new opportunities for Mark Pires.';
    $cta='Learn More';

    if($type==='sellers'){
      $campaignName=$town.' Home Value Campaign';
      $headline='What Is Your '.$town.' Home Really Worth?';
      $body='Online estimates miss the local details. Get a smarter Fairfield County home value review from Mark Pires and Jessica.';
      $cta='Get My Home Value';
    } elseif($type==='buyers'){
      $campaignName='NYC to CT Buyer Campaign';
      $headline='Leaving NYC For More Space?';
      $body='Discover which Fairfield County towns fit your commute, lifestyle, schools, and budget before you buy.';
      $cta='Get My Town Match';
    } elseif($type==='relocation'){
      $campaignName='Westchester to CT Relocation Campaign';
      $headline='Thinking About Moving From NY To CT?';
      $body='Compare lifestyle, space, commute, and local home options before making the move.';
      $cta='Get The CT Guide';
    } else {
      $campaignName=$town.' Builder Opportunity Campaign';
      $headline='Builder Opportunities In '.$town;
      $body='Land, teardown, renovation, and acquisition signals for builders and investors watching Fairfield County.';
      $cta='Join Watchlist';
    }

    $payload=[[
      'campaign_name'=>$campaignName,
      'audience'=>$o['audience']??'',
      'market'=>$o['market']??'',
      'town'=>$town,
      'objective'=>'lead_generation',
      'landing_page'=>$o['landing_page']??'/home-valuation',
      'primary_offer'=>$o['offer']??'',
      'ad_headline'=>$headline,
      'ad_body'=>$body,
      'cta'=>$cta,
      'status'=>'draft',
      'priority_score'=>(int)($o['priority_score']??0),
      'raw_payload'=>$o,
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ]];

    $cr=sb1231('POST','first_campaign_plan',$payload);
    if($cr['ok']) $campaigns[]=$campaignName;
  }

  sb1231('POST','launch_control_runs',[[
    'run_name'=>'V12.3.1 Launch Control',
    'ok'=>$fail===0,
    'steps_attempted'=>count($steps),
    'steps_ok'=>$ok,
    'steps_failed'=>$fail,
    'results'=>['steps'=>$results,'campaigns_created'=>$campaigns],
    'created_at'=>date('c')
  ]]);

  echo json_encode([
    'success'=>$fail===0,
    'steps_ok'=>$ok,
    'steps_failed'=>$fail,
    'campaigns_created'=>count($campaigns),
    'results'=>$results
  ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'success'=>false,
    'error'=>'PHP exception',
    'message'=>$e->getMessage(),
    'file'=>$e->getFile(),
    'line'=>$e->getLine()
  ], JSON_PRETTY_PRINT);
}
?>