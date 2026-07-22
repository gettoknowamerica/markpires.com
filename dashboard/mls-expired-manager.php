<?php
session_start(); require_once __DIR__.'/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
if(file_exists(__DIR__.'/includes/goliath-nav.php')) require_once __DIR__.'/includes/goliath-nav.php';
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>MLS Expired Manager</title><style>
body{margin:0;background:#f5f3ef;color:#111;font-family:Arial}.hero{background:#111827;color:white;padding:30px}.hero h1{font-family:Georgia,serif;color:#c8a96e}.wrap{max-width:1200px;margin:auto;padding:20px}.panel{background:white;border-radius:18px;padding:20px;margin:14px 0;box-shadow:0 4px 18px #0001}.btn{background:#c8a96e;border:0;border-radius:10px;padding:10px 14px;font-weight:900;margin:4px;cursor:pointer}.danger{background:#991b1b;color:white}pre{white-space:pre-wrap;background:#111827;color:white;border-radius:12px;padding:14px;max-height:460px;overflow:auto}table{width:100%;border-collapse:collapse}td,th{padding:10px;border-bottom:1px solid #eee;text-align:left}</style></head><body>
<section class="hero"><h1>MLS Expired Manager</h1><p>Count, delete, import, and rebuild MLS expired opportunity data safely.</p></section><main class="wrap">
<section class="panel"><button class="btn" onclick="count()">Refresh Count</button><a class="btn" href="/dashboard/mls-expired-import.php">Go To Chunk Import</a><button class="btn" onclick="build()">Build Opportunity Engine</button><button class="btn danger" onclick="delAll()">Delete ALL Expired Records</button><pre id="out">Click Refresh Count.</pre></section>
<section class="panel"><h2>Recommended Workflow</h2><ol><li>Refresh Count.</li><li>Delete ALL only when replacing the entire MLS dataset.</li><li>Go To Chunk Import and upload the richer file.</li><li>Return here and Build Opportunity Engine.</li></ol></section>
</main><script>
const KEY=<?=json_encode($key)?>;
async function count(){let r=await fetch('/lead-engine/mls-expired-count.php?key='+encodeURIComponent(KEY));document.getElementById('out').textContent=await r.text();}
async function delAll(){if(!confirm('Delete ALL records in mls_expired_records? This does not delete leads or municipal owner data.'))return;let r=await fetch('/lead-engine/delete-mls-expired.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:KEY,mode:'all'})});document.getElementById('out').textContent=await r.text();}
async function build(){let r=await fetch('/lead-engine/build-opportunity-engine.php?key='+encodeURIComponent(KEY));document.getElementById('out').textContent=await r.text();}
count();
</script></body></html>