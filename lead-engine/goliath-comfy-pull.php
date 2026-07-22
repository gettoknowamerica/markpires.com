<?php
require_once __DIR__.'/goliath-comfy-v75-bridge.php';
[$in,$raw]=gc55_in();
if(!gc55_key_ok($in)) gc55_out(['success'=>false,'error'=>'bad_key'],403);
gc55_seed_from_scorsese(10);
$job=gc55_pull();
if(!$job) gc55_out(['success'=>true,'job'=>null,'message'=>'No Scorsese render jobs waiting.']);
gc55_out(['success'=>true,'job'=>$job,'instruction'=>'Render this job locally through ComfyUI if possible. Upload the output to goliath-comfy-upload.php, then update with goliath-comfy-update.php.']);
