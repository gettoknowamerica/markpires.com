<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
$input=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$key=$input['key'] ?? '';
$good=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($key!==$good){echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}
$id=$input['id']??''; if(!$id){echo json_encode(['success'=>false,'error'=>'missing id']);exit;}
$patch=[];
foreach(['workflow_state','status','claimed_by','assigned_agent','current_phase','next_milestone','blocking_issue','ready_url'] as $k){ if(array_key_exists($k,$input)) $patch[$k]=$input[$k]; }
if(isset($input['progress'])) $patch['progress']=max(0,min(100,(int)$input['progress']));
$patch['last_heartbeat_at']=gmdate('c');
if(($patch['workflow_state']??'')==='complete' || ($patch['status']??'')==='done') $patch['completed_at']=gmdate('c');
$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/local_ai_tasks?id=eq.'.rawurlencode($id));
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>'PATCH',CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_POSTFIELDS=>json_encode($patch),CURLOPT_TIMEOUT=>30]);
$res=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
echo json_encode(['success'=>$http>=200&&$http<300,'http'=>$http,'updated'=>json_decode($res,true)]);
