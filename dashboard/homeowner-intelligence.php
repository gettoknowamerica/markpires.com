<?php
/**
 * Dashboard V4 Add-On — Homeowner Intelligence Panel
 * Upload to: /public_html/dashboard/homeowner-intelligence.php
 */

session_start();
require_once __DIR__ . '/../lead-engine/config.php';

if (!defined('MARK_DASHBOARD_PASSWORD')) {
  define('MARK_DASHBOARD_PASSWORD', 'Mannytheman13$');
}

if (empty($_SESSION['mp_dashboard_auth'])) {
  // shares session with dashboard/index.php; login there first
  header('Location: /dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function supabase_hi_get($endpoint) {
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
    CURLOPT_TIMEOUT => 15
  ]);
  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $data = json_decode($body, true);
  return ['ok'=>$http>=200 && $http<300, 'http'=>$http, 'body'=>$body, 'error'=>$err, 'data'=>is_array($data)?$data:[]];
}

$res = supabase_hi_get('homeowner_intelligence?select=*&order=lead_score.desc&limit=250');
$rows = $res['data'];

$total = count($rows); $hot=0; $dnc=0; $clear=0; $towns=[];
foreach($rows as $r){
  if((int)($r['lead_score']??0)>=75) $hot++;
  if(($r['dnc_status']??'')==='listed') $dnc++;
  if(($r['dnc_status']??'')==='clear') $clear++;
  $t=$r['town']??'Unknown'; $towns[$t]=($towns[$t]??0)+1;
}
arsort($towns);
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Homeowner Intelligence — Dashboard V4</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:#10101a;color:#fff;padding:22px 28px;display:flex;justify-content:space-between}.header a{color:#c8a96e;text-decoration:none}
.wrap{max-width:1300px;margin:0 auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}.kpi{background:#fff;border-radius:14px;padding:18px}.n{font-size:30px;font-weight:900}.l{font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#777}.panel{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.06)}.panel h2{font-family:Georgia,serif;margin:0;padding:18px 20px;border-bottom:1px solid #eee}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px 14px;border-bottom:1px solid #eee;font-size:14px}th{font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#777;background:#faf9f6}.badge{display:inline-block;border-radius:999px;padding:5px 8px;font-size:11px;text-transform:uppercase}.hot{background:#2b2110;color:#ffd36b}.high{background:#fff4d7;color:#8a5a00}.watch{background:#e9f2ff;color:#174ea6}.nurture{background:#eee;color:#555}.dnc{background:#ffeaea;color:#9b1c1c}.clear{background:#e6f7ec;color:#14783c}.search{padding:10px 12px;border:1px solid #ddd;border-radius:8px;width:260px}
@media(max-width:900px){.grid{grid-template-columns:repeat(2,1fr)}.wrap{padding:14px}.tablewrap{overflow:auto}}
</style>
</head>
<body>
<div class="header"><strong>Dashboard V4 — Homeowner Intelligence</strong><div><a href="/dashboard/">Main Dashboard</a> · <a href="/lead-engine/intelligence/import-homeowners.php">Import CSV</a></div></div>
<main class="wrap">
  <section class="grid">
    <div class="kpi"><div class="n"><?=h($total)?></div><div class="l">Loaded Records</div></div>
    <div class="kpi"><div class="n"><?=h($hot)?></div><div class="l">High Priority</div></div>
    <div class="kpi"><div class="n"><?=h($clear)?></div><div class="l">DNC Clear</div></div>
    <div class="kpi"><div class="n"><?=h($dnc)?></div><div class="l">DNC Suppressed</div></div>
  </section>

  <section class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;border-bottom:1px solid #eee;padding:0 20px">
      <h2 style="border:0;padding-left:0">Cold Homeowner Queue</h2>
      <input class="search" id="search" placeholder="Search…" oninput="filterRows()">
    </div>
    <div class="tablewrap">
      <table id="hiTable">
        <thead><tr><th>Priority</th><th>Owner</th><th>Property</th><th>Years</th><th>Value</th><th>DNC</th><th>Notes</th></tr></thead>
        <tbody>
        <?php foreach($rows as $r):
          $priority=$r['priority'] ?: 'nurture';
          $dncClass=($r['dnc_status']??'')==='listed'?'dnc':'clear';
          $search=strtolower(json_encode($r));
        ?>
          <tr data-search="<?=h($search)?>">
            <td><span class="badge <?=h($priority)?>"><?=h($priority)?> · <?=h($r['lead_score']??0)?></span></td>
            <td><strong><?=h($r['owner_name'] ?: 'Unknown')?></strong><br><span><?=h($r['phone'])?></span></td>
            <td><?=h($r['address'])?><br><span style="color:#777"><?=h($r['town'])?></span></td>
            <td><?=h($r['years_owned'])?></td>
            <td><?=h($r['estimated_value'] ? '$'.number_format($r['estimated_value']) : '')?></td>
            <td><span class="badge <?=h($dncClass)?>"><?=h($r['dnc_status'])?></span></td>
            <td><?=h($r['notes'])?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<script>
function filterRows(){
  const q=document.getElementById('search').value.toLowerCase();
  document.querySelectorAll('#hiTable tbody tr').forEach(r=>r.style.display=r.dataset.search.includes(q)?'':'none');
}
</script>
</body>
</html>
