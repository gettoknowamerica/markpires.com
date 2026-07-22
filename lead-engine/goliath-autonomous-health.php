<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/goliath-v75-mission-engine.php';
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if($key && !hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
gv75_install_schema();
$out=['ok'=>true,'version'=>'V75','tables'=>[],'counts'=>[],'missions'=>[],'current_trophy'=>null,'time'=>date('c')];
foreach(['executive_missions','executive_opportunities','executive_awards','executive_commissions','local_ai_tasks','goliath_worker_completions','goliath_review_queue'] as $t){$out['tables'][$t]=gv75_table($t);} 
if($out['tables']['executive_missions']){
  $out['counts']['missions_active']=(int)((gv75_one("SELECT COUNT(*) c FROM executive_missions WHERE status='active'")?:['c'=>0])['c']);
  $out['missions']=gv75_all("SELECT executive_key,title,status,priority,last_dispatched_at,max_daily_commissions FROM executive_missions ORDER BY priority DESC");
}
if($out['tables']['executive_commissions']){
  $out['counts']['pull_eligible']=(int)((gv75_one("SELECT COUNT(*) c FROM executive_commissions WHERE status IN ('queued','claimed','working','review','ready_for_review','in_progress','processing') AND COALESCE(progress,0)<100")?:['c'=>0])['c']);
  $out['counts']['autonomous_queued']=(int)((gv75_one("SELECT COUNT(*) c FROM executive_commissions WHERE commission_type='autonomous_mission' AND status='queued'")?:['c'=>0])['c']);
  $out['latest_eligible']=gv75_all("SELECT id,executive_key,title,status,priority,updated_at FROM executive_commissions WHERE status IN ('queued','claimed','working','review','ready_for_review','in_progress','processing') AND COALESCE(progress,0)<100 ORDER BY priority DESC,updated_at ASC LIMIT 15");
}
if($out['tables']['executive_awards']) $out['current_trophy']=gv75_one("SELECT * FROM executive_awards WHERE trophy_active=1 AND award_type='daily_mvp' ORDER BY award_date DESC LIMIT 1");
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>
