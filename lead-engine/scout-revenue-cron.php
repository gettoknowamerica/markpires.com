<?php
/**
 * Goliath Omni OS v57.5
 * Scout Revenue Cron
 *
 * Purpose:
 * - Seeds known public FSBO opportunities into Scout's queue.
 * - Creates Jessica prep events for speed-to-lead.
 * - Designed to be called by Hostinger cron every 15 minutes.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$key = $_GET['key'] ?? '';
$expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if (!hash_equals($expected, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function sb57($method,$endpoint,$payload=null){
  $url = rtrim(SUPABASE_URL,'/') . '/rest/v1/' . ltrim($endpoint,'/');
  $headers = [
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',
    'Prefer: return=representation'
  ];
  $ch = curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30]);
  if($payload !== null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body = curl_exec($ch); $http = curl_getinfo($ch,CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
  $data = json_decode($body,true);
  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'error'=>$err,'data'=>$data,'raw'=>$body];
}
function exists_by_url($url){
  $ep='scout_research_queue?select=id&source_url=eq.'.rawurlencode($url).'&limit=1';
  $r=sb57('GET',$ep);
  return $r['ok'] && is_array($r['data']) && count($r['data'])>0;
}
function scout_event($title,$detail,$meta=[],$dept='Scout',$type='scout_speed_to_lead'){
  return sb57('POST','goliath_events',[[
    'department'=>$dept,
    'event_type'=>$type,
    'title'=>$title,
    'detail'=>$detail,
    'roi_estimate'=>12000,
    'confidence'=>92,
    'status'=>'queued',
    'phase'=>'speed_to_lead',
    'progress'=>25,
    'link_url'=>'/dashboard/scout-intelligence.php',
    'metadata'=>$meta
  ]]);
}

$seed=[
  ['address'=>'30 Blackman Road','town'=>'Ridgefield','url'=>'https://fsbo.com/search/30-blackman-road-ridgefield-ct-06877-1778121218281','type'=>'fsbo'],
  ['address'=>'113 Pepper Street','town'=>'Monroe','url'=>'https://fsbo.com/search/113-pepper-street-monroe-ct-06468-1777324880331','type'=>'fsbo'],
  ['address'=>'72 Nichols Avenue','town'=>'Fairfield','url'=>'https://fsbo.com/search/72-nichols-avenue-fairfield-ct-06825-1781099722615','type'=>'fsbo'],
  ['address'=>'FSBO Opportunity','town'=>'Fairfield County','url'=>'https://fsbo.com/search/cmp2rpa9y14kis601zgii914t','type'=>'fsbo'],
  ['address'=>'FSBO Opportunity','town'=>'Fairfield County','url'=>'https://fsbo.com/search/cmp5usv6200bys601dvdv79fw','type'=>'fsbo'],
  ['address'=>'FSBO Opportunity','town'=>'Fairfield County','url'=>'https://fsbo.com/search/cmq8fojte00bgs601rbrn9vzu','type'=>'fsbo'],
  ['address'=>'FSBO Opportunity','town'=>'Fairfield County','url'=>'https://fsbo.com/search/cmqprfzpk01o1s601962qz0yn','type'=>'fsbo']
];

$created=[]; $skipped=[]; $errors=[];
foreach($seed as $s){
  if(exists_by_url($s['url'])){ $skipped[]=['url'=>$s['url'],'reason'=>'already_seen']; continue; }
  $row=[
    'source'=>'fsbo_seed',
    'lead_type'=>'fsbo',
    'source_url'=>$s['url'],
    'owner_name'=>'Unknown Owner',
    'property_address'=>$s['address'],
    'town'=>$s['town'],
    'state'=>'CT',
    'status'=>'queued',
    'priority'=>98,
    'recommended_action'=>'Scout: open source URL, capture public contact details, confirm property facts, prepare opportunity file. Jessica: prepare call script, voicemail, email, SMS, and door-drop package.',
    'metadata'=>['source_url'=>$s['url'],'seeded_by'=>'scout-revenue-cron','created_at'=>gmdate('c')]
  ];
  $r=sb57('POST','scout_research_queue',[$row]);
  if($r['ok']){
    $item=is_array($r['data']) && isset($r['data'][0]) ? $r['data'][0] : $row;
    $created[]=$item;
    scout_event('Scout queued FSBO opportunity',trim($s['address'].' · '.$s['town']),['queue_id'=>$item['id']??null,'source_url'=>$s['url']]);
    scout_event('Jessica prep requested','Prepare outreach package for '.$s['address'].' '.$s['town'],['queue_id'=>$item['id']??null,'source_url'=>$s['url']],'Jessica','jessica_prep_from_scout');
  } else {
    $errors[]=['url'=>$s['url'],'response'=>$r];
  }
}

$count=sb57('GET','scout_research_queue?select=id,status,lead_type&order=created_at.desc&limit=250');
$summary=['queued'=>0,'running'=>0,'done'=>0,'fsbo'=>0,'expired'=>0];
if($count['ok'] && is_array($count['data'])){
  foreach($count['data'] as $r){
    $st=$r['status']??''; if(isset($summary[$st])) $summary[$st]++;
    $lt=$r['lead_type']??''; if(isset($summary[$lt])) $summary[$lt]++;
  }
}

echo json_encode([
  'success'=>empty($errors),
  'version'=>'57.5',
  'created_count'=>count($created),
  'skipped_count'=>count($skipped),
  'created'=>$created,
  'skipped'=>$skipped,
  'errors'=>$errors,
  'summary'=>$summary,
  'next'=>'Keep Scout local worker running. Use /dashboard/scout-expired-upload.php for CSV uploads.'
], JSON_PRETTY_PRINT);
