<?php
/**
 * V12.15 Seed Conversation Scripts
 * Upload: /public_html/lead-engine/seed-conversation-scripts.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
if(!defined('AFTER_HOURS_CRON_KEY')||!AFTER_HOURS_CRON_KEY||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){
  http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
}

function sb151($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: resolution=ignore-duplicates,return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>35]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

$scripts=[
  [
    'script_name'=>'Seller Warm Valuation',
    'lead_type'=>'seller',
    'audience'=>'home valuation / future seller',
    'opener'=>'Hi, this is Jessica calling from Mark Pires and the Discover Connecticut support team. I saw you were looking into your home value. I just want to ask a couple quick questions so Mark can prepare something more useful than a generic online estimate.',
    'qualification_questions'=>[
      'Are you thinking about selling soon, or mostly curious right now?',
      'Have you made any major updates or renovations?',
      'Do you have a rough idea of what you think the home may be worth?',
      'Is there a timeframe where a move would make sense?',
      'What would be the best time for Mark to follow up personally?'
    ],
    'objection_handlers'=>[
      'just_looking'=>'Totally fine. A lot of people start that way. Mark can still give you a quiet, no-pressure read so you know where you stand.',
      'already_have_agent'=>'No problem. I will note that. If you need a second opinion or local data point, Mark can still be helpful.',
      'not_ready'=>'That is exactly why this can be useful now. You do not need to sell tomorrow to understand your position.',
      'too_busy'=>'I understand. I can keep this very quick and just have Mark send a short summary first.'
    ],
    'appointment_close'=>'Based on what you shared, the best next step is a quick conversation with Mark so he can give you a more accurate local read. Would tomorrow or the next day be easier?',
    'voicemail_script'=>'Hi, this is Jessica calling with Mark Pires and Discover Connecticut. I am following up on your home value request. Mark can prepare a more accurate local review than a generic online estimate. You can call or text Mark at 203-247-2655.',
    'sms_followup'=>'Jessica with Mark Pires here. Thanks for your home value request. Mark can prepare a smarter local review. What is the best time for a quick follow-up?',
    'email_followup'=>'Thanks for reaching out about your home value. Mark will review the local details and follow up with a more useful estimate than a generic online number.'
  ],
  [
    'script_name'=>'NYC Relocation Buyer',
    'lead_type'=>'relocation',
    'audience'=>'NYC / Brooklyn / Westchester buyer',
    'opener'=>'Hi, this is Jessica calling from Mark Pires and the Discover Connecticut support team. I saw you were looking at Connecticut options, and I wanted to help narrow down which Fairfield County towns may fit your commute, lifestyle, and budget.',
    'qualification_questions'=>[
      'Are you currently in NYC, Brooklyn, Westchester, or somewhere else?',
      'Which towns have you been considering so far?',
      'What price range are you hoping to stay within?',
      'Is commute, schools, space, or lifestyle the biggest driver?',
      'Are you already pre-approved or still early in the process?'
    ],
    'objection_handlers'=>[
      'early'=>'That is the perfect time to compare towns before touring homes.',
      'not_preapproved'=>'No problem. Mark can still help you understand towns and price ranges before you speak with a lender.',
      'too_many_towns'=>'That is common. Jessica and Mark can help narrow it to the best two or three.',
      'has_agent'=>'Understood. I will note that. If you need local town insight, Mark can still be a resource.'
    ],
    'appointment_close'=>'It sounds like a quick town-match conversation with Mark would save you time. Would you prefer a phone call or Zoom?',
    'voicemail_script'=>'Hi, this is Jessica with Mark Pires and Discover Connecticut. I am following up on your Connecticut relocation interest. Mark can help compare towns, commute, budget, and lifestyle. Call or text 203-247-2655.',
    'sms_followup'=>'Jessica with Mark Pires. Are you still comparing CT towns? Mark can help narrow the best fit by commute, lifestyle, and budget.',
    'email_followup'=>'Thanks for your Connecticut relocation inquiry. Mark can help compare Fairfield County towns based on commute, lifestyle, budget, and timing.'
  ],
  [
    'script_name'=>'Builder Developer Opportunity',
    'lead_type'=>'builder',
    'audience'=>'builder / developer / investor',
    'opener'=>'Hi, this is Jessica calling with Mark Pires. Mark is organizing Fairfield County builder and developer opportunities, including land, teardown, renovation, and acquisition signals. I wanted to see what types of projects you are actively looking for.',
    'qualification_questions'=>[
      'Which towns are you most interested in?',
      'Are you looking for land, teardowns, renovations, or larger development opportunities?',
      'What price range or project size is most relevant?',
      'Are you actively buying now or just watching opportunities?',
      'Would you like Mark to keep you on a private opportunity watchlist?'
    ],
    'objection_handlers'=>[
      'send_info'=>'Absolutely. I can have Mark send relevant opportunities first and only follow up when there is a fit.',
      'not_buying_now'=>'No problem. I can mark you as watchlist only.',
      'specific_towns'=>'That helps. I will narrow the watchlist to those towns.',
      'too_busy'=>'Understood. I can keep this to one quick question: what kind of project should Mark watch for?'
    ],
    'appointment_close'=>'If a relevant opportunity comes up, should Mark call, text, or email you first?',
    'voicemail_script'=>'Hi, this is Jessica with Mark Pires. Mark is organizing Fairfield County builder and developer opportunity signals. If you are looking for land, teardown, or renovation projects, call or text Mark at 203-247-2655.',
    'sms_followup'=>'Jessica with Mark Pires. What towns/project types should Mark watch for you: land, teardown, renovation, or development?',
    'email_followup'=>'Mark is building a private builder/developer opportunity watchlist for Fairfield County. Reply with towns and project types you want to track.'
  ],
  [
    'script_name'=>'Cold Review Safe Opener',
    'lead_type'=>'review',
    'audience'=>'compliance review / not yet approved for call',
    'opener'=>'This record is not cleared for outbound calling. Review DNC, consent, source, and approval status before calling.',
    'qualification_questions'=>[],
    'objection_handlers'=>[],
    'appointment_close'=>'Do not call until approved.',
    'voicemail_script'=>'',
    'sms_followup'=>'',
    'email_followup'=>''
  ]
];

$created=[];$errors=[];
foreach($scripts as $s){
  $payload=[[
    'script_name'=>$s['script_name'],
    'lead_type'=>$s['lead_type'],
    'audience'=>$s['audience'],
    'opener'=>$s['opener'],
    'qualification_questions'=>$s['qualification_questions'],
    'objection_handlers'=>$s['objection_handlers'],
    'appointment_close'=>$s['appointment_close'],
    'voicemail_script'=>$s['voicemail_script'],
    'sms_followup'=>$s['sms_followup'],
    'email_followup'=>$s['email_followup'],
    'active'=>true,
    'performance_score'=>50,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  $r=sb151('POST','conversation_script_variants',$payload);
  if($r['ok'])$created[]=$s['script_name']; else $errors[]=['script'=>$s['script_name'],'http'=>$r['http'],'body'=>$r['body']];
}
echo json_encode(['success'=>empty($errors),'created'=>$created,'errors'=>$errors],JSON_PRETTY_PRINT);
?>