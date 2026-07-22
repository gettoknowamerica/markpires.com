<?php
function mp_calendar_request($action, $params = []) {
  if (!defined('GOOGLE_CALENDAR_WEBHOOK_URL') || !GOOGLE_CALENDAR_WEBHOOK_URL) return ['ok'=>false,'http'=>0,'error'=>'GOOGLE_CALENDAR_WEBHOOK_URL missing','data'=>null,'raw'=>null];
  if (!defined('GOOGLE_CALENDAR_SECRET') || !GOOGLE_CALENDAR_SECRET) return ['ok'=>false,'http'=>0,'error'=>'GOOGLE_CALENDAR_SECRET missing','data'=>null,'raw'=>null];

  $params = array_merge(['secret'=>GOOGLE_CALENDAR_SECRET,'action'=>$action], $params);
  $url = GOOGLE_CALENDAR_WEBHOOK_URL . '?' . http_build_query($params);
  $ch = curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPGET=>true,CURLOPT_TIMEOUT=>45,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>5]);
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $final=curl_getinfo($ch,CURLINFO_EFFECTIVE_URL); $err=curl_error($ch); curl_close($ch);
  $d=json_decode($body,true);
  return ['ok'=>($http>=200&&$http<300&&is_array($d)&&!empty($d['success'])),'http'=>$http,'error'=>$err,'final_url'=>$final,'data'=>is_array($d)?$d:null,'raw'=>is_array($d)?null:$body];
}
function mp_calendar_log($relatedId,$action,$request,$response){
  if(!defined('SUPABASE_URL')||!defined('SUPABASE_SERVICE_ROLE_KEY'))return;
  $payload=[['related_type'=>'appointment_request','related_id'=>(string)$relatedId,'action'=>$action,'ok'=>!empty($response['ok']),'request_payload'=>$request,'response_payload'=>$response,'http_status'=>$response['http']??0,'error'=>$response['error']??'','created_at'=>date('c')]];
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/google_calendar_sync_logs');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=minimal'],CURLOPT_TIMEOUT=>15]);
  curl_exec($ch); curl_close($ch);
}
?>