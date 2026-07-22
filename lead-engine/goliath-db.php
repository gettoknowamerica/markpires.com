<?php
/**
 * Goliath Hostinger MySQL Bridge
 * Add constants to lead-engine/config.php:
 * define('GOLIATH_DB_HOST','localhost');
 * define('GOLIATH_DB_NAME','your_database');
 * define('GOLIATH_DB_USER','your_user');
 * define('GOLIATH_DB_PASS','your_password');
 * define('GOLIATH_DB_PORT',3306);
 */
if (!defined('GOLIATH_DB_PORT')) define('GOLIATH_DB_PORT', 3306);

function gdb_enabled(){
  return defined('GOLIATH_DB_HOST') && defined('GOLIATH_DB_NAME') && defined('GOLIATH_DB_USER') && GOLIATH_DB_HOST && GOLIATH_DB_NAME && GOLIATH_DB_USER;
}
function gdb(){
  static $pdo=null;
  if($pdo) return $pdo;
  if(!gdb_enabled()) return null;
  $dsn='mysql:host='.GOLIATH_DB_HOST.';port='.(int)GOLIATH_DB_PORT.';dbname='.GOLIATH_DB_NAME.';charset=utf8mb4';
  try{
    $pdo=new PDO($dsn, GOLIATH_DB_USER, defined('GOLIATH_DB_PASS')?GOLIATH_DB_PASS:'', [
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    return $pdo;
  }catch(Throwable $e){
    error_log('Goliath DB connection failed: '.$e->getMessage());
    return null;
  }
}
function gdb_json($v){ return json_encode($v, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
function gdb_uid($prefix){ return $prefix.'_'.date('YmdHis').'_'.substr(bin2hex(random_bytes(6)),0,12); }
function gdb_now(){ return date('Y-m-d H:i:s'); }
function gdb_all($sql,$params=[]){ $db=gdb(); if(!$db) return []; $st=$db->prepare($sql); $st->execute($params); return $st->fetchAll(); }
function gdb_one($sql,$params=[]){ $rows=gdb_all($sql,$params); return $rows[0]??null; }
function gdb_exec($sql,$params=[]){ $db=gdb(); if(!$db) return false; $st=$db->prepare($sql); return $st->execute($params); }
function gdb_insert($table,$row){
  $db=gdb(); if(!$db) return 0;
  $cols=array_keys($row); $ph=array_map(fn($c)=>':'.$c,$cols);
  $sql='INSERT INTO `'.$table.'` (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',$ph).')';
  $st=$db->prepare($sql); $st->execute($row); return (int)$db->lastInsertId();
}
function gdb_insert_ignore($table,$row){
  $db=gdb(); if(!$db) return 0;
  $cols=array_keys($row); $ph=array_map(fn($c)=>':'.$c,$cols);
  $sql='INSERT IGNORE INTO `'.$table.'` (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',$ph).')';
  $st=$db->prepare($sql); $st->execute($row); return (int)$db->lastInsertId();
}
function gdb_update($table,$row,$where,$params=[]){
  $db=gdb(); if(!$db) return false;
  $sets=[]; foreach($row as $k=>$v){$sets[]='`'.$k.'`=:u_'.$k; $params['u_'.$k]=$v;}
  $sql='UPDATE `'.$table.'` SET '.implode(',',$sets).' WHERE '.$where;
  $st=$db->prepare($sql); return $st->execute($params);
}
function goliath_event($executive,$title,$message='',$priority='normal',$commissionId=null,$url=null){
  if(!gdb_enabled()) return false;
  return gdb_insert('executive_notifications',[
    'notification_uid'=>gdb_uid('note'),
    'executive_key'=>strtolower($executive),
    'commission_id'=>$commissionId,
    'notification_type'=>'activity',
    'title'=>$title,
    'message'=>$message,
    'priority'=>$priority,
    'status'=>'new',
    'action_url'=>$url
  ]);
}
