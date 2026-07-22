<?php
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/scout-ready-contacts.php'));exit;}

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function rows($s,$p=[]){try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [];}}
function one($s,$p=[]){try{return gdb_one($s,$p)?:[];}catch(Throwable $e){return [];}}
function col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}

$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';

/* Speed-to-lead rule:
   1. contacts with phones/emails first
   2. newest updated/completed first
   3. anything ready_for_mark first
   4. then active queued/researching records */
$items=rows("
SELECT
 d.*,
 c.best_phone c_best_phone,c.phone_1 c_phone_1,c.phone_2 c_phone_2,c.phone_3 c_phone_3,c.phone_mobile c_phone_mobile,
 c.best_email c_best_email,c.email_1 c_email_1,c.email_2 c_email_2,
 c.contact_source_url c_source_url,c.evidence c_evidence,c.notes c_notes,c.contact_status c_status,c.raw_data c_raw_data,
 b.id browser_job_id,b.result_json browser_result_json,b.evidence browser_evidence,b.completed_at browser_completed_at,b.status browser_status
FROM scout_intel_dossiers d
LEFT JOIN internal_crm_contacts c ON c.id=d.contact_id
LEFT JOIN goliath_browser_jobs b ON b.executive_key='scout'
  AND b.job_type='contact_enrichment'
  AND b.status='complete'
  AND b.prompt LIKE CONCAT('%Dossier ID: ',d.id,'%')
WHERE
  d.handoff_status='ready_for_mark'
  OR COALESCE(d.best_phone,'')<>'' OR COALESCE(d.phone_1,'')<>'' OR COALESCE(d.phone,'')<>''
  OR COALESCE(d.best_email,'')<>'' OR COALESCE(d.email_1,'')<>'' OR COALESCE(d.email,'')<>''
  OR COALESCE(c.best_phone,'')<>'' OR COALESCE(c.phone_1,'')<>'' OR COALESCE(c.best_email,'')<>'' OR COALESCE(c.email_1,'')<>''
  OR COALESCE(d.research_status,'') IN ('queued_for_browser_intelligence','ready_for_mark','needs_external_search')
ORDER BY
  CASE WHEN (COALESCE(d.best_phone,d.phone_1,d.phone,c.best_phone,c.phone_1,'')<>'' OR COALESCE(d.best_email,d.email_1,d.email,c.best_email,c.email_1,'')<>'') THEN 0 ELSE 1 END,
  CASE WHEN d.handoff_status='ready_for_mark' THEN 0 ELSE 1 END,
  COALESCE(d.completed_at,b.completed_at,d.updated_at,d.created_at) DESC,
  d.id DESC
LIMIT 350");

$stats=[
 'ready'=>(int)(one("SELECT COUNT(*) c FROM scout_intel_dossiers WHERE handoff_status='ready_for_mark'")['c']??0),
 'with_contact'=>(int)(one("SELECT COUNT(*) c FROM scout_intel_dossiers d LEFT JOIN internal_crm_contacts c ON c.id=d.contact_id WHERE COALESCE(d.best_phone,d.phone_1,d.phone,c.best_phone,c.phone_1,'')<>'' OR COALESCE(d.best_email,d.email_1,d.email,c.best_email,c.email_1,'')<>''")['c']??0),
 'queued'=>(int)(one("SELECT COUNT(*) c FROM goliath_browser_jobs WHERE executive_key='scout' AND job_type='contact_enrichment' AND status IN ('queued','working')")['c']??0),
 'complete'=>(int)(one("SELECT COUNT(*) c FROM goliath_browser_jobs WHERE executive_key='scout' AND job_type='contact_enrichment' AND status='complete'")['c']??0)
];

$recentDeliverables=rows("SELECT * FROM executive_deliverables WHERE archived=0 ORDER BY viewed ASC, created_at DESC, id DESC LIMIT 8");
?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<title>Scout OS</title>
<style>
:root{--gold:#d4af37;--bg:#030712;--panel:#07111f;--panel2:#050914;--line:#ffffff1a;--green:#22c55e;--blue:#60a5fa;--red:#ef4444}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0;width:100%;max-width:100%;overflow-x:hidden;background:var(--bg);color:#fff;font-family:Inter,Arial,sans-serif}
body{background:radial-gradient(circle at 0 -10%,rgba(34,197,94,.18),transparent 34%),radial-gradient(circle at 100% 0,rgba(212,175,55,.13),transparent 30%),#030712}
a{color:inherit}.wrap{width:100%;max-width:1180px;margin:0 auto;padding:12px;padding-bottom:90px}
.topbar{position:sticky;top:0;z-index:20;margin:-12px -12px 12px;padding:calc(10px + env(safe-area-inset-top)) 12px 10px;background:rgba(3,7,18,.94);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
.topline{display:flex;align-items:center;justify-content:space-between;gap:8px}.topline h1{font-size:24px;margin:0;color:var(--gold);letter-spacing:.02em}.small{color:#94a3b8;font-size:12px;line-height:1.35}
.actions{display:flex;gap:8px;overflow-x:auto;white-space:nowrap;margin-top:10px;padding-bottom:3px;scrollbar-width:none}.actions::-webkit-scrollbar{display:none}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border:1px solid #ffffff22;border-radius:13px;padding:10px 12px;text-decoration:none;background:#101827;color:#fff;font-weight:900;font-size:13px;min-height:40px}
.btn.gold{background:linear-gradient(135deg,#f6d679,#9f7418);color:#111;border:0}.btn.green{background:linear-gradient(135deg,#16a34a,#064e3b)}.btn.blue{background:linear-gradient(135deg,#2563eb,#1e3a8a)}.btn.red{background:linear-gradient(135deg,#dc2626,#7f1d1d)}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-bottom:10px}.stat{background:linear-gradient(135deg,#07111f,#050914);border:1px solid var(--line);border-radius:16px;padding:10px}.stat b{font-size:22px;display:block}.stat span{font-size:9px;color:#94a3b8;text-transform:uppercase;font-weight:1000;letter-spacing:.04em}
.osGrid{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:12px}.panel{background:linear-gradient(135deg,#07111f,#050914);border:1px solid var(--line);border-radius:20px;padding:12px;box-shadow:0 18px 45px #0006}.panel h2{margin:0 0 10px;color:#d4af37;font-size:16px}
.newBanner{background:linear-gradient(90deg,#dc2626,#7f1d1d);border-radius:16px;padding:10px 12px;margin-bottom:10px;font-weight:1000;font-size:13px;box-shadow:0 0 26px #dc262640}
.contactList{display:grid;gap:9px}.contact{background:linear-gradient(135deg,#091527,#050914);border:1px solid #ffffff1d;border-radius:18px;overflow:hidden}.contact.hasContact{border-color:#22c55e55;box-shadow:0 0 0 1px #22c55e1a,0 18px 35px #0006}.summary{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(160px,.8fr) auto;gap:10px;align-items:center;padding:12px;cursor:pointer}.name{font-size:16px;font-weight:1000;color:#f5d48b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.addr{font-size:12px;color:#cbd5e1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:3px}.phone{font-size:18px;color:#86efac;font-weight:1000}.email{font-size:12px;color:#93c5fd;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.pill{display:inline-flex;border:1px solid #ffffff22;border-radius:999px;padding:3px 7px;color:#cbd5e1;font-size:10px;font-weight:900;margin:2px}.pill.new{background:#dc2626;border:0;color:#fff}.pill.green{background:#065f46;border-color:#22c55e55;color:#dcfce7}
.details{display:none;border-top:1px solid #ffffff12;background:#020617;padding:12px}.contact.open .details{display:block}.detailGrid{display:grid;grid-template-columns:1.1fr .9fr;gap:12px}.box{background:#07111f;border:1px solid #ffffff14;border-radius:16px;padding:11px}.box h3{margin:0 0 8px;color:#d4af37;font-size:14px}.infoGrid{display:grid;grid-template-columns:1fr 1fr;gap:8px}.kv{background:#020617;border:1px solid #ffffff12;border-radius:12px;padding:8px}.kv small{display:block;color:#94a3b8;text-transform:uppercase;font-size:9px;font-weight:1000}.kv b{font-size:12px;word-break:break-word}.evidence{max-height:180px;overflow:auto;color:#cbd5e1;font-size:12px;line-height:1.45;background:#020617;border:1px solid #ffffff12;border-radius:12px;padding:8px}label{display:block;color:#94a3b8;font-size:10px;font-weight:1000;text-transform:uppercase;margin-top:8px}input,textarea,select{width:100%;background:#020617;color:#fff;border:1px solid #ffffff24;border-radius:12px;padding:10px;margin-top:5px;font-size:16px}textarea{min-height:92px}.formLine{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}.saveRow{display:flex;gap:8px;align-items:center;margin-top:8px}.savedMsg{display:none;color:#86efac;font-weight:900;font-size:12px}.sideList{display:grid;gap:8px}.deliverable{display:block;text-decoration:none;background:#020617;border:1px solid #ffffff14;border-radius:14px;padding:10px}.deliverable b{display:block;color:#fff;font-size:13px}.deliverable small{color:#94a3b8}.quickSearch{display:flex;gap:8px;margin-bottom:10px}.quickSearch input{margin:0}.bottomNav{position:fixed;left:0;right:0;bottom:0;z-index:30;background:rgba(3,7,18,.96);backdrop-filter:blur(16px);border-top:1px solid #ffffff1a;padding:8px 8px calc(8px + env(safe-area-inset-bottom));display:none;grid-template-columns:repeat(4,1fr);gap:6px}.bottomNav a{text-decoration:none;text-align:center;background:#07111f;border:1px solid #ffffff18;border-radius:14px;padding:8px 4px;font-size:11px;font-weight:900;color:#cbd5e1}.bottomNav a.active{background:#d4af37;color:#111}
@media(max-width:900px){
 .wrap{max-width:none;padding:10px;padding-bottom:88px}.osGrid{grid-template-columns:1fr}.side{order:-1}.stats{grid-template-columns:repeat(4,1fr)}.summary{grid-template-columns:1fr;gap:6px}.detailGrid,.formLine,.infoGrid{grid-template-columns:1fr}.topline h1{font-size:22px}.panel{border-radius:18px}.phone{font-size:22px}.bottomNav{display:grid}.desktopOnly{display:none!important}.actions .btn{font-size:12px;padding:9px 10px}.addr{white-space:normal}.name{white-space:normal}.email{white-space:normal}
}
@media(max-width:420px){.stat{padding:8px}.stat b{font-size:18px}.stat span{font-size:8px}.topline h1{font-size:20px}.panel{padding:10px}.summary{padding:10px}.details{padding:10px}}
</style>
</head>
<body>
<div class="topbar">
 <div class="topline"><div><h1>🕵️ Scout OS</h1><div class="small">Speed-to-lead command center. Phones/emails and newest files always rise to the top.</div></div></div>
 <div class="actions">
  <a class="btn gold" target="_blank" href="/lead-engine/scout-autopilot.php?key=<?=h($key)?>&target_queue=25&batch=25">Run Autopilot</a>
  <a class="btn green" href="/dashboard/scout-ready-contacts.php">Contacts</a>
  <a class="btn blue" href="/dashboard/goliath-executive-inbox.php">Inbox</a>
  <a class="btn" href="/dashboard/goliath-mission-control.php">Mission</a>
 </div>
</div>

<div class="wrap">
 <section class="stats">
  <div class="stat"><b><?=h($stats['ready'])?></b><span>Ready</span></div>
  <div class="stat"><b><?=h($stats['with_contact'])?></b><span>Contacts</span></div>
  <div class="stat"><b><?=h($stats['queued'])?></b><span>Queue</span></div>
  <div class="stat"><b><?=h($stats['complete'])?></b><span>Done</span></div>
 </section>

 <section class="osGrid">
  <main>
   <div class="newBanner">🔴 NEW / ACTIONABLE CONTACTS ARE FIRST — click once to open the entire Scout file.</div>
   <div class="quickSearch"><input id="filterBox" placeholder="Filter by name, town, address, phone..." oninput="filterContacts(this.value)"><button class="btn" onclick="filterContacts('')">Clear</button></div>
   <section class="contactList" id="contactList">
<?php foreach($items as $d):
 $phone=$d['best_phone']?:$d['phone_1']?:$d['phone']?:$d['c_best_phone']?:$d['c_phone_1']?:$d['c_phone_2']?:$d['c_phone_3']?:$d['c_phone_mobile']?:'';
 $email=$d['best_email']?:$d['email_1']?:$d['email']?:$d['c_best_email']?:$d['c_email_1']?:$d['c_email_2']?:'';
 $src=$d['contact_source_url']?:$d['c_source_url']?:$d['source_url']?:'';
 $ev=$d['evidence_log']?:$d['browser_evidence']?:$d['c_evidence']?:$d['public_notes']?:'';
 $notes=$d['public_notes']?:$d['c_notes']?:'';
 $listing=$d['listing_history']?:'Not captured yet.';
 $nearby=$d['nearby_sales']?:'Not captured yet.';
 $raw=$d['raw_json']?:$d['c_raw_data']?:$d['browser_result_json']?:'';
 $updated=$d['completed_at']?:$d['browser_completed_at']?:$d['updated_at']?:$d['created_at'];
 $isNew=strtotime($updated)>time()-86400;
 $searchText=strtolower(($d['owner_name']??'').' '.($d['property_address']??'').' '.($d['town']??'').' '.$phone.' '.$email);
?>
<article class="contact <?=($phone||$email)?'hasContact':''?>" data-search="<?=h($searchText)?>" id="contact-<?=h($d['id'])?>">
 <div class="summary" onclick="toggleContact(this.parentNode)">
  <div><div class="name"><?=h($d['owner_name']?:'Unknown Owner')?> <?php if($isNew): ?><span class="pill new">NEW</span><?php endif; ?> <?php if($phone||$email): ?><span class="pill green">ACTIONABLE</span><?php endif; ?></div><div class="addr"><?=h($d['property_address'])?><?=($d['town']?' · '.h($d['town']):'')?><?=($d['state']?' · '.h($d['state']):'')?></div></div>
  <div><?php if($phone): ?><div class="phone"><?=h($phone)?></div><?php endif; ?><?php if($email): ?><div class="email"><?=h($email)?></div><?php endif; ?><?php if(!$phone&&!$email): ?><div class="small">No visible phone/email yet</div><?php endif; ?></div>
  <div><span class="pill">Dossier #<?=h($d['id'])?></span><span class="pill"><?=h($d['research_status'])?></span><span class="pill"><?=h($d['contact_confidence']?:$d['confidence_score'])?>%</span></div>
 </div>
 <div class="details">
  <div class="detailGrid">
   <div class="box">
    <h3>Entire Scout File</h3>
    <div class="infoGrid">
     <div class="kv"><small>Owner</small><b><?=h($d['owner_name'])?></b></div>
     <div class="kv"><small>Property</small><b><?=h($d['property_address'])?></b></div>
     <div class="kv"><small>Town</small><b><?=h($d['town'])?></b></div>
     <div class="kv"><small>Last Updated</small><b><?=h($updated)?></b></div>
     <div class="kv"><small>Phone</small><b><?=h($phone?:'—')?></b></div>
     <div class="kv"><small>Email</small><b><?=h($email?:'—')?></b></div>
    </div>
    <h3 style="margin-top:12px">Listing History / Expired Attempts</h3>
    <div class="evidence"><?=h($listing)?></div>
    <h3 style="margin-top:12px">Nearby Sales / CMA Snapshot</h3>
    <div class="evidence"><?=h($nearby)?></div>
    <h3 style="margin-top:12px">Evidence / Source</h3>
    <div class="evidence"><?=h($ev?:'No source evidence saved yet.')?></div>
    <div style="margin-top:8px"><?php if($src): ?><a class="btn blue" target="_blank" href="<?=h($src)?>">Open Source</a><?php endif; ?><a class="btn green" href="/dashboard/scout-dossier.php?id=<?=h($d['id'])?>">Dossier Page</a></div>
    <details style="margin-top:10px"><summary class="small">Raw data</summary><div class="evidence"><?=h($raw?:'No raw JSON saved.')?></div></details>
   </div>
   <div class="box">
    <h3>Update After Call</h3>
    <form onsubmit="saveScoutContact(event,this)">
     <input type="hidden" name="key" value="<?=h($key)?>"><input type="hidden" name="dossier_id" value="<?=h($d['id'])?>"><input type="hidden" name="contact_id" value="<?=h($d['contact_id'])?>">
     <div class="formLine"><div><label>Extra Phone</label><input name="extra_phone" placeholder="Add phone"></div><div><label>Extra Email</label><input name="extra_email" placeholder="Add email"></div><div><label>Status</label><select name="lead_status"><option value="">Keep Current</option><option value="hot_lead">Hot Lead</option><option value="follow_up">Follow Up</option><option value="longterm_drip">Long-Term Drip</option><option value="do_not_contact">Do Not Contact</option></select></div></div>
     <label>Call / Research Notes</label><textarea name="mark_notes" placeholder="Add call result, spouse name, timeline, objection, best call time..."><?=h($notes)?></textarea>
     <div class="saveRow"><button class="btn gold" type="submit">Save to File</button><span class="savedMsg">Saved.</span></div>
    </form>
   </div>
  </div>
 </div>
</article>
<?php endforeach; ?>
   </section>
  </main>
  <aside class="side desktopOnly">
   <div class="panel"><h2>New Deliverables</h2><div class="sideList">
   <?php foreach($recentDeliverables as $r): ?><a class="deliverable" href="<?=h($r['action_url']?:'/dashboard/goliath-executive-inbox.php')?>"><b><?=empty($r['viewed'])?'🔴 ':''?><?=h($r['title'])?></b><small><?=h($r['executive_key'])?> · <?=h($r['created_at'])?></small></a><?php endforeach; ?>
   </div></div>
  </aside>
 </section>
</div>
<nav class="bottomNav"><a class="active" href="/dashboard/scout-ready-contacts.php">Scout</a><a href="/dashboard/goliath-executive-inbox.php">Inbox</a><a href="/dashboard/gbi-dashboard.php">Browser</a><a href="/dashboard/goliath-mission-control.php">Mission</a></nav>
<script>
function toggleContact(el){el.classList.toggle('open'); if(el.classList.contains('open')) el.scrollIntoView({behavior:'smooth',block:'start'});}
function filterContacts(q){if(!q){document.getElementById('filterBox').value='';} q=(q||'').toLowerCase(); document.querySelectorAll('.contact').forEach(c=>{c.style.display=c.dataset.search.includes(q)?'block':'none';});}
async function saveScoutContact(e,form){
 e.preventDefault();
 const fd=new FormData(form);
 const res=await fetch('/lead-engine/scout-contact-update.php',{method:'POST',body:fd});
 const data=await res.json();
 const msg=form.querySelector('.savedMsg');
 msg.style.display='inline';
 msg.textContent=data.ok?'Saved to Scout file.':'Error: '+(data.error||'Could not save');
}
</script>
</body></html>