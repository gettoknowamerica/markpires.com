<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-agent-detail.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbq_g57($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>25]);
  $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $d=json_decode($b,true); return ($http>=200&&$http<300&&is_array($d))?$d:[];
}
$agents=[
  'Jessica'=>['title'=>'Director of Client Relationships','icon'=>'✉️','tag'=>'Trust is your deliverable.'],
  'Scout'=>['title'=>'Director of Intelligence','icon'=>'🕵️','tag'=>'Every clue matters.'],
  'Scorsese'=>['title'=>'Executive Creative Director','icon'=>'🎬','tag'=>'Every frame should move someone.'],
  'Mozart'=>['title'=>'Chief Emotional Experience Officer','icon'=>'🎼','tag'=>'Greatness is uncovered, not manufactured.'],
  'Shakespeare'=>['title'=>'Chief Storytelling Officer','icon'=>'✒️','tag'=>'Every word should create clarity, confidence, or connection.'],
  'Einstein'=>['title'=>'Chief Data Scientist','icon'=>'📊','tag'=>'What does the evidence tell us?'],
  'Columbo'=>['title'=>'Chief Historian & Legacy Curator','icon'=>'🕵️‍♂️','tag'=>'Nothing meaningful should ever be forgotten.'],
  'Prospector'=>['title'=>'Chief Opportunity Development Officer','icon'=>'⛏️','tag'=>'Keep digging.'],
  'Rockefeller'=>['title'=>'Chief Growth Officer','icon'=>'💰','tag'=>'Sustainable growth compounds.'],
  'Pandora'=>['title'=>'Chief Innovation & Strategic Expansion Officer','icon'=>'🌍','tag'=>'The future belongs to those willing to build it.'],
  'Goliath'=>['title'=>'Chief Executive Officer','icon'=>'⚡','tag'=>'Serve First. Lead Always.']
];
$agent=$_GET['department']??$_GET['agent']??'Goliath';
if(!isset($agents[$agent]))$agent='Goliath';
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';

