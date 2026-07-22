<?php
/**
 * V12.19 Conversation Learning + Appointment Intelligence
 * Upload: /public_html/lead-engine/build-conversation-learning.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb191($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
    $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'data'=>is_array($d)?$d:[]];
  }
  function rows191($table,$query){ $r=sb191('GET',$table.'?'.$query); return $r['ok']?$r['data']:[]; }

  function analyze191($text,$outcome='unknown'){
    $t=strtolower((string)$text.' '.$outcome);
    $mot=35; $urg=30; $appt=20; $obj='none'; $response='Keep tone helpful and low-pressure.'; $next='Review and nurture.';
    $win=''; $lose='';
    if(str_contains($t,'sell')||str_contains($t,'selling')||str_contains($t,'list')){$mot+=25;$next='Offer seller market-position review.';}
    if(str_contains($t,'downsize')||str_contains($t,'retire')||str_contains($t,'move out of state')||str_contains($t,'relocat')){$mot+=25;$urg+=15;$win='financial flexibility / options before conditions change';}
    if(str_contains($t,'asap')||str_contains($t,'soon')||str_contains($t,'today')||str_contains($t,'this week')||str_contains($t,'30 days')){$urg+=35;$appt+=20;}
    if(str_contains($t,'appointment')||str_contains($t,'meet')||str_contains($t,'schedule')||str_contains($t,'call me back')||str_contains($t,'talk to mark')){$appt+=50;$next='Create appointment/follow-up task for Mark.';}
    if(str_contains($t,'just curious')||str_contains($t,'just looking')){$obj='just_curious';$response='Acknowledge curiosity and offer a no-pressure market position review.';$next='Send helpful value review / nurture.';}
    if(str_contains($t,'not interested')){$obj='not_interested';$mot=5;$urg=5;$appt=0;$response='Respectfully end and do not push.';$next='Archive or long-term nurture only.';}
    if(str_contains($t,'already have an agent')||str_contains($t,'have an agent')){$obj='has_agent';$mot-=20;$response='Respect existing relationship. Offer only requested local info.';$next='Do not pursue unless they request help.';}
    if(str_contains($t,'too busy')||str_contains($t,'call later')){$obj='busy';$response='Ask for the easiest callback window.';$next='Schedule follow-up.';}
    if($outcome==='appointment_set'){$appt=100;$mot=max($mot,80);$urg=max($urg,70);$next='Confirm appointment and calendar.';}
    return [
      'motivation_score'=>max(0,min(100,$mot)),
      'urgency_score'=>max(0,min(100,$urg)),
      'appointment_intent_score'=>max(0,min(100,$appt)),
      'objection_type'=>$obj,
      'recommended_response'=>$response,
      'recommended_next_action'=>$next,
      'winning_phrase'=>$win,
      'losing_phrase'=>$lose
    ];
  }

  $sources=[];

  $calls=rows191('conversation_intelligence_calls','select=*&order=created_at.desc&limit=500');
  foreach($calls as $c){
    $sources[]=[
      'source_table'=>'conversation_intelligence_calls',
      'source_id'=>$c['id']??'',
      'source'=>'retell_lead_call',
      'caller_name'=>$c['name']??'',
      'caller_phone'=>$c['phone']??'',
      'caller_email'=>$c['email']??'',
      'lead_type'=>$c['lead_type']??'',
      'town'=>$c['town']??'',
      'market'=>$c['market']??'',
      'transcript'=>$c['transcript']??'',
      'summary'=>$c['summary']??'',
      'outcome'=>$c['outcome']??'unknown',
      'appointment_set'=>!empty($c['appointment_set']),
      'follow_up_needed'=>!empty($c['follow_up_needed']),
      'follow_up_date'=>$c['follow_up_date']??null,
      'script_variant'=>$c['script_variant']??'',
      'raw'=>$c
    ];
  }

  $exec=rows191('executive_call_inbox','select=*&order=created_at.desc&limit=500');
  foreach($exec as $c){
    if(($c['caller_category']??'')==='spam') continue;
    $outcome=!empty($c['appointment_requested'])?'follow_up':(!empty($c['callback_needed'])?'follow_up':'unknown');
    $sources[]=[
      'source_table'=>'executive_call_inbox',
      'source_id'=>$c['id']??'',
      'source'=>'executive_forwarded_call',
      'caller_name'=>$c['caller_name']??'',
      'caller_phone'=>$c['caller_phone']??'',
      'caller_email'=>$c['caller_email']??'',
      'lead_type'=>$c['lead_type']??($c['caller_category']??''),
      'town'=>'',
      'market'=>'Executive Inbox',
      'transcript'=>$c['transcript']??'',
      'summary'=>$c['summary']??'',
      'outcome'=>$outcome,
      'appointment_set'=>!empty($c['appointment_requested']),
      'follow_up_needed'=>!empty($c['callback_needed']),
      'follow_up_date'=>null,
      'script_variant'=>'executive_forwarded_call',
      'raw'=>$c
    ];
  }

  $events=[]; $appointments=[];
  foreach($sources as $s){
    $text=trim(($s['transcript']??'').' '.($s['summary']??''));
    $a=analyze191($text,$s['outcome']??'unknown');
    $apptSet=!empty($s['appointment_set']) || $a['appointment_intent_score']>=85;
    $follow=!empty($s['follow_up_needed']) || $a['appointment_intent_score']>=60;
    $event=[
      'event_date'=>date('c'),
      'source'=>$s['source'],
      'source_table'=>$s['source_table'],
      'source_id'=>(string)$s['source_id'],
      'caller_name'=>$s['caller_name']??'',
      'caller_phone'=>$s['caller_phone']??'',
      'caller_email'=>$s['caller_email']??'',
      'lead_type'=>$s['lead_type']??'',
      'town'=>$s['town']??'',
      'market'=>$s['market']??'',
      'transcript'=>$s['transcript']??'',
      'summary'=>$s['summary']??'',
      'outcome'=>$s['outcome']??'unknown',
      'appointment_set'=>$apptSet,
      'follow_up_needed'=>$follow,
      'follow_up_date'=>$s['follow_up_date']?:null,
      'motivation_score'=>$a['motivation_score'],
      'urgency_score'=>$a['urgency_score'],
      'appointment_intent_score'=>$a['appointment_intent_score'],
      'objection_type'=>$a['objection_type'],
      'objection_detail'=>'',
      'winning_phrase'=>$a['winning_phrase'],
      'losing_phrase'=>$a['losing_phrase'],
      'recommended_response'=>$a['recommended_response'],
      'recommended_next_action'=>$a['recommended_next_action'],
      'script_variant'=>$s['script_variant']??'',
      'raw_payload'=>$s['raw']??$s,
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
    $events[]=$event;
    if($apptSet || $follow){
      $appointments[]=[
        'queue_date'=>date('Y-m-d'),
        'source_event_id'=>null,
        'caller_name'=>$event['caller_name'],
        'caller_phone'=>$event['caller_phone'],
        'caller_email'=>$event['caller_email'],
        'lead_type'=>$event['lead_type'],
        'town'=>$event['town'],
        'appointment_priority'=>max($event['appointment_intent_score'],$event['urgency_score']),
        'requested_time'=>'',
        'recommended_time_window'=>$event['urgency_score']>=80?'Today':($event['urgency_score']>=60?'Next 24 hours':'This week'),
        'appointment_reason'=>$event['recommended_next_action'],
        'appointment_status'=>'pending',
        'calendar_needed'=>true,
        'mark_followup_needed'=>true,
        'notes'=>$event['summary'],
        'raw_payload'=>$event,
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ];
    }
  }

  usort($events,function($a,$b){return ($b['appointment_intent_score']+$b['motivation_score']+$b['urgency_score']) <=> ($a['appointment_intent_score']+$a['motivation_score']+$a['urgency_score']);});
  usort($appointments,function($a,$b){return $b['appointment_priority']<=>$a['appointment_priority'];});

  $inserted=[];$errors=[];
  foreach(array_chunk(array_slice($events,0,500),100) as $chunk){
    $r=sb191('POST','conversation_learning_events',$chunk);
    if($r['ok']) $inserted[]=['events'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['events_error'=>$r['body'],'http'=>$r['http']];
  }
  foreach(array_chunk(array_slice($appointments,0,100),100) as $chunk){
    $r=sb191('POST','appointment_intelligence_queue',$chunk);
    if($r['ok']) $inserted[]=['appointments'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['appointments_error'=>$r['body'],'http'=>$r['http']];
  }

  $ob=[];$appts=0;$followups=0;
  foreach($events as $e){ if(!empty($e['appointment_set']))$appts++; if(!empty($e['follow_up_needed']))$followups++; $o=$e['objection_type']?:'none'; $ob[$o]=($ob[$o]??0)+1; }
  arsort($ob);
  $recs=[];
  if($appts>0)$recs[]="Prioritize {$appts} appointment/follow-up opportunities first.";
  if(($ob['just_curious']??0)>0)$recs[]="Common objection: just curious. Use no-pressure market position review.";
  if(($ob['has_agent']??0)>0)$recs[]="Some callers mention agents. Respect existing relationships and avoid pressure.";
  if($followups>0)$recs[]="There are {$followups} follow-ups needing Mark/Jessica action.";

  $brief="Conversation Learning + Appointment Intelligence — ".date('Y-m-d')."\\n\\n";
  $brief.="Conversations analyzed: ".count($events)."\\n";
  $brief.="Appointments detected: {$appts}\\n";
  $brief.="Follow-ups detected: {$followups}\\n";
  $brief.="Most common objection: ".(array_key_first($ob)?:'none')."\\n\\nTop appointment/follow-up opportunities:\\n";
  foreach(array_slice($appointments,0,10) as $i=>$a){ $brief.=($i+1).". ".$a['caller_name']." — ".$a['lead_type']." — Priority ".$a['appointment_priority']." — ".$a['appointment_reason']."\\n"; }
  $brief.="\\nRecommendations:\\n";
  foreach($recs as $i=>$r){ $brief.=($i+1).". {$r}\\n"; }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),
    'conversations_analyzed'=>count($events),
    'appointments_detected'=>$appts,
    'followups_detected'=>$followups,
    'most_common_objection'=>array_key_first($ob)?:'none',
    'highest_intent_lead'=>$appointments[0]['caller_name']??'',
    'recommendations'=>$recs,
    'briefing_text'=>$brief,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  $dr=sb191('POST','conversation_learning_briefings_v2',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb191('PATCH','conversation_learning_briefings_v2?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode([
    'success'=>empty($errors),
    'conversations_analyzed'=>count($events),
    'appointments_detected'=>$appts,
    'followups_detected'=>$followups,
    'appointment_queue_created'=>count($appointments),
    'inserted'=>$inserted,
    'briefing'=>$brief,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>