<?php
/**
 * V95 Deliverable Actions
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
  require_once __DIR__.'/../config.php';
  require_once __DIR__.'/../goliath-db.php';
  require_once __DIR__.'/executive-engine.php';

  $key=$_REQUEST['key']??'';
  $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
  if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

  $id=(int)($_REQUEST['id']??0);
  $action=$_REQUEST['action']??'viewed';
  if(!$id){echo json_encode(['ok'=>false,'error'=>'missing_id']);exit;}

  $row=gdb_one("SELECT * FROM executive_deliverables WHERE id=?",[$id]);
  if(!$row){echo json_encode(['ok'=>false,'error'=>'not_found']);exit;}

  if($action==='viewed'){
    v95_update('executive_deliverables',$id,['viewed'=>1,'viewed_at'=>gdb_now(),'viewed_by'=>'mark','updated_at'=>gdb_now()]);
    v95_event($row['executive_key']??'goliath','deliverable_viewed',$row['title']??'Deliverable viewed','Marked viewed by Mark.', ['deliverable_id'=>$id]);
  } elseif($action==='archive'){
    v95_update('executive_deliverables',$id,['archived'=>1,'viewed'=>1,'viewed_at'=>gdb_now(),'updated_at'=>gdb_now()]);
  } elseif($action==='new'){
    v95_update('executive_deliverables',$id,['viewed'=>0,'viewed_at'=>null,'archived'=>0,'updated_at'=>gdb_now()]);
  }

  echo json_encode(['ok'=>true,'version'=>'V95 Deliverable Actions','id'=>$id,'action'=>$action,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>