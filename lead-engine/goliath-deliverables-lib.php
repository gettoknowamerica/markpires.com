<?php
if (!function_exists('gd_req')) {
  function gd_req($method, $endpoint, $body=null){
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

function gd_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function gd_type_for_agent($agent, $metadata=[]){
  $job = is_array($metadata) ? ($metadata['job_type'] ?? '') : '';
  if ($job === 'lead_discovery' || $agent === 'Scout') return 'lead_batch';
  if ($job === 'communications' || $agent === 'Jessica') return 'communication';
  if ($job === 'lead_scoring' || $agent === 'Einstein') return 'analysis';
  if ($job === 'roi_prioritization' || $agent === 'Rockefeller') return 'priority_brief';
  if ($job === 'archive_growth' || $agent === 'Columbo') return 'archive_gold';
  if ($job === 'video_production' || $agent === 'Scorsese') return 'video_package';
  if ($job === 'music_recovery' || $agent === 'Mozart') return 'song_package';
  if ($job === 'content_writing' || $agent === 'Shakespeare') return 'content_draft';
  if ($job === 'opportunity_mining' || $agent === 'Prospector') return 'opportunity_batch';
  if ($job === 'business_expansion' || $agent === 'Pandora') return 'expansion_opportunity';
  return 'work_output';
}

function gd_make_title($agent, $type, $output=''){
  $map = [
    'lead_batch' => 'Scout Lead Output',
    'communication' => 'Jessica Communication Output',
    'analysis' => 'Einstein Analysis',
    'priority_brief' => 'Rockefeller Priority Brief',
    'archive_gold' => 'Columbo Archive Gold',
    'video_package' => 'Scorsese Video Package',
    'song_package' => 'Mozart Song Package',
    'content_draft' => 'Shakespeare Content Draft',
    'opportunity_batch' => 'Prospector Opportunity Batch',
    'expansion_opportunity' => 'Pandora Expansion Output',
    'work_output' => $agent . ' Work Output'
  ];
  return $map[$type] ?? ($agent . ' Deliverable');
}

function gd_first_json($text){
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
  $start = strpos($text, '['); $end = strrpos($text, ']');
  if ($start !== false && $end !== false && $end > $start) {
    $j = json_decode(substr($text, $start, $end-$start+1), true);
    if (is_array($j)) return $j;
  }
  return null;
}

function gd_create_deliverable($agent, $taskId, $status, $output, $metadata=[]){
  $agent = $agent ?: (is_array($metadata) ? ($metadata['agent'] ?? 'Goliath') : 'Goliath');
  $type = gd_type_for_agent($agent, $metadata);
  $json = gd_first_json($output);
  $jobId = is_array($metadata) ? ($metadata['agent_job_id'] ?? null) : null;
  $missionId = is_array($metadata) ? ($metadata['mission_id'] ?? null) : null;
  $title = gd_make_title($agent, $type, $output);
  $body = [
    'agent' => $agent,
    'deliverable_type' => $type,
    'title' => $title,
    'status' => $status === 'completed' ? 'ready' : $status,
    'priority' => is_array($metadata) ? ($metadata['priority'] ?? 'normal') : 'normal',
    'source_task_id' => $taskId ?: null,
    'source_job_id' => $jobId ?: null,
    'source_mission_id' => $missionId ?: null,
    'content_text' => mb_substr((string)$output, 0, 120000),
    'content_json' => is_array($json) ? $json : new stdClass(),
    'action_url' => '/dashboard/goliath-deliverables.php?agent=' . rawurlencode($agent),
    'metadata' => is_array($metadata) ? $metadata : new stdClass()
  ];
  return gd_req('POST', 'goliath_deliverables?on_conflict=source_task_id', $body);
}

function gd_log_event_with_link($agent, $title, $detail, $link, $metadata=[]){
  $payload = [
    'department' => $agent ?: 'Goliath',
    'event_type' => 'deliverable_ready',
    'title' => $title,
    'detail' => mb_substr((string)$detail, 0, 700),
    'status' => 'active',
    'confidence' => 95,
    'roi_estimate' => 0,
    'link_url' => $link,
    'metadata' => $metadata
  ];
  return gd_req('POST', 'goliath_events', $payload);
}
