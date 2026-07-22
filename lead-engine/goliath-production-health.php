<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/goliath-v75-3-production-engine.php';
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($key && !hash_equals($expected,(string)$key)){ http_response_code(403); echo json_encode(['ok'=>false,'error'=>'bad_key']); exit; }
g753_install_schema(); g753_seed_missions(false); g753_seed_tools(false);
$out=['ok'=>true,'version'=>'V75.3','tables'=>[],'counts'=>[],'missions'=>[],'tools'=>[],'current_trophy'=>null,'time'=>date('c')];
foreach(['executive_missions','executive_tool_registry','executive_tool_queue','executive_opportunities','executive_awards','executive_commissions','local_ai_tasks','goliath_worker_completions','goliath_review_queue','scout_import_batches','scout_import_records'] as $t){ $out['tables'][$t]=g753_table($t); }
if(g753_table('executive_missions')){
  $out['counts']['missions_active']=(int)((g753_one("SELECT COUNT(*) c FROM executive_missions WHERE status='active'")?:['c'=>0])['c']);
  $out['missions']=g753_all("SELECT executive_key,department,title,status,priority,last_dispatched_at,max_daily_commissions FROM executive_missions ORDER BY priority DESC");
}
if(g753_table('executive_commissions')){
  $out['counts']['pull_eligible']=(int)((g753_one("SELECT COUNT(*) c FROM executive_commissions WHERE status IN ('queued','claimed','working','review','ready_for_review','in_progress','processing') AND COALESCE(progress,0)<100")?:['c'=>0])['c']);
  $out['counts']['production_queued']=(int)((g753_one("SELECT COUNT(*) c FROM executive_commissions WHERE commission_type='production_mission' AND status='queued'")?:['c'=>0])['c']);
  $out['latest_eligible']=g753_all("SELECT id,executive_key,title,status,priority,updated_at FROM executive_commissions WHERE status IN ('queued','claimed','working','review','ready_for_review','in_progress','processing') AND COALESCE(progress,0)<100 ORDER BY priority DESC,updated_at ASC LIMIT 20");
}
if(g753_table('executive_tool_registry')){
  $out['tools']=g753_all("SELECT tool_key,tool_name,capability,status,auth_status,last_health_at FROM executive_tool_registry ORDER BY capability,tool_key");
}
if(g753_table('scout_import_records')){
  $out['counts']['scout_records_needing_research']=(int)((g753_one("SELECT COUNT(*) c FROM scout_import_records WHERE record_status='needs_research'")?:['c'=>0])['c']);
}
if(g753_table('executive_awards')) $out['current_trophy']=g753_one("SELECT * FROM executive_awards WHERE trophy_active=1 AND award_type='daily_mvp' ORDER BY award_date DESC LIMIT 1");
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>
