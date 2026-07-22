<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location: /dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }

function sb($endpoint){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPGET=>true,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY
    ]
  ]);
  $body=curl_exec($ch);
  curl_close($ch);
  $d=json_decode($body,true);
  return is_array($d)?$d:[];
}

$rows=sb('homeowner_intelligence?select=*&order=lead_score.desc&limit=500');

$hot=0;$high=0;$watch=0;$towns=[];
foreach($rows as $r){
  $s=(int)($r['lead_score']??0);
  if($s>=90)$hot++;
  elseif($s>=75)$high++;
  elseif($s>=55)$watch++;

  $t=$r['town']?:'Unknown';
  $towns[$t]=($towns[$t]??0)+1;
}
arsort($towns);
$top=array_slice($rows,0,25);
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Homeowner Radar V2</title>
<style>
body{margin:0;background:#f5f3ef;font-family:Arial}
.header{background:#10101a;color:#fff;padding:24px}
.wrap{max-width:1400px;margin:auto;padding:20px}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.card,.panel{background:#fff;border-radius:14px;padding:18px}
.panel{margin-top:18px}
.n{font-size:34px;font-weight:900}
table{width:100%;border-collapse:collapse}
td,th{padding:10px;border-bottom:1px solid #eee;text-align:left}
.btn{display:inline-block;background:#10101a;color:#fff;padding:8px 10px;border-radius:8px;text-decoration:none}
.gold{background:#c8a96e;color:#111}
.reason{font-size:12px;color:#666}
</style>
</head>
<body>
<div class="header">
<h1>🏠 Homeowner Radar V2</h1>
<a style="color:#c8a96e" href="/dashboard/homeowner-intelligence.php">Homeowner Intelligence</a>
</div>

<div class="wrap">

<div class="grid">
<div class="card"><div class="n"><?=$hot?></div>Hot 90+</div>
<div class="card"><div class="n"><?=$high?></div>High 75-89</div>
<div class="card"><div class="n"><?=$watch?></div>Watch 55-74</div>
<div class="card"><div class="n"><?=count($rows)?></div>Total Records</div>
</div>

<div class="panel">
<h2>Top 25 Opportunities Today</h2>
<p>
<a class="btn gold" href="/dashboard/jessica-batch-caller.php">Send To Jessica</a>
<a class="btn" href="/dashboard/homeowner-intelligence.php">Review Queue</a>
</p>
<table>
<tr><th>Score</th><th>Owner</th><th>Town</th><th>Years</th><th>Equity</th><th>Reason</th></tr>
<?php foreach($top as $r): ?>
<tr>
<td><strong><?=h($r['lead_score'])?></strong></td>
<td><?=h($r['owner_name'])?></td>
<td><?=h($r['town'])?></td>
<td><?=h($r['years_owned'])?></td>
<td>$<?=number_format((float)($r['estimated_equity']??0))?></td>
<td class="reason">
Owned <?=h($r['years_owned'])?> yrs ·
Equity $<?=number_format((float)($r['estimated_equity']??0))?> ·
<?=h($r['property_type'])?>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="panel">
<h2>Town Leaderboard</h2>
<table>
<tr><th>Town</th><th>Opportunities</th></tr>
<?php foreach(array_slice($towns,0,15,true) as $town=>$count): ?>
<tr><td><?=h($town)?></td><td><?=h($count)?></td></tr>
<?php endforeach; ?>
</table>
</div>

</div>
</body>
</html>
