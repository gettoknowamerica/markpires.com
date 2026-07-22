<?php
require_once __DIR__.'/goliath-comfy-v75-bridge.php';
[$in,$raw]=gc55_in();
if(!gc55_key_ok($in)) gc55_out(['success'=>false,'error'=>'bad_key'],403);
gc55_out(gc55_health());
