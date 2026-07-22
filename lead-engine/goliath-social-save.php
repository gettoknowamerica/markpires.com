<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
$key=$_POST['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo 'bad key';exit;}
$pk=trim($_POST['platform_key']??'');
$pn=trim($_POST['platform_name']??$pk);
$u=trim($_POST['username']??'');
$note=trim($_POST['credential_note']??'');
$status=trim($_POST['status']??'disconnected');
$method=trim($_POST['connection_method']??'oauth');
if(!$pk){http_response_code(400);echo 'missing platform';exit;}
$exists=gdb_one('SELECT id FROM goliath_social_accounts WHERE platform_key=? LIMIT 1',[$pk]);
$meta=json_encode(['connection_method'=>$method],JSON_UNESCAPED_SLASHES);
$row=['platform_key'=>$pk,'platform_name'=>$pn,'username'=>$u,'credential_note'=>$note,'status'=>$status,'metadata'=>$meta,'updated_at'=>gdb_now()];
if($exists){gdb_update('goliath_social_accounts',$row,'id=:id',['id'=>(int)$exists['id']]);}
else{gdb_insert('goliath_social_accounts',$row+['created_at'=>gdb_now()]);}
header('Location:/dashboard/goliath-social.php?saved=1');
?>