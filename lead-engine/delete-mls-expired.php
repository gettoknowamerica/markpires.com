<?php
ini_set('display_errors',0); error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
function sb($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=minimal'],CURLOPT_TIMEOUT=>120]);
  if($p!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b];
}
try{
  $in=json_decode(file_get_contents('php://input'),true); if(!is_array($in))$in=$_POST;
  $key=$in['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY')||!hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
  $mode=$in['mode']??'';
  if($mode==='all'){
    $r=sb('DELETE','mls_expired_records?id=not.is.null');
    echo json_encode(['success'=>$r['ok'],'deleted'=>'all','details'=>$r],JSON_PRETTY_PRINT); exit;
  }
  if($mode==='batch'){
    $batch=trim($in['batch']??'');
    if(!$batch){echo json_encode(['success'=>false,'error'=>'Missing batch']);exit;}
    $r=sb('DELETE','mls_expired_records?import_batch=eq.'.rawurlencode($batch));
    echo json_encode(['success'=>$r['ok'],'deleted_batch'=>$batch,'details'=>$r],JSON_PRETTY_PRINT); exit;
  }
  echo json_encode(['success'=>false,'error'=>'Unknown delete mode']);
}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>