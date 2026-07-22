<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
require_once __DIR__.'/goliath-internal-crm-v119-3.php';

function r1193_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return trim((string)AFTER_HOURS_CRON_KEY);
 if(defined('RETELL_WEBHOOK_KEY'))return trim((string)RETELL_WEBHOOK_KEY);
 return 'timetomakethedonuts';
}
$key=trim((string)($_GET['key']??''));
if(!hash_equals(r1193_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$name=trim((string)($_GET['name']??'Sofia'));

try{
 $found=[];$sources=[];
 foreach(['internal_crm_contacts','leads'] as $table){
  if(!g1193_table($table))continue;
  $cols=g1193_cols($table);
  $nameCol=isset($cols['name'])?'name':(isset($cols['owner_name'])?'owner_name':null);
  if(!$nameCol)continue;
  $rows=gdb_all("SELECT * FROM `$table` WHERE LOWER(COALESCE(`$nameCol`,'')) LIKE ? ORDER BY id DESC LIMIT 25",['%'.strtolower($name).'%'])?:[];
  foreach($rows as $row){$found[]=['source'=>$table,'row'=>$row];}
 }
 // Search local capture logs for a missed website submission.
 $logCandidates=array_merge(glob(__DIR__.'/logs/*lead*')?:[],glob(__DIR__.'/logs/*capture*')?:[]);
 foreach($logCandidates as $file){
  if(!is_file($file)||filesize($file)>10*1024*1024)continue;
  $content=(string)file_get_contents($file);
  foreach(preg_split('/\R/',$content) as $line){
   if(stripos($line,$name)===false)continue;
   $decoded=json_decode(trim($line),true);
   if(is_array($decoded))$sources[]=['file'=>basename($file),'payload'=>$decoded];
   else $sources[]=['file'=>basename($file),'text'=>mb_substr($line,0,1000)];
  }
 }

 $repaired=[];
 foreach($found as $item){
  $row=$item['row'];
  $data=[
   'name'=>$row['name']??$row['owner_name']??$name,
   'email'=>$row['email']??$row['existing_email']??$row['best_email']??'',
   'phone'=>$row['phone']??$row['existing_phone']??$row['best_phone']??'',
   'address'=>$row['address']??$row['property_address']??'',
   'town'=>$row['town']??$row['city']??'',
   'type'=>$row['type']??$row['lead_type']??'website_lead',
   'message'=>$row['message']??$row['notes']??'',
   'source'=>$row['source']??'internal_repair'
  ];
  $lead=g1193_normalize($data);
  $saved=g1193_save($lead,$data);
  $drip=g1193_seed_drip($lead,(int)$saved['contact_id'],(int)$saved['lead_id']);
  $enrichment=g1193_enqueue_enrichment($lead,(int)$saved['contact_id'],(int)$saved['lead_id']);
  $team=g1193_trigger_team($lead,(int)$saved['contact_id'],(int)$saved['lead_id']);
  $repaired[]=['lead'=>$lead,'saved'=>$saved,'drip'=>$drip,'enrichment'=>$enrichment,'team'=>$team];
 }

 echo json_encode([
  'ok'=>true,'version'=>'V119.3 Lead Repair and Backfill',
  'searched_name'=>$name,'database_matches'=>count($found),'log_matches'=>count($sources),
  'repaired_count'=>count($repaired),'repaired'=>$repaired,'log_evidence'=>$sources,
  'note'=>count($repaired)?'Matching records were normalized into the internal CRM and enrolled.':'No matching database record was found. Review log_evidence and submit the known email/phone to capture.php if needed.',
  'time'=>date('c')
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
 http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>