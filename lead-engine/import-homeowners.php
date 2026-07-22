<?php
/**
 * Homeowner CSV Auto Importer V3
 * Upload to: /public_html/lead-engine/intelligence/import-homeowners-v3.php
 */

session_start();
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location: /dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function normalize_phone_import($phone) {
  $d = preg_replace('/\D+/', '', (string)$phone);
  if (strlen($d) === 10) return $d;
  if (strlen($d) === 11 && substr($d,0,1)==='1') return substr($d,1);
  return $d;
}

function clean_money($v) {
  $v = preg_replace('/[^0-9.\-]/', '', (string)$v);
  return $v === '' ? null : (float)$v;
}

function clean_date_import($v) {
  $v = trim((string)$v);
  if ($v === '') return null;
  $ts = strtotime($v);
  return $ts ? date('Y-m-d', $ts) : null;
}

function years_owned_from_date($date) {
  if (!$date) return null;
  $ts = strtotime($date);
  if (!$ts) return null;
  return round((time() - $ts) / (365.25 * 86400), 1);
}

function score_homeowner($r) {
  $score = 0;
  $reasons = [];

  $years = (float)($r['years_owned'] ?? 0);
  $equity = (float)($r['estimated_equity'] ?? 0);
  $value = (float)($r['estimated_value'] ?? 0);
  $town = strtolower((string)($r['town'] ?? ''));
  $type = strtolower((string)($r['property_type'] ?? ''));

  if ($years >= 20) { $score += 25; $reasons[] = '+25 owned 20+ years'; }
  elseif ($years >= 15) { $score += 20; $reasons[] = '+20 owned 15+ years'; }
  elseif ($years >= 10) { $score += 15; $reasons[] = '+15 owned 10+ years'; }
  elseif ($years >= 5) { $score += 8; $reasons[] = '+8 owned 5+ years'; }

  if ($equity >= 1000000) { $score += 25; $reasons[] = '+25 equity $1M+'; }
  elseif ($equity >= 750000) { $score += 20; $reasons[] = '+20 equity $750k+'; }
  elseif ($equity >= 500000) { $score += 15; $reasons[] = '+15 equity $500k+'; }
  elseif ($equity >= 250000) { $score += 8; $reasons[] = '+8 equity $250k+'; }

  foreach (['greenwich','westport','darien','new canaan'] as $lux) {
    if (str_contains($town, $lux)) { $score += 12; $reasons[] = '+12 luxury town'; break; }
  }

  foreach (['fairfield','wilton','ridgefield','weston','easton'] as $strong) {
    if (str_contains($town, $strong)) { $score += 8; $reasons[] = '+8 strong Fairfield County town'; break; }
  }

  if (str_contains($type, 'single')) { $score += 8; $reasons[] = '+8 single family'; }
  if (str_contains($type, 'multi')) { $score += 6; $reasons[] = '+6 multi-family'; }
  if ($value >= 1500000) { $score += 10; $reasons[] = '+10 luxury value'; }

  $score = min(100, $score);
  $priority = $score >= 90 ? 'hot' : ($score >= 75 ? 'high' : ($score >= 55 ? 'watch' : 'nurture'));

  return [$score, $priority, implode('; ', $reasons)];
}

function field_pick($row, $aliases) {
  foreach ($aliases as $a) {
    foreach ($row as $k=>$v) {
      if (strtolower(trim($k)) === strtolower(trim($a))) return $v;
    }
  }
  return '';
}

