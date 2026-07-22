<?php
$key=$_GET['key']??(defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts');
$urls=[
  'Health'=>'/dashboard/goliath-system-health.php?key='.urlencode($key),
  'Production Health'=>'/lead-engine/goliath-production-health.php?key='.urlencode($key),
  'Dispatch Normal'=>'/lead-engine/run-goliath-production-dispatcher.php?key='.urlencode($key).'&limit=25&update=1',
  'Force Missions'=>'/lead-engine/run-goliath-production-dispatcher.php?key='.urlencode($key).'&limit=25&force=1&update=1',
  'Scorsese Queue'=>'/lead-engine/scorsese-comfy-queue.php?key='.urlencode($key),
  'Mission Control'=>'/dashboard/goliath-mission-control.php',
  'Latest Work'=>'/dashboard/goliath-worker-output.php',
  'Scorsese Media'=>'/dashboard/scorsese-media-center.php'
];
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath Production Command Center</title>
<style>body{margin:0;background:#060a12;color:#f5efe2;font-family:Arial,sans-serif}.wrap{max-width:1150px;margin:auto;padding:28px}.hero{border:1px solid #d4af3755;background:linear-gradient(135deg,#111827,#07111f);border-radius:22px;padding:28px}h1{color:#d4af37;margin:0 0 8px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-top:18px}.card{background:#111827;border:1px solid #ffffff14;border-radius:16px;padding:18px}.btn{display:inline-block;color:#fff;font-weight:900;text-decoration:none;padding:12px 16px;border-radius:12px;margin:6px 8px 6px 0;border:1px solid #ffffff22}.gold{background:#d4af37;color:#111}.green{background:#16a34a}.blue{background:#2563eb}.purple{background:#9333ea}.orange{background:#f97316}.red{background:#dc2626}.dark{background:#334155}code{color:#d4af37}</style></head>
<body><div class="wrap"><div class="hero"><h1>Goliath Production Command Center</h1><p>Internal Hostinger-first production controls. No Supabase or HubSpot required for Mission Control.</p>
<?php $classes=['gold','green','blue','purple','orange','red','dark']; $i=0; foreach($urls as $label=>$url): ?><a class="btn <?=$classes[$i++%count($classes)]?>" href="<?=htmlspecialchars($url)?>" target="_blank"><?=htmlspecialchars($label)?></a><?php endforeach; ?>
</div><div class="grid"><div class="card"><h3>Production Rule</h3><p>No executive waits. If no assignment exists, create value.</p></div><div class="card"><h3>Local Runtime</h3><p>Run <code>goliath-universal-executive-runtime-v75-4-2.ps1</code>.</p></div><div class="card"><h3>Scorsese Render Runtime</h3><p>Run <code>goliath-comfy-direct-worker-v75-5-3.ps1</code>.</p></div><div class="card"><h3>Tool Broker</h3><p>Next: executive tool capability dictionary wired into prompts and dispatch.</p></div></div></div></body></html>