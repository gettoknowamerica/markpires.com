<?php
require_once __DIR__.'/scorsese-comfy-bridge.php';
if(!scb_key_ok()) scb_out(['success'=>false,'error'=>'bad_key'],403);
$h=scb_health(); if(!$h['ok']) scb_out(['success'=>false,'error'=>'V74 tables missing or DB not configured','health'=>$h],500);
$job=scb_pull_next();
if(!$job) scb_out(['success'=>true,'job'=>null,'message'=>'No Scorsese ComfyUI jobs queued.']);
scb_out(['success'=>true,'job'=>[
  'id'=>(int)$job['id'],
  'title'=>$job['title'],
  'prompt'=>$job['prompt'],
  'workflow_json'=>$job['workflow_json'],
  'media_type'=>$job['media_type']??'video',
  'priority'=>(int)($job['priority']??80),
  'status'=>'working'
]]);
