<?php
declare(strict_types=1);

if(!function_exists('g1193_table')){
 function g1193_table(string $table):bool{
  try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]);return (int)($r['c']??0)>0;}catch(Throwable $e){return false;}
 }
}
if(!function_exists('g1193_cols')){
 function g1193_cols(string $table):array{
  try{$rows=gdb_all("SELECT column_name,column_type,is_nullable,column_default,extra FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$table])?:[];$out=[];foreach($rows as $r)$out[(string)$r['column_name']]=$r;return $out;}catch(Throwable $e){return [];}
 }
}
if(!function_exists('g1193_uid')){
 function g1193_uid(string $prefix):string{return $prefix.'_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(18));}
}
if(!function_exists('g1193_json')){
 function g1193_json($value):string{return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
}
if(!function_exists('g1193_default')){
 function g1193_default(string $column,string $type){
  $n=strtolower($column);$t=strtolower($type);
  if(str_contains($n,'uid'))return g1193_uid('auto');
  if(str_contains($n,'status'))return 'new';
  if(str_contains($n,'type'))return 'website_lead';
  if(str_contains($t,'int')||str_contains($t,'decimal'))return 0;
  if(str_contains($t,'date')||str_contains($t,'time'))return gdb_now();
  return '';
 }
}
if(!function_exists('g1193_insert')){
 function g1193_insert(string $table,array $row):int{
  $cols=g1193_cols($table);if(!$cols)return 0;$safe=[];
  foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;
  foreach($cols as $c=>$d){
   if(array_key_exists($c,$safe)||strtolower((string)$d['is_nullable'])==='yes'||$d['column_default']!==null||str_contains(strtolower((string)$d['extra']),'auto_increment'))continue;
   $safe[$c]=g1193_default($c,(string)$d['column_type']);
  }
  return $safe?(int)gdb_insert($table,$safe):0;
 }
}
if(!function_exists('g1193_update')){
 function g1193_update(string $table,int $id,array $row):bool{
  if($id<1)return false;$cols=g1193_cols($table);$safe=[];
  foreach($row as $k=>$v)if(isset($cols[$k]))$safe[$k]=$v;
  if(!$safe)return false;gdb_update($table,$safe,'id=:id',['id'=>$id]);return true;
 }
}
if(!function_exists('g1193_clean')){
 function g1193_clean($value):string{
  if(is_array($value))$value=implode(', ',array_map('strval',$value));
  return trim(strip_tags((string)$value));
 }
}
if(!function_exists('g1193_phone')){
 function g1193_phone($value):string{
  $digits=preg_replace('/\D+/','',(string)$value);
  if(strlen($digits)===11&&str_starts_with($digits,'1'))$digits=substr($digits,1);
  return $digits;
 }
}
if(!function_exists('g1193_normalize')){
 function g1193_normalize(array $data):array{
  $lead=[
   'lead_uid'=>g1193_uid('lead'),
   'name'=>g1193_clean($data['name']??$data['full_name']??$data['contact_name']??''),
   'email'=>strtolower(g1193_clean($data['email']??$data['contact_email']??'')),
   'phone'=>g1193_phone($data['phone']??$data['mobile']??$data['contact_phone']??''),
   'address'=>g1193_clean($data['address']??$data['property_address']??''),
   'town'=>g1193_clean($data['town']??$data['towns']??$data['city']??''),
   'type'=>g1193_clean($data['type']??$data['lead_type']??$data['form_type']??'website_lead'),
   'tag'=>g1193_clean($data['tag']??'website'),
   'timeline'=>g1193_clean($data['timeline']??$data['timeframe']??''),
   'goal'=>g1193_clean($data['goal']??''),
   'message'=>g1193_clean($data['message']??$data['notes']??$data['comments']??''),
   'budget'=>g1193_clean($data['budget']??''),
   'estimated_value'=>g1193_clean($data['estimated_value']??$data['value_range']??''),
   'price_range'=>g1193_clean($data['price_range']??''),
   'source'=>g1193_clean($data['source']??($_SERVER['HTTP_HOST']??'markpires.com')),
   'page_url'=>g1193_clean($data['page_url']??($_SERVER['HTTP_REFERER']??'')),
   'created_at'=>gdb_now()
  ];
  $score=20;
  if($lead['email']!=='')$score+=15;if($lead['phone']!=='')$score+=20;if($lead['address']!=='')$score+=20;
  $blob=strtolower(g1193_json($lead));
  if(preg_match('/\b(asap|now|today|urgent|immediate|ready|soon)\b/',$blob))$score+=20;
  if(preg_match('/\b(seller|valuation|expired|inherited|absentee)\b/',$blob))$score+=10;
  $lead['lead_score']=min(100,$score);
  $lead['route']=$lead['lead_score']>=75?'mark_priority':'standard_followup';
  $lead['lead_temperature']=$lead['lead_score']>=80?'hot':($lead['lead_score']>=55?'warm':'new');
  return $lead;
 }
}
if(!function_exists('g1193_timeline')){
 function g1193_timeline(array $lead,int $contactId,string $actor,string $type,string $title,string $details,array $metadata=[]):int{
  return g1193_insert('goliath_lead_timeline',[
   'event_uid'=>g1193_uid('event'),'lead_uid'=>$lead['lead_uid']??null,
   'crm_contact_id'=>$contactId?:null,'actor'=>$actor,'event_type'=>$type,
   'title'=>$title,'details'=>$details,'status'=>'complete',
   'metadata'=>g1193_json($metadata),'created_at'=>gdb_now()
  ]);
 }
}
if(!function_exists('g1193_save')){
 function g1193_save(array $lead,array $raw=[]):array{
  gdb()->beginTransaction();
  try{
   $contact=null;
   if($lead['email']!=='')$contact=gdb_one("SELECT * FROM internal_crm_contacts WHERE LOWER(COALESCE(email,''))=? OR LOWER(COALESCE(existing_email,''))=? ORDER BY id DESC LIMIT 1",[$lead['email'],$lead['email']]);
   if(!$contact&&$lead['phone']!=='')$contact=gdb_one("SELECT * FROM internal_crm_contacts WHERE REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),'-',''),' ',''),'(',''),')','')=? OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(existing_phone,''),'-',''),' ',''),'(',''),')','')=? ORDER BY id DESC LIMIT 1",[$lead['phone'],$lead['phone']]);

   $contactRow=[
    'contact_uid'=>$contact['contact_uid']??$lead['lead_uid'],'lead_uid'=>$lead['lead_uid'],
    'source_type'=>'website_form','lead_type'=>$lead['type'],'name'=>$lead['name'],
    'owner_name'=>$lead['name'],'email'=>$lead['email']?:null,'existing_email'=>$lead['email']?:null,
    'phone'=>$lead['phone']?:null,'existing_phone'=>$lead['phone']?:null,
    'best_email'=>$lead['email']?:null,'best_phone'=>$lead['phone']?:null,
    'property_address'=>$lead['address']?:null,'address'=>$lead['address']?:null,
    'town'=>$lead['town']?:null,'city'=>$lead['town']?:null,'status'=>'new',
    'relationship_status'=>'new_lead','research_status'=>(($lead['phone']&&$lead['email'])?'complete':'queued'),
    'contact_enrichment_status'=>(($lead['phone']&&$lead['email'])?'complete':'queued'),
    'drip_status'=>$lead['email']?'queued':'not_eligible',
    'priority'=>max(1,(int)ceil($lead['lead_score']/20)),'lead_score'=>$lead['lead_score'],
    'route'=>$lead['route'],'notes'=>$lead['message'],
    'evidence'=>'Website form captured directly into internal MySQL at '.date('c'),
    'raw_json'=>g1193_json($raw),'metadata'=>g1193_json(['lead'=>$lead,'internal_only'=>true]),
    'updated_at'=>gdb_now()
   ];
   if($contact){$contactId=(int)$contact['id'];g1193_update('internal_crm_contacts',$contactId,$contactRow);}
   else{$contactRow['created_at']=gdb_now();$contactId=g1193_insert('internal_crm_contacts',$contactRow);}

   $existingLead=null;
   if($lead['email']!=='')$existingLead=gdb_one("SELECT * FROM leads WHERE LOWER(COALESCE(email,''))=? ORDER BY id DESC LIMIT 1",[$lead['email']]);
   if(!$existingLead&&$lead['phone']!=='')$existingLead=gdb_one("SELECT * FROM leads WHERE REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),'-',''),' ',''),'(',''),')','')=? ORDER BY id DESC LIMIT 1",[$lead['phone']]);

   $leadRow=[
    'uid'=>$existingLead['uid']??$lead['lead_uid'],'crm_contact_id'=>$contactId,
    'type'=>$lead['type'],'tag'=>$lead['tag'],'status'=>'new','name'=>$lead['name'],
    'email'=>$lead['email']?:null,'phone'=>$lead['phone']?:null,'address'=>$lead['address']?:null,
    'town'=>$lead['town']?:null,'timeline'=>$lead['timeline']?:null,'goal'=>$lead['goal']?:null,
    'message'=>$lead['message']?:null,'price_range'=>$lead['price_range']?:null,
    'estimated_value'=>$lead['estimated_value']?:null,'budget'=>$lead['budget']?:null,
    'source'=>$lead['source'],'page_url'=>$lead['page_url']?:null,'lead_score'=>$lead['lead_score'],
    'route'=>$lead['route'],'lead_temperature'=>$lead['lead_temperature'],
    'lead_origin'=>'website_form','drip_status'=>$lead['email']?'queued':'not_eligible',
    'raw_payload'=>g1193_json($raw),'updated_at'=>gdb_now()
   ];
   if($existingLead){$leadId=(int)$existingLead['id'];g1193_update('leads',$leadId,$leadRow);}
   else{$leadRow['created_at']=gdb_now();$leadId=g1193_insert('leads',$leadRow);}

   g1193_timeline($lead,$contactId,'Revenue Engine','lead_committed','Website lead committed internally','Contact and lead records were committed before any external service.', ['lead_id'=>$leadId]);
   gdb()->commit();
   return ['ok'=>true,'contact_id'=>$contactId,'lead_id'=>$leadId];
  }catch(Throwable $e){
   if(gdb()->inTransaction())gdb()->rollBack();
   try{g1193_insert('goliath_revenue_engine_failures',['failure_uid'=>g1193_uid('failure'),'lead_uid'=>$lead['lead_uid']??null,'service'=>'internal_crm_commit','severity'=>'critical','message'=>$e->getMessage(),'payload'=>g1193_json(['lead'=>$lead,'raw'=>$raw]),'created_at'=>gdb_now()]);}catch(Throwable $ignored){}
   throw $e;
  }
 }
}
if(!function_exists('g1193_sequence')){
 function g1193_sequence(array $lead):string{
  $type=strtolower($lead['type']??'');
  if(preg_match('/seller|valuation|expired|absentee|inherited/',$type))return 'seller_nurture';
  if(preg_match('/buyer|relocation|guide/',$type))return 'buyer_nurture';
  return 'general_nurture';
 }
}
if(!function_exists('g1193_email_body')){
 function g1193_email_body(array $lead,int $step):array{
  $first=trim(explode(' ',trim($lead['name']??''))[0]??'there')?:'there';
  $type=strtolower($lead['type']??'lead');
  $seller=preg_match('/seller|valuation|expired|absentee|inherited/',$type);
  $subjects=$seller?[
   1=>'Thank you for reaching out — Mark will be calling you shortly',
   2=>'A quick question about your Connecticut property',
   3=>'The biggest mistake remote Connecticut sellers make',
   4=>'A personal check-in from Mark Pires'
  ]:[
   1=>'Thank you for reaching out — Mark will be calling you shortly',
   2=>'Which Fairfield County towns are you considering?',
   3=>'A useful Fairfield County buying shortcut',
   4=>'A personal check-in from Mark Pires'
  ];
  $subject=$subjects[$step]??'A personal follow-up from Mark Pires';
  if($step===1)$body="<p>Hi ".htmlspecialchars($first).",</p><p>Thank you so much for reaching out. Mark will be calling you shortly.</p><p>In the meantime, feel free to reply with anything that would help Mark prepare for the conversation.</p><p>— Jessica<br>Executive Assistant to Mark Pires</p>";
  elseif($seller)$body="<p>Hi ".htmlspecialchars($first).",</p><p>Mark asked me to check in with one useful question: what is the biggest concern you have about the property right now—timing, condition, tenants, repairs, distance, or simply knowing what it may be worth?</p><p>Your answer helps Mark make the conversation more useful and specific.</p><p>— Jessica</p>";
  else $body="<p>Hi ".htmlspecialchars($first).",</p><p>Mark asked me to check which towns, commute needs, and home style matter most to you. Even a short reply helps him prepare a much better search and town comparison.</p><p>— Jessica</p>";
  return ['subject'=>$subject,'html'=>'<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;line-height:1.65">'.$body.'</div>','text'=>strip_tags($body)];
 }
}
if(!function_exists('g1193_seed_drip')){
 function g1193_seed_drip(array $lead,int $contactId,int $leadId):array{
  if(empty($lead['email']))return ['ok'=>false,'reason'=>'no_email','created'=>0];
  $sequence=g1193_sequence($lead);$days=[1=>0,2=>2,3=>7,4=>21];$created=[];
  foreach($days as $step=>$day){
   $content=g1193_email_body($lead,$step);
   $id=g1193_insert('goliath_email_drip_queue',[
    'queue_uid'=>g1193_uid('drip'),'lead_id'=>$leadId,'crm_contact_id'=>$contactId,
    'lead_uid'=>$lead['lead_uid'],'sequence_key'=>$sequence,'step_no'=>$step,
    'scheduled_at'=>date('Y-m-d H:i:s',strtotime("+$day days")),
    'status'=>'pending','subject'=>$content['subject'],'body_html'=>$content['html'],
    'body_text'=>$content['text'],'recipient_email'=>$lead['email'],'recipient_name'=>$lead['name'],
    'metadata_json'=>g1193_json(['source'=>'capture.php','version'=>'119.3']),
    'created_at'=>gdb_now(),'updated_at'=>gdb_now()
   ]);
   if($id)$created[]=$id;
  }
  g1193_update('internal_crm_contacts',$contactId,['drip_status'=>'enrolled','updated_at'=>gdb_now()]);
  g1193_update('leads',$leadId,['drip_status'=>'enrolled','updated_at'=>gdb_now()]);
  g1193_timeline($lead,$contactId,'Jessica','drip_enrolled','Jessica drip campaign enrolled',"$sequence sequence created with ".count($created)." steps.",['queue_ids'=>$created]);
  return ['ok'=>true,'sequence'=>$sequence,'created'=>count($created),'queue_ids'=>$created];
 }
}
if(!function_exists('g1193_enqueue_enrichment')){
 function g1193_enqueue_enrichment(array $lead,int $contactId,int $leadId):array{
  $missingPhone=empty($lead['phone']);$missingEmail=empty($lead['email']);
  if(!$missingPhone&&!$missingEmail)return ['ok'=>true,'needed'=>false];
  $existing=gdb_one("SELECT id,status FROM goliath_contact_enrichment_queue WHERE contact_id=? LIMIT 1",[$contactId]);
  $row=[
   'queue_uid'=>$existing?null:g1193_uid('enrich'),'contact_id'=>$contactId,'lead_id'=>$leadId,
   'owner_name'=>$lead['name'],'property_address'=>$lead['address'],'town'=>$lead['town'],
   'current_phone'=>$lead['phone']?:null,'current_email'=>$lead['email']?:null,
   'missing_phone'=>$missingPhone?1:0,'missing_email'=>$missingEmail?1:0,
   'status'=>'queued','priority'=>max(300,$lead['lead_score']*5),'updated_at'=>gdb_now()
  ];
  if($existing){unset($row['queue_uid']);g1193_update('goliath_contact_enrichment_queue',(int)$existing['id'],$row);$queueId=(int)$existing['id'];}
  else{$row['created_at']=gdb_now();$queueId=g1193_insert('goliath_contact_enrichment_queue',$row);}
  g1193_timeline($lead,$contactId,'Scout','enrichment_queued','Contact enrichment queued','Scout/OpenClaw must research missing phone/email using legitimate public or licensed sources.',['queue_id'=>$queueId]);
  return ['ok'=>true,'needed'=>true,'queue_id'=>$queueId];
 }
}
if(!function_exists('g1193_create_task')){
 function g1193_create_task(string $agent,string $taskType,string $prompt,int $priority,array $metadata=[]):int{
  return g1193_insert('local_ai_tasks',[
   'task_uid'=>g1193_uid('task'),'agent'=>$agent,'executive_key'=>strtolower($agent),
   'task_type'=>$taskType,'type'=>$taskType,'model'=>'goliath-local-worker',
   'prompt'=>$prompt,'status'=>'queued','workflow_state'=>'queued','priority'=>$priority,
   'progress'=>0,'metadata'=>g1193_json($metadata),'metadata_json'=>g1193_json($metadata),
   'created_at'=>gdb_now(),'updated_at'=>gdb_now()
  ]);
 }
}
if(!function_exists('g1193_trigger_team')){
 function g1193_trigger_team(array $lead,int $contactId,int $leadId):array{
  $context="Lead UID: {$lead['lead_uid']}\nContact ID: $contactId\nLead ID: $leadId\nName: {$lead['name']}\nEmail: {$lead['email']}\nPhone: {$lead['phone']}\nAddress: {$lead['address']}\nTown: {$lead['town']}\nType: {$lead['type']}\nTimeline: {$lead['timeline']}\nMessage: {$lead['message']}\nScore: {$lead['lead_score']}\nRoute: {$lead['route']}";
  $tasks=[];
  $tasks['Jessica']=g1193_create_task('Jessica','lead_human_touch',"Create a real immediate relationship deliverable for this lead: acknowledgement, call preparation, reply draft, and next follow-up. Do not fabricate communication as sent.\n\n$context",900,['lead_uid'=>$lead['lead_uid'],'contact_id'=>$contactId,'lead_id'=>$leadId]);
  $tasks['Scout']=g1193_create_task('Scout','lead_contact_enrichment',"Use OpenClaw and legitimate public/licensed research tools to enrich missing phone/email/property context. Never guess contact data. Return sources and confidence.\n\n$context",880,['lead_uid'=>$lead['lead_uid'],'contact_id'=>$contactId,'lead_id'=>$leadId]);
  $tasks['Prospector']=g1193_create_task('Prospector','lead_next_action',"Create the concrete call/text/email next-action package for Mark. This is preparation, not fabricated outreach.\n\n$context",820,['lead_uid'=>$lead['lead_uid'],'contact_id'=>$contactId,'lead_id'=>$leadId]);
  $tasks['Einstein']=g1193_create_task('Einstein','lead_content_opportunity',"Analyze the lead intent and create a personalized content-compounding brief that Shakespeare and Scorsese can execute.\n\n$context",760,['lead_uid'=>$lead['lead_uid'],'contact_id'=>$contactId,'lead_id'=>$leadId]);
  g1193_timeline($lead,$contactId,'Goliath','executive_dispatch','Executive team dispatched','Jessica, Scout, Prospector and Einstein received real tasks.',['tasks'=>$tasks]);
  return $tasks;
 }
}
?>