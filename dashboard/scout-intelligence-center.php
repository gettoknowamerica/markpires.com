<?php
/**
 * Goliath V93.2 Scout Intelligence Center
 * Upload to /public_html/dashboard/scout-intelligence-center.php
 */
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/scout-intelligence-center.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function rows($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}
function one($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];}}

$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
$today=date('Y-m-d 00:00:00');
$stats=[
 'ready_today'=>(int)(one("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE handoff_status='ready_for_mark' AND completed_at>=?",[$today])['c']??0),
 'built_today'=>(int)(one("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE created_at>=?",[$today])['c']??0),
 'needs'=>(int)(one("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE handoff_status<>'ready_for_mark'")['c']??0),
 'queued'=>(int)(one("SELECT COUNT(*) c FROM internal_crm_contacts WHERE COALESCE(research_status,'') IN ('','queued','needs_review','needs_research')")['c']??0),
];
$pct=min(100,round(($stats['ready_today']/20)*100));
$missions=rows("SELECT * FROM scout_intel_missions ORDER BY updated_at DESC,id DESC LIMIT 20");
$dossiers=rows("SELECT * FROM scout_intel_dossiers ORDER BY updated_at DESC,id DESC LIMIT 30");
$events=rows("SELECT * FROM scout_intel_events ORDER BY created_at DESC,id DESC LIMIT 20");
$current=$dossiers[0]??null;
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scout Intelligence Center</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33"><link rel="stylesheet" href="/dashboard/assets/goliath-v45-final.css?v=456">
<style>
body{background:radial-gradient(circle at 20% 0%,rgba(34,197,94,.22),transparent 36%),linear-gradient(180deg,#020617,#07111f 65%,#020617);color:#fff}.hero,.panel,.card{background:#07111f;border:1px solid #22c55e33;border-radius:22px;padding:16px;box-shadow:0 18px 45px #0007;margin-bottom:14px}.hero h1{margin:0;font-size:36px}.muted{color:#94a3b8}.btn{display:inline-flex;align-items:center;gap:6px;border-radius:12px;padding:10px 13px;font-weight:1000;text-decoration:none;border:1px solid #ffffff22;color:#fff;background:#111827;cursor:pointer}.btn.gold{background:linear-gradient(135deg,#f5d48b,#d4af37);color:#111;border:0}.btn.green{background:linear-gradient(135deg,#22c55e,#166534)}.btn.blue{background:linear-gradient(135deg,#2563eb,#1e3a8a)}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.grid2{display:grid;grid-template-columns:1fr 380px;gap:14px}.kpi{font-size:34px;font-weight:1000;color:#fff}.bar{height:12px;background:#020617;border:1px solid #ffffff24;border-radius:999px;overflow:hidden}.bar span{display:block;height:100%;background:linear-gradient(90deg,#22c55e,#d4af37);box-shadow:0 0 18px #22c55e77}.mission,.dossier,.event{background:#050914;border:1px solid #ffffff16;border-radius:14px;padding:12px;margin:8px 0}.mission b,.dossier b,.event b{color:#f5d48b}.status{display:inline-block;padding:4px 7px;border-radius:999px;font-size:11px;font-weight:1000;background:#1e3a8a;color:#dbeafe}.ready{background:#14532d;color:#dcfce7}.needs{background:#422006;color:#fde68a}input,select,textarea{width:100%;box-sizing:border-box;background:#020617;color:#fff;border:1px solid #ffffff24;border-radius:12px;padding:10px;margin:7px 0}label{font-size:11px;color:#94a3b8;text-transform:uppercase;font-weight:1000}.dossierGrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:8px}.mini{background:#020617;border:1px solid #ffffff12;border-radius:12px;padding:10px}.mini small{color:#94a3b8}.mini strong{display:block;color:#fff}@media(max-width:1050px){.grid2,.grid4{grid-template-columns:1fr}.hero h1{font-size:30px}}
</style></head><body><div class="shell"><?php if(file_exists(__DIR__.'/includes/goliath-sidebar-v33.php')) require __DIR__.'/includes/goliath-sidebar-v33.php'; ?><main class="main">
<section class="hero"><h1>🕵️ Scout Intelligence Center</h1><p class="muted">Goal: 20 complete homeowner / lead dossiers per day. Scout only hands off evidence-backed files to Jessica and Mark.</p><div class="actions"><a class="btn gold" href="/dashboard/goliath-mission-control.php">Mission Control</a><a class="btn green" target="_blank" href="/lead-engine/scout-intelligence-install.php?key=<?=h($key)?>">Install / Verify</a><a class="btn blue" target="_blank" href="/lead-engine/scout-run-cycle.php?key=<?=h($key)?>&limit=20">Run 20-Dossier Cycle</a></div></section>

<section class="grid4">
 <div class="card"><div class="muted">Ready Today</div><div class="kpi"><?=h($stats['ready_today'])?> / 20</div><div class="bar"><span style="width:<?=$pct?>%"></span></div></div>
 <div class="card"><div class="muted">Built Today</div><div class="kpi"><?=h($stats['built_today'])?></div></div>
 <div class="card"><div class="muted">Needs Research</div><div class="kpi"><?=h($stats['needs'])?></div></div>
 <div class="card"><div class="muted">CRM Queue</div><div class="kpi"><?=h($stats['queued'])?></div></div>
</section>

<section class="grid2">
 <div>
  <div class="panel">
   <h2>CSV Mission Upload</h2>
   <form method="post" enctype="multipart/form-data" action="/lead-engine/scout-csv-mission-upload.php">
    <input type="hidden" name="key" value="<?=h($key)?>">
    <label>Mission Title</label><input name="mission_title" placeholder="July Expireds / Absentee Owners / Westport Sellers" required>
    <label>Mission Type</label><select name="mission_type"><option value="expired">Expired Listings</option><option value="absentee">Absentee Owners</option><option value="valuation">Home Valuation Leads</option><option value="luxury">Luxury</option><option value="buyer">Buyer / Relocation</option><option value="sphere">Sphere</option><option value="custom">Custom</option></select>
    <label>Priority</label><select name="priority"><option value="10">★★★★★ Highest</option><option value="8">★★★★ High</option><option value="5" selected>★★★ Normal</option><option value="3">★★ Low</option></select>
    <label>CSV File</label><input type="file" name="csv_file" accept=".csv,text/csv" required>
    <label>Notes</label><textarea name="notes" placeholder="What should Scout know about this list?"></textarea>
    <button class="btn green" type="submit">Upload + Queue Scout</button>
   </form>
  </div>

  <div class="panel">
   <h2>Latest Dossiers</h2>
   <?php foreach($dossiers as $d): $ready=($d['handoff_status']==='ready_for_mark'); ?>
    <a class="dossier" style="display:block;text-decoration:none;color:#fff" href="/dashboard/scout-dossier.php?id=<?=h($d['id'])?>">
      <b><?=h($d['owner_name'] ?: 'Unknown Owner')?></b><br>
      <span class="muted"><?=h($d['property_address'] ?: 'No address')?> <?=h($d['town'] ? '· '.$d['town'] : '')?></span><br>
      <span class="status <?=$ready?'ready':'needs'?>"><?=h($ready?'Ready for Mark':$d['research_status'])?></span>
      <span class="muted"> Confidence <?=h($d['confidence_score'])?>%</span>
    </a>
   <?php endforeach; ?>
  </div>
 </div>

 <aside>
  <div class="panel">
   <h2>Current Investigation</h2>
   <?php if($current): ?>
    <div class="dossierGrid">
     <div class="mini"><small>Owner</small><strong><?=h($current['owner_name']?:'Unknown')?></strong></div>
     <div class="mini"><small>Town</small><strong><?=h($current['town']?:'Unknown')?></strong></div>
     <div class="mini"><small>Phone</small><strong><?=h($current['phone']?'Found':'Not Found')?></strong></div>
     <div class="mini"><small>Email</small><strong><?=h($current['email']?'Found':'Not Found')?></strong></div>
     <div class="mini"><small>Contact Confidence</small><strong><?=h($current['contact_confidence'])?>%</strong></div>
     <div class="mini"><small>Property Confidence</small><strong><?=h($current['property_confidence'])?>%</strong></div>
    </div>
    <p class="muted"><?=h($current['next_action'])?></p>
    <a class="btn gold" href="/dashboard/scout-dossier.php?id=<?=h($current['id'])?>">Open Dossier</a>
   <?php else: ?><p class="muted">No dossiers yet. Upload a CSV or run a cycle.</p><?php endif; ?>
  </div>

  <div class="panel">
   <h2>Mission History</h2>
   <?php foreach($missions as $m): ?>
    <div class="mission"><b><?=h($m['title'])?></b><br><span class="muted"><?=h($m['mission_type'])?> · <?=h($m['status'])?> · <?=h($m['completed_records'])?> / <?=h($m['imported_records'] ?: $m['total_records'])?></span><div class="actions"><a class="btn blue" target="_blank" href="/lead-engine/scout-run-cycle.php?key=<?=h($key)?>&mission_id=<?=h($m['id'])?>&limit=20">Run This Mission</a></div></div>
   <?php endforeach; ?>
  </div>

  <div class="panel">
   <h2>Activity Stream</h2>
   <?php foreach($events as $e): ?><div class="event"><b><?=h($e['title'])?></b><br><span class="muted"><?=h($e['event_type'])?> · <?=h($e['created_at'])?></span></div><?php endforeach; ?>
  </div>
 </aside>
</section>
</main></div></body></html>