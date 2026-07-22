<?php
/**
 * V95 Lead Status Update
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/goliath-db.php';
  require_once __DIR__.'/executive-engine/executive-engine.php';
  $key=$_REQUEST['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
  $contactId=(int)($_REQUEST['contact_id']??0);
  $status=$_REQUEST['status']??'new';
  $allowed=['new','researching','qualified','appointment_scheduled','hot_lead','active_client','longterm_drip','do_not_contact','closed'];
  if(!in_array($status,$allowed,true)){echo json_encode(['ok'=>false,'error'=>'bad_status','allowed'=>$allowed]);exit;}
  if(!$contactId){echo json_encode(['ok'=>false,'error'=>'missing_contact_id']);exit;}
  $flags=[
    'lead_status'=>$status,
    'hot_lead'=>$status==='hot_lead'?1:0,
    'do_not_contact'=>$status==='do_not_contact'?1:0,
    'longterm_drip'=>$status==='longterm_drip'?1:0,
    'campaign_status'=>$status,
    'updated_at'=>gdb_now()
  ];
  v95_update('internal_crm_contacts',$contactId,$flags);
  v95_event('jessica','lead_status_updated','Lead status updated',$status,['contact_id'=>$contactId]);
  echo json_encode(['ok'=>true,'version'=>'V95 Lead Status Update','contact_id'=>$contactId,'status'=>$status,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>