<?php
if (!function_exists('g53_req')) {
  function g53_req($method, $endpoint, $body=null){
    $url = rtrim(SUPABASE_URL,'/') . '/rest/v1/' . ltrim($endpoint,'/');
    $headers = [
      'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_TIMEOUT => 35
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $data = json_decode($raw, true);
    return ['ok'=>($http>=200 && $http<300), 'http'=>$http, 'data'=>is_array($data)?$data:$raw, 'raw'=>$raw, 'error'=>$err];
  }
}
function g53_key_ok(){
  $expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
  $got = $_GET['key'] ?? $_POST['key'] ?? '';
  return hash_equals((string)$expected, (string)$got);
}
function g53_first_json($text){
  $text = trim((string)$text);
  if ($text === '') return null;
  $direct = json_decode($text, true);
  if (is_array($direct)) return $direct;
  if (preg_match('/```json\s*(.*?)```/is', $text, $m)) {
    $j = json_decode(trim($m[1]), true);
    if (is_array($j)) return $j;
  }
  $start = strpos($text, '{'); $end = strrpos($text, '}');
  if ($start !== false && $end !== false && $end > $start) {
    $j = json_decode(substr($text, $start, $end-$start+1), true);
    if (is_array($j)) return $j;
  }
  return null;
}
function g53_event($agent,$title,$detail,$link='',$metadata=[],$confidence=90,$roi=0){
  return g53_req('POST','goliath_events', [[
    'department'=>$agent,
    'event_type'=>'deliverable_ready',
    'title'=>$title,
    'detail'=>mb_substr((string)$detail,0,700),
    'status'=>'active',
    'confidence'=>$confidence,
    'roi_estimate'=>$roi,
    'link_url'=>$link,
    'metadata'=>$metadata
  ]]);
}
function g53_create_deliverable($agent,$type,$title,$contentText,$contentJson=[],$metadata=[],$priority='normal',$sourceTaskId=null,$sourceJobId=null){
  $payload = [
    'agent'=>$agent ?: 'Goliath',
    'deliverable_type'=>$type ?: 'work_output',
    'title'=>$title ?: (($agent ?: 'Goliath') . ' Deliverable'),
    'status'=>'ready',
    'priority'=>$priority ?: 'normal',
    'source_task_id'=>$sourceTaskId,
    'source_job_id'=>$sourceJobId,
    'content_text'=>mb_substr((string)$contentText,0,120000),
    'content_json'=>is_array($contentJson) ? $contentJson : new stdClass(),
    'action_url'=>'/dashboard/goliath-deliverables.php?agent=' . rawurlencode($agent ?: 'Goliath'),
    'metadata'=>is_array($metadata) ? $metadata : new stdClass(),
    'updated_at'=>gmdate('c')
  ];
  if ($sourceTaskId) return g53_req('POST','goliath_deliverables?on_conflict=source_task_id',$payload);
  return g53_req('POST','goliath_deliverables',$payload);
}
function g53_clean_phone($v){ return trim((string)$v); }
function g53_valid_real_lead($lead){
  if (!is_array($lead)) return false;
  $blob = strtolower(json_encode($lead));
  foreach(['555-1234','203-555','example.com','test@test','john smith','jane doe','placeholder','sample lead'] as $bad){
    if (strpos($blob,$bad)!==false) return false;
  }
  $hasContact = !empty($lead['phone']) || !empty($lead['email']);
  $hasAddress = !empty($lead['address']) || !empty($lead['property_address']);
  return $hasContact || $hasAddress;
}
function g53_format_lead_batch($leads){
  if (!$leads) return "No usable lead records were found yet.";
  $out = "SCOUT LEAD DELIVERABLE\n\n";
  foreach($leads as $i=>$l){
    $name = $l['name'] ?? $l['lead_name'] ?? 'Unknown';
    $phone = $l['phone'] ?? '';
    $email = $l['email'] ?? '';
    $addr = $l['address'] ?? ($l['property_address'] ?? '');
    $town = $l['town'] ?? '';
    $score = $l['lead_score'] ?? ($l['confidence'] ?? '');
    $source = $l['source'] ?? '';
    $reason = $l['reason'] ?? ($l['message'] ?? '');
    $out .= ($i+1).". {$name}\n";
    if($phone) $out .= "   Phone: {$phone}\n";
    if($email) $out .= "   Email: {$email}\n";
    if($addr || $town) $out .= "   Property: ".trim($addr.' '.$town)."\n";
    if($score !== '') $out .= "   Score/Confidence: {$score}\n";
    if($source) $out .= "   Source: {$source}\n";
    if($reason) $out .= "   Why it matters: {$reason}\n";
    $out .= "   Next: Call / Email / Research\n\n";
  }
  return $out;
}