function sb_import_post($records) {
  if (!$records) return ['ok'=>true,'body'=>'[]','http'=>200];

  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/homeowner_intelligence';
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($records),
    CURLOPT_HTTPHEADER=>[
      'Content-Type: application/json',
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Prefer: resolution=merge-duplicates,return=representation'
    ],
    CURLOPT_TIMEOUT=>60
  ]);
  $body=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$body,'error'=>$err];
}

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv']['tmp_name'])) {
  $townDefault = trim($_POST['town_default'] ?? '');
  $source = trim($_POST['source'] ?? 'csv_import_v3');
  $dryRun = !empty($_POST['dry_run']);

  $fh = fopen($_FILES['csv']['tmp_name'], 'r');
  $headers = fgetcsv($fh);
  $records = [];
  $preview = [];
  $skipped = 0;

  while(($cols = fgetcsv($fh)) !== false) {
    $row = [];
    foreach($headers as $i=>$head) $row[$head] = $cols[$i] ?? '';

    $owner = trim(field_pick($row, ['owner_name','owner','name','full_name','mailing_name','primary_owner']));
    $phone = normalize_phone_import(field_pick($row, ['phone','phone_number','mobile','cell','telephone','owner_phone']));
    $email = trim(field_pick($row, ['email','owner_email']));
    $address = trim(field_pick($row, ['address','property_address','site_address','situs_address','street_address']));
    $town = trim(field_pick($row, ['town','city','municipality','property_city'])) ?: $townDefault;
    $state = trim(field_pick($row, ['state','property_state'])) ?: 'CT';
    $ptype = trim(field_pick($row, ['property_type','type','land_use','use_code']));
    $purchaseDate = clean_date_import(field_pick($row, ['purchase_date','sale_date','last_sale_date','recording_date']));
    $yearsOwned = field_pick($row, ['years_owned','yrs_owned']);
    $yearsOwned = $yearsOwned !== '' ? (float)$yearsOwned : years_owned_from_date($purchaseDate);
    $lastSale = clean_money(field_pick($row, ['last_sale_price','sale_price','purchase_price']));
    $value = clean_money(field_pick($row, ['estimated_value','value','avm','market_value','zestimate']));
    $equity = clean_money(field_pick($row, ['estimated_equity','equity']));
    if ($equity === null && $value && $lastSale) $equity = max(0, $value - $lastSale);

    if (!$phone && !$address) { $skipped++; continue; }

    $rec = [
      'owner_name'=>$owner,
      'phone'=>$phone,
      'email'=>$email,
      'address'=>$address,
      'town'=>$town,
      'state'=>$state,
      'area_code'=>substr($phone,0,3),
      'property_type'=>$ptype,
      'purchase_date'=>$purchaseDate,
      'years_owned'=>$yearsOwned,
      'last_sale_price'=>$lastSale,
      'estimated_value'=>$value,
      'estimated_equity'=>$equity,
      'source'=>$source,
      'lead_temperature'=>'cold',
      'dnc_status'=>'unknown',
      'status'=>'new',
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];

    [$score,$priority,$reason] = score_homeowner($rec);
    $rec['lead_score'] = $score;
    $rec['priority'] = $priority;
    $rec['motivation_signal'] = $reason;

    $records[] = $rec;
    if (count($preview) < 20) $preview[] = $rec;
  }
  fclose($fh);

  if ($dryRun) {
    $result = ['ok'=>true,'dry_run'=>true,'count'=>count($records),'skipped'=>$skipped,'preview'=>$preview];
  } else {
    $chunks = array_chunk($records, 250);
    $responses = [];
    $ok = true;
    foreach($chunks as $chunk) {
      $res = sb_import_post($chunk);
      $responses[] = $res;
      if (!$res['ok']) $ok = false;
    }
    $result = ['ok'=>$ok,'dry_run'=>false,'count'=>count($records),'skipped'=>$skipped,'responses'=>$responses,'preview'=>$preview];
  }
}
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Homeowner CSV Importer V3</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:#10101a;color:#fff;padding:24px 28px;display:flex;justify-content:space-between}.header a{color:#c8a96e;text-decoration:none}
.wrap{max-width:1200px;margin:0 auto;padding:24px}.panel{background:#fff;border-radius:16px;padding:22px;margin-bottom:18px;box-shadow:0 8px 30px rgba(0,0,0,.06)}
h1,h2{font-family:Georgia,serif}input,select{width:100%;padding:12px;border:1px solid #ddd;border-radius:10px;margin:6px 0 14px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.btn{background:#10101a;color:#fff;border:0;border-radius:10px;padding:12px 16px;font-weight:900}.gold{background:#c8a96e;color:#111}
table{width:100%;border-collapse:collapse}td,th{padding:10px;border-bottom:1px solid #eee;text-align:left;font-size:13px}.tablewrap{overflow:auto}
.badge{border-radius:999px;padding:4px 8px;font-size:11px;text-transform:uppercase}.hot{background:#2b2110;color:#ffd36b}.high{background:#fff4d7;color:#8a5a00}.watch{background:#e9f2ff;color:#174ea6}.nurture{background:#eee;color:#555}
</style>
</head>
<body>
<div class="header"><strong>Homeowner CSV Importer V3</strong><div><a href="/dashboard/homeowner-radar.php">Radar</a> · <a href="/dashboard/homeowner-intelligence.php">Homeowner Intelligence</a></div></div>
<main class="wrap">
<section class="panel">
<h1>Import Homeowner CSV</h1>
<p>Upload PropStream, BatchData, county export, skip trace, or manual CSV. V3 auto maps common field names, scores, dedupes by phone, and stores in Supabase.</p>
<form method="post" enctype="multipart/form-data">
  <div class="grid">
    <div><label>CSV File</label><input type="file" name="csv" accept=".csv" required></div>
    <div><label>Default Town</label><input name="town_default" placeholder="Fairfield"></div>
    <div><label>Source</label><input name="source" value="csv_import_v3"></div>
    <div><label><input type="checkbox" name="dry_run" value="1" style="width:auto"> Dry run preview only</label></div>
  </div>
  <button class="btn gold" type="submit">Import + Score</button>
</form>
</section>

<?php if($result): ?>
<section class="panel">
<h2>Import Result</h2>
<p><strong>Status:</strong> <?=h($result['ok']?'OK':'ERROR')?> · <strong>Records:</strong> <?=h($result['count'])?> · <strong>Skipped:</strong> <?=h($result['skipped'])?> · <strong>Dry Run:</strong> <?=h($result['dry_run']?'yes':'no')?></p>
<?php if(!$result['ok']): ?><pre><?=h(json_encode($result['responses'] ?? [], JSON_PRETTY_PRINT))?></pre><?php endif; ?>

<h2>Preview</h2>
<div class="tablewrap"><table><tr><th>Score</th><th>Owner</th><th>Phone</th><th>Town</th><th>Address</th><th>Years</th><th>Equity</th><th>Reason</th></tr>
<?php foreach($result['preview'] as $r): ?>
<tr>
<td><span class="badge <?=h($r['priority'])?>"><?=h($r['lead_score'])?></span></td>
<td><?=h($r['owner_name'])?></td>
<td><?=h($r['phone'])?></td>
<td><?=h($r['town'])?></td>
<td><?=h($r['address'])?></td>
<td><?=h($r['years_owned'])?></td>
<td><?=h($r['estimated_equity'] ? '$'.number_format($r['estimated_equity']) : '')?></td>
<td><?=h($r['motivation_signal'])?></td>
</tr>
<?php endforeach; ?>
</table></div>
</section>
<?php endif; ?>
</main>
</body>
</html>
