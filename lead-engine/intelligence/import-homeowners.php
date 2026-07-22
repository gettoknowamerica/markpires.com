<?php
/**
 * Homeowner Intelligence CSV Importer V1
 * Upload to: /public_html/lead-engine/intelligence/import-homeowners.php
 *
 * Protected by MARK_DASHBOARD_PASSWORD from config.php.
 * Imports cold homeowner records into Supabase homeowner_intelligence table.
 */

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../dnc-check.php';

if (!defined('MARK_DASHBOARD_PASSWORD')) {
  define('MARK_DASHBOARD_PASSWORD', 'Mannytheman13$');
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function clean($v) {
  return trim(strip_tags((string)$v));
}

function digits10($phone) {
  $digits = preg_replace('/\D+/', '', (string)$phone);
  if (strlen($digits) === 11 && substr($digits,0,1)==='1') $digits = substr($digits,1);
  return $digits;
}

function parse_money_hi($v) {
  $digits = preg_replace('/[^0-9.]/', '', (string)$v);
  return $digits !== '' ? (float)$digits : null;
}

function parse_date_hi($v) {
  $v = trim((string)$v);
  if ($v === '') return null;
  $ts = strtotime($v);
  return $ts ? date('Y-m-d', $ts) : null;
}

function years_owned_from_date($date) {
  if (!$date) return null;
  $ts = strtotime($date);
  if (!$ts) return null;
  return round((time() - $ts) / (365.25 * 24 * 3600), 1);
}

function pick($row, $keys) {
  foreach ($keys as $k) {
    foreach ($row as $rk => $rv) {
      $norm = strtolower(trim($rk));
      if ($norm === strtolower($k)) return $rv;
    }
  }
  return '';
}

function score_homeowner($r) {
  $score = 10;

  $years = (float)($r['years_owned'] ?? 0);
  $value = (float)($r['estimated_value'] ?? 0);
  $equity = (float)($r['estimated_equity'] ?? 0);
  $town = strtolower((string)($r['town'] ?? ''));
  $notes = strtolower((string)($r['notes'] ?? '') . ' ' . (string)($r['property_type'] ?? ''));

  if ($years >= 5) $score += 18;
  if ($years >= 8) $score += 10;
  if ($years >= 12) $score += 8;

  if ($value >= 600000) $score += 14;
  if ($value >= 1000000) $score += 18;
  if ($value >= 2000000) $score += 25;

  if ($equity >= 250000) $score += 10;
  if ($equity >= 500000) $score += 14;

  $priorityTowns = ['greenwich','westport','darien','new canaan','fairfield','wilton','weston','ridgefield','stamford','norwalk'];
  foreach ($priorityTowns as $pt) {
    if (strpos($town, $pt) !== false) { $score += 10; break; }
  }

  foreach (['multifamily','multi-family','waterfront','estate','absentee','expired','fsbo','probate','inherited','downsizing'] as $sig) {
    if (strpos($notes, $sig) !== false) $score += 8;
  }

  return min(100, $score);
}

function priority_from_score($score) {
  if ($score >= 90) return 'hot';
  if ($score >= 75) return 'high';
  if ($score >= 55) return 'watch';
  return 'nurture';
}

function supabase_upsert_homeowner($record) {
  if (!defined('SUPABASE_URL') || !SUPABASE_URL || !defined('SUPABASE_SERVICE_ROLE_KEY') || !SUPABASE_SERVICE_ROLE_KEY) {
    return ['ok'=>false, 'error'=>'Supabase not configured'];
  }

  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/homeowner_intelligence?on_conflict=phone';

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([$record]),
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
      'Prefer: resolution=merge-duplicates,return=minimal'
    ],
    CURLOPT_TIMEOUT => 20
  ]);

  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  return ['ok'=>$http>=200 && $http<300, 'http'=>$http, 'body'=>$body, 'error'=>$err];
}

if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: /lead-engine/intelligence/import-homeowners.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dashboard_password'])) {
  if (hash_equals(MARK_DASHBOARD_PASSWORD, $_POST['dashboard_password'])) {
    $_SESSION['hi_auth'] = true;
    header('Location: /lead-engine/intelligence/import-homeowners.php');
    exit;
  }
  $error = 'Incorrect password.';
}

