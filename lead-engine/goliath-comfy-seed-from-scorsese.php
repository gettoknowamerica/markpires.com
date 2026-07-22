<?php
require_once __DIR__.'/goliath-comfy-v75-bridge.php';
[$in,$raw]=gc55_in();
if(!gc55_key_ok($in)) gc55_out(['success'=>false,'error'=>'bad_key'],403);
$limit=max(1,min(100,(int)($in['limit']??25)));
$created=gc55_seed_from_scorsese($limit);
gc55_out(['success'=>true,'version'=>'V75.5','jobs_created'=>$created,'health'=>gc55_health(),'time'=>date('c')]);
