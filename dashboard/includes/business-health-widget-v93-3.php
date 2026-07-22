<?php
/**
 * Goliath V93.3 Business Health Widget
 * Include inside Mission Control if desired:
 * require __DIR__.'/includes/business-health-widget-v93-3.php';
 */
if(!function_exists('bh93_rows')){
  function bh93_rows($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}
}
if(!function_exists('bh93_one')){
  function bh93_one($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];} }
}
$today=date('Y-m-d');
$bh=[
 'leads_today'=>(int)(bh93_one("SELECT COUNT(*) c FROM internal_crm_contacts WHERE DATE(created_at)=?",[$today])['c']??0),
 'callbacks'=>(int)(bh93_one("SELECT COUNT(*) c FROM goliath_callback_tasks WHERE status IN ('queued','pending')")['c']??0),
 'scout_ready'=>(int)(bh93_one("SELECT COUNT(*) c FROM scout_dossiers WHERE status IN ('ready_for_mark','ready_for_jessica','complete') AND DATE(updated_at)=?",[$today])['c']??0),
 'scout_queue'=>(int)(bh93_one("SELECT COUNT(*) c FROM scout_dossiers WHERE status IN ('queued','researching','verifying','building_dossier')")['c']??0),
 'jessica_tasks'=>(int)(bh93_one("SELECT COUNT(*) c FROM local_ai_tasks WHERE LOWER(agent)='jessica' AND status='queued'")['c']??0),
 'scout_tasks'=>(int)(bh93_one("SELECT COUNT(*) c FROM local_ai_tasks WHERE LOWER(agent)='scout' AND status='queued'")['c']??0),
];
$goal=20;
$done=min($goal,$bh['scout_ready']);
$pct=$goal?round(($done/$goal)*100):0;
?>
<style>
.bizHealth{background:#07111f;border:1px solid #22405f;border-radius:20px;box-shadow:0 18px 45px #0007;padding:16px;margin:14px 0}
.bizHealth h3{margin:0 0 10px;color:#d4af37;text-transform:uppercase;letter-spacing:.06em}
.bizGrid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}
.bizCard{background:#050914;border:1px solid #ffffff18;border-radius:15px;padding:12px}
.bizCard b{display:block;font-size:24px;color:#fff}.bizCard span{font-size:11px;color:#94a3b8;text-transform:uppercase;font-weight:1000}
.bizMeter{height:10px;background:#020617;border:1px solid #ffffff24;border-radius:999px;overflow:hidden;margin-top:10px}
.bizMeter i{display:block;height:100%;background:linear-gradient(90deg,#22c55e,#d4af37);width:<?=$pct?>%}
@media(max-width:900px){.bizGrid{grid-template-columns:repeat(2,1fr)}}@media(max-width:520px){.bizGrid{grid-template-columns:1fr}}
</style>
<section class="bizHealth">
  <h3>Business Health — Revenue Engine</h3>
  <div class="bizGrid">
    <div class="bizCard"><b><?=h($bh['leads_today'])?></b><span>Leads Today</span></div>
    <div class="bizCard"><b><?=h($bh['callbacks'])?></b><span>Callbacks Queued</span></div>
    <div class="bizCard"><b><?=h($bh['scout_ready'])?></b><span>Dossiers Ready</span></div>
    <div class="bizCard"><b><?=h($bh['scout_queue'])?></b><span>Scout Queue</span></div>
    <div class="bizCard"><b><?=h($bh['scout_tasks'])?></b><span>Scout Tasks</span></div>
    <div class="bizCard"><b><?=h($bh['jessica_tasks'])?></b><span>Jessica Tasks</span></div>
  </div>
  <div class="bizMeter"><i></i></div>
  <div style="margin-top:8px;color:#cbd5e1;font-size:12px;font-weight:900">Today's Scout Target: <?=h($done)?> / 20 complete dossiers</div>
</section>
