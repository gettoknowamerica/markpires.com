<?php
require_once __DIR__ . '/lead-brain-core.php';
$key=$_GET['key']??''; $good=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($key!==$good) gb_json(['success'=>false,'error'=>'Bad key'],403);
$leads=gb_supabase('GET','goliath_lead_brain?select=*&order=created_at.desc&limit=50');
$journey=gb_supabase('GET','goliath_lead_journey?select=*&order=stage_order.asc&limit=1000');
$cmd=gb_supabase('GET','goliath_command_bus?select=*&order=created_at.desc&limit=100');
gb_json(['success'=>true,'leads'=>$leads['body']?:[],'journey'=>$journey['body']?:[],'commands'=>$cmd['body']?:[]]);
