<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
$in=json_decode(file_get_contents('php://input'),true)?:$_POST;
$key=(string)($in['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$service=preg_replace('/[^a-z0-9_-]/i','',(string)($in['service_key']??''));
if($service===''){echo json_encode(['ok'=>false,'error'=>'missing_service_key']);exit;}
$row=['status'=>(string)($in['status']??'online'),'endpoint'=>(string)($in['endpoint']??''),'details'=>(string)($in['details']??''),'last_seen_at'=>gdb_now(),'updated_at'=>gdb_now()];
$x=gdb_one("SELECT id FROM goliath_local_service_status_v111 WHERE service_key=?",[$service]);
if($x)gdb_update('goliath_local_service_status_v111',$row,'id=:id',['id'=>$x['id']]);else{$row['service_key']=$service;gdb_insert('goliath_local_service_status_v111',$row);}
echo json_encode(['ok'=>true,'service_key'=>$service]);
?>