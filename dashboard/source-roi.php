<?php
/**
 * MarkPires Source ROI Dashboard V1
 * Upload to: /public_html/dashboard/source-roi.php
 */

session_start();
require_once __DIR__ . '/../lead-engine/config.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location: /dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sb_get($endpoint) {
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
  $data = json_decode($body, true);
  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function source_label($lead) {
  $tag = trim((string)($lead['tag'] ?? ''));
  $type = trim((string)($lead['type'] ?? ''));
  $url = strtolower((string)($lead['page_url'] ?? ''));

  if (str_contains($url, '/towns/')) {
    if (preg_match('#/towns/([^/?]+)#', $url, $m)) return 'Town: ' . ucwords(str_replace('-', ' ', preg_replace('/\.html$/','',$m[1])));
    return 'Town Pages';
  }
  if (str_contains($url, '/blog/')) return 'Blog';
  if (str_contains($url, 'home-valuation') || $type === 'valuation') return 'Home Valuation';
  if (str_contains($tag, 'town_')) return 'Town: ' . ucwords(str_replace(['town_','-'], ['', ' '], $tag));
  if ($tag) return $tag;
  if ($type) return $type;
  return 'Unknown';
}

function priority_bucket($score) {
  $score=(int)$score;
  if ($score>=90) return 'hot';
  if ($score>=75) return 'high';
  if ($score>=50) return 'watch';
  return 'nurture';
}

$leadsRes = sb_get('leads?select=*&order=created_at.desc&limit=1000');
$leads = $leadsRes['data'];

$callRes = sb_get('call_intelligence?select=*&order=created_at.desc&limit=1000');
$calls = $callRes['data'];

$sources=[];
$total=0; $hot=0; $withPhone=0; $today=0;
$todayPrefix=date('Y-m-d');

foreach($leads as $l){
  $total++;
  $score=(int)($l['lead_score']??0);
  if($score>=75) $hot++;
  if(!empty($l['phone'])) $withPhone++;
  if(strpos((string)($l['created_at']??''),$todayPrefix)===0) $today++;

  $src=source_label($l);
  if(!isset($sources[$src])){
    $sources[$src]=[
      'source'=>$src,'leads'=>0,'hot'=>0,'high'=>0,'watch'=>0,'nurture'=>0,'score_sum'=>0,
      'phone'=>0,'email'=>0,'valuation'=>0,'buyer'=>0,'seller'=>0,'appointments'=>0,'calls'=>0,
      'last'=>''
    ];
  }

  $sources[$src]['leads']++;
  $sources[$src]['score_sum'] += $score;
  $bucket=priority_bucket($score);
  $sources[$src][$bucket]++;
  if($score>=75) $sources[$src]['hot']++;
  if(!empty($l['phone'])) $sources[$src]['phone']++;
  if(!empty($l['email'])) $sources[$src]['email']++;

  $blob=strtolower(json_encode($l));
  if(str_contains($blob,'valuation')) $sources[$src]['valuation']++;
  if(str_contains($blob,'buyer') || str_contains($blob,'buying')) $sources[$src]['buyer']++;
  if(str_contains($blob,'seller') || str_contains($blob,'selling') || str_contains($blob,'sell')) $sources[$src]['seller']++;

  $created=$l['created_at']??'';
  if($created && (!$sources[$src]['last'] || strtotime($created)>strtotime($sources[$src]['last']))) $sources[$src]['last']=$created;
}

/* Best-effort map calls back by source metadata */
foreach($calls as $c){
  $src=$c['source'] ?: (($c['metadata']['source'] ?? '') ?: 'Jessica Calls');
  if(!isset($sources[$src])){
    $sources[$src]=[
      'source'=>$src,'leads'=>0,'hot'=>0,'high'=>0,'watch'=>0,'nurture'=>0,'score_sum'=>0,
      'phone'=>0,'email'=>0,'valuation'=>0,'buyer'=>0,'seller'=>0,'appointments'=>0,'calls'=>0,
      'last'=>''
    ];
  }
  $sources[$src]['calls']++;
  if(!empty($c['appointment_requested'])) $sources[$src]['appointments']++;
}

uasort($sources, function($a,$b){
  $qa = ($a['hot']*4) + ($a['leads']*2) + ($a['appointments']*5);
  $qb = ($b['hot']*4) + ($b['leads']*2) + ($b['appointments']*5);
  return $qb <=> $qa;
});

