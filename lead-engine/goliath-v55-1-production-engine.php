<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit; }
function v55_req($method, $endpoint, $body=null){
  $url = rtrim(SUPABASE_URL,'/') . '/rest/v1/' . ltrim($endpoint,'/');
  $headers = ['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>40]);
  if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
  $raw = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
  $json = json_decode($raw, true);
  return ['ok'=>($http>=200 && $http<300), 'http'=>$http, 'data'=>is_array($json)?$json:$raw, 'raw'=>$raw, 'error'=>$err];
}
function v55_first_json($value){
  if (is_array($value)) return $value;
  if (is_object($value)) return json_decode(json_encode($value), true);
  $text = trim((string)$value); if ($text==='') return null;
  $try = json_decode($text, true); if (is_array($try)) return $try;
  if (preg_match('/```json\s*([\s\S]*?)```/i', $text, $m)) { $try=json_decode(trim($m[1]),true); if(is_array($try)) return $try; }
  $s = strpos($text,'{'); $e = strrpos($text,'}');
  if ($s!==false && $e!==false && $e>$s) { $try=json_decode(substr($text,$s,$e-$s+1),true); if(is_array($try)) return $try; }
  return null;
}
function v55_extract_result($task){
  $r = $task['result'] ?? null;
  if (is_string($r)) { $j=v55_first_json($r); if($j) return $j; }
  if (is_array($r)) {
    foreach (['json','output_json','content_json','response','data'] as $k) if(isset($r[$k])) { $j=v55_first_json($r[$k]); if($j) return $j; }
    foreach (['output','text','message','content','raw'] as $k) if(isset($r[$k])) { $j=v55_first_json($r[$k]); if($j) return $j; }
    return $r;
  }
  return null;
}
function v55_text($v){ return (is_array($v)||is_object($v)) ? json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : trim((string)$v); }
function v55_score($impact){ $i=(int)$impact; if($i<1)$i=1; if($i>5)$i=5; return $i*20; }
$limit = max(1, min(50, (int)($_GET['limit'] ?? 25)));
$tasksRes = v55_req('GET', 'local_ai_tasks?select=*&task_type=eq.v55_deliverable_commission&status=in.(done,completed)&order=updated_at.asc&limit='.$limit);
$tasks = is_array($tasksRes['data']) ? $tasksRes['data'] : [];
$created=[]; $skipped=[]; $handoffs=[]; $errors=[];
foreach($tasks as $task){
  $taskId=$task['id']??''; $meta=$task['metadata']??[]; if(is_string($meta)) $meta=json_decode($meta,true)?:[];
  $agent=$meta['agent']??'Goliath'; $version=(string)($meta['version']??'');
  if(strpos($version,'55')!==0){$skipped[]=['task'=>$taskId,'reason'=>'not_v55'];continue;}
  $dup=v55_req('GET','goliath_deliverables?select=id&source_task_id=eq.'.rawurlencode($taskId).'&limit=1');
  if(!empty($dup['data'][0]['id'])){$skipped[]=['task'=>$taskId,'reason'=>'already_promoted'];continue;}
  $out=v55_extract_result($task); if(!$out||!is_array($out)){$skipped[]=['task'=>$taskId,'reason'=>'no_parseable_result'];continue;}
  $asset=$out['asset']??[]; $workProduct=is_array($asset)?($asset['work_product']??$asset):$asset;
  $deliverableType=$meta['deliverable_type']??(is_array($asset)?($asset['type']??'deliverable'):'deliverable');
  $impact=(int)($out['business_impact']??1); $commissionId=$meta['commission_id']??($out['commission_id']??null); $nextAgent=$out['next_agent']??($meta['next_agent']??null);
  $row=['department'=>$agent,'executive'=>$agent,'commission_id'=>$commissionId,'source_task_id'=>$taskId,'deliverable_type'=>$deliverableType,'title'=>$out['title']??($meta['title']??$agent.' Deliverable'),'summary'=>$out['summary']??v55_text($workProduct),'status'=>!empty($out['ready_for_founder'])?'ready_for_review':'working','priority'=>(int)($task['priority']??50),'score'=>v55_score($impact),'business_impact'=>$impact,'asset_json'=>is_array($asset)?$asset:['work_product'=>$asset],'ready_for_founder'=>!empty($out['ready_for_founder']),'next_agent'=>$nextAgent,'next_action'=>$out['next_action']??'','handoff_notes'=>$out['handoff_notes']??'','payload'=>['v55'=>true,'agent'=>$agent,'version'=>$version,'commission_id'=>$commissionId,'deliverable_type'=>$deliverableType,'content_json'=>$out,'content_text'=>v55_text($workProduct),'business_impact'=>$impact,'next_agent'=>$nextAgent,'next_action'=>$out['next_action']??'','handoff_notes'=>$out['handoff_notes']??''],'completed_at'=>date('c')];
  $ins=v55_req('POST','goliath_deliverables',$row); if(!$ins['ok']){$errors[]=['task'=>$taskId,'stage'=>'deliverable_insert','http'=>$ins['http'],'error'=>$ins['data']];continue;}
  $deliverable=is_array($ins['data'])&&isset($ins['data'][0])?$ins['data'][0]:null; $created[]=['task'=>$taskId,'agent'=>$agent,'deliverable_id'=>$deliverable['id']??null,'title'=>$row['title'],'next_agent'=>$nextAgent];
  if($nextAgent && strtolower((string)$nextAgent)!=='founder'){
    $hrow=['commission_id'=>$commissionId,'deliverable_id'=>$deliverable['id']??null,'source_task_id'=>$taskId,'from_executive'=>$agent,'to_executive'=>$nextAgent,'next_action'=>$row['next_action'],'handoff_notes'=>$row['handoff_notes'],'status'=>'ready','payload'=>$row['payload']];
    $hi=v55_req('POST','goliath_handoffs',$hrow); if($hi['ok']) $handoffs[]=['from'=>$agent,'to'=>$nextAgent,'deliverable_id'=>$deliverable['id']??null];
  }
  if($commissionId) v55_req('PATCH','goliath_commissions?commission_id=eq.'.rawurlencode($commissionId),['status'=>'deliverable_ready','updated_at'=>date('c')]);
}
$today=date('Y-m-d'); $readyRes=v55_req('GET','goliath_deliverables?select=*&ready_for_founder=eq.true&order=score.desc,created_at.desc&limit=25'); $readyItems=is_array($readyRes['data'])?$readyRes['data']:[];
$brief=['brief_date'=>$today,'title'=>'Good Morning Mark — Executive Council Prepared Today\'s Work','summary'=>count($readyItems).' ready deliverable(s) prepared for founder review.','status'=>'ready','ready_count'=>count($readyItems),'queued_count'=>0,'top_priority'=>$readyItems[0]['title']??'Review overnight deliverables','founder_actions'=>array_values(array_filter(array_map(function($x){return $x['next_action']??null;},$readyItems))),'deliverables'=>array_map(function($x){return ['id'=>$x['id']??null,'executive'=>$x['department']??'','title'=>$x['title']??'','type'=>$x['deliverable_type']??'','score'=>$x['score']??0];},$readyItems),'raw_payload'=>['created_now'=>$created,'handoffs'=>$handoffs]];
v55_req('POST','goliath_morning_briefs',$brief);
echo json_encode(['success'=>true,'version'=>'55.1','processed_count'=>count($tasks),'created_count'=>count($created),'created'=>$created,'handoffs'=>$handoffs,'skipped'=>$skipped,'errors'=>$errors,'next'=>'Open Mission Control or Executive Brief after local worker completes queued v55 commissions.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
