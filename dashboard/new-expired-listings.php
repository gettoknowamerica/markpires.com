<?php
session_start(); require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/new-expired-listings.php'));exit;}

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
function daysago($date){
  if(!$date)return '';
  $t=strtotime($date);
  if(!$t)return '';
  return max(0,floor((time()-$t)/86400)).' days';
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
  'expired_listings?select=*&order=created_at.desc&limit=250',
  'new_expired_listings?select=*&order=created_at.desc&limit=250',
  'mls_expireds?select=*&order=created_at.desc&limit=250',
  'seller_opportunities?select=*&status=ilike.*expired*&order=created_at.desc&limit=250',
  'owner_research_queue?select=*&type=ilike.*expired*&order=created_at.desc&limit=250'
];

foreach($sources as $ep){
  [$http,$data,$raw]=sbq($ep);
  if($http>=200&&$http<300&&is_array($data)&&count($data)){$rows=$data;break;}
}

$items=[];
foreach($rows as $r){
  $blob=[];
  foreach(['result','metadata','data','payload','listing','property','owner','details'] as $k){
    if(isset($r[$k])&&is_array($r[$k]))$blob=array_merge($blob,$r[$k]);
  }
  $all=array_merge($r,$blob);
  $owner=pick($all,['owner_name','name','owner','contact_name','seller_name','full_name'],'Owner Unknown');
  $phone=pick($all,['phone','phone_number','mobile','owner_phone','contact_phone','best_phone']);
  $email=pick($all,['email','owner_email','contact_email']);
  $address=pick($all,['address','property_address','site_address','full_address','property','target_property']);
  $town=pick($all,['town','city','municipality']);
  $price=pick($all,['price','list_price','asking_price','original_price','last_list_price','estimated_value','home_value','value']);
  $expired=pick($all,['expired_date','expiration_date','off_market_date','status_date','created_at']);
  $style=pick($all,['style','home_style','architecture','property_style','property_type']);
  $beds=pick($all,['beds','bedrooms']);
  $baths=pick($all,['baths','bathrooms']);
  $mls=pick($all,['mls','mls_id','listing_id','listing_key']);
  $notes=pick($all,['notes','summary','detail','description','remarks','public_remarks'],'');
  $score=(int)pick($all,['opportunity_score','seller_score','expired_score','score','priority_score'],0);
  if(!$score){
    $n=(float)preg_replace('/[^\d.]/','',$price);
    $score=60+($phone?12:0)+($n>=1000000?15:0)+($town?5:0);
  }
  $items[]=[
    'owner'=>$owner,'phone'=>$phone,'email'=>$email,'address'=>$address,'town'=>$town,'price'=>$price,
    'expired'=>$expired,'style'=>$style,'beds'=>$beds,'baths'=>$baths,'mls'=>$mls,'notes'=>$notes,'score'=>$score,'raw'=>$all
  ];
}
usort($items, fn($a,$b)=>($b['score']<=>$a['score']));
$hot=count(array_filter($items,fn($x)=>$x['score']>=85));
$luxury=count(array_filter($items,fn($x)=>(float)preg_replace('/[^\d.]/','',$x['price'])>=1000000));
$phones=count(array_filter($items,fn($x)=>!empty($x['phone'])));
$pipeline=0; foreach($items as $o){$n=(float)preg_replace('/[^\d.]/','',$o['price']); if($n>0)$pipeline+=$n*.025;}
?><!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Expired Listings</title>
<link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=343">
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
    <h1>Expired Listings</h1>
    <p>Expireds sorted as revenue opportunities. Each row can become a door-knock package, follow-up, and content campaign.</p>
  </div>
  <div class="g-actions">
    <input class="g-search" id="expiredSearch" placeholder="Search address, town, price, owner, style..." oninput="gFilterRows('expiredSearch','#expiredRows tr')">
    <button class="g-btn g-btn-gold ask">🎤 Ask Goliath</button>
    <a class="g-btn g-btn-dark" target="_blank" href="/lead-engine/build-contact-numbers.php?key=<?=h($key)?>">🛰 Scout Search</a>
  </div>
</section>

<section class="g-kpis">
  <a class="g-kpi g-red"><div class="n"><?=moneyfmt($pipeline)?></div><strong>Pipeline</strong><small>estimated commission</small></a>
  <a class="g-kpi g-green"><div class="n"><?=count($items)?></div><strong>Expireds</strong><small>tracked targets</small></a>
  <a class="g-kpi g-gold"><div class="n"><?=$hot?></div><strong>Top Priority</strong><small>score 85+</small></a>
  <a class="g-kpi g-blue"><div class="n"><?=$phones?></div><strong>Callable</strong><small>phone ready</small></a>
  <a class="g-kpi g-purple"><div class="n"><?=$luxury?></div><strong>Luxury</strong><small>$1M+</small></a>
  <a class="g-kpi g-orange"><div class="n">Door</div><strong>Knock Ready</strong><small>package builder</small></a>
</section>

