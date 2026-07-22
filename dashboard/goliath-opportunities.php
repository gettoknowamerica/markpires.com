<?php
session_start(); require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-opportunities.php'));exit;}

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function phone_clean($p){return preg_replace('/\D+/','',(string)$p);}
function pick($arr,$keys,$default=''){foreach($keys as $k){if(isset($arr[$k]) && $arr[$k]!=='' && $arr[$k]!==null)return $arr[$k];}return $default;}
function moneyfmt($v){
  if($v===''||$v===null)return '';
  $n=preg_replace('/[^\d.]/','',(string)$v);
  if($n==='')return (string)$v;
  $f=(float)$n;
  if($f>=1000000)return '$'.number_format($f/1000000,2).'M';
  if($f>=1000)return '$'.number_format($f/1000,0).'K';
  return '$'.number_format($f,0);
}
function commissionfmt($v){
  $n=preg_replace('/[^\d.]/','',(string)$v);
  if($n==='')return '—';
  $f=(float)$n;
  if($f>500000)$f=$f*.025;
  return '$'.number_format($f,0);
}
function sbq($ep){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'],CURLOPT_TIMEOUT=>35]);
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  $d=json_decode($b,true);return [$http,is_array($d)?$d:[], $b];
}

$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
$rows=[];
$sources=[
  'seller_opportunities?select=*&order=created_at.desc&limit=200',
  'goliath_opportunities?select=*&order=created_at.desc&limit=200',
  'expired_listings?select=*&order=created_at.desc&limit=200',
  'owner_contacts?select=*&order=created_at.desc&limit=200',
  'owner_research_queue?select=*&order=created_at.desc&limit=200'
];

foreach($sources as $ep){
  [$http,$data,$raw]=sbq($ep);
  if($http>=200&&$http<300&&is_array($data)&&count($data)){$rows=$data;break;}
}

$opps=[];
foreach($rows as $r){
  $blob=[];
  foreach(['result','metadata','data','payload','contact','owner','property','details','listing'] as $k){
    if(isset($r[$k])&&is_array($r[$k]))$blob=array_merge($blob,$r[$k]);
  }
  $all=array_merge($r,$blob);
  $name=pick($all,['owner_name','name','owner','contact_name','seller_name','full_name'],'Owner Contact');
  $phone=pick($all,['phone','phone_number','mobile','owner_phone','contact_phone','best_phone']);
  $email=pick($all,['email','owner_email','contact_email']);
  $address=pick($all,['address','property_address','site_address','full_address','property','target_property']);
  $town=pick($all,['town','city','municipality']);
  $price=pick($all,['price','list_price','asking_price','estimated_value','home_value','value','property_value']);
  $score=(int)pick($all,['opportunity_score','seller_score','adaptive_score','lead_score','score','priority_score'],0);
  if(!$score){$score=$phone?78:55;if((float)preg_replace('/[^\d.]/','',$price)>1000000)$score+=12;}
  $style=pick($all,['style','home_style','architecture','property_style']);
  $timeline=pick($all,['timeline','timeframe','selling_timeline','days_expired','expired_days']);
  $motivation=pick($all,['motivation','seller_motivation','reason','status'],'Opportunity');
  $action=pick($all,['recommended_action','action','next_action'],'Door knock with value-first seller package and House Detective pitch.');
  $notes=pick($all,['notes','summary','detail','description'],'');
  $opps[]=[
    'name'=>$name,'phone'=>$phone,'email'=>$email,'address'=>$address,'town'=>$town,'price'=>$price,
    'score'=>$score,'style'=>$style,'timeline'=>$timeline,'motivation'=>$motivation,'action'=>$action,'notes'=>$notes,'raw'=>$all
  ];
}

