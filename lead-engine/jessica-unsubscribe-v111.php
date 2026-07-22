<?php
declare(strict_types=1);ini_set('display_errors','0');require_once __DIR__.'/config.php';require_once __DIR__.'/goliath-db.php';
$email=strtolower(trim((string)($_GET['email']??'')));$token=(string)($_GET['token']??'');$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
$valid=$email!==''&&hash_equals(hash_hmac('sha256',$email,(string)$key),$token);
header('Content-Type:text/html; charset=utf-8');
if(!$valid){http_response_code(400);echo '<h1>Invalid unsubscribe link.</h1>';exit;}
$x=gdb_one("SELECT id FROM jessica_suppression_v111 WHERE email=?",[$email]);if(!$x)gdb_insert('jessica_suppression_v111',['email'=>$email,'reason'=>'recipient_unsubscribed','created_at'=>gdb_now()]);
echo '<!doctype html><meta name="viewport" content="width=device-width"><body style="font-family:Arial;padding:40px;background:#08111f;color:white"><h1>You have been unsubscribed.</h1><p>No further automated campaign emails will be sent to '.htmlspecialchars($email,ENT_QUOTES,'UTF-8').'.</p></body>';
?>