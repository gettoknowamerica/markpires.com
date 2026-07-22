<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json');
$key=$_POST['key']??$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid key']); exit;
}
function supa_insert($table,$payload){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($payload),
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT=>30
  ]);
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  return [$http,$body,$err];
}
$prompt='Refresh owner contact numbers. Find newly enriched ready-to-call phone numbers from current expired/seller opportunity records, normalize owner name, phone, property address, town, source, confidence, and recommended action. Return usable rows only.';
[$http,$body,$err]=supa_insert('local_ai_tasks',[
  'task_type'=>'refresh_contact_numbers',
  'model'=>'auto',
  'prompt'=>$prompt,
  'priority'=>100,
  'status'=>'queued',
  'metadata'=>['source'=>'contact_numbers_refresh','created_by'=>'goliath_ui']
]);
echo json_encode([
  'success'=>$http>=200 && $http<300,
  'http'=>$http,
  'message'=>$http>=200 && $http<300 ? 'Goliath is refreshing contact numbers.' : 'Could not queue refresh.',
  'body'=>json_decode($body,true) ?: $body,
  'error'=>$err
]);
?>