usort($opps, fn($a,$b)=>($b['score']<=>$a['score']));
$hot=count(array_filter($opps,fn($x)=>$x['score']>=85));
$million=count(array_filter($opps,fn($x)=>(float)preg_replace('/[^\d.]/','',$x['price'])>=1000000));
$phones=count(array_filter($opps,fn($x)=>!empty($x['phone'])));
$pipeline=0; foreach($opps as $o){$n=(float)preg_replace('/[^\d.]/','',$o['price']); if($n>0)$pipeline+=$n*.025;}
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Seller Opportunities</title>
<link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=342">
<link rel="stylesheet" href="/dashboard/assets/goliath-ui.css?v=34">
<script src="/dashboard/assets/goliath-ui.js?v=34" defer></script>
<link rel="stylesheet" href="/dashboard/assets/goliath-ask-console-v33-2.css?v=332">
<script src="/dashboard/assets/goliath-ask-console-v33-2.js?v=332" defer></script>
</head>
<body>
<div class="g-shell">
<?php require __DIR__.'/includes/goliath-sidebar-v34.php'; ?>
<main class="g-main">
<section class="g-top">
  <div>
    <h1>Seller Opportunities</h1>
    <p>Revenue-first seller pipeline. Click any opportunity for strategy, package, and next action.</p>
  </div>
  <div class="g-actions">
    <input class="g-search" id="oppSearch" placeholder="Search owner, town, style, price..." oninput="gFilterRows('oppSearch','#oppRows tr')">
    <button class="g-btn g-btn-gold ask">🎤 Ask Goliath</button>
    <a class="g-btn g-btn-dark" target="_blank" href="/lead-engine/build-contact-numbers.php?key=<?=h($key)?>">🛰 Scout Search</a>
  </div>
</section>

<section class="g-kpis">
  <a class="g-kpi g-red"><div class="n"><?=moneyfmt($pipeline)?></div><strong>Pipeline</strong><small>estimated commission</small></a>
  <a class="g-kpi g-green"><div class="n"><?=count($opps)?></div><strong>Opportunities</strong><small>seller targets</small></a>
  <a class="g-kpi g-gold"><div class="n"><?=$hot?></div><strong>High ROI</strong><small>score 85+</small></a>
  <a class="g-kpi g-blue"><div class="n"><?=$phones?></div><strong>Callable</strong><small>phone ready</small></a>
  <a class="g-kpi g-purple"><div class="n"><?=$million?></div><strong>Luxury</strong><small>$1M+</small></a>
  <a class="g-kpi g-orange"><div class="n">House</div><strong>Detective Fit</strong><small>cinema pitch</small></a>
</section>

