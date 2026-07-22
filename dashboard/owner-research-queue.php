<?php
session_start(); require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__ . '/includes/goliath-ui.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next=/dashboard/owner-research-queue.php');exit;}
if(file_exists(__DIR__.'/includes/goliath-nav.php')) require_once __DIR__.'/includes/goliath-nav.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>60]);
  if($p!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch); curl_close($ch); $d=json_decode($b,true); return is_array($d)?$d:[];
}
if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['id'])){
  sb('PATCH','owner_research_queue?id=eq.'.rawurlencode($_POST['id']),[
    'owner_name'=>$_POST['owner_name']??'',
    'phone_1'=>$_POST['phone_1']??'',
    'phone_2'=>$_POST['phone_2']??'',
    'email_1'=>$_POST['email_1']??'',
    'notes'=>$_POST['notes']??'',
    'mark_review_status'=>$_POST['mark_review_status']??'not_reviewed',
    'queue_status'=>$_POST['queue_status']??'needs_research',
    'updated_at'=>date('c')
  ]);
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$rows=sb('GET','owner_research_queue?select=*&order=priority_score.desc,created_at.asc&limit=500');
$total=count($rows); $researched=0; $approved=0; $phones=0; $need=0;
$towns=[];
foreach($rows as $r){
 if(($r['queue_status']??'')==='researched')$researched++;
 if(($r['mark_review_status']??'')==='mark_approved')$approved++;
 if(!empty($r['phone_1'])||!empty($r['phone_2']))$phones++;
 if(empty($r['phone_1'])&&empty($r['phone_2']))$need++;
 $t=$r['town']?:'Unknown'; $towns[$t]=($towns[$t]??0)+1;
}
arsort($towns); $topTowns=array_slice($towns,0,7,true);
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Goliath Owner Research Command</title>
<style>
:root{--navy:#101827;--gold:#c8a96e;--cream:#f5f3ef;--ink:#111827;--muted:#667085;--card:#fff;--line:#ece7dc}
*{box-sizing:border-box}body{margin:0;background:var(--cream);color:var(--ink);font-family:Inter,Arial,sans-serif}.shell{display:grid;grid-template-columns:250px 1fr;min-height:100vh}.side{background:var(--navy);color:white;padding:22px;position:sticky;top:0;height:100vh}.brand{font-family:Georgia,serif;color:var(--gold);font-size:28px;font-weight:800;margin-bottom:26px}.side a{display:block;color:#e5e7eb;text-decoration:none;padding:12px;border-radius:12px;margin:5px 0;font-weight:800}.side a:hover,.side a.active{background:#1d2939;color:white}.main{padding:24px}.top{display:flex;align-items:flex-end;justify-content:space-between;gap:15px;margin-bottom:18px}.top h1{font-family:Georgia,serif;font-size:42px;margin:0;color:#1b2435}.top p{margin:6px 0 0;color:var(--muted)}.actions a,.actions button{background:var(--gold);border:0;border-radius:12px;padding:11px 14px;color:#111;text-decoration:none;font-weight:900;margin-left:6px;cursor:pointer}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px}.card{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:18px;box-shadow:0 8px 24px #1018280d}.metric{font-size:36px;color:var(--gold);font-weight:900}.label{font-size:13px;color:var(--muted);font-weight:800}.dash{display:grid;grid-template-columns:1.35fr .65fr;gap:14px;margin-bottom:16px}.bars .barrow{display:grid;grid-template-columns:110px 1fr 42px;align-items:center;gap:8px;margin:10px 0;font-size:13px}.track{height:10px;background:#ececec;border-radius:99px;overflow:hidden}.fill{height:100%;background:var(--gold)}.toolbar{display:flex;gap:10px;align-items:center;margin:10px 0}.toolbar input,.toolbar select{padding:11px;border:1px solid var(--line);border-radius:12px;background:white;min-width:180px}.tablewrap{background:white;border:1px solid var(--line);border-radius:20px;overflow:hidden}.row{display:grid;grid-template-columns:86px 1.2fr 1fr 1.4fr 110px;gap:12px;align-items:start;padding:13px 16px;border-bottom:1px solid #f0ede6}.row.head{background:#fbfaf7;color:#667085;font-size:11px;text-transform:uppercase;font-weight:900}.score{font-size:28px;color:var(--gold);font-weight:900}.addr{font-weight:900}.small{font-size:12px;color:var(--muted);line-height:1.35}.pill{display:inline-block;background:#f2ead9;border-radius:99px;padding:5px 8px;font-size:11px;font-weight:900;margin:2px}.links a{display:inline-block;color:#111827;background:#f7f4ed;border:1px solid #eadfc9;border-radius:10px;padding:6px 8px;text-decoration:none;font-size:12px;font-weight:900;margin:2px}details summary{cursor:pointer;font-weight:900;color:#111827}details[open]{background:#fbfaf7;border-radius:12px;padding:10px}.mini input,.mini textarea,.mini select{width:100%;padding:8px;border:1px solid var(--line);border-radius:9px;margin:3px 0}.mini button{background:var(--navy);color:var(--gold);border:0;border-radius:10px;padding:8px 10px;font-weight:900}.modal{display:none;position:fixed;inset:0;background:#0008;z-index:99;align-items:center;justify-content:center;padding:20px}.modal .box{background:white;border-radius:22px;max-width:900px;width:100%;max-height:85vh;overflow:auto;padding:22px}.close{float:right;background:#991b1b;color:white;border:0;border-radius:99px;width:34px;height:34px;font-weight:900}@media(max-width:1000px){.shell{grid-template-columns:1fr}.side{height:auto;position:relative}.dash,.grid{grid-template-columns:1fr}.row{grid-template-columns:1fr}.row.head{display:none}.top{display:block}.actions a{display:inline-block;margin:5px 3px}} 
</style><link rel="stylesheet" href="/dashboard/assets/goliath-os.css?v=4"><link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=4"><?php goliath_ui_head(); ?></head><body><?php goliath_ui_open(); ?><div class="shell">
<main class="main"><div class="top"><div><h1>Owner Research Command</h1><p>Confirmed failed-sale targets, contact discovery, and Mark review pipeline.</p></div><div class="actions"><a target="_blank" href="/lead-engine/build-owner-research-queue.php?key=<?=h($key)?>&limit=250&mode=replace">Build Top 250</a><a href="/lead-engine/export-owner-research-queue.php?key=<?=h($key)?>">Export CSV</a></div></div>
<section class="grid"><div class="card"><div class="metric"><?=$total?></div><div class="label">Targets Loaded</div></div><div class="card"><div class="metric"><?=$phones?></div><div class="label">With Phone</div></div><div class="card"><div class="metric"><?=$need?></div><div class="label">Needs Research</div></div><div class="card"><div class="metric"><?=$approved?></div><div class="label">Mark Approved</div></div></section>
<section class="dash"><div class="card"><h3 style="margin-top:0">Pipeline</h3><div class="bars">
<?php $max=max(1,$total); foreach(['Loaded'=>$total,'Researched'=>$researched,'With Phone'=>$phones,'Approved'=>$approved] as $k=>$v): ?>
<div class="barrow"><strong><?=h($k)?></strong><div class="track"><div class="fill" style="width:<?=round($v/$max*100)?>%"></div></div><span><?=h($v)?></span></div>
<?php endforeach; ?></div></div>
<div class="card"><h3 style="margin-top:0">Top Towns</h3><div class="bars"><?php $m=max(1,max($topTowns?:[1])); foreach($topTowns as $t=>$v): ?><a class="barrow" style="text-decoration:none;color:inherit" href="/dashboard/town-opportunities.php?town=<?=urlencode($t)?>"><strong><?=h($t)?></strong><div class="track"><div class="fill" style="width:<?=round($v/$m*100)?>%"></div></div><span><?=h($v)?></span></a><?php endforeach;?></div></div></section>
<div class="toolbar"><input id="q" placeholder="Search address, town, owner..." oninput="filterRows()"><select id="statusFilter" onchange="filterRows()"><option value="">All statuses</option><option value="needs_research">Needs Research</option><option value="researched">Researched</option><option value="mark_approved">Mark Approved</option><option value="suppress">Suppress</option></select></div>
<section class="tablewrap"><div class="row head"><div>Score</div><div>Target</div><div>Research Links</div><div>Quick Detail</div><div>Action</div></div>
<?php foreach($rows as $r):
$q1=urlencode($r['google_query']??'');$q2=urlencode($r['assessor_query']??'');$q3=urlencode($r['people_search_query']??'');
$hay=strtolower(($r['address']??'').' '.($r['town']??'').' '.($r['owner_name']??'').' '.($r['queue_status']??'').' '.($r['mark_review_status']??''));
?>
<div class="row item" data-hay="<?=h($hay)?>" data-status="<?=h(($r['mark_review_status']==='mark_approved')?'mark_approved':($r['queue_status']??''))?>">
<div><div class="score"><?=h($r['priority_score'])?></div></div>
<div><div class="addr"><?=h($r['address'])?></div><div><a style="color:#111827;font-weight:900" href="/dashboard/town-opportunities.php?town=<?=urlencode($r['town']??'')?>"><?=h($r['town'])?></a></div><span class="pill"><?=h($r['queue_status'])?></span><span class="pill"><?=h($r['mark_review_status'])?></span></div>
<div class="links"><a target="_blank" href="https://www.google.com/search?q=<?=$q1?>">Owner Search</a><a target="_blank" href="https://www.google.com/search?q=<?=$q2?>">Assessor</a><a target="_blank" href="https://www.google.com/search?q=<?=$q3?>">Phone Search</a></div>
<div><details><summary>View property intelligence</summary><p class="small"><?=h($r['why_now'])?></p><p class="small"><strong>Recommended:</strong> <?=h($r['recommended_action'])?></p><form class="mini" method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><input name="owner_name" placeholder="Owner name" value="<?=h($r['owner_name'])?>"><input name="phone_1" placeholder="Phone 1" value="<?=h($r['phone_1'])?>"><input name="phone_2" placeholder="Phone 2" value="<?=h($r['phone_2'])?>"><input name="email_1" placeholder="Email" value="<?=h($r['email_1'])?>"><textarea name="notes" placeholder="Notes"><?=h($r['notes'])?></textarea><select name="queue_status"><option>needs_research</option><option <?=($r['queue_status']==='researched'?'selected':'')?>>researched</option><option <?=($r['queue_status']==='bad_match'?'selected':'')?>>bad_match</option></select><select name="mark_review_status"><option>not_reviewed</option><option <?=($r['mark_review_status']==='mark_approved'?'selected':'')?>>mark_approved</option><option <?=($r['mark_review_status']==='suppress'?'selected':'')?>>suppress</option></select><button>Save</button></form></details></div>
<div><button class="btn" onclick='openModal(<?=json_encode($r,JSON_HEX_APOS|JSON_HEX_QUOT)?>)'>Open</button></div>
</div><?php endforeach;?></section>
</main></div><div class="modal" id="modal"><div class="box"><button class="close" onclick="modal.style.display='none'">×</button><div id="modalBody"></div></div></div>
<script>
function filterRows(){let q=document.getElementById('q').value.toLowerCase(),s=document.getElementById('statusFilter').value;document.querySelectorAll('.item').forEach(r=>{let ok=(!q||r.dataset.hay.includes(q))&&(!s||r.dataset.status===s||r.dataset.hay.includes(s));r.style.display=ok?'grid':'none';});}
function openModal(r){document.getElementById('modalBody').innerHTML=`<h2>${r.address||''}</h2><h3>${r.town||''}</h3><p><a href="/dashboard/town-opportunities.php?town=${encodeURIComponent(r.town||'')}" style="font-weight:900;color:#111827">Open ${r.town||''} town board →</a></p><p><strong>Score:</strong> ${r.priority_score||''}</p><p><strong>Owner:</strong> ${r.owner_name||'Not found yet'}</p><p><strong>Phone:</strong> ${r.phone_1||'Not found yet'}</p><p><strong>Why now:</strong><br>${r.why_now||''}</p><p><strong>Recommended action:</strong><br>${r.recommended_action||''}</p><p><strong>Research:</strong><br>${r.google_query||''}<br>${r.assessor_query||''}<br>${r.people_search_query||''}</p>`;document.getElementById('modal').style.display='flex';}
</script><?php goliath_ui_close(); ?></body></html>