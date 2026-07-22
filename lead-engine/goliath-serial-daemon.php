<?php
/**
 * Goliath Omni V45.3 — Hermes/OpenClaw serial poll endpoint
 * Your local Hermes Desktop/OpenClaw runner can poll this URL and process queued tasks nonstop.
 * GET /lead-engine/goliath-serial-daemon.php?key=KEY&limit=8
 */
require_once __DIR__.'/config.php';header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key'] ?? '';$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';if($key!==$expected){http_response_code(403);echo json_encode(['success'=>false,'error'=>'bad_key']);exit;}
$limit=max(1,min(25,(int)($_GET['limit']??8)));
function sb_get($ep){$ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>25]);$b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);return [$http,is_array($d)?$d:[],$b];}
[$http,$tasks,$raw]=sb_get('local_ai_tasks?select=*&status=eq.queued&order=priority.desc,created_at.asc&limit='.$limit);
echo json_encode(['success'=>$http>=200&&$http<300,'mode'=>'serial','runner'=>'Hermes Desktop or OpenClaw local worker','instructions'=>'Poll this endpoint nonstop, run each prompt through the local LLM/agent toolchain, then mark task complete through your existing local-ai-task-update.php endpoint.','tasks'=>$tasks]);