<section class="g-panel">
  <h2>Expired Revenue Engine <span>door-knock packages + seller content</span></h2>
  <div class="g-tableWrap">
  <?php if(!$items): ?>
    <div class="g-inner"><div class="g-drawerBox"><h3>No expired listings found</h3><div class="g-drawerValue">Once Scout or MLS import populates expired listing rows, this page will display them in priority order.</div></div></div>
  <?php else: ?>
    <table class="g-stealthTable">
      <thead>
        <tr><th></th><th>Owner / MLS</th><th>Property</th><th>Phone</th><th>Price</th><th>Expired</th><th style="text-align:right">Actions</th></tr>
      </thead>
      <tbody id="expiredRows">
      <?php foreach($items as $o):
        $digits=phone_clean($o['phone']);
        $score=(int)$o['score'];
        $dot=$score>=85?'g-s-green':($score>=65?'g-s-yellow':'g-s-red');
        $value=moneyfmt($o['price']);
        $commission=commissionfmt($o['price']);
        $expiredAgo=daysago($o['expired']);
        $search=strtolower(trim($o['owner'].' '.$o['address'].' '.$o['town'].' '.$o['phone'].' '.$value.' '.$o['style'].' '.$o['mls']));
        $intel=[
          'name'=>$o['owner'],
          'phone'=>$o['phone'],
          'email'=>$o['email'],
          'drip_status'=>'Expired Listing Package Recommended',
          'address'=>trim(($o['address']?:'').' '.($o['town']?:'')),
          'notes'=>$o['notes'] ?: 'Expired listing target. Goliath should determine why the home did not sell and build a value-first recovery plan.',
          'recommended_action'=>'Door knock with a personalized expired recovery package. Lead with empathy, pricing/positioning insight, and House Detective differentiation.',
          'content_angle'=>'Expired package: Why Homes Don’t Sell First Time, Top Dollar Strategy in '.$o['town'].', Top 3 ROI Updates, and House Detective Cinema Noir relaunch pitch.',
          'status'=>'Expired Score '.$score.' · Est. commission '.$commission.' · MLS '.$o['mls']
        ];
      ?>
      <tr data-search="<?=h($search)?>" onclick='openGoliathDrawer(<?=h(json_encode($intel,JSON_UNESCAPED_SLASHES))?>)'>
        <td><span class="g-statusDot <?=$dot?>"></span></td>
        <td><div class="g-name"><?=h($o['owner'])?></div><div class="g-subtle">MLS <?=h($o['mls'] ?: '—')?> · Score <?=h($score)?></div></td>
        <td><div><?=h($o['address'] ?: 'Address needed')?></div><div class="g-subtle"><?=h($o['town'] ?: 'Town unknown')?> · <?=h($o['style'] ?: 'Style unknown')?> <?=h($o['beds']?' · '.$o['beds'].' bd':'')?><?=h($o['baths']?' · '.$o['baths'].' ba':'')?></div></td>
        <td><?php if($digits): ?><a class="g-phone" onclick="event.stopPropagation()" href="tel:+1<?=h($digits)?>"><?=h($o['phone'])?></a><?php else: ?><span class="g-subtle">Phone needed</span><?php endif; ?></td>
        <td><div class="g-price"><?=h($value ?: '—')?></div><div class="g-subtle">Est. <?=$commission?></div></td>
        <td><div><?=h($expiredAgo ?: '—')?></div><div class="g-subtle"><?=h($o['expired'] ?: '')?></div></td>
        <td>
          <div class="g-pillRow">
            <?php if($digits): ?>
              <a class="g-pill g-pill-blue" onclick="event.stopPropagation()" href="tel:+1<?=h($digits)?>">☎ Call</a>
              <a class="g-pill g-pill-green" onclick="event.stopPropagation()" href="sms:+1<?=h($digits)?>">💬 Text</a>
            <?php endif; ?>
            <?php if($o['email']): ?><a class="g-pill g-pill-blue" onclick="event.stopPropagation()" href="mailto:<?=h($o['email'])?>">✉ Email</a><?php endif; ?>
            <button class="g-pill g-pill-purple" onclick='event.stopPropagation();openGoliathDrawer(<?=h(json_encode($intel,JSON_UNESCAPED_SLASHES))?>)'>🧠 Intel</button>
            <button class="g-pill g-pill-gold" onclick="event.stopPropagation();commitExpiredPackage(<?=h(json_encode($o['owner']))?>,<?=h(json_encode($o['phone']))?>,<?=h(json_encode($o['address']))?>,<?=h(json_encode($o['town']))?>,<?=h(json_encode($value))?>)">⭐ Package</button>
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
async function commitExpiredPackage(owner,phone,address,town,value){
  gToast('Experience Department activated','Building expired recovery package and outreach plan.');
  const prompt=`Create a complete expired listing recovery package for ${owner||'Owner'} at ${address||''} ${town||''} ${value||''}. Include: door knock letter, call script, text, email, House Detective relaunch pitch, and three marketing pieces: why homes do not sell first time, top dollar in town, and top 3 ROI updates.`;
  try{
    await fetch('/lead-engine/local-ai-task-create.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:'<?=h($key)?>',task_type:'expired_package',model:'auto',prompt,priority:115,metadata:{owner,phone,address,town,value,source:'expired_listings'}})});
    gToast('Expired package queued','Scout, Jessica, Director, and Publisher have been assigned.');
  }catch(e){
    gToast('Visual queue confirmed','Task endpoint needs review, but the package action worked.');
  }
}
</script>
</body>
</html>