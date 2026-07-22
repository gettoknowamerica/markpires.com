<?php
require_once __DIR__ . '/config.php'; header('Content-Type: application/json');
function cfgx($k,$d=null){return defined($k)?constant($k):$d;}
$key=$_POST['key']??($_GET['key']??''); if($key !== cfgx('AFTER_HOURS_CRON_KEY','timetomakethedonuts')){echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;}
$agent=$_POST['agent']??'Goliath'; $title=$_POST['title']??'Goliath work ready'; $message=$_POST['message']??''; $url=$_POST['ready_url']??'';
function sb($ep,$body){$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_POST=>true,CURLOPT_POSTFIELDS=json_encode($body),CURLOPT_TIMEOUT=>20]);$o=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return [$h,json_decode($o,true),$o];}
[$h,$d,$o]=sb('goliath_notifications',[['agent'=>$agent,'title'=>$title,'message'=>$message,'ready_url'=>$url,'metadata'=>['source'=>'goliath-notify-founder','version'=>'58.10']]]);
echo json_encode(['success'=>$h>=200&&$h<300,'http'=>$h,'notification'=>$d]);
