<?php
require_once __DIR__ . '/goliath-db.php';

function goliath_first_name($name){ $p=preg_split('/\s+/',trim((string)$name)); return $p[0]??''; }
function goliath_last_name($name){ $p=preg_split('/\s+/',trim((string)$name)); array_shift($p); return implode(' ',$p); }
function goliath_find_or_create_contact($lead){
  if(!gdb_enabled()) return 0;
  $email=strtolower(trim((string)($lead['email']??''))); $phone=trim((string)($lead['phone']??''));
  $contact=null;
  if($email) $contact=gdb_one('SELECT * FROM goliath_contacts WHERE email=? LIMIT 1',[$email]);
  if(!$contact && $phone) $contact=gdb_one('SELECT * FROM goliath_contacts WHERE phone=? LIMIT 1',[$phone]);
  $name=trim((string)($lead['name']??''));
  $payload=[
    'full_name'=>$name,
    'first_name'=>goliath_first_name($name),
    'last_name'=>goliath_last_name($name),
    'email'=>$email?:null,
    'phone'=>$phone?:null,
    'address'=>$lead['address']??null,
    'town'=>$lead['town']??($lead['towns']??null),
    'contact_type'=>'lead',
    'relationship_stage'=>'new',
    'lead_temperature'=>$lead['lead_temperature']??'new',
    'lead_score'=>(int)($lead['lead_score']??0),
    'source'=>$lead['source']??'markpires.com',
    'page_url'=>$lead['page_url']??null,
    'notes'=>$lead['message']??($lead['goal']??null),
    'raw_payload'=>gdb_json($lead)
  ];
  if($contact){ gdb_update('goliath_contacts',$payload,'id=:id',['id'=>$contact['id']]); return (int)$contact['id']; }
  $payload['contact_uid']=gdb_uid('contact');
  return gdb_insert('goliath_contacts',$payload);
}
function goliath_store_lead($lead,$contactId=0){
  if(!gdb_enabled()) return 0;
  $email=strtolower(trim((string)($lead['email']??'')));
  $existing=$email?gdb_one('SELECT id FROM leads WHERE email=? LIMIT 1',[$email]):null;
  $row=[
    'uid'=>$lead['uid']??gdb_uid('lead'),
    'type'=>$lead['type']??'general','tag'=>$lead['tag']??'website','status'=>'new','name'=>$lead['name']??'',
    'email'=>$email?:null,'phone'=>$lead['phone']??'','address'=>$lead['address']??'','town'=>$lead['town']??'',
    'timeline'=>$lead['timeline']??'','goal'=>$lead['goal']??'','message'=>$lead['message']??'',
    'price_range'=>$lead['price_range']??null,'estimated_value'=>$lead['estimated_value']??null,'budget'=>$lead['budget']??null,
    'source'=>$lead['source']??'markpires.com','page_url'=>$lead['page_url']??null,'ip_hash'=>$lead['ip_hash']??null,
    'retell_agent_id'=>$lead['retell_agent_id']??null,'referral_fee_percent'=>$lead['referral_fee_percent']??null,
    'lead_score'=>(int)($lead['lead_score']??0),'route'=>$lead['route']??null,
    'lead_temperature'=>$lead['lead_temperature']??null,'lead_origin'=>$lead['lead_origin']??null,
    'crm_contact_id'=>$contactId?:null,'raw_payload'=>gdb_json($lead)
  ];
  if($existing){ unset($row['uid']); gdb_update('leads',$row,'id=:id',['id'=>$existing['id']]); return (int)$existing['id']; }
  return gdb_insert('leads',$row);
}
function goliath_timeline($contactId,$type,$exec,$title,$detail,$source='goliath',$raw=[]){
  if(!gdb_enabled()) return 0;
  return gdb_insert('goliath_relationship_timeline',[
    'contact_id'=>$contactId?:null,'event_type'=>$type,'executive'=>strtolower($exec),'title'=>$title,'detail'=>$detail,
    'priority'=>'normal','source'=>$source,'raw_payload'=>gdb_json($raw)
  ]);
}
function goliath_commission($exec,$title,$missionType,$prompt,$priority=50,$contactId=null,$leadId=null,$payload=[]){
  if(!gdb_enabled()) return 0;
  $uid=gdb_uid('com');
  $id=gdb_insert('executive_commissions',[
    'commission_uid'=>$uid,'executive_key'=>strtolower($exec),'commissioned_by'=>'goliath','contact_id'=>$contactId?:null,'lead_id'=>$leadId?:null,
    'title'=>$title,'mission_type'=>$missionType,'prompt'=>$prompt,'status'=>'queued','priority'=>$priority,'progress'=>0,
    'current_phase'=>'Queued','current_task'=>'Waiting for executive claim','payload'=>gdb_json($payload)
  ]);
  goliath_event($exec,'Commission queued: '.$title,$missionType,'normal',$id,'/dashboard/goliath-mission-control.php');
  return $id;
}
function goliath_capture_to_crm($lead){
  if(!gdb_enabled()) return ['ok'=>false,'error'=>'Goliath MySQL not configured'];
  $contactId=goliath_find_or_create_contact($lead);
  $leadId=goliath_store_lead($lead,$contactId);
  $summary='Lead: '.($lead['name']??'Unknown').' | '.($lead['type']??'general').' | Town: '.($lead['town']??'').' | Score: '.($lead['lead_score']??0).' | Notes: '.(($lead['message']??'') ?: ($lead['goal']??''));
  goliath_timeline($contactId,'lead_capture','jessica','New lead captured',$summary,'capture.php',$lead);
  gdb_insert('knowledge_assets',[
    'asset_uid'=>gdb_uid('asset'),'contact_id'=>$contactId?:null,'lead_id'=>$leadId?:null,'source_type'=>'lead_capture','asset_type'=>'lead_context',
    'title'=>'Lead context — '.(($lead['name']??'') ?: ($lead['email']??$lead['phone']??'Unknown')),'summary'=>$summary,'body'=>gdb_json($lead),'status'=>'active','raw_payload'=>gdb_json($lead)
  ]);
  $missions=[
    ['Jessica','Human Touch response','human_touch','Prepare immediate warm follow-up in Mark Pires voice. Confirm next step and relationship tone.',100],
    ['Scout','Lead discovery and contact enrichment','lead_enrichment','Research missing phone/email/property/town context and safe contact enrichment opportunities.',92],
    ['Einstein','Intent analysis and content compounding plan','asset_compounding','Analyze the lead intent and design SEO/AEO/backlink/content aftercare plan so content does not die on delivery.',88],
    ['Shakespeare','Client-specific written content pack','client_content','Create personalized email/blog/social copy that feels hand-built for this exact buyer or seller.',84],
    ['Scorsese','Client-specific video/media brief','client_media','Create media brief for personalized reel/video/visual package tied to the prospect intent.',80],
    ['Rockefeller','Revenue path and next-best action','revenue_priority','Rank ROI, priority, direct/referral route, and next action for Mark.',76]
  ];
  $ids=[]; foreach($missions as $m){ $ids[]=goliath_commission($m[0],$m[1],$m[2],$m[3]."\n\n".$summary,$m[4],$contactId,$leadId,$lead); }
  return ['ok'=>true,'contact_id'=>$contactId,'lead_id'=>$leadId,'commissions_created'=>count(array_filter($ids)),'commission_ids'=>$ids];
}
