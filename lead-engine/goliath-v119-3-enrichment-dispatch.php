<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
require_once __DIR__.'/goliath-internal-crm-v119-3.php';

function e1193_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(e1193_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$limit=max(1,min(100,(int)($_GET['limit']??25)));

try{
 // Backfill missing-contact queue from all internal contacts.
 $contacts=gdb_all("SELECT * FROM internal_crm_contacts
  WHERE (COALESCE(best_phone,phone,existing_phone,'')='' OR COALESCE(best_email,email,existing_email,'')='')
  ORDER BY priority DESC,created_at ASC LIMIT $limit")?:[];
 $backfilled=0;
 foreach($contacts as $c){
  $lead=[
   'lead_uid'=>$c['lead_uid']??g1193_uid('lead'),'name'=>$c['name']??$c['owner_name']??'',
   'email'=>$c['best_email']??$c['email']??$c['existing_email']??'',
   'phone'=>$c['best_phone']??$c['phone']??$c['existing_phone']??'',
   'address'=>$c['property_address']??$c['address']??'','town'=>$c['town']??$c['city']??'',
   'type'=>$c['lead_type']??'contact','lead_score'=>(int)($c['lead_score']??50)
  ];
  $result=g1193_enqueue_enrichment($lead,(int)$c['id'],0);
  if(!empty($result['needed']))$backfilled++;
 }

 $rows=gdb_all("SELECT q.*,c.name,c.owner_name,c.property_address c_address,c.town c_town
  FROM goliath_contact_enrichment_queue q
  JOIN internal_crm_contacts c ON c.id=q.contact_id
  WHERE q.status IN ('queued','retry')
    AND NOT EXISTS(
      SELECT 1 FROM local_ai_tasks t
      WHERE t.task_type='scout_openclaw_contact_enrichment'
        AND t.status IN ('queued','working','claimed')
        AND (JSON_UNQUOTE(JSON_EXTRACT(COALESCE(t.metadata_json,t.metadata),'$.queue_id'))=CAST(q.id AS CHAR))
    )
  ORDER BY q.priority DESC,q.created_at ASC LIMIT $limit")?:[];

 $created=[];
 foreach($rows as $r){
  $owner=trim((string)($r['owner_name']?:$r['name']));
  $address=trim((string)($r['property_address']?:$r['c_address']));
  $town=trim((string)($r['town']?:$r['c_town']));
  $queries=array_values(array_filter([
   $owner?'"'.$owner.'" "'.$town.'" phone email':'',
   ($owner&&$address)?'"'.$owner.'" "'.$address.'" contact':'',
   $address?'"'.$address.'" owner phone':'',
   $owner?'"'.$owner.'" LinkedIn '.$town:'',
  ]));
  $prompt="SCOUT + OPENCLAW CONTACT ENRICHMENT\n\nQueue ID: {$r['id']}\nContact ID: {$r['contact_id']}\nName/Owner: $owner\nProperty: $address\nTown: $town\nMissing phone: {$r['missing_phone']}\nMissing email: {$r['missing_email']}\n\nUse available browser/search/scraping tools including OpenClaw, Browser Use, Firecrawl/Crawl4AI, Newspaper4k/Trafilatura/BeautifulSoup, and approved public/licensed data sources. Never guess or fabricate a phone number or email. Respect access rules and return source URLs/evidence.\n\nSearch intentions:\n- ".implode("\n- ",$queries)."\n\nRETURN ONLY JSON:\n{\"queue_id\":{$r['id']},\"contact_id\":{$r['contact_id']},\"status\":\"found|not_found|needs_tool_access\",\"phone\":\"\",\"email\":\"\",\"phone_confidence\":0,\"email_confidence\":0,\"source_evidence\":\"\",\"source_urls\":[],\"notes\":\"\"}";
  $taskId=g1193_create_task('Scout','scout_openclaw_contact_enrichment',$prompt,(int)$r['priority'],[
   'queue_id'=>(int)$r['id'],'contact_id'=>(int)$r['contact_id'],'queries'=>$queries,'requires_openclaw'=>true
  ]);
  g1193_update('goliath_contact_enrichment_queue',(int)$r['id'],[
   'status'=>'working','local_task_id'=>$taskId,'attempts'=>(int)$r['attempts']+1,'search_urls_json'=>g1193_json($queries),'updated_at'=>gdb_now()
  ]);
  $created[]=['queue_id'=>(int)$r['id'],'contact_id'=>(int)$r['contact_id'],'task_id'=>$taskId,'name'=>$owner];
 }
 echo json_encode(['ok'=>true,'version'=>'V119.3 Scout OpenClaw Enrichment','queue_backfilled'=>$backfilled,'tasks_created'=>count($created),'created'=>$created,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>