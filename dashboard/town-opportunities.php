<?php
session_start(); require_once __DIR__.'/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next=/dashboard/town-opportunities.php');exit;}
if(file_exists(__DIR__.'/includes/goliath-nav.php')) require_once __DIR__.'/includes/goliath-nav.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY],CURLOPT_TIMEOUT=>60]);
  $b=curl_exec($ch); curl_close($ch); $d=json_decode($b,true); return is_array($d)?$d:[];
}
$town=trim($_GET['town']??'');
if($town===''){ $town='Fairfield'; }
$rows=sb('owner_research_queue?select=*&town=eq.'.rawurlencode($town).'&order=priority_score.desc&limit=500');
$total=count($rows); $phones=0; $approved=0; $need=0;
foreach($rows as $r){ if(!empty($r['phone_1'])||!empty($r['phone_2']))$phones++; if(($r['mark_review_status']??'')==='mark_approved')$approved++; if(empty($r['phone_1'])&&empty($r['phone_2']))$need++; }
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($town)?> Goliath Opportunities</title><style>
:root{--navy:#101827;--gold:#c8a96e;--cream:#f5f3ef;--ink:#111827;--muted:#667085;--line:#ece7dc}
body{margin:0;background:var(--cream);font-family:Arial;color:var(--ink)}.hero{background:var(--navy);color:white;padding:28px}.hero h1{font-family:Georgia,serif;color:var(--gold);font-size:44px;margin:0}.wrap{max-width:1500px;margin:auto;padding:20px}.btn{background:var(--gold);padding:10px 14px;border-radius:10px;text-decoration:none;color:#111;font-weight:900;display:inline-block;margin:4px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:16px 0}.card{background:white;border:1px solid var(--line);border-radius:18px;padding:18px;box-shadow:0 8px 24px #1018280d}.metric{font-size:36px;color:var(--gold);font-weight:900}.table{background:white;border-radius:18px;overflow:hidden;border:1px solid var(--line)}.row{display:grid;grid-template-columns:80px 1.1fr 1.5fr 1fr;gap:12px;padding:13px 16px;border-bottom:1px solid #eee}.head{background:#fbfaf7;color:#667085;text-transform:uppercase;font-size:11px;font-weight:900}.score{font-size:28px;color:var(--gold);font-weight:900}.small{font-size:12px;color:var(--muted);line-height:1.4}.links a{display:inline-block;background:#f7f4ed;border:1px solid #eadfc9;border-radius:9px;padding:6px 8px;text-decoration:none;color:#111;font-weight:900;font-size:12px;margin:2px}@media(max-width:900px){.grid,.row{grid-template-columns:1fr}.head{display:none}}</style></head><body>
<section class="hero"><h1><?=h($town)?> Opportunities</h1><p>Town-specific Goliath seller target board.</p></section>
<main class="wrap"><p><a class="btn" href="/dashboard/owner-research-queue.php">← Owner Queue</a><a class="btn" href="/dashboard/goliath-opportunities.php">All Opportunities</a></p>
<section class="grid"><div class="card"><div class="metric"><?=$total?></div><strong>Total Targets</strong></div><div class="card"><div class="metric"><?=$phones?></div><strong>With Phone</strong></div><div class="card"><div class="metric"><?=$need?></div><strong>Needs Research</strong></div><div class="card"><div class="metric"><?=$approved?></div><strong>Mark Approved</strong></div></section>
<section class="table"><div class="row head"><div>Score</div><div>Property</div><div>Why Now</div><div>Research</div></div>
<?php foreach($rows as $r): $q=urlencode($r['google_query']??($r['address'].' '.$r['town'].' owner phone')); $a=urlencode($r['assessor_query']??($r['address'].' '.$r['town'].' assessor')); ?>
<div class="row"><div class="score"><?=h($r['priority_score'])?></div><div><strong><?=h($r['address'])?></strong><br><span class="small"><?=h($r['owner_name']?:'Owner not found yet')?></span></div><div class="small"><?=h($r['why_now'])?></div><div class="links"><a target="_blank" href="https://www.google.com/search?q=<?=$q?>">Owner Search</a><a target="_blank" href="https://www.google.com/search?q=<?=$a?>">Assessor</a></div></div>
<?php endforeach; ?>
</section></main></body></html>