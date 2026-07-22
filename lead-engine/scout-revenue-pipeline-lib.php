<?php
/**
 * Goliath Omni OS v57.4
 * Scout Revenue Pipeline helpers
 */
function srp_cfg($name, $fallback=''){
  if (defined($name)) return constant($name);
  $v = getenv($name);
  return $v !== false ? $v : $fallback;
}
function srp_key_ok($incoming){
  $expected = srp_cfg('AFTER_HOURS_CRON_KEY','timetomakethedonuts');
  return $expected && hash_equals((string)$expected, (string)$incoming);
}
function srp_json($payload, $code=200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  exit;
}
function srp_sb($method,$endpoint,$payload=null,$prefer='return=representation'){
  $url = rtrim(SUPABASE_URL,'/') . '/rest/v1/' . ltrim($endpoint,'/');
  $headers = [
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',
    'Prefer: '.$prefer
  ];
  $ch = curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>$headers,
    CURLOPT_TIMEOUT=>45
  ]);
  if($payload !== null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body = curl_exec($ch);
  $http = curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $data = json_decode($body,true);
  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'error'=>$err,'data'=>$data,'raw'=>$body];
}
function srp_slug_header($s){
  $s = strtolower(trim((string)$s));
  $s = preg_replace('/[^a-z0-9]+/','_',$s);
  return trim($s,'_');
}
function srp_pick($row,$names,$default=''){
  foreach($names as $n){
    $k = srp_slug_header($n);
    if(isset($row[$k]) && trim((string)$row[$k]) !== '') return trim((string)$row[$k]);
  }
  return $default;
}
function srp_digits($s){ return preg_replace('/\D+/','',(string)$s); }
function srp_num($s){
  $s = preg_replace('/[^0-9.]/','',(string)$s);
  return $s === '' ? null : (float)$s;
}
function srp_date($s){
  $s = trim((string)$s);
  if(!$s) return null;
  $t = strtotime($s);
  return $t ? gmdate('Y-m-d',$t) : null;
}
function srp_score($type,$price,$phone,$email,$town,$dom){
  $score = 50;
  if($type === 'expired_listing') $score += 25;
  if($type === 'fsbo') $score += 20;
  if($phone) $score += 12;
  if($email) $score += 6;
  if($price && $price >= 600000) $score += 8;
  if($dom && (int)$dom >= 60) $score += 5;
  $lower = strtolower($town);
  if(in_array($lower,['greenwich','darien','new canaan','westport','wilton','fairfield','ridgefield'])) $score += 5;
  return max(0,min(100,$score));
}
function srp_source_hash($source,$address,$town,$mls,$url=''){
  return hash('sha256', strtolower(trim($source.'|'.$address.'|'.$town.'|'.$mls.'|'.$url)));
}
function srp_jessica_prompt($o){
  $addr = trim(($o['property_address'] ?? '').' '.($o['town'] ?? ''));
  return "Jessica, prepare a speed-to-lead outreach package for this ".($o['opportunity_type'] ?? 'seller')." opportunity.\n\n".
    "Name/Owner: ".($o['owner_name'] ?: 'Unknown')."\n".
    "Property: ".$addr."\n".
    "Phone: ".($o['phone'] ?: 'Not found yet')."\n".
    "Email: ".($o['email'] ?: 'Not found yet')."\n".
    "Source: ".($o['source'] ?: '')."\n".
    "Score: ".($o['lead_score'] ?: '')."\n\n".
    "Create: 8 AM call script, voicemail, SMS, email, door-knock opener, leave-behind checklist, and follow-up schedule. Tone: direct, human, local, no spam.";
}
function srp_scripts($o){
  $addr = trim(($o['property_address'] ?? '').' '.($o['town'] ?? ''));
  $name = $o['owner_name'] ?: 'there';
  $type = $o['opportunity_type'] ?? 'seller_opportunity';
  $source = $o['source'] ?? 'public source';
  return [
    'call_script'=>"Hi {$name}, this is Mark Pires. I noticed {$addr} came up as a {$type} opportunity from {$source}. I know timing matters, so I’ll be brief. Are you still considering selling, or did you decide to pause for now?",
    'voicemail_script'=>"Hi {$name}, this is Mark Pires, local Fairfield County Realtor. I saw {$addr} come up in my market research and wanted to offer a quick second opinion if selling is still on your radar. You can call or text me at 203-247-2655.",
    'sms_body'=>"Hi {$name}, Mark Pires here. I saw {$addr} come up in my local market research. If selling is still on your radar, I can offer a quick second opinion. 203-247-2655",
    'email_subject'=>"Quick second opinion on {$addr}",
    'email_body'=>"Hi {$name},\n\nThis is Mark Pires. I saw {$addr} come up in my local Fairfield County research and wanted to reach out personally.\n\nIf you’re still considering selling, I’d be happy to give you a clear second opinion on pricing, presentation, and what I’d do differently to attract the right buyer.\n\nNo pressure at all — just happy to help if useful.\n\nMark Pires\n203-247-2655\nmark@markpires.com",
    'door_knock_script'=>"Hi, I’m Mark Pires. I work locally here in Fairfield County. I saw this property come up in my market research and wanted to drop off a quick seller strategy note. If selling is still something you're considering, I can give you a fresh read on what buyers are responding to right now."
  ];
}
?>