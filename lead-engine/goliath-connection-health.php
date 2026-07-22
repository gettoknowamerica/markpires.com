<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
$key = $_GET['key'] ?? $_POST['key'] ?? '';
$expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if($key !== $expected && empty($_SESSION['mp_dashboard_auth'])){ http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }
function has_const($name){ return defined($name) && trim((string)constant($name)) !== ''; }
function quick_url($url,$headers=[],$method='GET',$body=null){
  if(!$url) return ['ok'=>false,'status'=>0,'note'=>'missing url'];
  $ch=curl_init($url); $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_CUSTOMREQUEST=>$method];
  if($headers) $opts[CURLOPT_HTTPHEADER]=$headers; if($body!==null){$opts[CURLOPT_POSTFIELDS]=$body;}
  curl_setopt_array($ch,$opts); $resp=curl_exec($ch); $err=curl_error($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return ['ok'=>($http>=200 && $http<400),'status'=>$http,'error'=>$err?:null,'sample'=>is_string($resp)?substr($resp,0,180):null];
}
$checks=[];
$checks['supabase_config']=['ok'=>has_const('SUPABASE_URL')&&has_const('SUPABASE_SERVICE_ROLE_KEY'),'required'=>true];
if($checks['supabase_config']['ok']){
  $checks['supabase_rest']=quick_url(rtrim(SUPABASE_URL,'/').'/rest/v1/', ['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY]);
}
$checks['resend_config']=['ok'=>has_const('RESEND_API_KEY'),'required'=>true];
if(has_const('RESEND_API_KEY')) $checks['resend_api']=quick_url('https://api.resend.com/domains', ['Authorization: Bearer '.RESEND_API_KEY]);
$checks['hubspot_config']=['ok'=>has_const('HUBSPOT_ACCESS_TOKEN'),'required'=>false];
if(has_const('HUBSPOT_ACCESS_TOKEN')) $checks['hubspot_api']=quick_url('https://api.hubapi.com/crm/v3/objects/contacts?limit=1', ['Authorization: Bearer '.HUBSPOT_ACCESS_TOKEN]);
$checks['twilio_config']=['ok'=>has_const('TWILIO_ACCOUNT_SID')&&has_const('TWILIO_AUTH_TOKEN')&&has_const('TWILIO_FROM_NUMBER'),'required'=>false];
if($checks['twilio_config']['ok']) $checks['twilio_api']=quick_url('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode(TWILIO_ACCOUNT_SID).'.json', ['Authorization: Basic '.base64_encode(TWILIO_ACCOUNT_SID.':'.TWILIO_AUTH_TOKEN)]);
$checks['google_calendar_config']=['ok'=>has_const('GOOGLE_CALENDAR_ID') || has_const('GOOGLE_SERVICE_ACCOUNT_JSON') || has_const('GOOGLE_CLIENT_ID'),'required'=>false,'note'=>'config presence only; OAuth/service account live test depends on your current integration'];
$checks['n8n_webhook_config']=['ok'=>has_const('N8N_WEBHOOK_URL'),'required'=>false];
if(has_const('N8N_WEBHOOK_URL')) $checks['n8n_webhook']=quick_url(N8N_WEBHOOK_URL, [], 'POST', json_encode(['source'=>'goliath_connection_health','timestamp'=>date('c') ]));
$checks['openai_config']=['ok'=>has_const('OPENAI_API_KEY'),'required'=>false];
if(has_const('OPENAI_API_KEY')) $checks['openai_api']=quick_url('https://api.openai.com/v1/models', ['Authorization: Bearer '.OPENAI_API_KEY]);
$required_ok=true; foreach($checks as $c){ if(!empty($c['required']) && empty($c['ok'])) $required_ok=false; }
echo json_encode(['success'=>true,'server_time'=>date('c'),'required_ok'=>$required_ok,'checks'=>$checks], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
