<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/goliath-v75-3-production-engine.php';
$key=$_GET['key']??($_POST['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){ http_response_code(403); echo json_encode(['ok'=>false,'error'=>'bad_key']); exit; }
g753_install_schema();
function scout_norm($s){ return strtolower(preg_replace('/[^a-z0-9]+/','_',trim((string)$s))); }
function scout_pick($row,$map,$keys){ foreach($keys as $k){ if(isset($map[$k]) && isset($row[$map[$k]])) return trim((string)$row[$map[$k]]); } return ''; }
if(empty($_FILES['csv']['tmp_name'])){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_csv_file']); exit; }
$tmp=$_FILES['csv']['tmp_name']; $name=$_FILES['csv']['name']??'scout-upload.csv';
$fh=fopen($tmp,'r'); if(!$fh){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'cannot_read_file']); exit; }
$headers=fgetcsv($fh); if(!$headers){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'empty_csv']); exit; }
$map=[]; foreach($headers as $i=>$h){ $map[scout_norm($h)]=$i; }
$batchId=g753_insert_filtered('scout_import_batches',['batch_uid'=>gdb_uid('scout_batch'),'filename'=>$name,'source_type'=>'scout_csv_upload','mapped_fields'=>g753_json($map),'status'=>'imported','notes'=>'Uploaded absentee/owner/contact research list for Scout.']);
$count=0; $samples=[];
while(($row=fgetcsv($fh))!==false){
  $owner=scout_pick($row,$map,['owner','owner_name','name','full_name','property_owner','taxpayer','taxpayer_name','mailing_name']);
  $addr=scout_pick($row,$map,['property_address','situs_address','address','site_address','location','property_location']);
  $mail=scout_pick($row,$map,['mailing_address','owner_address','mail_address','taxpayer_address']);
  $town=scout_pick($row,$map,['town','city','municipality']);
  $state=scout_pick($row,$map,['state','owner_state']);
  $zip=scout_pick($row,$map,['zip','zipcode','postal_code','owner_zip']);
  $phone=scout_pick($row,$map,['phone','phone_number','owner_phone','telephone']);
  $email=scout_pick($row,$map,['email','email_address','owner_email']);
  if(!$owner && !$addr && !$mail) continue;
  $rid=g753_insert_filtered('scout_import_records',['batch_id'=>$batchId,'owner_name'=>$owner,'property_address'=>$addr,'mailing_address'=>$mail,'town'=>$town,'state'=>$state,'zip'=>$zip,'phone'=>$phone,'email'=>$email,'record_status'=>($phone||$email)?'has_contact_needs_verification':'needs_research','priority'=>90,'raw_payload'=>g753_json(array_combine($headers,array_pad($row,count($headers),'')))]);
  if($rid){ $count++; if(count($samples)<5)$samples[]=['owner'=>$owner,'address'=>$addr,'mailing'=>$mail]; }
}
fclose($fh);
g753_update_filtered('scout_import_batches',['record_count'=>$count],'id=:id',['id'=>$batchId]);
$commissionId=0;
if($count>0 && g753_table('executive_commissions')){
  $prompt="SCOUT CSV IMPORT PRODUCTION MISSION\n\nA new owner/absentee/property list was uploaded. Batch ID: {$batchId}. Records imported: {$count}.\n\nScout mission:\n1. Inspect the imported records.\n2. Prioritize records missing phone/email.\n3. Create a research plan using available scraper/browser tools.\n4. Identify the first 25 records to enrich.\n5. Create Jessica handoff language for high-value sellers once verified.\n\nReturn a ranked work packet. Do not invent phone numbers. Mark found this list from MLS/backend owner data and wants Scout to turn it into real seller opportunity intelligence.";
  $commissionId=g753_insert_filtered('executive_commissions',['commission_uid'=>gdb_uid('com'),'executive_key'=>'scout','title'=>'Research uploaded owner list: '.$name,'commission_type'=>'scout_csv_import','status'=>'queued','priority'=>99,'progress'=>0,'current_task'=>'Research uploaded owner/contact list','prompt'=>$prompt,'metadata'=>g753_json(['batch_id'=>$batchId,'records'=>$count,'source'=>'scout_csv_import'])]);
}
echo json_encode(['ok'=>true,'batch_id'=>$batchId,'records_imported'=>$count,'commission_id'=>$commissionId,'sample'=>$samples,'next'=>'Desktop worker will pull Scout commission if running.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>