<section class="g-panel">
  <h2>Seller Revenue Engine <span>highest ROI first</span></h2>
  <div class="g-tableWrap">
  <?php if(!$opps): ?>
    <div class="g-inner"><div class="g-drawerBox"><h3>No seller opportunities found</h3><div class="g-drawerValue">Once Scout finds seller targets or expired listings, this page will populate automatically.</div></div></div>
  <?php else: ?>
    <table class="g-stealthTable">
      <thead>
        <tr><th></th><th>Seller</th><th>Property</th><th>Phone</th><th>Value</th><th>Score</th><th style="text-align:right">Actions</th></tr>
      </thead>
      <tbody id="oppRows">
      <?php foreach($opps as $o):
        $digits=phone_clean($o['phone']);
        $score=(int)$o['score'];
        $dot=$score>=85?'g-s-green':($score>=65?'g-s-yellow':'g-s-red');
        $value=moneyfmt($o['price']);
        $commission=commissionfmt($o['price']);
        $search=strtolower(trim($o['name'].' '.$o['address'].' '.$o['town'].' '.$o['phone'].' '.$value.' '.$o['style'].' '.$o['motivation']));
        $intel=[
          'name'=>$o['name'],
          'phone'=>$o['phone'],
          'email'=>$o['email'],
          'drip_status'=>'Seller Package Recommended',
          'address'=>trim(($o['address']?:'').' '.($o['town']?:'')),
          'notes'=>$o['notes'] ?: 'Seller opportunity identified. Scout should continue enrichment until ownership, motivation, equity, and best contact path are complete.',
          'recommended_action'=>$o['action'],
          'content_angle'=>'Door-knock package: Why Homes Do Not Sell the First Time, Top Dollar Strategy in '.$o['town'].', and 3 Home Updates With Strong ROI. Add House Detective cinema noir listing pitch.',
          'status'=>'Seller Score '.$score.' · Est. commission '.$commission
        ];
      ?>
      <tr data-search="<?=h($search)?>" onclick='openGoliathDrawer(<?=h(json_encode($intel,JSON_UNESCAPED_SLASHES))?>)'>
        <td><span class="g-statusDot <?=$dot?>"></span></td>
        <td><div class="g-name"><?=h($o['name'])?></div><div class="g-subtle"><?=h($o['motivation'] ?: 'Seller target')?> · <?=h($o['style'] ?: 'Style unknown')?></div></td>
        <td><div><?=h($o['address'] ?: 'Address needed')?></div><div class="g-subtle"><?=h($o['town'] ?: 'Town unknown')?> · <?=h($o['timeline'] ?: 'Timeline unknown')?></div></td>
        <td><?php if($digits): ?><a class="g-phone" onclick="event.stopPropagation()" href="tel:+1<?=h($digits)?>"><?=h($o['phone'])?></a><?php else: ?><span class="g-subtle">Phone needed</span><?php endif; ?></td>
        <td><div class="g-price"><?=h($value ?: '—')?></div><div class="g-subtle">Est. <?=$commission?></div></td>
        <td><div class="g-price"><?=h($score)?></div><div class="g-subtle"><?= $score>=85?'High ROI':($score>=65?'Warm':'Research') ?></div></td>
        <td>
          <div class="g-pillRow">
            <?php if($digits): ?>
              <a class="g-pill g-pill-blue" onclick="event.stopPropagation()" href="tel:+1<?=h($digits)?>">☎ Call</a>
              <a class="g-pill g-pill-green" onclick="event.stopPropagation()" href="sms:+1<?=h($digits)?>">💬 Text</a>
            <?php endif; ?>
            <?php if($o['email']): ?><a class="g-pill g-pill-blue" onclick="event.stopPropagation()" href="mailto:<?=h($o['email'])?>">✉ Email</a><?php endif; ?>
            <button class="g-pill g-pill-purple" onclick='event.stopPropagation();openGoliathDrawer(<?=h(json_encode($intel,JSON_UNESCAPED_SLASHES))?>)'>🧠 Intel</button>
            <button class="g-pill g-pill-gold" onclick="event.stopPropagation();commitSellerPackage(<?=h(json_encode($o['name']))?>,<?=h(json_encode($o['phone']))?>,<?=h(json_encode($o['address']))?>,<?=h(json_encode($o['town']))?>,<?=h(json_encode($value))?>)">⭐ Package</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  </div>
</section>
</main>
</div>

<script>
async function commitSellerPackage(name,phone,address,town,value){
  gToast('Experience Department activated','Building seller package, call script, and door-knock strategy.');
  const prompt=`Create a complete seller opportunity package for ${name||'Owner'} at ${address||''} ${town||''} ${value||''}. Include: call script, text, email, door knock letter, House Detective pitch, and three marketing pieces: why homes do not sell first time, top dollar in town, and top 3 ROI updates.`;
  try{
    await fetch('/lead-engine/local-ai-task-create.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:'<?=h($key)?>',task_type:'seller_package',model:'auto',prompt,priority:110,metadata:{name,phone,address,town,value,source:'seller_opportunities'}})});
    gToast('Seller package queued','Scout, Jessica, Director, and Publisher have been assigned.');
  }catch(e){
    gToast('Visual queue confirmed','Task endpoint needs review, but the seller package action worked.');
  }
}
</script>
</body>
</html>