<?php
/**
 * Goliath Local Store
 * File-based internal CRM / executive work store. Keeps Goliath running without Supabase.
 */
if(!defined('GOLIATH_LOCAL_STORE')) define('GOLIATH_LOCAL_STORE', true);
function goliath_data_dir(){
  $dir = __DIR__ . '/data';
  if(!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir;
}
function goliath_table_file($table){
  $safe = preg_replace('/[^a-zA-Z0-9_\-]/','_', (string)$table);
  return goliath_data_dir() . '/' . $safe . '.json';
}
function goliath_read_table($table){
  $file = goliath_table_file($table);
  if(!is_file($file)) return [];
  $raw = file_get_contents($file);
  $rows = json_decode($raw, true);
  return is_array($rows) ? $rows : [];
}
function goliath_write_table($table, $rows){
  $file = goliath_table_file($table);
  $tmp = $file . '.tmp';
  file_put_contents($tmp, json_encode(array_values($rows), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  @rename($tmp, $file);
  return true;
}
function goliath_append_row($table, $row){
  $rows = goliath_read_table($table);
  if(empty($row['id'])) $row['id'] = 'gol_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
  $now = gmdate('c');
  if(empty($row['created_at'])) $row['created_at'] = $now;
  $row['updated_at'] = $now;
  $rows[] = $row;
  goliath_write_table($table, $rows);
  return $row;
}
function goliath_upsert_row($table, $id, $patch){
  $rows = goliath_read_table($table);
  $found = false;
  foreach($rows as &$row){
    if((string)($row['id'] ?? '') === (string)$id){
      $row = array_merge($row, $patch);
      $row['updated_at'] = gmdate('c');
      $found = true;
      break;
    }
  }
  unset($row);
  if(!$found){
    $patch['id'] = $id ?: ('gol_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)));
    $patch['created_at'] = gmdate('c');
    $patch['updated_at'] = gmdate('c');
    $rows[] = $patch;
  }
  goliath_write_table($table, $rows);
  return $patch;
}
function goliath_local_select($table, $limit=250){
  $rows = goliath_read_table($table);
  if($table === 'local_ai_tasks' && !$rows){
    $rows = goliath_seed_tasks_from_existing_files();
  }
  if($table === 'goliath_events' && !$rows){
    $rows = goliath_seed_events_from_logs();
  }
  usort($rows, function($a,$b){ return strcmp((string)($b['updated_at']??$b['created_at']??''), (string)($a['updated_at']??$a['created_at']??'')); });
  return array_slice($rows, 0, $limit);
}
function goliath_seed_tasks_from_existing_files(){
  $rows=[];
  $scRaw = __DIR__ . '/../data/scorsese_raw';
  if(is_dir($scRaw)){
    foreach(glob($scRaw.'/*') ?: [] as $file){
      $rows[]=['id'=>'scorsese_'.md5($file),'assigned_agent'=>'Scorsese','title'=>'Raw media intake: '.basename($file),'status'=>'queued','workflow_state'=>'queued','progress'=>8,'current_phase'=>'Raw media uploaded and waiting for production claim','metadata'=>['agent'=>'Scorsese','source_file'=>basename($file)],'created_at'=>date('c',filemtime($file)),'updated_at'=>date('c',filemtime($file))];
    }
  }
  $scoutUploads = __DIR__ . '/../data/scout_uploads';
  if(is_dir($scoutUploads)){
    foreach(glob($scoutUploads.'/*') ?: [] as $file){
      $rows[]=['id'=>'scout_'.md5($file),'assigned_agent'=>'Scout','title'=>'Lead source uploaded: '.basename($file),'status'=>'working','workflow_state'=>'working','progress'=>34,'current_phase'=>'Owner/lead source data queued for enrichment','metadata'=>['agent'=>'Scout','source_file'=>basename($file)],'created_at'=>date('c',filemtime($file)),'updated_at'=>date('c',filemtime($file))];
    }
  }
  $leadCsv = __DIR__ . '/logs/leads_2026-06.csv';
  if(is_file($leadCsv)){
    $count = max(0, count(file($leadCsv)) - 1);
    if($count>0){
      $rows[]=['id'=>'jessica_leads_csv','assigned_agent'=>'Jessica','title'=>$count.' website leads in local CRM log','status'=>'review','workflow_state'=>'review','progress'=>92,'current_phase'=>'New relationship records ready for Human Touch follow-up','metadata'=>['agent'=>'Jessica','lead_count'=>$count],'created_at'=>date('c',filemtime($leadCsv)),'updated_at'=>date('c',filemtime($leadCsv))];
      $rows[]=['id'=>'shakespeare_personalized_content','assigned_agent'=>'Shakespeare','title'=>'Personalized lead content opportunities','status'=>'queued','workflow_state'=>'queued','progress'=>12,'current_phase'=>'Waiting for lead transcript/profile content prompts','metadata'=>['agent'=>'Shakespeare','source'=>'local_leads'],'created_at'=>date('c',filemtime($leadCsv)),'updated_at'=>date('c',filemtime($leadCsv))];
    }
  }
  goliath_write_table('local_ai_tasks',$rows);
  return $rows;
}
function goliath_seed_events_from_logs(){
  $rows=[];
  $log = __DIR__ . '/logs/capture-log-2026-06.json';
  if(is_file($log)){
    foreach(array_slice(file($log), -80) as $line){
      $j=json_decode(trim($line),true); if(!$j) continue;
      $svc=$j['service']??'Goliath';
      $agent = stripos($svc,'email')!==false || stripos($svc,'reply')!==false ? 'Jessica' : (stripos($svc,'csv')!==false ? 'Scout' : 'Goliath');
      $rows[]=['id'=>'event_'.md5($line),'department'=>$agent,'title'=>$svc.' '.$j['status'],'detail'=>$j['detail']??'','status'=>$j['status']??'active','progress'=>($j['status']??'')==='success'?100:20,'confidence'=>($j['status']??'')==='success'?92:45,'created_at'=>$j['timestamp']??gmdate('c'),'updated_at'=>$j['timestamp']??gmdate('c'),'metadata'=>['agent'=>$agent,'local_log'=>true]];
    }
  }
  goliath_write_table('goliath_events',$rows);
  return $rows;
}
