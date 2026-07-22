<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$data['key'] ?? '';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}
$id=$data['id'] ?? '';
if(!$id){echo json_encode(['success'=>false,'error'=>'Missing id']);exit;}

function sb($method,$table,$payload=null,$query=''){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table.$query);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return [$http,json_decode($body,true) ?: $body];
}

$patch=[
  'status'=>$data['status'] ?? 'done',
  'confidence'=>(int)($data['confidence'] ?? 0),
  'found_phone'=>$data['found_phone'] ?? null,
  'found_email'=>$data['found_email'] ?? null,
  'public_record_url'=>$data['public_record_url'] ?? null,
  'gis_url'=>$data['gis_url'] ?? null,
  'research_notes'=>$data['research_notes'] ?? '',
  'recommended_action'=>$data['recommended_action'] ?? '',
  'metadata'=>$data['metadata'] ?? new stdClass(),
  'updated_at'=>date('c')
];
[$http,$res]=sb('PATCH','scout_research_queue',$patch,'?id=eq.'.rawurlencode($id));

if(!empty($data['results']) && is_array($data['results'])){
  $rows=[];
  foreach($data['results'] as $r){
    $rows[]=[
      'queue_id'=>$id,
      'mission_id'=>$data['mission_id'] ?? null,
      'source_name'=>$r['source_name'] ?? '',
      'source_url'=>$r['source_url'] ?? '',
      'result_type'=>$r['result_type'] ?? '',
      'value'=>$r['value'] ?? '',
      'confidence'=>(int)($r['confidence'] ?? 0),
      'notes'=>$r['notes'] ?? '',
      'metadata'=>$r['metadata'] ?? new stdClass()
    ];
  }
  if($rows) sb('POST','scout_research_results',$rows);
}

$event=[
  'department'=>'Scout',
  'event_type'=>'scout_research_complete',
  'title'=>!empty($patch['found_phone'])?'Scout found a phone number':'Scout completed research',
  'detail'=>trim(($patch['found_phone']?:'No phone found yet').' · '.$patch['research_notes']),
  'roi_estimate'=>12000,
  'confidence'=>$patch['confidence'],
  'status'=>$patch['status'],
  'phase'=>'research_complete',
  'progress'=>100,
  'link_url'=>'/dashboard/scout-intelligence.php?id='.rawurlencode($id),
  'metadata'=>['queue_id'=>$id,'found_phone'=>$patch['found_phone'],'found_email'=>$patch['found_email']]
];
sb('POST','goliath_events',[$event]);

echo json_encode(['success'=>$http>=200&&$http<300,'updated'=>$res],JSON_PRETTY_PRINT);
?>