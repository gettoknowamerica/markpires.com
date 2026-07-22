<?php
session_start(); require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){
  header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/followup-command.php'));
  exit;
}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sbq($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>35]);
  $b=curl_exec($ch); curl_close($ch); $d=json_decode($b,true); return is_array($d)?$d:[];
}
function pick($a,$keys,$def=''){foreach($keys as $k){if(isset($a[$k])&&$a[$k]!==''&&$a[$k]!==null)return $a[$k];}return $def;}
function phone_clean($p){return preg_replace('/\D+/','',(string)$p);}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';

$leads=sbq('leads?select=*&order=created_at.desc&limit=160');
$callbacks=sbq('after_hours_callbacks?select=*&order=created_at.desc&limit=120');
$commands=sbq('goliath_commands?select=*&order=created_at.desc&limit=160');

$rows=[];
foreach($leads as $l){
  $score=(int)pick($l,['lead_score','adaptive_score','score'],70);
  $name=pick($l,['name','full_name','email'],'Unknown Lead');
  $type=strtolower(pick($l,['type','lead_type','intent','category'],'lead'));
  $town=pick($l,['town','city','target_area']);
  $timeline=pick($l,['timeline','timeframe']);
  $phone=pick($l,['phone','phone_number','mobile']);
  $email=pick($l,['email']);
  $message=pick($l,['message','notes','summary','detail'],'');
  $drip=pick($l,['drip_status','followup_status','status'],'Not Set');
  $isSeller=(str_contains($type,'sell')||str_contains(strtolower($message),'sell')||str_contains(strtolower($message),'valuation'));
  $rows[]=['source'=>'lead','score'=>$score,'name'=>$name,'type'=>$isSeller?'Seller':'Buyer','town'=>$town,'timeline'=>$timeline,'phone'=>$phone,'email'=>$email,'message'=>$message,'drip'=>$drip,'raw'=>$l];
}
foreach($callbacks as $c){
  $rows[]=['source'=>'callback','score'=>85,'name'=>pick($c,['name','lead_name','phone'],'Callback'),'type'=>'Callback','town'=>pick($c,['town','city']),'timeline'=>pick($c,['best_time','status']),'phone'=>pick($c,['phone']),'email'=>pick($c,['email']),'message'=>pick($c,['notes','summary','transcript'],''),'drip'=>pick($c,['status'],'Callback'),'raw'=>$c];
}
usort($rows,fn($a,$b)=>($b['score']<=>$a['score']));
$active=count(array_filter($rows,fn($r)=>strtolower($r['drip'])!=='not set' && strtolower($r['drip'])!=='off'));
$sellers=count(array_filter($rows,fn($r)=>$r['type']==='Seller'));
$buyers=count(array_filter($rows,fn($r)=>$r['type']==='Buyer'));
$hot=count(array_filter($rows,fn($r)=>$r['score']>=85));
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Follow-Up Command</title>
<link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=355">
<link rel="stylesheet" href="/dashboard/assets/goliath-ui.css?v=34">
<script src="/dashboard/assets/goliath-ui.js?v=34" defer></script>
<link rel="stylesheet" href="/dashboard/assets/goliath-ask-console-v33-2.css?v=332">
<script src="/dashboard/assets/goliath-ask-console-v33-2.js?v=332" defer></script>
<script src="/dashboard/assets/goliath-command-bus-v35.js?v=35" defer></script>
<style>
.flowBar{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin:14px 0}
.flowStep{background:#08111f;border:1px solid #263753;border-radius:14px;padding:10px;text-align:center;position:relative}
.flowStep strong{display:block;color:#fff;font-size:12px}.flowStep small{color:#94a3b8}.flowIcon{font-size:22px;margin-bottom:4px}
.flowStep:after{content:"";position:absolute;right:-8px;top:50%;width:8px;height:1px;background:#c8a96e66}.flowStep:last-child:after{display:none}
.followStatus{display:inline-flex;gap:5px;align-items:center;border-radius:999px;padding:4px 8px;font-size:10px;font-weight:950;border:1px solid #ffffff22}
.dripOn{background:#166534;color:#fff}.dripWarm{background:#b7791f;color:#fff}.dripOff{background:#991b1b;color:#fff}
.flowMini{display:flex;gap:4px;flex-wrap:wrap}
.flowMini span{font-size:10px;border:1px solid #ffffff22;border-radius:999px;padding:3px 6px;color:#cbd5e1;background:#0f172a}
@media(max-width:1000px){.flowBar{grid-template-columns:1fr 1fr}.flowStep:after{display:none}}
@media(max-width:700px){.flowBar{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="g-shell">
<?php require __DIR__.'/includes/goliath-sidebar-v34.php'; ?>
<main class="g-main">
<section class="g-top">
  <div>
    <h1>Follow-Up Command</h1>
    <p>Jessica owns the relationship. Einstein, Scout, Scorsese, Shakespeare, and Rockefeller support every lead with intelligence, content, publishing, and ROI decisions.</p>
  </div>
  <div class="g-actions">
    <input class="g-search" id="followSearch" placeholder="Search name, town, type, phone..." oninput="gFilterRows('followSearch','#followRows tr')">
    <button class="g-btn g-btn-gold ask">🎤 Ask Goliath</button>
    <a class="g-btn g-btn-dark" target="_blank" href="/lead-engine/cron-drip.php?key=<?=h($key)?>">💬 Run Jessica Drip</a>
  </div>
</section>

<section class="flowBar">
  <div class="flowStep"><div class="flowIcon">💬</div><strong>Jessica</strong><small>intake + drip</small></div>
  <div class="flowStep"><div class="flowIcon">🧠</div><strong>Einstein</strong><small>90-day MLS stats</small></div>
  <div class="flowStep"><div class="flowIcon">🕵️</div><strong>Scout</strong><small>research + verify</small></div>
  <div class="flowStep"><div class="flowIcon">🎬</div><strong>Scorsese</strong><small>video + graphics</small></div>
  <div class="flowStep"><div class="flowIcon">✒️</div><strong>Shakespeare</strong><small>blog + publish</small></div>
  <div class="flowStep"><div class="flowIcon">💬</div><strong>Jessica</strong><small>personal touch</small></div>
  <div class="flowStep"><div class="flowIcon">💰</div><strong>Rockefeller</strong><small>ROI priority</small></div>
</section>

<section class="g-kpis">
  <a class="g-kpi g-blue"><div class="n"><?=count($rows)?></div><strong>Total Follow-Ups</strong><small>relationship queue</small></a>
  <a class="g-kpi g-green"><div class="n"><?=$active?></div><strong>Drip Active</strong><small>Jessica working</small></a>
  <a class="g-kpi g-red"><div class="n"><?=$hot?></div><strong>Hot</strong><small>score 85+</small></a>
  <a class="g-kpi g-gold"><div class="n"><?=$buyers?></div><strong>Buyers</strong><small>content journeys</small></a>
  <a class="g-kpi g-purple"><div class="n"><?=$sellers?></div><strong>Sellers</strong><small>listing packages</small></a>
  <a class="g-kpi g-orange"><div class="n"><?=count($commands)?></div><strong>Team Commands</strong><small>Goliath queue</small></a>
</section>

<section class="g-panel">
<h2>Relationship Pipeline <span>stealth follow-up rows</span></h2>
<div class="g-tableWrap">
<table class="g-stealthTable">
<thead><tr><th></th><th>Lead</th><th>Type / Town</th><th>Phone</th><th>Drip</th><th>Team Flow</th><th style="text-align:right">Actions</th></tr></thead>
<tbody id="followRows">
<?php foreach($rows as $r):
  $digits=phone_clean($r['phone']);
  $dot=$r['score']>=85?'g-s-green':($r['score']>=65?'g-s-yellow':'g-s-red');
  $dripClass=strtolower($r['drip'])==='not set'?'dripOff':(str_contains(strtolower($r['drip']),'open')?'dripWarm':'dripOn');
  $search=strtolower($r['name'].' '.$r['type'].' '.$r['town'].' '.$r['phone'].' '.$r['email'].' '.$r['message']);
  $intel=[
    'name'=>$r['name'],
    'phone'=>$r['phone'],
    'email'=>$r['email'],
    'drip_status'=>$r['drip'],
    'address'=>$r['town'],
    'notes'=>$r['message'],
    'recommended_action'=>'Begin the full Goliath follow-up flow: Jessica intake, Einstein 90-day MLS analysis, Scout verification, Scorsese personalized creative, Shakespeare publishing, Jessica drip, Rockefeller ROI tracking.',
    'content_angle'=>$r['type']==='Seller'
      ? 'Seller content: town-specific 90-day MLS stats, list-to-sale ratio in price band, top dollar strategy, House Detective pitch, and door-knock package.'
      : 'Buyer content: town-specific 90-day MLS stats, buyer guide, relocation/lifestyle content, modern homes content, and personalized property education.'
  ];
?>
<tr data-search="<?=h($search)?>" onclick='openGoliathDrawer(<?=h(json_encode($intel,JSON_UNESCAPED_SLASHES))?>)'>
<td><span class="g-statusDot <?=$dot?>"></span></td>
<td><div class="g-name"><?=h($r['name'])?></div><div class="g-subtle"><?=h($r['email']?:'No email')?> · Score <?=h($r['score'])?></div></td>
<td><div><?=h($r['type'])?></div><div class="g-subtle"><?=h($r['town']?:'Town needed')?> · <?=h($r['timeline']?:'Timeline unknown')?></div></td>
<td><?php if($digits): ?><a class="g-phone" onclick="event.stopPropagation()" href="tel:+1<?=h($digits)?>"><?=h($r['phone'])?></a><?php else: ?><span class="g-subtle">No phone</span><?php endif; ?></td>
<td><span class="followStatus <?=$dripClass?>"><?=h($r['drip'])?></span></td>
<td><div class="flowMini"><span>Jessica</span><span>Einstein</span><span>Scout</span><span>Scorsese</span><span>Shakespeare</span><span>Rockefeller</span></div></td>
<td><div class="g-pillRow">
<?php if($digits): ?><a class="g-pill g-pill-blue" onclick="event.stopPropagation()" href="tel:+1<?=h($digits)?>">☎ Call</a><a class="g-pill g-pill-green" onclick="event.stopPropagation()" href="sms:+1<?=h($digits)?>">💬 Text</a><?php endif; ?>
<?php if($r['email']): ?><a class="g-pill g-pill-blue" onclick="event.stopPropagation()" href="mailto:<?=h($r['email'])?>">✉ Email</a><?php endif; ?>
<button class="g-pill g-pill-purple" onclick='event.stopPropagation();openGoliathDrawer(<?=h(json_encode($intel,JSON_UNESCAPED_SLASHES))?>)'>🧠 Intel</button>
<button class="g-pill g-pill-gold" onclick="event.stopPropagation();startGoliathDrip(<?=h(json_encode($r['name']))?>,<?=h(json_encode($r['type']))?>,<?=h(json_encode($r['town']))?>,<?=h(json_encode($r['phone']))?>,<?=h(json_encode($r['email']))?>)">⭐ Start Drip</button>
</div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
</main>
</div>

<script>
const KEY=<?=json_encode($key)?>;
async function startGoliathDrip(name,type,town,phone,email){
  const prompt=`Begin full Goliath follow-up flow for ${name}. Type: ${type}. Town/property target: ${town}. Contact: ${phone} ${email}.
Flow:
1. Jessica intake and first message.
2. Einstein analyze only last 90 days of MLS stats for the relevant town/property/price band: list-price-to-sale-price ratio, days on market, inventory, absorption, median sale price.
3. Scout enrich owner/buyer/contact details.
4. Scorsese create personalized video/graphic/content angle.
5. Shakespeare create blog/email/social drip assets.
6. Jessica begin buyer/seller drip.
7. Rockefeller score revenue priority.`;
  await goliathCommand({key:KEY,command_type:'full_followup_drip',department:'Jessica',title:'Jessica started full Goliath drip for '+name,prompt,priority:120,roi_estimate:type==='Seller'?25000:12000,metadata:{name,type,town,phone,email,source:'followup_command'}});
}
</script>
</body>
</html>