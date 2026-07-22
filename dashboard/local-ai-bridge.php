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
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Local AI Bridge</title><link rel="stylesheet" href="/dashboard/assets/goliath-v31.css?v=313"><link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=313"></head><body><div class="shell"><?php require __DIR__.'/includes/goliath-sidebar-v31.php'; ?>
<main class="main">
<section class="top"><div><h1>Local AI Bridge</h1><p>Connect Ollama/DeepSeek/Qwen for thinking and ComfyUI/Flux/Wan for images/video.</p></div><a class="btn" href="/dashboard/personalized-content-engine.php">Client Content Engine</a></section>
<section class="kpis"><a class="kpi blue" href="#"><div class="n">Ollama</div><strong>Reasoning</strong><small>DeepSeek/Qwen/Llama</small></a><a class="kpi gold" href="#"><div class="n">Comfy</div><strong>Creation</strong><small>Flux/Wan/Thumbnails</small></a><a class="kpi green" href="#"><div class="n">Worker</div><strong>Always-On PC</strong><small>Runs heavy tasks</small></a><a class="kpi purple" href="#"><div class="n">API</div><strong>Bridge</strong><small>Token protected</small></a><a class="kpi red" href="#"><div class="n">Soon</div><strong>Scraper</strong><small>Playwright local</small></a></section>
<section class="grid2">
<section class="panel"><h2>Bridge Test</h2><div class="inner"><label>Prompt</label><textarea id="prompt">Create a 3-email drip for a California buyer interested in modern homes in Fairfield CT.</textarea><button class="btn blue" onclick="testLocal()">Send Test Task</button><pre id="out">Ready. This creates a local task for the worker. When your always-on PC is connected, it will process via Ollama/ComfyUI.</pre></div></section>
<aside class="stack"><section class="panel"><h2>Tonight Setup Checklist</h2><div class="inner"><div class="scriptBox">1. Confirm Ollama is running locally:
ollama serve
ollama list

2. Confirm model names:
deepseek-r1, qwen, llama, etc.

3. Confirm ComfyUI is running:
http://127.0.0.1:8188

4. Start local worker:
python local_goliath_worker.py

5. Test from this page.

The server stores/queues tasks. Your computer does the heavy thinking/creation.</div></div></section><section class="panel"><h2>Endpoints</h2><div class="inner"><div class="scriptBox">Task create:
POST /lead-engine/local-ai-task-create.php

Worker pulls:
GET /lead-engine/local-ai-task-pull.php?key=...

Worker updates:
POST /lead-engine/local-ai-task-update.php

Local services:
Ollama: http://127.0.0.1:11434
ComfyUI: http://127.0.0.1:8188</div></div></section></aside>
</section></main></div><script>
async function testLocal(){let out=document.getElementById('out');out.textContent='Creating task...';let r=await fetch('/lead-engine/local-ai-task-create.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:'<?=h($key)?>',task_type:'ollama_text',model:'deepseek-r1',prompt:document.getElementById('prompt').value})});out.textContent=await r.text();}
</script></body></html>