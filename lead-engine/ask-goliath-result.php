<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
$key=$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'bad_key']);exit;}
$id=(int)($_GET['id']??0); if(!$id){echo json_encode(['success'=>false,'error'=>'missing_id']);exit;}
$row=gdb_one('SELECT * FROM local_ai_tasks WHERE id=? LIMIT 1',[$id]); if(!$row){echo json_encode(['success'=>false,'error'=>'not_found']);exit;}
$status=strtolower((string)($row['status']??'')); $done=in_array($status,['completed','complete','done']);
$answer=''; $res=$row['result']??''; if(is_string($res)){ $j=json_decode($res,true); if(is_array($j)) $answer=$j['output']??($j['answer']??json_encode($j)); else $answer=$res; }
echo json_encode(['success'=>true,'done'=>$done,'status'=>$status,'answer'=>$answer]);
?>
