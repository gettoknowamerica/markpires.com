<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$data['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb($method,$table,$payload=null,$query=''){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table.$query);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT=>25
  ]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return [$http,json_decode($body,true) ?: $body];
}

$row=[
  'source'=>$data['source'] ?? 'manual',
  'lead_id'=>$data['lead_id'] ?? null,
  'mission_id'=>$data['mission_id'] ?? null,
  'owner_name'=>$data['owner_name'] ?? $data['name'] ?? '',
  'property_address'=>$data['property_address'] ?? $data['address'] ?? '',
  'town'=>$data['town'] ?? '',
  'state'=>$data['state'] ?? 'CT',
  'price'=>$data['price'] ?? null,
  'property_type'=>$data['property_type'] ?? '',
  'status'=>'queued',
  'priority'=>(int)($data['priority'] ?? 95),
  'recommended_action'=>'Scout should verify owner identity, public property record, GIS/tax link, and available phone/email from permitted public sources.',
  'metadata'=>$data['metadata'] ?? new stdClass()
];

[$http,$res]=sb('POST','scout_research_queue',[$row]);
$created=is_array($res)&&isset($res[0])?$res[0]:null;

$event=[
  'department'=>'Scout',
  'event_type'=>'scout_research_queued',
  'title'=>'Scout received research assignment',
  'detail'=>trim(($row['owner_name']?:'Unknown owner').' · '.$row['property_address'].' · '.$row['town']),
  'roi_estimate'=>9000,
  'confidence'=>90,
  'status'=>'queued',
  'phase'=>'research',
  'progress'=>10,
  'link_url'=>'/dashboard/scout-intelligence.php',
  'metadata'=>['queue_id'=>$created['id']??null,'assignment'=>$row]
];
sb('POST','goliath_events',[$event]);

echo json_encode(['success'=>$http>=200&&$http<300,'assignment'=>$created ?: $res],JSON_PRETTY_PRINT);
?>