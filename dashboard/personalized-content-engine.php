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
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Client Content Engine</title><link rel="stylesheet" href="/dashboard/assets/goliath-v31.css?v=313"><link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=313"></head><body><div class="shell"><?php require __DIR__.'/includes/goliath-sidebar-v31.php'; ?>
<?php
$leads=sbq('leads?select=*&order=created_at.desc&limit=80');
$opps=sbq('jessica_opportunity_engine?select=*&order=revenue_score.desc,created_at.desc&limit=80');
$dist=sbq('blotato_distribution_queue?select=*&order=created_at.desc&limit=40');
?>
<main class="main">
<section class="top"><div><h1>Client Content Engine</h1><p>Turn every buyer, seller, and expired lead into personalized drip + public SEO/blog/social authority content.</p></div><input class="command" placeholder="Ask Goliath: create a California-to-Fairfield buyer drip..." disabled></section>
<section class="kpis">
<a class="kpi blue" href="#leads"><div class="n"><?=count($leads)?></div><strong>Lead Sources</strong><small>Buyer/seller pain points</small></a>
<a class="kpi red" href="#expired"><div class="n"><?=count($opps)?></div><strong>Seller/Expired Targets</strong><small>Door pieces + outreach</small></a>
<a class="kpi gold" href="#queue"><div class="n"><?=count($dist)?></div><strong>Publishing Queue</strong><small>Blotato/blog/social</small></a>
<a class="kpi green" href="/dashboard/campaigns.php"><div class="n">SEO</div><strong>Blog + Backlinks</strong><small>Dual-use content</small></a>
<a class="kpi purple" href="/dashboard/local-ai-bridge.php"><div class="n">AI</div><strong>Local Brain</strong><small>Ollama/ComfyUI tonight</small></a>
</section>
<section class="grid2">
<div class="stack">
<section class="panel"><h2>Create Personalized Content</h2><div class="inner grid3">
<div class="box"><h3>Audience</h3><select id="aud"><option>Buyer relocating to Connecticut</option><option>Seller considering listing</option><option>Expired listing homeowner</option><option>Luxury buyer</option><option>Modern home buyer</option><option>Discover CT town audience</option></select><input id="towns" placeholder="Towns: Fairfield, Westport, New Canaan..."><input id="pain" placeholder="Pain point / interest: modern homes, schools, commute..."></div>
<div class="box"><h3>Output</h3><label><input type="checkbox" checked> Email drip</label><br><label><input type="checkbox" checked> Blog post</label><br><label><input type="checkbox" checked> Social posts</label><br><label><input type="checkbox"> Door piece</label><br><label><input type="checkbox"> Video script</label><br><label><input type="checkbox"> Thumbnail prompt</label></div>
<div class="box"><h3>Run</h3><button class="btn blue" onclick="draftContent()">Create Content Plan</button><button class="btn dark" onclick="queuePrompt()">Create Local AI Task</button><a class="btn dark" href="/dashboard/goliath-studio.php">Open Studio</a><a class="btn dark" href="/dashboard/campaigns.php">Campaigns</a></div>
</div><div class="inner"><div id="plan" class="scriptBox">Ready. Pick audience, towns, pain point, then create the plan.</div></div></section>

<section class="panel" id="leads"><h2>Website Leads → Personalized Drips</h2><div class="list"><?php foreach($leads as $l): $score=(int)($l['lead_score']??$l['adaptive_score']??0);?><div class="row" onclick="tog(this)"><div class="score"><?=h($score)?></div><div><div class="title"><?=h($l['name']??'Unknown Lead')?></div><div class="sub"><?=h(($l['town']??'').' · '.($l['timeline']??'').' · '.($l['message']??''))?></div></div><div class="hide"><span class="pill hot"><?=h($l['route']??'lead')?></span></div></div><div class="drawer"><div class="grid3"><div class="box"><h3>Contact</h3><div class="small"><?=h(($l['email']??'')."\n".($l['phone']??''))?></div></div><div class="box"><h3>Interest</h3><div class="small"><?=h(($l['type']??'').' · '.($l['goal']??'').' · '.($l['timeline']??''))?></div></div><div class="box"><h3>Content Angle</h3><div class="small">Create town-specific drip and publish public blog/social version.</div></div></div><div class="scriptBox"><?=h(json_encode($l,JSON_PRETTY_PRINT))?></div></div><?php endforeach;?></div></section>
</div>
<aside class="stack">
<section class="panel" id="expired"><h2>Expired / Seller Content</h2><div class="list"><?php foreach($opps as $o):?><div class="row" onclick="tog(this)"><div class="score"><?=h($o['revenue_score']??0)?></div><div><div class="title"><?=h($o['title']??$o['address']??'Opportunity')?></div><div class="sub"><?=h(($o['town']??'').' · '.($o['why_now']??''))?></div></div><div class="hide"><span class="pill warm">Seller</span></div></div><div class="drawer"><div class="scriptBox"><?=h(json_encode($o,JSON_PRETTY_PRINT))?></div></div><?php endforeach;?></div></section>
<section class="panel" id="queue"><h2>Content Queue</h2><div class="list"><?php foreach($dist as $d):?><a class="row" href="/dashboard/campaigns.php"><div class="score"><?=h($d['distribution_score']??0)?></div><div><div class="title"><?=h($d['distribution_title']??'Post')?></div><div class="sub"><?=h(($d['brand_pillar']??'').' · '.($d['distribution_status']??''))?></div></div><div class="hide"><span class="pill warm">Queue</span></div></a><?php endforeach;?></div></section>
</aside>
</section></main></div><script>
function tog(el){let d=el.nextElementSibling;if(d)d.classList.toggle('open')}
function draftContent(){let aud=document.getElementById('aud').value,t=document.getElementById('towns').value||'Fairfield County',p=document.getElementById('pain').value||'moving confidently and choosing the right town';document.getElementById('plan').textContent=`CLIENT CONTENT PLAN\n\nAudience: ${aud}\nTowns: ${t}\nPain point / interest: ${p}\n\n1. Email Drip\n• Email 1: helpful relocation/seller insight\n• Email 2: town-specific lifestyle/value tips\n• Email 3: Discover CT video tie-in\n• Email 4: available/off-market/strategy CTA\n\n2. Blog / SEO Asset\nTitle idea: Why ${aud.replace('Buyer relocating to Connecticut','California Families Are Choosing')} ${t}\nAdd schema, internal links, FAQ, Discover CT embed, CTA to MarkPires.com.\n\n3. Social Pack\nCreate TikTok/Reel, FB post, LinkedIn authority post, YouTube Short caption.\n\n4. Goliath Studio\nCreate thumbnail prompt, video script, B-roll list, and Blotato queue.\n\n5. Follow-Up\nJessica references the same content in the next touch so it feels custom, not generic.`}
function queuePrompt(){alert('Local AI task bridge is installed next. Tonight Ollama/ComfyUI will turn this into a queued local generation job.');}
</script></body></html>