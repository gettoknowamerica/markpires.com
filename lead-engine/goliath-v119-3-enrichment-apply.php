<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
require_once __DIR__.'/goliath-internal-crm-v119-3.php';

function a1193_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
function a1193_parse(string $raw):array{
 $j=json_decode(trim($raw),true);if(is_array($j)){
  foreach(['output','result','content'] as $k)if(isset($j[$k])&&is_string($j[$k])){$nested=a1193_parse($j[$k]);if($nested)return $nested;}
  return $j;
 }
 $a=strpos($raw,'{');$b=strrpos($raw,'}');if($a!==false&&$b>$a){$j=json_decode(substr($raw,$a,$b-$a+1),true);if(is_array($j))return $j;}
 return [];
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(a1193_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$limit=max(1,min(300,(int)($_GET['limit']??100)));

try{
 $tasks=gdb_all("SELECT * FROM local_ai_tasks
  WHERE task_type='scout_openclaw_contact_enrichment' AND status IN ('complete','completed')
  ORDER BY id ASC LIMIT $limit")?:[];
 $applied=[];$guarded=[];
 foreach($tasks as $task){
  $meta=json_decode((string)($task['metadata_json']??$task['metadata']??''),true);if(!is_array($meta))$meta=[];
  $result=a1193_parse((string)($task['result']??''));
  $queueId=(int)($result['queue_id']??$meta['queue_id']??0);
  $contactId=(int)($result['contact_id']??$meta['contact_id']??0);
  if($queueId<1||$contactId<1)continue;
  $phone=g1193_phone($result['phone']??'');$email=strtolower(g1193_clean($result['email']??''));
  $status=strtolower(g1193_clean($result['status']??'not_found'));
  $evidence=g1193_clean($result['source_evidence']??'');
  $urls=$result['source_urls']??[];
  $notes=g1193_clean($result['notes']??'');
  if($phone===''&&$email===''){
   g1193_update('goliath_contact_enrichment_queue',$queueId,[
    'status'=>$status==='needs_tool_access'?'needs_tool_access':'not_found',
    'source_evidence'=>$evidence,'error_message'=>$notes,'updated_at'=>gdb_now()
   ]);
   $guarded[]=['task_id'=>(int)$task['id'],'queue_id'=>$queueId,'reason'=>'no_verified_contact'];
   continue;
  }
  $phoneConfidence=(int)($result['phone_confidence']??($phone?70:0));
  $emailConfidence=(int)($result['email_confidence']??($email?70:0));
  $contact=gdb_one("SELECT * FROM internal_crm_contacts WHERE id=? LIMIT 1",[$contactId])?:[];
  g1193_update('internal_crm_contacts',$contactId,[
   'best_phone'=>$phone?:($contact['best_phone']??$contact['phone']??null),
   'best_email'=>$email?:($contact['best_email']??$contact['email']??null),
   'phone'=>$phone?:($contact['phone']??null),'email'=>$email?:($contact['email']??null),
   'research_status'=>'ready_for_mark','contact_enrichment_status'=>'candidate_found',
   'evidence'=>$evidence,'updated_at'=>gdb_now()
  ]);
  g1193_update('goliath_contact_enrichment_queue',$queueId,[
   'status'=>'ready_for_mark','best_phone'=>$phone?:null,'best_email'=>$email?:null,
   'phone_confidence'=>$phoneConfidence,'email_confidence'=>$emailConfidence,
   'source_evidence'=>trim($evidence."\n".(is_array($urls)?implode("\n",$urls):'')),
   'error_message'=>$notes,'updated_at'=>gdb_now()
  ]);
  $lead=['lead_uid'=>$contact['lead_uid']??'', 'name'=>$contact['name']??$contact['owner_name']??'', 'email'=>$email, 'phone'=>$phone];
  g1193_timeline($lead,$contactId,'Scout','contact_enriched','Candidate phone/email found','Verify evidence before outreach.',['queue_id'=>$queueId,'phone_confidence'=>$phoneConfidence,'email_confidence'=>$emailConfidence,'source_urls'=>$urls]);
  $applied[]=['task_id'=>(int)$task['id'],'queue_id'=>$queueId,'contact_id'=>$contactId,'phone'=>$phone,'email'=>$email];
 }
 echo json_encode(['ok'=>true,'version'=>'V119.3 Apply Enrichment','applied_count'=>count($applied),'guarded_count'=>count($guarded),'applied'=>$applied,'guarded'=>$guarded,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>