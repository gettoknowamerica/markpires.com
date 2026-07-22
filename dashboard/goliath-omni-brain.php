<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbq($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>35]);
  $b=curl_exec($ch); curl_close($ch); $d=json_decode($b,true);
  return is_array($d)?$d:[];
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath Omni Brain</title><link rel="stylesheet" href="/dashboard/assets/goliath-v31.css?v=315"><link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=315"></head><body><div class="shell"><?php require __DIR__.'/includes/goliath-sidebar-v31.php'; ?>
<?php
$tasks=sbq('local_ai_tasks?select=*&order=created_at.desc&limit=80');
$queued=0;$done=0;$running=0;$failed=0;
foreach($tasks as $t){$s=$t['status']??''; if($s==='queued')$queued++; elseif($s==='running')$running++; elseif($s==='done')$done++; elseif($s==='failed')$failed++;}
?>
<main class="main">
<section class="top"><div><h1>Goliath Omni Brain</h1><p>One command center for Ollama, DeepSeek, Mistral, GPT-OSS, Qwen, ComfyUI/Wan, content, and income actions.</p></div><input class="command" placeholder="Ask Goliath Omni to create income..." disabled></section>
<section class="kpis">
<a class="kpi blue"><div class="n"><?=$queued?></div><strong>Queued</strong><small>waiting for local worker</small></a>
<a class="kpi gold"><div class="n"><?=$running?></div><strong>Running</strong><small>local brain active</small></a>
<a class="kpi green"><div class="n"><?=$done?></div><strong>Completed</strong><small>results stored</small></a>
<a class="kpi red"><div class="n"><?=$failed?></div><strong>Failed</strong><small>needs review</small></a>
<a class="kpi purple" href="/dashboard/goliath-studio.php"><div class="n">Wan</div><strong>Comfy Video</strong><small>workflow installed</small></a>
</section>
<section class="grid2">
<div class="stack">
<section class="panel"><h2>Create Local AI Task</h2><div class="inner grid3">
<div class="box"><h3>Task Type</h3><select id="task_type"><option value="content_plan">Client Content Plan</option><option value="lead_strategy">Lead Strategy</option><option value="ollama_text">General Brain Task</option><option value="wan_video">Wan 2.2 Video Prompt</option><option value="site_fix_plan">Website Fix Plan</option><option value="business_growth_plan">Business Growth Plan</option></select><select id="model"><option>deepseek-r1</option><option>mistral</option><option>qwen2.5:7b</option><option>llama3.1:8b</option><option>gemma3:4b</option><option>gpt-oss:20b</option></select></div>
<div class="box"><h3>Preset</h3><button class="btn dark" onclick="preset('buyer')">CA → Fairfield Buyer Drip</button><button class="btn dark" onclick="preset('expired')">5 Expired Door Pieces</button><button class="btn dark" onclick="preset('music')">Music Venue Outreach</button><button class="btn dark" onclick="preset('legacy')">LegacySaved Funnel</button></div>
<div class="box"><h3>Run</h3><button class="btn blue" onclick="createTask()">Queue Local Task</button><a class="btn dark" href="/dashboard/daily-hot-sheet.php">Daily Hot Sheet</a><a class="btn dark" href="/dashboard/personalized-content-engine.php">Content Engine</a></div>
</div><div class="inner"><textarea id="prompt">Create a 3-email drip, blog outline, social captions, and Discover CT tie-in for a California buyer interested in Fairfield CT modern homes.</textarea><pre id="out">Ready. Your local worker will pick up queued tasks.</pre></div></section>
<section class="panel"><h2>Local AI Task Log</h2><div class="list"><?php foreach($tasks as $t):?><div class="row" onclick='showResult(<?=json_encode($t,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>)'><div class="score"><?=h(strtoupper(substr($t['status']??'Q',0,1)))?></div><div><div class="title"><?=h(($t['task_type']??'task').' · '.($t['model']??''))?></div><div class="sub"><?=h(substr($t['prompt']??'',0,140))?></div></div><div class="hide"><span class="pill warm"><?=h($t['status']??'queued')?></span></div></div><?php endforeach;?></div></section>
</div>
<aside class="stack">
<section class="panel"><h2>Result Viewer</h2><div class="inner"><div id="viewer" class="scriptBox">Click any task to view result. Completed tasks from Ollama/ComfyUI appear here.</div></div></section>
<section class="panel"><h2>What Goliath Can Modify</h2><div class="inner"><div class="scriptBox">YES — Goliath Omni can internally help fix/modify the site, but the safe operating model is:
1. Goliath audits the issue.
2. Local model drafts fix.
3. Goliath creates a patch zip.
4. Mark reviews/uploads.
5. Goliath tests and logs result.

We do NOT let it randomly overwrite production without review. Goliath can become your internal dev team, but with approval gates.</div></div></section>
</aside>
</section>
</main></div><script>
function preset(x){let p='';let type='content_plan';let model='deepseek-r1';
if(x==='buyer'){p='Create a personalized buyer nurture system for a California buyer interested in Fairfield, Westport, New Canaan, and Greenwich. Include 3 emails, 1 blog post, 5 social captions, Discover CT video angle, and Jessica follow-up script.'}
if(x==='expired'){p='Create door-knock deliverables for 5 expired listings. Include seller psychology, town stats, call script, text script, door piece copy, and a high-value offer to review why it did not sell.';type='lead_strategy'}
if(x==='music'){p='Create a high-ticket venue outreach plan for Mark Pires live looping concerts in CT/NY/NE. Include winery/venue strategy, EPK email, press angle, and follow-up schedule.';type='business_growth_plan'}
if(x==='legacy'){p='Create a LegacySaved sales funnel: emotional blog, 3-email drip, Facebook ad copy, landing page sections, and trust-building video concept.';type='business_growth_plan'}
document.getElementById('prompt').value=p;document.getElementById('task_type').value=type;document.getElementById('model').value=model;}
async function createTask(){let out=document.getElementById('out');out.textContent='Creating local task...';let body={key:'<?=h($key)?>',task_type:document.getElementById('task_type').value,model:document.getElementById('model').value,prompt:document.getElementById('prompt').value,priority:90};let r=await fetch('/lead-engine/local-ai-task-create.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});out.textContent=await r.text();}
function showResult(t){document.getElementById('viewer').textContent=JSON.stringify(t,null,2);}
</script></body></html>