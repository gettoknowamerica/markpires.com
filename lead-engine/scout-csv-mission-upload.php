<?php
/**
 * Goliath V93.2 Scout CSV Mission Upload
 * Upload to /public_html/lead-engine/scout-csv-mission-upload.php
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
require_once __DIR__.'/scout-intelligence-helpers.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_POST['key']??$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$title=scout_clean($_POST['mission_title']??'Scout Upload '.date('Y-m-d H:i'));
$type=scout_clean($_POST['mission_type']??'custom');
$priority=(int)($_POST['priority']??5);
if(empty($_FILES['csv_file']['tmp_name'])){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'missing_csv_file']);exit;}
$dir=dirname(__DIR__).'/data/scout_uploads'; if(!is_dir($dir))@mkdir($dir,0755,true);
$original=basename($_FILES['csv_file']['name']);
$safe=date('Ymd-His').'-'.preg_replace('/[^a-zA-Z0-9._-]+/','-',$original);
$target=$dir.'/'.$safe;
if(!move_uploaded_file($_FILES['csv_file']['tmp_name'],$target)){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'upload_failed']);exit;}

$missionId=scout_insert('scout_intel_missions',[
  'mission_uid'=>scout_uid('mission'),'title'=>$title,'mission_type'=>$type,'source_file'=>$target,'original_filename'=>$original,
  'priority'=>$priority,'status'=>'queued','notes'=>scout_clean($_POST['notes']??''),'metadata'=>scout_json(['uploaded_by'=>'mission_control','file'=>$safe]),
  'created_at'=>gdb_now(),'updated_at'=>gdb_now()
]);

$rows=0;$imported=0;$headers=[];
if(($fh=fopen($target,'r'))!==false){
  $headers=fgetcsv($fh);
  if(!is_array($headers))$headers=[];
  $headers=array_map(function($h){return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/','_', (string)$h),'_'));},$headers);
  while(($data=fgetcsv($fh))!==false){
    $rows++;
    $r=[];
    foreach($headers as $i=>$h){$r[$h]=$data[$i]??'';}
    $owner=scout_pick($r,['owner_name','owner','name','full_name','contact_name']);
    $address=scout_pick($r,['property_address','address','site_address','situs_address','location']);
    $town=scout_pick($r,['town','city','municipality']);
    $phone=scout_pick($r,['phone','phone_number','mobile','cell','telephone']);
    $email=scout_pick($r,['email','email_address']);
    if(!$owner && !$address && !$phone && !$email) continue;
    $contactRow=[
      'contact_uid'=>scout_uid('contact'),'source_type'=>'scout_csv_upload','lead_type'=>$type,'name'=>$owner,'owner_name'=>$owner,
      'property_address'=>$address,'address'=>$address,'mailing_address'=>scout_pick($r,['mailing_address','mail_address']),
      'town'=>$town,'city'=>$town,'state'=>scout_pick($r,['state']),'zip'=>scout_pick($r,['zip','zipcode','postal_code']),
      'phone'=>$phone,'existing_phone'=>$phone,'email'=>$email,'existing_email'=>$email,'status'=>'queued','relationship_status'=>'research_target',
      'research_status'=>'queued','priority'=>$priority,'notes'=>scout_pick($r,['notes','remarks','description']),
      'evidence'=>'Uploaded via Scout CSV mission '.$title.' row '.$rows,'raw_json'=>scout_json($r),
      'metadata'=>scout_json(['scout_mission_id'=>$missionId,'source_file'=>$safe,'row'=>$rows]),'created_at'=>gdb_now(),'updated_at'=>gdb_now()
    ];
    $contactId=scout_insert('internal_crm_contacts',$contactRow);
    if($contactId){$imported++;}
  }
  fclose($fh);
}
scout_update('scout_intel_missions',$missionId,['total_records'=>$rows,'imported_records'=>$imported,'status'=>'queued','updated_at'=>gdb_now()]);
scout_event($missionId,null,null,'csv_uploaded','Scout CSV mission uploaded',"Imported {$imported} of {$rows} rows.",['file'=>$safe,'headers'=>$headers]);

echo json_encode(['ok'=>true,'version'=>'V93.2 Scout CSV Upload','mission_id'=>$missionId,'rows_seen'=>$rows,'imported'=>$imported,'file'=>$safe,'next'=>'Run scout-run-cycle.php?key=...&limit=20'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>