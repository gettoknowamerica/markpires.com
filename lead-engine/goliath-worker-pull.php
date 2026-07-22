<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
$in=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$in['key']??'';$good=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($key!==$good){echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
$worker=$in['worker']??'local-worker';
function sb($method,$ep,$body=null){$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>30]);if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));$b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return[$h,json_decode($b,true),$b];}
list($h,$rows,$raw)=sb('GET','local_ai_tasks?select=*&workflow_state=eq.dispatched&order=priority.desc,created_at.asc&limit=1');
if(!is_array($rows)||!count($rows)){echo json_encode(['success'=>true,'task'=>null]);exit;}
$t=$rows[0];$id=$t['id'];
$patch=['workflow_state'=>'claimed','status'=>'claimed','claimed_by'=>$worker,'progress'=>5,'current_phase'=>'Claimed by '.$worker,'next_milestone'=>'Begin work','last_heartbeat_at'=>gmdate('c')];
list($ph,$updated,$praw)=sb('PATCH','local_ai_tasks?id=eq.'.rawurlencode($id).'&workflow_state=eq.dispatched',$patch);
if($ph<200||$ph>=300||!is_array($updated)||!count($updated)){echo json_encode(['success'=>false,'error'=>'claim_failed','http'=>$ph,'raw'=>$praw]);exit;}
echo json_encode(['success'=>true,'task'=>$updated[0]],JSON_UNESCAPED_SLASHES);
