<?php
/**
 * V16.3 Blotato Direct Publisher
 * Upload: /public_html/lead-engine/run-blotato-publisher.php
 *
 * Safe by default:
 * - Requires approval_status=approved
 * - Requires distribution_status=scheduled or queued
 * - Requires blotato_api_enabled=true
 * - Honors blotato_dry_run=true
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Invalid key']);
    exit;
  }

  function sb163($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_CUSTOMREQUEST=>$method,
      CURLOPT_HTTPHEADER=>[
        'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
        'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
      ],
      CURLOPT_TIMEOUT=>45
    ]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
  }
  function rows163($t,$q){$r=sb163('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
  function settings163(){
    $rows=rows163('blotato_distribution_settings','select=setting_key,setting_value&limit=100');
    $out=[]; foreach($rows as $r){$out[$r['setting_key']]=$r['setting_value'];}
    return $out;
  }
  function log163($queueId,$platform,$payload,$response,$http,$status,$error=''){
    sb163('POST','blotato_publish_log',[[
      'queue_id'=>$queueId,
      'platform'=>$platform,
      'request_payload'=>$payload,
      'response_payload'=>$response,
      'http_status'=>$http,
      'publish_status'=>$status,
      'error_message'=>$error,
      'created_at'=>date('c')
    ]]);
  }

  $s=settings163();
  $enabled=($s['blotato_api_enabled']??'false')==='true';
  $dryRun=($s['blotato_dry_run']??'true')==='true';
  $endpoint=trim($s['blotato_api_endpoint']??'');
  $apiKey=getenv('BLOTATO_API_KEY') ?: trim($s['blotato_api_key']??'');
  $max=max(1,min(10,(int)($s['max_posts_per_run']??3)));

  $now=urlencode(date('c'));
  $queue=rows163('blotato_distribution_queue',
    'select=*&approval_status=eq.approved&distribution_status=in.(queued,scheduled)&order=distribution_score.desc,created_at.asc&limit='.$max
  );

  $results=[];

  foreach($queue as $item){
    if(!empty($item['scheduled_for']) && strtotime($item['scheduled_for']) > time()){
      $results[]=['queue_id'=>$item['id'],'status'=>'skipped_future_schedule'];
      continue;
    }

    $platforms=is_array($item['platforms']??null)?$item['platforms']:[];
    if(empty($platforms)) $platforms=['Instagram','Facebook'];

    $payload=[
      'title'=>$item['distribution_title']??'',
      'caption'=>$item['caption']??'',
      'hashtags'=>$item['hashtags']??'',
      'cta'=>$item['cta']??'',
      'media_url'=>$item['media_url']??'',
      'landing_page_url'=>$item['landing_page_url']??'',
      'platforms'=>$platforms,
      'source'=>'Jessica V16.3',
      'queue_id'=>$item['id']
    ];

    if(!$enabled || $dryRun || !$endpoint || !$apiKey){
      foreach($platforms as $p){
        log163($item['id'],$p,$payload,['dry_run'=>true,'reason'=>'not enabled or missing endpoint/key'],200,'dry_run');
      }
      sb163('PATCH','blotato_distribution_queue?id=eq.'.rawurlencode($item['id']),[
        'distribution_status'=>'queued',
        'blotato_response'=>['dry_run'=>true,'message'=>'Payload prepared. API not live yet.'],
        'updated_at'=>date('c')
      ]);
      $results[]=['queue_id'=>$item['id'],'status'=>'dry_run','platforms'=>$platforms];
      continue;
    }

    $ch=curl_init($endpoint);
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_POST=>true,
      CURLOPT_HTTPHEADER=>[
        'Content-Type: application/json',
        'Authorization: Bearer '.$apiKey
      ],
      CURLOPT_POSTFIELDS=>json_encode($payload),
      CURLOPT_TIMEOUT=>60
    ]);
    $body=curl_exec($ch);
    $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $err=curl_error($ch);
    curl_close($ch);
    $resp=json_decode($body,true);
    if(!is_array($resp)) $resp=['raw'=>$body];

    $ok=$http>=200 && $http<300 && !$err;
    foreach($platforms as $p){
      log163($item['id'],$p,$payload,$resp,$http,$ok?'posted':'failed',$err);
    }

    sb163('PATCH','blotato_distribution_queue?id=eq.'.rawurlencode($item['id']),[
      'distribution_status'=>$ok?'posted':'failed',
      'blotato_response'=>$resp,
      'blotato_post_id'=>(string)($resp['id']??$resp['post_id']??''),
      'posted_at'=>$ok?date('c'):null,
      'updated_at'=>date('c')
    ]);

    $results[]=['queue_id'=>$item['id'],'status'=>$ok?'posted':'failed','http'=>$http,'error'=>$err,'response'=>$resp];
  }

  echo json_encode([
    'success'=>true,
    'enabled'=>$enabled,
    'dry_run'=>$dryRun,
    'endpoint_configured'=>!empty($endpoint),
    'api_key_configured'=>!empty($apiKey),
    'processed'=>count($results),
    'results'=>$results
  ],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>