$topSource = $sources ? reset($sources) : null;
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Source ROI — Mark Pires</title>
<style>
:root{--navy:#10101a;--gold:#c8a96e;--bg:#f5f3ef;--line:#e7e1d8;--muted:#777}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--navy);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px 28px}
.header-inner{max-width:1350px;margin:0 auto;display:flex;justify-content:space-between;gap:16px;align-items:flex-start}
.brand{font-family:Georgia,serif;color:var(--gold);font-size:34px}.sub{color:rgba(255,255,255,.68);margin-top:6px}.header a{color:#fff;text-decoration:none;opacity:.78}
.wrap{max-width:1350px;margin:0 auto;padding:26px}
.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px}
.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.05)}
.kpi{padding:18px}.n{font-size:30px;font-weight:900}.l{font-size:11px;letter-spacing:1.2px;text-transform:uppercase;color:var(--muted)}
.panel{overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px 20px;border-bottom:1px solid var(--line)}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:13px 14px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#777;background:#faf9f6}
.badge{display:inline-block;border-radius:999px;padding:5px 8px;font-size:11px;text-transform:uppercase}.hot{background:#2b2110;color:#ffd36b}.high{background:#fff4d7;color:#8a5a00}.watch{background:#e9f2ff;color:#174ea6}.nurture{background:#eee;color:#555}
.bar{height:8px;background:#eee;border-radius:99px;overflow:hidden;margin-top:7px}.bar span{display:block;height:100%;background:#c8a96e}
.muted{color:#777;font-size:12px}.tablewrap{overflow:auto}.score{font-weight:900}
@media(max-width:900px){.grid{grid-template-columns:repeat(2,1fr)}.header-inner{display:block}.wrap{padding:14px}}
</style>
</head>
<body>
<header class="header">
  <div class="header-inner">
    <div><div class="brand">Source ROI Dashboard</div><div class="sub">Find which funnels create the best leads — town pages, blog, valuation, CTAs, Jessica calls.</div></div>
    <div><a href="/dashboard/operations.php">Operations</a> · <a href="/dashboard/">Main Dashboard</a></div>
  </div>
</header>

<main class="wrap">
<?php if(!$leadsRes['ok']): ?><div class="panel"><h2>Supabase Error</h2><div style="padding:20px"><?=h($leadsRes['body'] ?: $leadsRes['error'])?></div></div><?php endif; ?>

<section class="grid">
  <div class="kpi"><div class="n"><?=h($total)?></div><div class="l">Leads Analyzed</div></div>
  <div class="kpi"><div class="n"><?=h($today)?></div><div class="l">Today</div></div>
  <div class="kpi"><div class="n"><?=h($hot)?></div><div class="l">Hot 75+</div></div>
  <div class="kpi"><div class="n"><?=h($withPhone)?></div><div class="l">Phone Captured</div></div>
  <div class="kpi"><div class="n"><?=h($topSource['source'] ?? '—')?></div><div class="l">Top Source</div></div>
</section>

<section class="panel">
<h2>Funnel / Source Performance</h2>
<div class="tablewrap">
<table>
<thead>
<tr>
<th>Source</th><th>Leads</th><th>Quality</th><th>Intent Mix</th><th>Capture</th><th>Jessica</th><th>Last Lead</th>
</tr>
</thead>
<tbody>
<?php foreach($sources as $s):
  $avg = $s['leads'] ? round($s['score_sum'] / $s['leads']) : 0;
  $quality = min(100, ($s['hot']*15) + ($avg));
  $bar = min(100, $quality);
?>
<tr>
<td><strong><?=h($s['source'])?></strong><div class="bar"><span style="width:<?=h($bar)?>%"></span></div></td>
<td><span class="score"><?=h($s['leads'])?></span><div class="muted"><?=h($s['hot'])?> hot</div></td>
<td>
  <span class="badge <?=h(priority_bucket($avg))?>">Avg <?=h($avg)?></span>
  <div class="muted">Hot <?=h($s['hot'])?> · Watch <?=h($s['watch'])?> · Nurture <?=h($s['nurture'])?></div>
</td>
<td>
  <div>Valuation: <?=h($s['valuation'])?></div>
  <div>Buyer: <?=h($s['buyer'])?></div>
  <div>Seller: <?=h($s['seller'])?></div>
</td>
<td>
  <div>Phone: <?=h($s['phone'])?></div>
  <div>Email: <?=h($s['email'])?></div>
</td>
<td>
  <div>Calls: <?=h($s['calls'])?></div>
  <div>Appts: <?=h($s['appointments'])?></div>
</td>
<td><?=h($s['last'] ? date('M j, g:i A', strtotime($s['last'])) : '')?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
</main>
</body>
</html>
