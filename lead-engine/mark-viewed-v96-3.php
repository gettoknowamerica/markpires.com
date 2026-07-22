<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_POST['key']??($_GET['key']??''); $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 $table=$_POST['table']??($_GET['table']??'relationship_timeline'); $id=(int)($_POST['id']??($_GET['id']??0));
 $allowed=['relationship_timeline','daily_briefs','executive_deliverables'];
 if(!in_array($table,$allowed,true)||!$id){echo json_encode(['ok'=>false,'error'=>'bad_request']);exit;}
 $pdo=gdb();
 if($table==='relationship_timeline'){$st=$pdo->prepare("UPDATE relationship_timeline SET is_new=0, viewed_at=NOW() WHERE id=?");}
 elseif($table==='daily_briefs'){$st=$pdo->prepare("UPDATE daily_briefs SET viewed=1, viewed_at=NOW(), status='viewed' WHERE id=?");}
 else {$st=$pdo->prepare("UPDATE executive_deliverables SET viewed=1, viewed_at=NOW() WHERE id=?");}
 $st->execute([$id]);
 echo json_encode(['ok'=>true,'version'=>'V96.3 Mark Viewed','table'=>$table,'id'=>$id,'time'=>date('c')],JSON_PRETTY_PRINT);
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_PRETTY_PRINT);}
?>