<?php
/**
 * V11.6 Builder Follow-Up Queue Builder
 * Upload: /public_html/lead-engine/build-builder-followups.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function sb116($method,$endpoint,$payload=null){
  $url = rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers = [
    'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json'
  ];
  $headers[] = $method === 'POST'
    ? 'Prefer: resolution=ignore-duplicates,return=representation'
    : 'Prefer: return=representation';

  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_HTTPHEADER=>$headers,
    CURLOPT_TIMEOUT=>45
  ]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $body=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $data=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function priority116($stage,$prob){
  if(in_array($stage,['offer_made','under_contract'],true) || $prob>=70) return 'hot';
  if(in_array($stage,['offer_possible','site_visit'],true) || $prob>=50) return 'high';
  return 'normal';
}

function due_for_stage116($stage){
  if($stage==='intro_sent') return '+3 weekdays 10:00';
  if($stage==='interested') return '+7 days 10:00';
  if($stage==='reviewing') return '+7 days 10:00';
  if($stage==='site_visit') return '+2 days 10:00';
  if($stage==='offer_possible') return '+5 days 10:00';
  if($stage==='offer_made') return '+3 days 10:00';
  if($stage==='under_contract') return '+14 days 10:00';
  return '+7 days 10:00';
}

function make_msg116($name,$stage,$address,$town){
  $first = trim(explode(' ', trim((string)$name))[0] ?? 'there');
  if($stage==='intro_sent') return "Follow up with {$first} about the {$town} opportunity at {$address}. Ask if it is worth a closer look.";
  if($stage==='interested') return "Check in with {$first}. They showed interest in {$address}. Ask what they need to evaluate it.";
  if($stage==='site_visit') return "Follow up after site visit for {$address}. Ask for feedback and whether an offer is possible.";
  if($stage==='offer_possible') return "Ask {$first} if they want to move toward an offer on {$address}.";
  if($stage==='offer_made') return "Track offer status for {$address}. Confirm next step and timing.";
  if($stage==='under_contract') return "Check contract progress and closing timing for {$address}.";
  return "Follow up with {$first} about {$address}.";
}

$created=[]; $errors=[]; $skipped=[];

/* Pipeline-driven followups */
$pipeline = sb116('GET','builder_pipeline?select=*&pipeline_stage=not.in.(closed,dead)&followup_created=neq.true&order=deal_probability.desc&limit=500')['data'];
foreach($pipeline as $p){
  if(!is_array($p) || empty($p['id'])) continue;
  $stage=$p['pipeline_stage'] ?? 'new';
  $prob=(int)($p['deal_probability'] ?? 10);
  $due = !empty($p['next_followup_at']) ? $p['next_followup_at'] : date('c', strtotime(due_for_stage116($stage)));
  $address=$p['opportunity_address'] ?? '';
  $town=$p['opportunity_town'] ?? '';
  $builder=$p['builder_name'] ?? '';
  $msg=make_msg116($builder,$stage,$address,$town);

  $payload=[[
    'related_type'=>'builder_pipeline',
    'related_id'=>(string)$p['id'],
    'pipeline_id'=>$p['id'],
    'match_id'=>$p['match_id'] ?? null,
    'outreach_id'=>$p['outreach_id'] ?? null,
    'builder_contact_id'=>$p['builder_contact_id'] ?? null,
    'builder_name'=>$builder,
    'company'=>$p['company'] ?? '',
    'phone'=>$p['phone'] ?? '',
    'email'=>$p['email'] ?? '',
    'opportunity_address'=>$address,
    'opportunity_town'=>$town,
    'opportunity_type'=>$p['opportunity_type'] ?? '',
    'followup_type'=>'pipeline_'.$stage,
    'channel'=>'call',
    'priority'=>priority116($stage,$prob),
    'subject'=>'Builder follow-up: '.$town.' '.$stage,
    'message'=>$msg,
    'recommended_action'=>$msg,
    'due_at'=>$due,
    'status'=>'queued',
    'raw_payload'=>$p,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $r=sb116('POST','builder_followup_queue',$payload);
  if($r['ok']){
    $created[]=['type'=>'pipeline','builder'=>$builder,'stage'=>$stage,'address'=>$address];
    sb116('PATCH','builder_pipeline?id=eq.'.rawurlencode($p['id']),['followup_created'=>true,'updated_at'=>date('c')]);
  } else $errors[]=['type'=>'pipeline','id'=>$p['id'],'http'=>$r['http'],'body'=>$r['body']];
}

/* Outreach sent but not yet in pipeline */
$outreach = sb116('GET','builder_intro_outreach?select=*&status=eq.sent&followup_created=neq.true&order=created_at.desc&limit=300')['data'];
foreach($outreach as $o){
  if(!is_array($o) || empty($o['id'])) continue;
  $builder=$o['builder_name'] ?? '';
  $address=$o['opportunity_address'] ?? '';
  $town=$o['opportunity_town'] ?? '';
  $msg=make_msg116($builder,'intro_sent',$address,$town);

  $payload=[[
    'related_type'=>'builder_intro_outreach',
    'related_id'=>(string)$o['id'],
    'pipeline_id'=>null,
    'match_id'=>$o['match_id'] ?? null,
    'outreach_id'=>$o['id'],
    'builder_contact_id'=>$o['builder_contact_id'] ?? null,
    'builder_name'=>$builder,
    'company'=>$o['company'] ?? '',
    'phone'=>$o['builder_phone'] ?? '',
    'email'=>$o['builder_email'] ?? '',
    'opportunity_address'=>$address,
    'opportunity_town'=>$town,
    'opportunity_type'=>$o['opportunity_type'] ?? '',
    'followup_type'=>'intro_sent',
    'channel'=>'call',
    'priority'=>'normal',
    'subject'=>'Builder intro follow-up: '.$town,
    'message'=>$msg,
    'recommended_action'=>$msg,
    'due_at'=>date('c',strtotime('+3 weekdays 10:00')),
    'status'=>'queued',
    'raw_payload'=>$o,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $r=sb116('POST','builder_followup_queue',$payload);
  if($r['ok']){
    $created[]=['type'=>'outreach','builder'=>$builder,'address'=>$address];
    sb116('PATCH','builder_intro_outreach?id=eq.'.rawurlencode($o['id']),['followup_created'=>true,'updated_at'=>date('c')]);
  } else $errors[]=['type'=>'outreach','id'=>$o['id'],'http'=>$r['http'],'body'=>$r['body']];
}

/* Push due builder followups to Mark action queue */
$due = sb116('GET','builder_followup_queue?select=*&status=eq.queued&due_at=lte.'.rawurlencode(date('c',strtotime('+24 hours'))).'&limit=200')['data'];
$actions=0;
foreach($due as $f){
  if(!is_array($f) || empty($f['id'])) continue;
  $exists=sb116('GET','mark_action_queue?select=id&related_type=eq.builder_followup&related_id=eq.'.rawurlencode($f['id']).'&status=in.(open,pending)&limit=1');
  if(!empty($exists['data'])) continue;
  $action=[[
    'related_type'=>'builder_followup',
    'related_id'=>$f['id'],
    'name'=>$f['builder_name'] ?? '',
    'phone'=>$f['phone'] ?? '',
    'email'=>$f['email'] ?? '',
    'town'=>$f['opportunity_town'] ?? '',
    'address'=>$f['opportunity_address'] ?? '',
    'source'=>'Builder Follow-Up Intelligence',
    'priority'=>$f['priority'] ?? 'normal',
    'action_type'=>'builder_followup',
    'recommended_action'=>$f['recommended_action'] ?? $f['message'] ?? '',
    'notes'=>$f['notes'] ?? '',
    'status'=>'open',
    'due_at'=>$f['due_at'] ?? date('c'),
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  $r=sb116('POST','mark_action_queue',$action);
  if($r['ok']) $actions++;
}

echo json_encode([
  'success'=>empty($errors),
  'created_count'=>count($created),
  'created'=>$created,
  'actions_created'=>$actions,
  'skipped'=>$skipped,
  'errors'=>$errors
],JSON_PRETTY_PRINT);
?>