if($agent === 'Scout'){
  $opps = sbq_g57('scout_opportunity_files?select=*&order=created_at.desc&limit=500');
  $research = sbq_g57('scout_research_queue?select=*&order=created_at.desc&limit=500');
  $leads = sbq_g57('leads?select=*&order=created_at.desc&limit=300');
  $rows=[]; $seen=[];
  $addRow=function($type,$source,$r) use (&$rows,&$seen){
    $address = $r['property_address'] ?? $r['address'] ?? '';
    $town = $r['town'] ?? '';
    $phone = $r['phone'] ?? $r['found_phone'] ?? '';
    $email = $r['email'] ?? $r['found_email'] ?? '';
    $name = $r['owner_name'] ?? $r['name'] ?? ($r['lead_name'] ?? 'Unknown');
    $key = strtolower(trim($address.'|'.$phone.'|'.$email.'|'.$name));
    if($key && isset($seen[$key])) return;
    $seen[$key]=true;
    $score = (int)($r['lead_score'] ?? $r['priority_score'] ?? $r['confidence'] ?? 0);
    if(!$score && !empty($phone)) $score=85;
    $rows[]=[
      'type'=>$type,
      'source'=>$source ?: ($r['source'] ?? ''),
      'id'=>$r['id'] ?? '',
      'name'=>$name,
      'phone'=>$phone,
      'email'=>$email,
      'address'=>$address,
      'town'=>$town,
      'price'=>$r['list_price'] ?? $r['price'] ?? $r['estimated_value'] ?? $r['budget'] ?? '',
      'score'=>$score,
      'status'=>$r['status'] ?? 'new',
      'created_at'=>$r['created_at'] ?? '',
      'notes'=>$r['scout_summary'] ?? $r['research_notes'] ?? $r['message'] ?? $r['notes'] ?? $r['goal'] ?? '',
      'action'=>$r['recommended_action'] ?? 'Review, call, map, and send to Jessica.',
      'url'=>$r['source_url'] ?? $r['page_url'] ?? ''
    ];
  };
  foreach($opps as $r) $addRow($r['opportunity_type'] ?? 'opportunity','Scout Opportunity',$r);
  foreach($research as $r) $addRow('research_queue','Scout Research',$r);
  foreach($leads as $r) $addRow($r['type'] ?? 'lead','Website Lead',$r);
  usort($rows,function($a,$b){return ($b['score']<=>$a['score']) ?: strcmp((string)$b['created_at'],(string)$a['created_at']);});
  $hot=count(array_filter($rows,fn($r)=>(int)$r['score']>=85));
  $phones=count(array_filter($rows,fn($r)=>!empty($r['phone'])));
  $emails=count(array_filter($rows,fn($r)=>!empty($r['email'])));
  ?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scout Revenue Desk</title>
  <link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33"><link rel="stylesheet" href="/dashboard/assets/goliath-v45-final.css?v=456">
  <style>
  body{background:#07101d;color:#edf4ff}.gScoutDesk{padding:18px}.hero{border:1px solid rgba(245,200,93,.28);background:linear-gradient(135deg,#050914,#171106);border-radius:22px;padding:18px 20px;display:flex;justify-content:space-between;gap:14px;align-items:center}.hero h1{margin:0;color:#fff;font-size:30px}.hero h2{margin:2px 0;color:#f5c85d;font-size:14px}.hero p{color:#cbd5e1;margin:4px 0 0}.seal{width:62px;height:62px;border-radius:18px;display:grid;place-items:center;background:radial-gradient(circle,#f5c85d,#8a5b12 60%,#0b1020);font-size:28px}.actions{display:flex;gap:8px;flex-wrap:wrap}.btn{background:#101a2b;border:1px solid #2b3d59;color:#eaf2ff;text-decoration:none;border-radius:10px;padding:9px 11px;font-weight:900;cursor:pointer;font-size:12px}.btn.gold{background:#f5c85d;color:#0b1220;border-color:#f5c85d}.btn.green{background:#06351f;border-color:#22c55e}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:12px 0}.stat{background:#111c2d;border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:12px}.stat strong{display:block;font-size:24px;color:#f5c85d;line-height:1}.stat span{text-transform:uppercase;letter-spacing:.08em;color:#aab6c8;font-size:10px}.toolbar{background:#081321;border:1px solid #1d334e;border-radius:15px;padding:10px;margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center}.toolbar input{background:#050b13;color:#fff;border:1px solid #293d58;border-radius:10px;padding:9px 10px;min-width:260px;flex:1;font-size:12px}.tableWrap{background:#081321;border:1px solid #1d334e;border-radius:16px;overflow:auto;max-height:calc(100vh - 265px)}.crm{width:100%;border-collapse:collapse;min-width:1280px;font-size:12px}.crm th{position:sticky;top:0;background:#0d1728;color:#f5c85d;text-align:left;text-transform:uppercase;font-size:9px;letter-spacing:.08em;padding:7px 8px;border-bottom:1px solid #263753;z-index:2}.crm td{padding:6px 8px;border-bottom:1px solid rgba(255,255,255,.055);vertical-align:middle;line-height:1.2}.crm tr:hover{background:rgba(245,200,93,.065)}.leadName{font-weight:950;color:#fff;font-size:12px}.sub{color:#95a7c0;font-size:10px;line-height:1.25}.score{display:inline-grid;place-items:center;min-width:31px;height:24px;border-radius:8px;background:#334155;color:#fff;font-weight:950;font-size:11px}.score.hot{background:#f5c85d;color:#0b1220}.pill{display:inline-block;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.06);border-radius:999px;padding:3px 6px;font-size:9px;color:#dbeafe;margin:1px 0}.rowActions{display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end}.rowActions a,.rowActions button{border:0;background:#13243b;color:#eaf2ff;text-decoration:none;border-radius:7px;padding:5px 7px;font-weight:850;cursor:pointer;font-size:10px;line-height:1}.rowActions .gold{background:#f5c85d;color:#0b1220}.miniForm{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;width:100%}.miniForm input{min-width:0}.miniForm button{grid-column:auto}.result{white-space:pre-wrap;color:#cbd5e1;margin-top:8px;font-size:12px}.empty{padding:18px;color:#cbd5e1}.gScoutCheck{width:16px;height:16px;accent-color:#f5c85d}.gScoutStickyActions{min-width:118px}.gScoutNotes{max-width:260px}.gScoutProperty{max-width:260px}.gScoutPhone,.gScoutEmail{white-space:nowrap}.gScoutTopline{display:flex;align-items:center;gap:8px}.gScoutTopline small{color:#64748b}@media(max-width:1000px){.stats{grid-template-columns:1fr 1fr}.hero{display:block}.miniForm{grid-template-columns:1fr}.toolbar input{min-width:0;width:100%}.tableWrap{max-height:none}.crm{min-width:1100px}}
  </style></head><body><div class="shell"><?php @require __DIR__.'/includes/goliath-sidebar-v33.php'; ?><main class="main gScoutDesk">
    <section class="hero"><div style="display:flex;gap:16px;align-items:center"><div class="seal">🕵️</div><div><p style="margin:0;color:#f5c85d;text-transform:uppercase;letter-spacing:.14em;font-weight:900;font-size:12px">Executive Office</p><h1>Scout Revenue Desk</h1><h2>Director of Intelligence</h2><p>Every contact, expired, FSBO, foreclosure, and website lead in one clean speed-to-lead table.</p></div></div><div class="actions"><a class="btn" href="/dashboard/goliath-mission-control.php">Mission Control</a><a class="btn gold" href="/dashboard/scout-expired-upload.php">Upload Contacts</a><a class="btn" href="/dashboard/scout-intelligence.php">Scout Queue</a><a class="btn green" target="_blank" href="/lead-engine/scout-revenue-cron.php?key=<?=h($key)?>">Run Scout Now</a></div></section>
    <section class="stats"><div class="stat"><strong><?=count($rows)?></strong><span>Total Contacts / Opportunities</span></div><div class="stat"><strong><?=$hot?></strong><span>Hot Leads 85+</span></div><div class="stat"><strong><?=$phones?></strong><span>Phone Numbers</span></div><div class="stat"><strong><?=$emails?></strong><span>Email Addresses</span></div></section>
    <section class="toolbar"><input id="search" placeholder="Search all Scout contacts: name, address, town, phone, email, source..." onkeyup="filterRows()"><a class="btn gold" href="/dashboard/scout-expired-upload.php">Upload CSV / CRV</a><button class="btn" onclick="document.getElementById('quickAdd').style.display=document.getElementById('quickAdd').style.display==='none'?'grid':'none'">Add Address</button></section>
    <section class="toolbar miniForm" id="quickAdd" style="display:none"><input id="qa_owner" placeholder="Owner name"><input id="qa_address" placeholder="Property address"><input id="qa_town" placeholder="Town"><input id="qa_price" placeholder="Price / Value"><button class="btn gold" onclick="quickAdd()">Send to Scout</button><div id="qa_result" class="result"></div></section>
    <section class="tableWrap"><table class="crm" id="crm"><thead><tr><th><input type="checkbox" class="gScoutCheck" onclick="document.querySelectorAll('.rowCheck').forEach(c=>c.checked=this.checked)"></th><th>Score</th><th>Contact / Owner</th><th>Property</th><th>Phone</th><th>Email</th><th>Source / Type</th><th>Status</th><th>Scout Notes</th><th>Actions</th></tr></thead><tbody>
    <?php if(!$rows): ?><tr><td colspan="10" class="empty">No Scout contacts yet. Upload expired CSV, seed FSBO URLs, or add an address.</td></tr><?php endif; ?>
    <?php foreach($rows as $r): $phone=preg_replace('/[^0-9+]/','',$r['phone']); $addr=trim($r['address'].' '.$r['town'].' CT'); ?>
      <tr data-search="<?=h(strtolower(json_encode($r)))?>"><td><input class="gScoutCheck rowCheck" type="checkbox"></td><td><span class="score <?=($r['score']>=85?'hot':'')?>"><?=h($r['score']?:'—')?></span></td><td><div class="leadName"><?=h($r['name']?:'Unknown')?></div><div class="sub"><?=h($r['created_at']?date('M j g:i A',strtotime($r['created_at'])):'')?></div></td><td class="gScoutProperty"><strong><?=h($r['address']?:'—')?></strong><div class="sub"><?=h($r['town']?:'')?></div><?php if($r['price']):?><div class="sub">$<?=h(is_numeric($r['price'])?number_format((float)$r['price']):$r['price'])?></div><?php endif;?></td><td class="gScoutPhone"><?=h($r['phone']?:'—')?></td><td class="gScoutEmail"><?=h($r['email']?:'—')?></td><td><span class="pill"><?=h($r['type'])?></span><div class="sub"><?=h($r['source'])?></div></td><td><span class="pill"><?=h($r['status'])?></span></td><td class="gScoutNotes"><div class="sub"><?=h(mb_strimwidth($r['notes'] ?: $r['action'],0,140,'...'))?></div></td><td class="gScoutStickyActions"><div class="rowActions"><?php if($phone):?><a class="gold" href="tel:<?=h($phone)?>">Call</a><a href="sms:<?=h($phone)?>">Text</a><?php endif;?><?php if($r['email']):?><a href="mailto:<?=h($r['email'])?>">Email</a><?php endif;?><?php if(trim($addr)):?><a target="_blank" href="https://www.google.com/maps/search/<?=rawurlencode($addr)?>">Map</a><?php endif;?><?php if($r['url']):?><a target="_blank" href="<?=h($r['url'])?>">Source</a><?php endif;?><button onclick="sendJessica('<?=h($r['id'])?>')">Jessica</button></div></td></tr>
    <?php endforeach; ?>
    </tbody></table></section>
  </main></div>
  <script>
  const KEY=<?=json_encode($key)?>;
  function filterRows(){const q=document.getElementById('search').value.toLowerCase();document.querySelectorAll('#crm tbody tr').forEach(tr=>{tr.style.display=(tr.dataset.search||'').includes(q)?'':'none'});}
  async function quickAdd(){const payload={key:KEY,owner_name:qa_owner.value,property_address:qa_address.value,town:qa_town.value,price:qa_price.value,metadata:{source:'scout_revenue_desk_quick_add'}};qa_result.textContent='Sending to Scout...';const r=await fetch('/lead-engine/scout-research-enqueue.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});const j=await r.json();qa_result.textContent=JSON.stringify(j,null,2);if(j.success)setTimeout(()=>location.reload(),900);}
  async function sendJessica(id){const body={key:KEY,action:'command',command_type:'jessica_speed_to_lead_from_scout',department:'Jessica',title:'Jessica speed-to-lead package requested',prompt:'Create call script, voicemail, SMS, email, and follow-up plan for Scout item '+id,priority:130,source:'scout_revenue_desk'};const r=await fetch('/lead-engine/goliath-event-bus.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const j=await r.json();alert(j.success?'Sent to Jessica':'Jessica queue issue');}
  </script></body></html><?php exit; }
?><!doctype html>
<html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($agent)?> Office</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33">
<link rel="stylesheet" href="/dashboard/assets/goliath-v45-final.css?v=456">
<link rel="stylesheet" href="/dashboard/assets/goliath-agent-detail-v54.css?v=541">
</head><body>
<div class="shell"><?php @require __DIR__.'/includes/goliath-sidebar-v33.php'; ?>
<main class="main g54Office" data-agent="<?=h($agent)?>">
<section class="g54Hero">
  <div class="g54Seal"><span><?=h($agents[$agent]['icon'])?></span></div>
  <div><p class="g54Eyebrow">Executive Office</p><h1><?=h($agent)?></h1><h2><?=h($agents[$agent]['title'])?></h2><p><?=h($agents[$agent]['tag'])?></p></div>
  <div class="g54Actions"><a class="btn dark" href="/dashboard/goliath-mission-control.php">Mission Control</a><a class="btn" href="/dashboard/goliath-executive-brief.php">Executive Brief</a></div>
</section>
<section class="g54Stats" id="g54Stats"><div><strong>Loading</strong><span>Ready Work</span></div><div><strong>Loading</strong><span>Queued Items</span></div><div><strong><?=h($agent)?></strong><span>Commissioned Executive</span></div></section>
<section class="g54Grid">
  <aside class="g54QueuePanel">
    <div class="g54Tabs"><button class="active" data-tab="ready">Finished Work</button><button data-tab="queued">Queued / Working</button><button data-tab="all">All</button></div>
    <div class="g54Search"><input id="g54Search" placeholder="Search this office..." autocomplete="off"></div>
    <div id="g54List" class="g54List"><div class="g54Skeleton">Loading real work...</div></div>
  </aside>
  <section class="g54Viewer">
    <div class="g54ViewerTop"><div><p id="g54Kind">Select work</p><h2 id="g54Title">Open a deliverable from <?=h($agent)?>'s office</h2></div><div id="g54Score" class="g54Score">—</div></div>
    <div id="g54Canvas" class="g54Canvas g54CanvasEmpty">
      <h3>No status fluff. This window shows the work.</h3>
      <p>Click a finished or queued item on the left to open the actual lead, blog, video package, opportunity, email, analysis, or CEO report.</p>
    </div>
    <div class="g54PromptBox">
      <label>Executive Intercom</label>
      <textarea id="g54Prompt" placeholder="Speak or type your direction to <?=h($agent)?>..."></textarea>
      <div><button class="btn" onclick="g54CopyPrompt()">Send to <?=h($agent)?></button><button class="btn dark" onclick="document.getElementById('g54Prompt').value=''">Clear</button></div>
    </div>
  </section>
</section>
</main></div>
<script>window.G54_AGENT=<?=json_encode($agent)?>;</script>
<script src="/dashboard/assets/goliath-agent-detail-v54.js?v=541" defer></script>
</body></html>
