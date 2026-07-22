<?php
/**
 * V13.6 Voice Intelligence Router Builder
 * Upload: /public_html/lead-engine/build-voice-intelligence.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb136($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }
  function rows136($table,$query){$r=sb136('GET',$table.'?'.$query); return $r['ok']?$r['data']:[];}

  $events=rows136('voice_intelligence_events','select=*&status=eq.new&order=lead_score.desc,created_at.desc&limit=300');
  $routed=['executive'=>0,'conversation'=>0,'pipeline'=>0]; $errors=[];

  foreach($events as $e){
    $id=$e['id'];
    if(empty($e['routed_to_executive_inbox']) && in_array($e['call_type'],['forwarded_call','vendor','personal','spam'],true)){
      $payload=[[
        'call_date'=>$e['event_date']??date('c'),'caller_name'=>$e['caller_name']??'','caller_phone'=>$e['caller_phone']??'','caller_email'=>$e['caller_email']??'',
        'caller_category'=>$e['call_type']??'forwarded_call','summary'=>$e['summary']??'','transcript'=>$e['transcript']??'','recording_url'=>$e['recording_url']??'',
        'urgency'=>$e['urgency']??'normal','callback_needed'=>!empty($e['callback_needed']),'appointment_requested'=>!empty($e['appointment_requested']),
        'lead_related'=>!empty($e['lead_related']),'recommended_action'=>$e['recommended_action']??'Review call.','status'=>'new','raw_payload'=>$e,'created_at'=>date('c'),'updated_at'=>date('c')
      ]];
      $res=sb136('POST','executive_call_inbox',$payload);
      if($res['ok']){$routed['executive']++; sb136('PATCH','voice_intelligence_events?id=eq.'.rawurlencode($id),['routed_to_executive_inbox'=>true,'updated_at'=>date('c')]);}
      else $errors[]=['executive'=>$res['body']];
    }

    if(empty($e['routed_to_conversation_learning']) && !in_array($e['call_type'],['spam','vendor'],true)){
      $payload=[[
        'event_date'=>$e['event_date']??date('c'),'source'=>'voice_intelligence','source_table'=>'voice_intelligence_events','source_id'=>$id,
        'caller_name'=>$e['caller_name']??'','caller_phone'=>$e['caller_phone']??'','caller_email'=>$e['caller_email']??'',
        'lead_type'=>$e['lead_type']??($e['call_type']??''),'town'=>$e['town']??'','market'=>'Lower Fairfield County','transcript'=>$e['transcript']??'','summary'=>$e['summary']??'',
        'outcome'=>!empty($e['appointment_requested'])?'appointment_set':(!empty($e['callback_needed'])?'follow_up':'unknown'),
        'appointment_set'=>!empty($e['appointment_requested']),'follow_up_needed'=>!empty($e['callback_needed']),
        'motivation_score'=>(int)($e['lead_score']??0),'urgency_score'=>($e['urgency']??'normal')==='urgent'?90:60,
        'appointment_intent_score'=>!empty($e['appointment_requested'])?95:(!empty($e['callback_needed'])?70:30),
        'recommended_next_action'=>$e['recommended_action']??'Review call.','script_variant'=>'voice_intelligence_router',
        'raw_payload'=>$e,'created_at'=>date('c'),'updated_at'=>date('c')
      ]];
      $res=sb136('POST','conversation_learning_events',$payload);
      if($res['ok']){$routed['conversation']++; sb136('PATCH','voice_intelligence_events?id=eq.'.rawurlencode($id),['routed_to_conversation_learning'=>true,'updated_at'=>date('c')]);}
      else $errors[]=['conversation'=>$res['body']];
    }

    if(empty($e['routed_to_pipeline']) && (!empty($e['lead_related']) || !empty($e['appointment_requested']) || !empty($e['hot_lead']))){
      $stage=!empty($e['appointment_requested'])?'appointment':'conversation';
      $prob=$stage==='appointment'?45:30;
      $v=700000; $commission=$v*.025;
      $payload=[[
        'pipeline_date'=>date('Y-m-d'),'source_table'=>'voice_intelligence_events','source_id'=>$id,'opportunity_type'=>'seller',
        'name'=>$e['caller_name']??'','phone'=>$e['caller_phone']??'','email'=>$e['caller_email']??'','address'=>$e['address']??'','town'=>$e['town']??'',
        'pipeline_stage'=>$stage,'stage_score'=>$stage==='appointment'?70:50,'priority_score'=>(int)($e['lead_score']??0),'probability'=>$prob,
        'estimated_sale_price'=>$v,'estimated_commission'=>round($commission,2),'expected_value'=>round($commission*($prob/100),2),
        'next_step'=>$e['recommended_action']??'Follow up.','next_followup_at'=>date('c',strtotime('+2 hours')),'last_activity_at'=>date('c'),
        'notes'=>$e['summary']??'','raw_payload'=>$e,'status'=>'active','created_at'=>date('c'),'updated_at'=>date('c')
      ]];
      $res=sb136('POST','jessica_opportunity_pipeline',$payload);
      if($res['ok']){$routed['pipeline']++; sb136('PATCH','voice_intelligence_events?id=eq.'.rawurlencode($id),['routed_to_pipeline'=>true,'updated_at'=>date('c')]);}
      else $errors[]=['pipeline'=>$res['body']];
    }
  }

  $all=rows136('voice_intelligence_events','select=*&order=lead_score.desc,created_at.desc&limit=500');
  $counts=['forwarded'=>0,'inbound'=>0,'hot'=>0,'appointments'=>0,'callbacks'=>0,'spam'=>0];
  foreach($all as $e){
    if(($e['call_type']??'')==='forwarded_call')$counts['forwarded']++;
    if(($e['call_type']??'')==='inbound_lead')$counts['inbound']++;
    if(!empty($e['hot_lead']))$counts['hot']++;
    if(!empty($e['appointment_requested']))$counts['appointments']++;
    if(!empty($e['callback_needed']))$counts['callbacks']++;
    if(in_array(($e['call_type']??''),['spam','vendor'],true))$counts['spam']++;
  }

  $recs=[
    'Use this as Jessica’s voice router: forwarded calls stay separate, lead calls enter learning and pipeline.',
    'Retell should post all forwarded/inbound call transcripts to voice-intelligence-intake.php.',
    'Hot calls and appointment calls should be handled before new prospecting.'
  ];

  $brief="V13.6 VOICE INTELLIGENCE ROUTER\\n========================================\\n\\n";
  $brief.="Total Calls:       ".count($all)."\\n";
  $brief.="Forwarded Calls:   ".$counts['forwarded']."\\n";
  $brief.="Inbound Leads:     ".$counts['inbound']."\\n";
  $brief.="Hot Leads:         ".$counts['hot']."\\n";
  $brief.="Appointments:      ".$counts['appointments']."\\n";
  $brief.="Callbacks:         ".$counts['callbacks']."\\n";
  $brief.="Spam/Vendor:       ".$counts['spam']."\\n\\n";
  $brief.="Routed This Run:\\n";
  $brief.="Executive Inbox:   ".$routed['executive']."\\nConversation:      ".$routed['conversation']."\\nPipeline:          ".$routed['pipeline']."\\n\\n";
  $brief.="TOP CALLS\\n----------------------------------------\\n";
  foreach(array_slice($all,0,15) as $i=>$e){$brief.=($i+1).". ".(($e['caller_name']??'')?:$e['caller_phone'])." — ".$e['call_type']." — Score ".$e['lead_score']." — ".$e['recommended_action']."\\n";}

  $daily=[[
    'briefing_date'=>date('Y-m-d'),'total_calls'=>count($all),'forwarded_calls'=>$counts['forwarded'],'inbound_leads'=>$counts['inbound'],
    'hot_leads'=>$counts['hot'],'appointments'=>$counts['appointments'],'callbacks'=>$counts['callbacks'],'spam_or_vendor'=>$counts['spam'],
    'top_calls'=>array_slice($all,0,25),'recommendations'=>$recs,'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $dr=sb136('POST','voice_intelligence_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb136('PATCH','voice_intelligence_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode(['success'=>empty($errors),'events_processed'=>count($events),'routed'=>$routed,'briefing'=>$brief,'errors'=>$errors],JSON_PRETTY_PRINT);
} catch(Throwable $e){
  http_response_code(500); echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>