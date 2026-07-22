<?php
require_once __DIR__.'/scorsese-comfy-bridge.php';
if(!scb_key_ok()) scb_out(['ok'=>false,'error'=>'bad_key'],403);
$h=scb_health(); if(!$h['ok']) scb_out(['ok'=>false,'error'=>'V74 tables missing or DB not configured','health'=>$h],500);
scb_out(['ok'=>true,'jobs_seeded'=>scb_seed_from_scorsese_completions(),'health'=>scb_health(),'time'=>date('c')]);
