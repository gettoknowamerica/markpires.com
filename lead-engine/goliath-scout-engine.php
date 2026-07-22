<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$key = $_GET['key'] ?? ($_POST['key'] ?? '');
$expected = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
if($key !== $expected){ http_response_code(403); echo json_encode(['success'=>false,'error'=>'Bad key']); exit; }
function hclean($v){ return trim(preg_replace('/\s+/', ' ', (string)$v)); }
function sb_insert($table,$rows){$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.$table);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($rows),CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>30]);$body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return [$http,json_decode($body,true),$body];}
function sbq($ep){$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>25]);$b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);return ($http>=200&&$http<300&&is_array($d))?$d:[];}
function queue_task($agent,$prompt,$metadata=[],$priority=90){ return sb_insert('local_ai_tasks',[['task_type'=>'agent_command','model'=>'qwen2.5:7b','prompt'=>$prompt,'status'=>'queued','priority'=>$priority,'metadata'=>array_merge(['agent'=>$agent,'source'=>'scout_engine'],$metadata)]]); }
function eventx($dept,$title,$detail,$roi=1000,$link='/dashboard/goliath-scout-intelligence.php'){ sb_insert('goliath_events',[['department'=>$dept,'event_type'=>'scout_engine','title'=>$title,'detail'=>$detail,'roi_estimate'=>$roi,'confidence'=>88,'status'=>'queued','link_url'=>$link,'metadata'=>['agent'=>$dept]]]); }
function outreach_body($lead){
  $name = $lead['owner_name'] ?: 'there';
  $addr = $lead['property_address'] ?: 'your property';
  $town = $lead['town'] ?: 'your area';
  return "Hi {$name},\n\nI noticed {$addr} in {$town} had been on the market previously and I wanted to reach out personally. I’m Mark Pires, the CT House Detective Realtor.\n\nIf selling is still even a small possibility, I may be able to give you a clearer read on what changed in the market, what buyers are responding to now, and whether there is a smarter way to relaunch or quietly test interest.\n\nI’ll be reaching out, but you can also reply here with the best time for a quick call.\n\nBest,\nMark Pires\nThe CT House Detective Realtor\n203-247-2655\nmark@markpires.com\n\nIf you do not want future emails from me, reply STOP and I’ll remove you.";
}
$raw = file_get_contents('php://input'); $data = json_decode($raw,true) ?: $_POST;
$action = $data['action'] ?? ($_GET['action'] ?? 'seed_tasks');
if($action==='seed_tasks'){
  $prompts=[
    ['Scout','Find public contact intelligence for expired/withdrawn Fairfield County CT homes. Return only legally usable public sources, owner name, address, email if public, phone if public, source URL, confidence, and DNC/consent risk flags. Do not call or text automatically.', ['mission'=>'expired_contact_finder'], 99],
    ['Scout','Search public foreclosure, probate, estate, FSBO, price-reduction, builder permit, relocation and owner-intent sources for Fairfield County CT. Create lead candidates with source URLs and verification notes.', ['mission'=>'public_opportunity_scan'], 97],
    ['Jessica','Review newly verified Scout leads and prepare compliant human-review appointment-request emails in Mark Pires voice. Do not send texts/calls automatically. Ask for appointment, best call time, and permission to call.', ['mission'=>'appointment_email_prep'], 92]
  ];
  foreach($prompts as $p){ queue_task($p[0],$p[1],$p[2],$p[3]); }
  eventx('Scout','Scout contact intelligence cycle started','Scout is now queued to find owner contact info, public opportunity sources, and verified appointment targets.',5000);
  eventx('Jessica','Jessica appointment prep started','Jessica is queued to prepare compliant appointment-request emails for verified Scout leads.',2500);
  echo json_encode(['success'=>true,'message'=>'Scout + Jessica tasks queued','tasks'=>count($prompts)]); exit;
}
if($action==='add_lead'){
  $lead=[
    'source'=>hclean($data['source']??'manual'), 'source_url'=>hclean($data['source_url']??''),
    'property_address'=>hclean($data['property_address']??$data['address']??''), 'town'=>hclean($data['town']??''),
    'owner_name'=>hclean($data['owner_name']??$data['name']??''), 'owner_email'=>hclean($data['owner_email']??$data['email']??''),
    'owner_phone'=>hclean($data['owner_phone']??$data['phone']??''), 'lead_type'=>hclean($data['lead_type']??'expired_listing'),
    'status'=>'new','confidence'=>(float)($data['confidence']??60),'notes'=>hclean($data['notes']??''),'raw'=>$data
  ];
  [$http,$rows,$body]=sb_insert('goliath_scout_leads',[$lead]);
  if($http<200||$http>=300){ echo json_encode(['success'=>false,'http'=>$http,'body'=>$body]); exit; }
  $saved=$rows[0]??$lead; eventx('Scout','New Scout lead found',($lead['owner_name']?:'Owner').' — '.$lead['property_address'].' — '.$lead['town'],3000,'/dashboard/goliath-scout-intelligence.php');
  if(!empty($lead['owner_email'])){
    $subject='Quick question about '.$lead['property_address'];
    $bodyText=outreach_body($lead);
    sb_insert('goliath_outreach_queue',[['scout_lead_id'=>$saved['id']??null,'contact_name'=>$lead['owner_name'],'contact_email'=>$lead['owner_email'],'contact_phone'=>$lead['owner_phone'],'channel'=>'email','subject'=>$subject,'body'=>$bodyText,'status'=>'review_required','approval_required'=>true,'metadata'=>['agent'=>'Jessica','from'=>'scout_engine']]]);
    eventx('Jessica','Appointment email ready for review',$subject,2000,'/dashboard/goliath-appointment-queue.php');
  }
  echo json_encode(['success'=>true,'lead'=>$saved]); exit;
}
if($action==='create_outreach_for_new'){
  $leads=sbq('goliath_scout_leads?select=*&status=eq.new&owner_email=not.is.null&order=created_at.asc&limit=50'); $n=0;
  foreach($leads as $lead){
    sb_insert('goliath_outreach_queue',[['scout_lead_id'=>$lead['id']??null,'contact_name'=>$lead['owner_name']??'','contact_email'=>$lead['owner_email']??'','contact_phone'=>$lead['owner_phone']??'','channel'=>'email','subject'=>'Quick question about '.($lead['property_address']??'your property'),'body'=>outreach_body($lead),'status'=>'review_required','approval_required'=>true,'metadata'=>['agent'=>'Jessica','from'=>'create_outreach_for_new']]]); $n++;
  }
  eventx('Jessica','Appointment email queue refreshed',$n.' review-required appointment emails created.',2000,'/dashboard/goliath-appointment-queue.php');
  echo json_encode(['success'=>true,'created'=>$n]); exit;
}
echo json_encode(['success'=>false,'error'=>'Unknown action']);
