<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
require_once __DIR__.'/goliath-action-ledger.php';
$key=$_GET['key']??$_POST['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403); echo json_encode(['ok'=>false,'error'=>'bad_key']); exit;}
$exec=strtolower(preg_replace('/[^a-z]/','',$_POST['executive']??'goliath'));
$suggestion=trim((string)($_POST['suggestion']??''));
if($suggestion===''){header('Location: '.($_SERVER['HTTP_REFERER']??'/dashboard/goliath-executive-offices.php')); exit;}
$title='Founder suggestion for '.ucfirst($exec);
if(gdb_enabled() && function_exists('gal_action')) gal_action(ucfirst($exec),'founder_suggestion',$title,$suggestion,'open',0,['source'=>'executive_workstation_prompt']);
if(gdb_enabled() && function_exists('gdb_insert')){try{gdb_insert('executive_commissions',['commission_uid'=>gdb_uid('com'),'executive_key'=>$exec,'title'=>$title,'commission_type'=>'founder_suggestion','status'=>'queued','priority'=>99,'progress'=>0,'current_task'=>$suggestion,'metadata'=>gdb_json(['source'=>'workstation_prompt'])]);}catch(Throwable $e){}}
header('Location: '.($_SERVER['HTTP_REFERER']??'/dashboard/executive-office.php?exec='.$exec));
