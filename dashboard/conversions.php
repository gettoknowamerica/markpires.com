<?php
/**
 * MarkPires Conversion Dashboard V1
 * Upload to: /public_html/dashboard/conversions.php
 */

session_start();
require_once __DIR__ . '/../lead-engine/config.php';
require_once __DIR__ . '/includes/goliath-ui.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location: /dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sb_conv($endpoint) {
  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
      'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 20
  ]);
  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $data=json_decode($body,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

$eventsRes = sb_conv('conversion_events?select=*&order=created_at.desc&limit=1000');
$leadsRes = sb_conv('leads?select=*&order=created_at.desc&limit=1000');

$events = $eventsRes['data'];
$leads = $leadsRes['data'];

$views=0; $clicks=0; $forms=0; $today=0;
$todayPrefix=date('Y-m-d');
$funnels=[]; $towns=[]; $campaigns=[];

foreach($events as $e){
  if(($e['event_type']??'')==='page_view') $views++;
  if(($e['event_type']??'')==='click') $clicks++;
  if(str_contains((string)($e['event_type']??''),'form')) $forms++;
  if(strpos((string)($e['created_at']??''),$todayPrefix)===0) $today++;

  $f=$e['funnel'] ?: 'unknown';
  if(!isset($funnels[$f])) $funnels[$f]=['views'=>0,'clicks'=>0,'forms'=>0,'leads'=>0];
  if(($e['event_type']??'')==='page_view') $funnels[$f]['views']++;
  if(($e['event_type']??'')==='click') $funnels[$f]['clicks']++;
  if(str_contains((string)($e['event_type']??''),'form')) $funnels[$f]['forms']++;

  $t=$e['town'] ?: '';
  if($t){
    if(!isset($towns[$t])) $towns[$t]=['views'=>0,'clicks'=>0,'forms'=>0,'leads'=>0];
    if(($e['event_type']??'')==='page_view') $towns[$t]['views']++;
    if(($e['event_type']??'')==='click') $towns[$t]['clicks']++;
    if(str_contains((string)($e['event_type']??''),'form')) $towns[$t]['forms']++;
  }

  $c=$e['campaign'] ?: ($e['source'] ?: '');
  if($c){
    if(!isset($campaigns[$c])) $campaigns[$c]=['views'=>0,'clicks'=>0,'forms'=>0,'leads'=>0];
    if(($e['event_type']??'')==='page_view') $campaigns[$c]['views']++;
    if(($e['event_type']??'')==='click') $campaigns[$c]['clicks']++;
    if(str_contains((string)($e['event_type']??''),'form')) $campaigns[$c]['forms']++;
  }
}

foreach($leads as $l){
  $url=strtolower((string)($l['page_url'] ?? ''));
  $f='website';
  if(str_contains($url,'/towns/')) $f='town_page';
  elseif(str_contains($url,'/blog/')) $f='blog';
  elseif(str_contains($url,'valuation')) $f='home_valuation';
  if(!isset($funnels[$f])) $funnels[$f]=['views'=>0,'clicks'=>0,'forms'=>0,'leads'=>0];
  $funnels[$f]['leads']++;

  $town=$l['town'] ?? '';
  if(!$town && preg_match('#/towns/([^/?]+)#',$url,$m)) $town=ucwords(str_replace('-',' ',preg_replace('/\.html$/','',$m[1])));
  if($town){
    if(!isset($towns[$town])) $towns[$town]=['views'=>0,'clicks'=>0,'forms'=>0,'leads'=>0];
    $towns[$town]['leads']++;
  }

  $camp=$l['utm_campaign'] ?? '';
  if($camp){
    if(!isset($campaigns[$camp])) $campaigns[$camp]=['views'=>0,'clicks'=>0,'forms'=>0,'leads'=>0];
    $campaigns[$camp]['leads']++;
  }
}

uasort($funnels, fn($a,$b)=>($b['leads']*10+$b['forms']*3+$b['views']) <=> ($a['leads']*10+$a['forms']*3+$a['views']));
uasort($towns, fn($a,$b)=>($b['leads']*10+$b['forms']*3+$b['views']) <=> ($a['leads']*10+$a['forms']*3+$a['views']));
uasort($campaigns, fn($a,$b)=>($b['leads']*10+$b['forms']*3+$b['views']) <=> ($a['leads']*10+$a['forms']*3+$a['views']));
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Conversion Tracking — Mark Pires</title>
<style>
:root{--navy:#10101a;--gold:#c8a96e;--bg:#f5f3ef;--line:#e7e1d8}
body{margin:0;background:var(--bg);color:var(--navy);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px 28px}.inner{max-width:1350px;margin:0 auto;display:flex;justify-content:space-between;gap:18px}.brand{font-family:Georgia,serif;color:var(--gold);font-size:34px}.sub{color:rgba(255,255,255,.68)}.header a{color:#fff;text-decoration:none;opacity:.8}
.wrap{max-width:1350px;margin:0 auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:20px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.05)}.kpi{padding:18px}.n{font-size:32px;font-weight:900}.l{font-size:11px;text-transform:uppercase;color:#777;letter-spacing:1px}
.layout{display:grid;grid-template-columns:1fr 1fr;gap:18px}.panel{overflow:hidden;margin-bottom:18px}.panel h2{font-family:Georgia,serif;margin:0;padding:18px 20px;border-bottom:1px solid var(--line)}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px 14px;border-bottom:1px solid #eee;font-size:14px}th{font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}.tablewrap{overflow:auto}
@media(max-width:900px){.grid,.layout{grid-template-columns:1fr}.inner{display:block}.wrap{padding:14px}}
</style>
<link rel="stylesheet" href="/dashboard/assets/goliath-os.css?v=4"><link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=4"><?php goliath_ui_head(); ?></head>
<body><?php goliath_ui_open(); ?>
<header class="header"><div class="inner"><div><div class="brand">Conversion Tracking</div><div class="sub">Views, clicks, form attempts, leads, campaigns, and town-page momentum.</div></div><div><a href="/dashboard/operations.php">Operations</a> · <a href="/dashboard/source-roi.php">Source ROI</a></div></div></header>
<main class="wrap">
<?php if(!$eventsRes['ok']): ?><div class="panel"><h2>Error</h2><div style="padding:20px"><?=h($eventsRes['body'] ?: $eventsRes['error'])?></div></div><?php endif; ?>

<section class="grid">
  <div class="kpi"><div class="n"><?=h($views)?></div><div class="l">Page Views</div></div>
  <div class="kpi"><div class="n"><?=h($clicks)?></div><div class="l">Clicks</div></div>
  <div class="kpi"><div class="n"><?=h($forms)?></div><div class="l">Form Attempts</div></div>
  <div class="kpi"><div class="n"><?=h(count($leads))?></div><div class="l">Leads</div></div>
  <div class="kpi"><div class="n"><?=h($today)?></div><div class="l">Events Today</div></div>
</section>

<div class="layout">
<section class="panel"><h2>Funnels</h2><div class="tablewrap"><table><thead><tr><th>Funnel</th><th>Views</th><th>Clicks</th><th>Forms</th><th>Leads</th></tr></thead><tbody>
<?php foreach($funnels as $name=>$r): ?><tr><td><strong><?=h($name)?></strong></td><td><?=h($r['views'])?></td><td><?=h($r['clicks'])?></td><td><?=h($r['forms'])?></td><td><?=h($r['leads'])?></td></tr><?php endforeach; ?>
</tbody></table></div></section>

<section class="panel"><h2>Town Momentum</h2><div class="tablewrap"><table><thead><tr><th>Town</th><th>Views</th><th>Clicks</th><th>Forms</th><th>Leads</th></tr></thead><tbody>
<?php foreach(array_slice($towns,0,20,true) as $name=>$r): ?><tr><td><strong><?=h($name)?></strong></td><td><?=h($r['views'])?></td><td><?=h($r['clicks'])?></td><td><?=h($r['forms'])?></td><td><?=h($r['leads'])?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
</div>

<section class="panel"><h2>Recent Events</h2><div class="tablewrap"><table><thead><tr><th>Time</th><th>Event</th><th>Funnel</th><th>Town</th><th>Page</th></tr></thead><tbody>
<?php foreach(array_slice($events,0,50) as $e): ?><tr><td><?=h($e['created_at'])?></td><td><?=h($e['event_type'])?></td><td><?=h($e['funnel'])?></td><td><?=h($e['town'])?></td><td class="muted"><?=h($e['page_url'])?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
</main>
<?php goliath_ui_close(); ?></body>
</html>