if (empty($_SESSION['hi_auth'])):
?>
<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Homeowner Intelligence Login</title>
<style>body{font-family:Arial;background:#10101a;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh}.card{background:#fff;color:#111;padding:30px;border-radius:16px;width:min(420px,92vw)}input,button{width:100%;padding:13px;margin:8px 0;border-radius:8px;border:1px solid #ddd}button{background:#10101a;color:#fff;font-weight:bold}</style></head><body><form class="card" method="post"><h1>Homeowner Intelligence</h1><?php if($error): ?><p style="color:red"><?=h($error)?></p><?php endif; ?><input type="password" name="dashboard_password" placeholder="Dashboard password" required><button>Login</button></form></body></html>
<?php exit; endif;

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
  $file = $_FILES['csv']['tmp_name'] ?? '';
  if (!$file || !is_uploaded_file($file)) {
    $result = ['error'=>'No CSV uploaded'];
  } else {
    $handle = fopen($file, 'r');
    $headers = fgetcsv($handle);
    if (!$headers) {
      $result = ['error'=>'Could not read headers'];
    } else {
      $imported = 0; $skipped = 0; $errors = [];
      while (($values = fgetcsv($handle)) !== false) {
        $row = [];
        foreach ($headers as $i => $h) $row[$h] = $values[$i] ?? '';

        $phone = digits10(pick($row, ['phone','Phone','mobile','Mobile','number','Phone Number']));
        $address = clean(pick($row, ['address','Property Address','street','Street Address']));
        $owner = clean(pick($row, ['owner_name','Owner Name','name','Name','Full Name']));

        if (!$phone && !$address) { $skipped++; continue; }

        $purchaseDate = parse_date_hi(pick($row, ['purchase_date','Purchase Date','last_sale_date','Last Sale Date','sale_date']));
        $yearsOwned = years_owned_from_date($purchaseDate);

        $record = [
          'owner_name' => $owner,
          'phone' => $phone,
          'email' => strtolower(clean(pick($row, ['email','Email']))),
          'address' => $address,
          'town' => clean(pick($row, ['town','Town','city','City'])),
          'state' => clean(pick($row, ['state','State'])) ?: 'CT',
          'area_code' => $phone ? substr($phone, 0, 3) : '',
          'property_type' => clean(pick($row, ['property_type','Property Type','type'])),
          'purchase_date' => $purchaseDate,
          'years_owned' => $yearsOwned,
          'last_sale_price' => parse_money_hi(pick($row, ['last_sale_price','Last Sale Price','sale_price','Sale Price'])),
          'estimated_value' => parse_money_hi(pick($row, ['estimated_value','Estimated Value','current_value','Value'])),
          'estimated_equity' => parse_money_hi(pick($row, ['estimated_equity','Estimated Equity','equity'])),
          'source' => clean(pick($row, ['source','Source'])) ?: 'homeowner_intelligence',
          'lead_temperature' => 'cold',
          'motivation_signal' => clean(pick($row, ['motivation_signal','Motivation','signal'])),
          'notes' => clean(pick($row, ['notes','Notes','message','Message'])),
          'status' => 'new',
          'updated_at' => date('c')
        ];

        $dnc = function_exists('mp_is_dnc_number') && $phone ? mp_is_dnc_number($phone) : ['is_dnc'=>false,'reason'=>'not_checked'];
        $record['dnc_status'] = !empty($dnc['is_dnc']) ? 'listed' : 'clear';
        $record['dnc_reason'] = $dnc['reason'] ?? '';

        $record['lead_score'] = score_homeowner($record);
        $record['priority'] = priority_from_score($record['lead_score']);

        $up = supabase_upsert_homeowner($record);
        if ($up['ok']) $imported++;
        else { $skipped++; $errors[] = $up; }
      }
      fclose($handle);
      $result = ['imported'=>$imported, 'skipped'=>$skipped, 'errors'=>array_slice($errors,0,5)];
    }
  }
}
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Homeowner Intelligence Import</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:#10101a;color:#fff;padding:22px 28px;display:flex;justify-content:space-between}.header a{color:#c8a96e}
.wrap{max-width:900px;margin:0 auto;padding:28px}.card{background:#fff;border-radius:16px;padding:26px;box-shadow:0 8px 30px rgba(0,0,0,.07)}
h1{font-family:Georgia,serif}.btn,button{background:#10101a;color:#fff;border:0;border-radius:8px;padding:13px 18px;font-weight:bold;cursor:pointer}
input[type=file]{padding:16px;border:1px dashed #c8a96e;width:100%;border-radius:12px;background:#fffdf7}
pre{background:#10101a;color:#c8a96e;padding:16px;border-radius:12px;overflow:auto}
.note{color:#666;line-height:1.6}
</style>
</head>
<body>
<div class="header"><strong>Homeowner Intelligence Import</strong><a href="?logout=1">Logout</a></div>
<main class="wrap">
  <div class="card">
    <h1>Upload Homeowner CSV</h1>
    <p class="note">This imports cold homeowner records into Supabase, scores them, and marks DNC status. It does not call anyone. Jessica calling comes later from a separate reviewed queue.</p>
    <form method="post" enctype="multipart/form-data">
      <input type="file" name="csv" accept=".csv,text/csv" required><br><br>
      <button type="submit">Import CSV</button>
    </form>
    <?php if ($result): ?>
      <h2>Result</h2>
      <pre><?=h(json_encode($result, JSON_PRETTY_PRINT))?></pre>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
