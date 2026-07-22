<?php
/**
 * Goliath Omni V75.6 — System Health
 * Hostinger/Internal view of what is connected.
 */
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
require_once __DIR__ . '/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-system-health.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function dbok(){return function_exists('gdb_enabled') && gdb_enabled();}
function one($sql,$p=[]){try{return dbok()?(gdb_one($sql,$p)?:[]):[];}catch(Throwable $e){return ['__error'=>$e->getMessage()];}}
function allx($sql,$p=[]){try{return dbok()?(gdb_all($sql,$p)?:[]):[];}catch(Throwable $e){return [];} }
function table_exists($t){$r=one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}
function count_where($t,$where='1=1'){ if(!table_exists($t)) return null; $r=one("SELECT COUNT(*) c FROM `$t` WHERE $where"); return (int)($r['c']??0); }
function status_class($ok,$warn=false){return $ok?($warn?'warn':'ok'):'bad';}
function row($name,$status,$detail,$url=''){
  $cls=$status==='ok'?'ok':($status==='warn'?'warn':'bad');
  echo '<div class="check '.$cls.'"><div><strong>'.h($name).'</strong><p>'.h($detail).'</p></div>'.($url?'<a href="'.h($url).'" target="_blank">Open</a>':'').'</div>';
}
$key=$_GET['key']??(defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts');
$tables=['executive_commissions','local_ai_tasks','goliath_worker_completions','goliath_review_queue','goliath_notifications','scorsese_comfy_jobs','executive_tool_registry','executive_tool_queue','executive_missions'];
$missing=[]; foreach($tables as $t){ if(!table_exists($t)) $missing[]=$t; }

$queued=count_where('executive_commissions',"status='queued'");
$working=count_where('executive_commissions',"status IN ('working','claimed')");
$complete=count_where('executive_commissions',"status IN ('complete','completed')");
$tasks=count_where('local_ai_tasks',"status IN ('queued','working','running')");
$reviews=count_where('goliath_review_queue',"review_status IN ('ready','needs_review','review')");
$scQueued=count_where('scorsese_comfy_jobs',"status IN ('queued','retry')");
$scWorking=count_where('scorsese_comfy_jobs',"status IN ('working','rendering')");
$scComplete=count_where('scorsese_comfy_jobs',"status IN ('complete','completed')");
$scFailed=count_where('scorsese_comfy_jobs',"status IN ('failed','error')");
$lastTask=table_exists('local_ai_tasks')?one("SELECT updated_at, agent, status FROM local_ai_tasks ORDER BY updated_at DESC, created_at DESC LIMIT 1"):[];
$lastComfy=table_exists('scorsese_comfy_jobs')?one("SELECT updated_at, status, title FROM scorsese_comfy_jobs ORDER BY updated_at DESC, created_at DESC LIMIT 1"):[];
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath System Health</title>
<style>
body{margin:0;background:#060a12;color:#f8f1df;font-family:Arial,sans-serif}.wrap{max-width:1150px;margin:auto;padding:28px}h1{color:#d4af37}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:14px}.check{border:1px solid #ffffff18;border-radius:16px;padding:16px;background:#111827;display:flex;justify-content:space-between;gap:14px;align-items:center}.check strong{font-size:18px}.check p{color:#b8c0ce;margin:6px 0 0}.check a{color:#061015;background:#d4af37;padding:8px 10px;border-radius:9px;text-decoration:none;font-weight:900}.ok{box-shadow:inset 5px 0 #00d084}.warn{box-shadow:inset 5px 0 #ffb020}.bad{box-shadow:inset 5px 0 #ff4d4f}.top{border:1px solid #d4af3750;border-radius:22px;padding:22px;background:linear-gradient(135deg,#101827,#07111f);margin-bottom:18px}.btn{display:inline-block;background:#d4af37;color:#07111f;font-weight:900;padding:10px 14px;border-radius:10px;text-decoration:none;margin-right:8px}code{color:#d4af37}
</style></head><body><div class="wrap">
<div class="top"><h1>Goliath System Health</h1><p>Internal production health for Hostinger CRM, workers, executive missions, Scorsese ComfyUI, and review pipeline.</p><a class="btn" href="/dashboard/goliath-mission-control.php">Mission Control</a><a class="btn" href="/lead-engine/goliath-production-health.php?key=<?=urlencode($key)?>" target="_blank">Raw Production Health</a><a class="btn" href="/lead-engine/scorsese-comfy-health.php?key=<?=urlencode($key)?>" target="_blank">Raw Comfy Health</a></div>
<div class="grid">
<?php
row('Hostinger Internal DB', dbok()?'ok':'bad', dbok()?'Connected. Hostinger internal CRM is source of truth.':'Database is not connected.');
row('Required Tables', empty($missing)?'ok':'bad', empty($missing)?'All production tables found.':'Missing: '.implode(', ',$missing));
row('Executive Commissions', ($queued+$working+$complete)>0?'ok':'warn', "Queued: $queued | Working: $working | Complete: $complete");
row('Local AI Runtime', ($tasks>0 || !empty($lastTask))?'ok':'warn', $lastTask?('Last: '.($lastTask['agent']??'agent').' / '.($lastTask['status']??'').' / '.($lastTask['updated_at']??'')):'No local AI task activity found.');
row('Review Queue', $reviews>0?'ok':'warn', "Ready/needs review: $reviews", '/dashboard/goliath-worker-output.php');
row('Scorsese Comfy Jobs', ($scQueued+$scWorking+$scComplete)>0?'ok':'warn', "Queued: $scQueued | Working: $scWorking | Complete: $scComplete | Failed: $scFailed", '/dashboard/scorsese-media-center.php');
row('ComfyUI Local App', $scWorking>0 || $scComplete>0?'ok':'warn', 'Hostinger cannot ping localhost. Status inferred from Scorsese job movement. Local URL should be http://127.0.0.1:8188');
row('Ollama Local LLM', !empty($lastTask)?'ok':'warn', 'Hostinger cannot ping localhost. Status inferred from completed local_ai_tasks.');
row('OpenClaw Gateway', 'warn', 'Local-only check. Start with C:\\Users\\markp\\.openclaw\\gateway.cmd, then wire into Tool Broker.');
row('n8n', 'warn', 'Pending Node 20 LTS fix. Not required for current production loop.');
?>
</div>
<h2 style="color:#d4af37;margin-top:30px">Current Cron Recommendations</h2>
<pre style="white-space:pre-wrap;background:#0b1220;border:1px solid #ffffff18;padding:16px;border-radius:14px;color:#d9e6ff">*/5 * * * * curl -fsS "https://www.markpires.com/lead-engine/run-goliath-production-dispatcher.php?key=<?=h($key)?>&limit=25&update=1" >/dev/null 2>&1
*/10 * * * * curl -fsS "https://www.markpires.com/lead-engine/scorsese-comfy-queue.php?key=<?=h($key)?>" >/dev/null 2>&1
*/15 * * * * curl -fsS "https://www.markpires.com/lead-engine/goliath-production-health.php?key=<?=h($key)?>" >/dev/null 2>&1</pre>
</div></body></html>
