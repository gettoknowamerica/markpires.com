<?php
/**
 * White-screen-safe Municipal CSV Importer
 * Upload: /public_html/dashboard/municipal-csv-import.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

$errors = [];
$result = null;

$config = __DIR__ . '/../lead-engine/config.php';
if (!file_exists($config)) {
    $errors[] = 'Missing config.php at /lead-engine/config.php';
} else {
    require_once $config;
}

if (empty($_SESSION['mp_dashboard_auth'])) {
    header('Location:/dashboard/');
    exit;
}

if (file_exists(__DIR__ . '/includes/goliath-nav.php')) {
    require_once __DIR__ . '/includes/goliath-nav.php';
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function sb_safe($method, $endpoint, $payload=null){
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_ROLE_KEY')) {
        return ['ok'=>false,'http'=>0,'body'=>'SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY missing from config.php','data'=>[]];
    }

    $ch = curl_init(rtrim(SUPABASE_URL,'/') . '/rest/v1/' . ltrim($endpoint,'/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ],
        CURLOPT_TIMEOUT => 60
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $json = json_decode($body, true);
    return [
        'ok'=>$http>=200 && $http<300,
        'http'=>$http,
        'curl_error'=>$curlErr,
        'body'=>$body,
        'data'=>is_array($json)?$json:[]
    ];
}

function money_clean_safe($v){ return (float)preg_replace('/[^0-9.\-]/','',(string)$v); }

function csv_value_safe($headers, $row, $keys){
    $map = [];
    foreach($headers as $i=>$head){ $map[strtolower(trim($head))] = $i; }
    foreach($keys as $key){
        $lk = strtolower($key);
        if(isset($map[$lk])) return trim($row[$map[$lk]] ?? '');
    }
    return '';
}

function street_from_address_safe($address){
    $address = preg_replace('/\s+/', ' ', trim($address));
    return trim(preg_replace('/^\d+\s+/', '', $address));
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_FILES['csv']['tmp_name'])) {
            $errors[] = 'No CSV file was received. The upload may have exceeded server limits.';
        } else {
            $town = trim($_POST['town'] ?? 'Fairfield') ?: 'Fairfield';
            $dir = __DIR__ . '/../uploads/imports';
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception('Could not create /uploads/imports folder.');
                }
            }

            $safeName = 'municipal_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/','_', basename($_FILES['csv']['name']));
            $dest = $dir . '/' . $safeName;

            if (!move_uploaded_file($_FILES['csv']['tmp_name'], $dest)) {
                throw new Exception('Upload received but could not move file into /uploads/imports/. Check folder permissions.');
            }

            $fh = fopen($dest, 'r');
            if (!$fh) throw new Exception('Could not open uploaded CSV.');

            $headers = fgetcsv($fh);
            if (!$headers) throw new Exception('CSV has no header row.');

            $inserted = 0;
            $updated = 0;
            $skipped = 0;
            $rowCount = 0;
            $rowErrors = [];
            $sampleHeaders = $headers;

            while (($row = fgetcsv($fh)) !== false) {
                $rowCount++;
                if (count(array_filter($row, fn($x)=>trim((string)$x) !== '')) === 0) continue;

                $owner = csv_value_safe($headers, $row, ['owner_name','owner','name','Name/Address']);
                $address = csv_value_safe($headers, $row, ['property_address','address','Property/Vehicle','property']);
                $bill = csv_value_safe($headers, $row, ['bill_number','bill','Bill #']);
                $acct = csv_value_safe($headers, $row, ['account_number','account']);
                $year = csv_value_safe($headers, $row, ['tax_year','year']);
                $total = csv_value_safe($headers, $row, ['total_tax','total tax','tax']);
                $paid = csv_value_safe($headers, $row, ['paid']);
                $outstanding = csv_value_safe($headers, $row, ['outstanding']);

                if (!$address) { $skipped++; continue; }

                $address = preg_replace('/\s+/', ' ', strtoupper(trim($address)));
                $owner = preg_replace('/\s+/', ' ', trim($owner));
                $street = street_from_address_safe($address);
                $taxYear = (int)preg_replace('/[^0-9]/','',(string)$year);
                if ($taxYear < 1900 || $taxYear > 2100) $taxYear = null;

                $raw = [];
                foreach($headers as $i=>$head){ $raw[$head] = $row[$i] ?? ''; }

                $payload = [
                    'town' => $town,
                    'source_name' => 'manual_csv_import',
                    'source_file' => $safeName,
                    'owner_name' => $owner,
                    'property_address' => $address,
                    'street_name' => $street,
                    'bill_number' => $bill,
                    'account_number' => $acct,
                    'tax_year' => $taxYear,
                    'property_type' => 'REAL ESTATE',
                    'total_tax' => money_clean_safe($total),
                    'paid' => money_clean_safe($paid),
                    'outstanding' => money_clean_safe($outstanding),
                    'duplicate_key' => strtolower($town . '|' . $address),
                    'raw_payload' => $raw,
                    'updated_at' => date('c')
                ];

                $existing = sb_safe('GET', 'municipal_owner_records?select=id,first_seen_year,last_seen_year&town=eq.' . rawurlencode($town) . '&property_address=eq.' . rawurlencode($address) . '&limit=1');

                if (!$existing['ok']) {
                    $rowErrors[] = 'Supabase read failed row '.$rowCount.': HTTP '.$existing['http'].' '.$existing['body'];
                    if (count($rowErrors) >= 5) break;
                    continue;
                }

                if (!empty($existing['data'])) {
                    $id = $existing['data'][0]['id'];
                    $old = $existing['data'][0];
                    $first = $old['first_seen_year'] ? (int)$old['first_seen_year'] : ($taxYear ?: null);
                    $last = $old['last_seen_year'] ? (int)$old['last_seen_year'] : ($taxYear ?: null);

                    if ($taxYear) {
                        $first = $first ? min($first, $taxYear) : $taxYear;
                        $last = $last ? max($last, $taxYear) : $taxYear;
                    }

                    $payload['first_seen_year'] = $first;
                    $payload['last_seen_year'] = $last;
                    $payload['years_observed'] = ($first && $last) ? ($last - $first + 1) : 1;
                    $payload['estimated_tenure_years'] = $first ? (intval(date('Y')) - $first) : 0;

                    $r = sb_safe('PATCH', 'municipal_owner_records?id=eq.' . rawurlencode($id), $payload);
                    if ($r['ok']) $updated++; else $rowErrors[] = 'Update failed: HTTP '.$r['http'].' '.$r['body'];
                } else {
                    $payload['first_seen_year'] = $taxYear;
                    $payload['last_seen_year'] = $taxYear;
                    $payload['years_observed'] = $taxYear ? 1 : 0;
                    $payload['estimated_tenure_years'] = $taxYear ? (intval(date('Y')) - $taxYear) : 0;
                    $payload['created_at'] = date('c');

                    $r = sb_safe('POST', 'municipal_owner_records', [$payload]);
                    if ($r['ok']) $inserted++; else $rowErrors[] = 'Insert failed: HTTP '.$r['http'].' '.$r['body'];
                }

                if (count($rowErrors) >= 5) break;
            }

            fclose($fh);

            $result = [
                'uploaded_file' => $safeName,
                'headers_detected' => $sampleHeaders,
                'rows_read' => $rowCount,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped_no_address' => $skipped,
                'errors' => $rowErrors
            ];
        }
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage() . ' on line ' . $e->getLine();
}

$key = defined('AFTER_HOURS_CRON_KEY') ? AFTER_HOURS_CRON_KEY : 'timetomakethedonuts';
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Municipal CSV Import</title>
<style>
body{margin:0;background:#f5f3ef;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#111827}
.hero{background:#111827;color:white;padding:30px}
.hero h1{font-family:Georgia,serif;color:#c8a96e;margin:0;font-size:40px}
.wrap{max-width:1200px;margin:auto;padding:22px}
.panel{background:white;border-radius:18px;padding:20px;box-shadow:0 4px 18px #0001;margin:16px 0}
input{display:block;margin:10px 0;padding:10px}
.btn{background:#c8a96e;border:0;border-radius:10px;padding:12px 16px;font-weight:900;cursor:pointer}
pre{background:#111827;color:white;padding:14px;border-radius:12px;white-space:pre-wrap;overflow:auto}
.err{background:#fff1f1;border-left:5px solid #b91c1c}
.ok{background:#f0fff4;border-left:5px solid #166534}
</style>
</head>
<body>
<section class="hero"><h1>Municipal CSV Import</h1><p>White-screen-safe importer with visible diagnostics.</p></section>
<main class="wrap">

<?php if($errors): ?>
<section class="panel err"><h2>Errors</h2><pre><?=h(json_encode($errors, JSON_PRETTY_PRINT))?></pre></section>
<?php endif; ?>

<section class="panel">
<form method="post" enctype="multipart/form-data">
<label>Town</label>
<input name="town" value="Fairfield">
<label>CSV File</label>
<input type="file" name="csv" accept=".csv">
<button class="btn">Import CSV</button>
</form>
</section>

<?php if($result): ?>
<section class="panel ok">
<h2>Import Result</h2>
<pre><?=h(json_encode($result, JSON_PRETTY_PRINT))?></pre>
<p><a href="/lead-engine/build-municipal-owner-intelligence.php?key=<?=h($key)?>" target="_blank">Run Municipal Owner Scoring</a></p>
</section>
<?php endif; ?>

<section class="panel">
<h2>Expected Headers</h2>
<pre>Best:
owner_name, property_address, tax_year, total_tax, paid, outstanding, bill_number, account_number

Flexible:
owner / address / year / total tax / Bill # also work.</pre>
</section>

</main>
</body>
</html>
