<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
require_once __DIR__ . '/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/scorsese-render-command.php'));exit;}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function t($name){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$name]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
$jobs=t('goliath_comfy_jobs')?gdb_all("SELECT * FROM goliath_comfy_jobs ORDER BY COALESCE(updated_at,created_at) DESC LIMIT 80"):[];
$counts=['queued'=>0,'working'=>0,'submitted'=>0,'complete'=>0,'failed'=>0];
foreach($jobs as $j){$s=strtolower($j['status']??'queued'); if(isset($counts[$s]))$counts[$s]++; if($s==='completed')$counts['complete']++;}
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scorsese Render Command</title>
<style>body{margin:0;background:#020617;color:#e5e7eb;font-family:Inter,Segoe UI,Arial}.wrap{max-width:1200px;margin:auto;padding:24px}.hero{background:linear-gradient(135deg,#581c87,#020617);border:1px solid #f5c85d55;border-radius:22px;padding:24px}.btn{display:inline-block;background:#f5c85d;color:#111827;text-decoration:none;font-weight:900;border-radius:12px;padding:12px 14px;margin:6px 6px 0 0}.ghost{background:#111827;color:#e5e7eb;border:1px solid #334155}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin:16px 0}.card,.row{background:#0f172a;border:1px solid #334155;border-radius:16px;padding:14px}.num{font-size:34px;color:#f5c85d;font-weight:1000}.row{margin:10px 0}.pill{display:inline-block;border:1px solid #475569;border-radius:999px;padding:3px 8px;color:#cbd5e1;font-size:12px}</style></head><body><div class="wrap"><section class="hero"><h1>🎬 Scorsese Render Command</h1><p>Turns Scorsese production briefs into ComfyUI/media jobs and tracks real render outputs.</p>
<a class="btn" target="_blank" href="/lead-engine/goliath-comfy-health.php?key=<?=h($key)?>">Health</a>
<a class="btn" target="_blank" href="/lead-engine/goliath-comfy-seed-from-scorsese.php?key=<?=h($key)?>&limit=50">Seed From Scorsese</a>
<a class="btn ghost" href="/dashboard/scorsese-media-center.php">Media Center</a>
<a class="btn ghost" href="/dashboard/goliath-mission-control.php">Mission Control</a></section>
<section class="grid"><?php foreach($counts as $k=>$v): ?><div class="card"><div class="num"><?=h($v)?></div><strong><?=h(strtoupper($k))?></strong></div><?php endforeach; ?></section>
<?php foreach($jobs as $j): ?><div class="row"><h3><?=h($j['title']??'Scorsese Render')?></h3><p><span class="pill"><?=h($j['status']??'queued')?></span> <span class="pill"><?=h($j['media_type']??'image')?></span> <span class="pill">Job #<?=h($j['id'])?></span></p>
<?php if(!empty($j['output_url'])): ?><p><a class="btn" href="<?=h($j['output_url'])?>" target="_blank">Open Asset</a></p><?php endif; ?>
<p style="color:#94a3b8"><?=h(mb_substr((string)($j['prompt']??''),0,280))?></p></div><?php endforeach; ?></div></body></html>