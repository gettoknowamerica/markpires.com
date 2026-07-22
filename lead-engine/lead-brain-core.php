<?php
require_once __DIR__ . '/config.php';
function gb_json($data,$code=200){http_response_code($code);header('Content-Type: application/json');echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
function gb_h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function gb_supabase($method,$endpoint,$payload=null,$extraHeaders=[]){
  if(!defined('SUPABASE_URL')||!defined('SUPABASE_SERVICE_ROLE_KEY')) return ['ok'=>false,'status'=>0,'body'=>null,'raw'=>'Missing Supabase config'];
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $ch=curl_init($url);
  $headers=array_merge([
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',
    'Prefer: return=representation'
  ],$extraHeaders);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
  $raw=curl_exec($ch);$err=curl_error($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  $body=json_decode((string)$raw,true);
  return ['ok'=>$status>=200&&$status<300,'status'=>$status,'body'=>is_array($body)?$body:null,'raw'=>$raw,'error'=>$err];
}
function gb_lead_stages(){return [
  ['key'=>'captured','label'=>'Lead Captured','agent'=>'Goliath','order'=>10],
  ['key'=>'scout','label'=>'Scout Contact Intel','agent'=>'Scout','order'=>20],
  ['key'=>'einstein','label'=>'Einstein Search Intent','agent'=>'Einstein','order'=>30],
  ['key'=>'shakespeare','label'=>'Shakespeare Content','agent'=>'Shakespeare','order'=>40],
  ['key'=>'columbo','label'=>'Columbo Archive Match','agent'=>'Columbo','order'=>50],
  ['key'=>'scorsese','label'=>'Scorsese Video Queue','agent'=>'Scorsese','order'=>60],
  ['key'=>'jessica','label'=>'Jessica Follow-up','agent'=>'Jessica','order'=>70],
  ['key'=>'distribution','label'=>'Distribution OS','agent'=>'Distribution','order'=>80],
  ['key'=>'appointment','label'=>'Appointment / Mark Call','agent'=>'Jessica','order'=>90],
  ['key'=>'rockefeller','label'=>'Rockefeller Priority','agent'=>'Rockefeller','order'=>100]
];}
function gb_seed_journey($leadId,$leadName='lead'){
  $rows=[];
  foreach(gb_lead_stages() as $s){$rows[]=['lead_id'=>$leadId,'stage'=>$s['key'],'stage_order'=>$s['order'],'status'=>$s['key']==='captured'?'complete':'pending','agent'=>$s['agent'],'title'=>$s['label'],'detail'=>$s['key']==='captured'?'Lead file created and ready for the executive team.':'Waiting for '.$s['agent'].' output.','started_at'=>$s['key']==='captured'?date('c'):null,'completed_at'=>$s['key']==='captured'?date('c'):null];}
  return gb_supabase('POST','goliath_lead_journey',$rows);
}
function gb_queue_agent_commands($leadId,$context){
  $commands=[
    ['Scout','Find verified phone/email/source data, DNC status, public lead context and contact confidence.'],
    ['Einstein','Extract search intent and AEO questions from the lead context.'],
    ['Shakespeare','Draft one personalized email, one short blog, and social captions matched to the lead.'],
    ['Columbo','Search Mark archive/Discover CT/Mark Inspires content for matching moments.'],
    ['Scorsese','Queue a personalized short video package using the best matching content.'],
    ['Jessica','Prepare assistant follow-up, calendar next-step, and hot-lead alert if needed.'],
    ['Distribution','Queue approved content to calendar/review mode.'],
    ['Rockefeller','Score deal value and recommend Mark’s next action.']
  ];
  $rows=[];foreach($commands as $c){$rows[]=['lead_id'=>$leadId,'agent'=>$c[0],'command'=>$c[1].' Context: '.$context,'priority'=>80,'status'=>'queued','metadata'=>['source'=>'lead_brain_commissioning']];}
  return gb_supabase('POST','goliath_command_bus',$rows);
}
?>
