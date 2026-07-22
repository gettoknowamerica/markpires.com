<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/scout-expired-upload.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbq($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch); curl_close($ch); $d=json_decode($b,true); return is_array($d)?$d:[];
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$opps=sbq('scout_opportunity_files?select=*&order=created_at.desc&limit=120');
$hot=count(array_filter($opps,fn($r)=>(int)($r['lead_score']??0)>=85));
$expired=count(array_filter($opps,fn($r)=>($r['opportunity_type']??'')==='expired_listing'));
$fsbo=count(array_filter($opps,fn($r)=>($r['opportunity_type']??'')==='fsbo'));
?><!doctype html>
<html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scout Upload Contacts</title>
<style>
body{margin:0;background:#07101d;color:#eef5ff;font-family:Inter,system-ui,Segoe UI,Arial}.wrap{max-width:1360px;margin:0 auto;padding:24px}.top{display:flex;justify-content:space-between;gap:16px;align-items:center}.btn{background:#0e1b2e;border:1px solid #28405f;color:#eaf2ff;text-decoration:none;border-radius:12px;padding:10px 14px;font-weight:900;cursor:pointer}.gold{background:#2b1b07;border-color:#f6c85f;color:#ffe9ad}.green{background:#062a1b;border-color:#33d28b}.panel{background:#081321;border:1px solid #1d334e;border-radius:22px;padding:18px;margin-top:18px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.kpi{background:#0d1929;border:1px solid #2a4464;border-radius:16px;padding:14px}.kpi .n{font-size:32px;color:#f6c85f;font-weight:950}.upload{border:1px dashed #4b6588;padding:18px;border-radius:16px;background:#07101d}.upload input,.upload textarea{width:100%;box-sizing:border-box;background:#050b13;color:#fff;border:1px solid #293d58;border-radius:12px;padding:12px;margin:7px 0}.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:14px}.card{background:#0c1626;border:1px solid #28405f;border-radius:18px;padding:15px}.card.hot{border-color:#f6c85f}.meta{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#9fb2cc}.score{float:right;background:#f6c85f;color:#081321;border-radius:999px;padding:7px 10px;font-weight:950}.small{color:#9fb2cc}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.pill{background:#14243a;border:1px solid #355171;color:#eaf2ff;text-decoration:none;border-radius:10px;padding:8px 10px;font-weight:850}.result{white-space:pre-wrap;background:#050b13;border:1px solid #293d58;border-radius:12px;padding:12px;max-height:280px;overflow:auto}.two{display:grid;grid-template-columns:1fr 1fr;gap:16px}@media(max-width:900px){.grid,.two{grid-template-columns:1fr}.top{display:block}}
</style></head><body><div class="wrap">
<div class="top"><div><h1>Scout Upload Contacts</h1><p class="small">Expired CSV / CRV upload, FSBO seeds, opportunity scoring, and Jessica speed-to-lead handoff. Uploaded CSV files are saved in /data/scout_uploads/.</p></div><div><a class="btn" href="/dashboard/goliath-mission-control.php">Mission Control</a> <a class="btn" href="/dashboard/scout-intelligence.php">Scout Intelligence</a></div></div>
<div class="grid"><div class="kpi"><div class="n"><?=count($opps)?></div><strong>Total Opportunities</strong></div><div class="kpi"><div class="n"><?=$expired?></div><strong>Expireds</strong></div><div class="kpi"><div class="n"><?=$fsbo?></div><strong>FSBO</strong></div><div class="kpi"><div class="n"><?=$hot?></div><strong>Hot</strong></div></div>

<div class="two">
<section class="panel"><h2>Upload Contacts / Expired CSV</h2><div class="upload"><form id="csvForm"><input type="hidden" name="key" value="<?=h($key)?>"><input type="file" name="csv" accept=".csv,text/csv" required><button class="btn gold" type="submit">Import Expired Listings</button></form><p class="small">Accepted expired-listing columns: Owner Name, Property Address, Town, Phone, Email, MLS #, List Price, Expired Date, Previous Agent, Brokerage, DOM, Notes. If you upload an agent/master contact file, Scout will save it to /data/scout_uploads but will not import it as expired listings.</p></div></section>
<section class="panel"><h2>Seed FSBO URLs</h2><textarea id="fsboUrls" rows="7" placeholder="Paste one FSBO/Zillow/public opportunity URL per line..."></textarea><button class="btn green" onclick="seedFsbo()">Send URLs to Scout</button></section>
</div>

<section class="panel"><h2>Import Result</h2><div id="result" class="result">Ready.</div></section>

<section class="panel"><h2>Scout Opportunity Files</h2><div class="cards">
<?php foreach($opps as $o): $score=(int)($o['lead_score']??0); $phone=$o['phone']??''; $email=$o['email']??''; $addr=trim(($o['property_address']??'').' '.($o['town']??'')); ?>
<div class="card <?=$score>=85?'hot':''?>"><span class="score"><?=$score?></span><div class="meta"><?=h($o['opportunity_type']??'opportunity')?> · <?=h($o['source']??'')?></div><h3><?=h($o['property_address']?:'Unknown Address')?></h3><p class="small"><?=h($o['town']??'')?> <?=h($o['state']??'CT')?><?=!empty($o['list_price'])?' · $'.number_format((float)$o['list_price']):''?></p><p><?=h($o['scout_summary']??'Scout opportunity file ready.')?></p><p><strong>Action:</strong> <?=h($o['recommended_action']??'Review and contact.')?></p><div class="actions"><?php if($phone):?><a class="pill" href="tel:<?=h(preg_replace('/[^0-9+]/','',$phone))?>">Call</a><?php endif;?><?php if($email):?><a class="pill" href="mailto:<?=h($email)?>">Email</a><?php endif;?><?php if($addr):?><a class="pill" target="_blank" href="https://www.google.com/maps/search/<?=rawurlencode($addr.' CT')?>">Map</a><?php endif;?><?php if(!empty($o['source_url'])):?><a class="pill" target="_blank" href="<?=h($o['source_url'])?>">Source</a><?php endif;?></div></div>
<?php endforeach;?>
</div></section>
</div>
<script>
const KEY=<?=json_encode($key)?>;
const result=document.getElementById('result');
document.getElementById('csvForm').addEventListener('submit',async e=>{
 e.preventDefault(); result.textContent='Importing expired listings...';
 const fd=new FormData(e.target);
 const r=await fetch('/lead-engine/scout-expired-import.php',{method:'POST',body:fd});
 const j=await r.json(); result.textContent=JSON.stringify(j,null,2);
 if(j.imported>0) setTimeout(()=>location.reload(),1500);
});
async function seedFsbo(){
 result.textContent='Sending FSBO URLs to Scout...';
 const urls=document.getElementById('fsboUrls').value.split(/\s+/).filter(Boolean);
 const r=await fetch('/lead-engine/scout-fsbo-seed.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:KEY,urls})});
 const j=await r.json(); result.textContent=JSON.stringify(j,null,2);
 if(j.created_count>0) setTimeout(()=>location.reload(),1500);
}
</script></body></html>