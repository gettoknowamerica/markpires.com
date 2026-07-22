<?php
/**
 * MarkPires Jessica Batch Caller V1
 * Upload to: /public_html/dashboard/jessica-batch-caller.php
 */

session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if (file_exists(__DIR__ . '/../lead-engine/dnc-check.php')) require_once __DIR__ . '/../lead-engine/dnc-check.php';

if (empty($_SESSION['mp_dashboard_auth'])) {
  header('Location: /dashboard/');
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function normalize_phone_batch($phone) {
  $d = preg_replace('/\D+/', '', (string)$phone);
  if (strlen($d) === 10) return '+1'.$d;
  if (strlen($d) === 11 && substr($d,0,1)==='1') return '+'.$d;
  return $d ? '+'.$d : '';
}

function first_batch($name) {
  $name = trim((string)$name);
  if ($name === '') return 'there';
  $p = preg_split('/\s+/', $name);
  return $p[0] ?: 'there';
}

function sb_batch($method, $endpoint, $payload=null) {
  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
  $headers = [
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',
    'Prefer: return=representation'
  ];
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 25
  ]);
  if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $data = json_decode($body, true);
  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$body,'error'=>$err,'data'=>is_array($data)?$data:[]];
}

function retell_batch_call($r) {
  if (!defined('RETELL_API_KEY') || !RETELL_API_KEY) return ['ok'=>false,'error'=>'RETELL_API_KEY missing'];
  if (!defined('RETELL_FROM_NUMBER') || !RETELL_FROM_NUMBER) return ['ok'=>false,'error'=>'RETELL_FROM_NUMBER missing'];

  $agent = defined('RETELL_AGENT_ID_COLD_HOMEOWNER') && RETELL_AGENT_ID_COLD_HOMEOWNER
    ? RETELL_AGENT_ID_COLD_HOMEOWNER
    : (defined('RETELL_AGENT_ID_MARK_PRIORITY') ? RETELL_AGENT_ID_MARK_PRIORITY : '');

  if (!$agent) return ['ok'=>false,'error'=>'No cold homeowner or mark priority agent id configured'];

  $to = normalize_phone_batch($r['phone'] ?? '');
  if (!$to) return ['ok'=>false,'error'=>'Invalid phone'];

  $dynamic = [
    'first_name'=>first_batch($r['owner_name'] ?? ''),
    'name'=>$r['owner_name'] ?? '',
    'phone'=>$to,
    'email'=>$r['email'] ?? '',
    'address'=>$r['address'] ?? '',
    'town'=>$r['town'] ?? '',
    'property_type'=>$r['property_type'] ?? '',
    'purchase_date'=>$r['purchase_date'] ?? '',
    'years_owned'=>(string)($r['years_owned'] ?? ''),
    'estimated_value'=>(string)($r['estimated_value'] ?? ''),
    'estimated_equity'=>(string)($r['estimated_equity'] ?? ''),
    'lead_score'=>(string)($r['lead_score'] ?? ''),
    'priority'=>$r['priority'] ?? '',
    'motivation_signal'=>$r['motivation_signal'] ?? '',
    'notes'=>$r['notes'] ?? '',
    'source'=>'jessica_batch_caller',
    'call_type'=>'reviewed_cold_homeowner_batch'
  ];

  $payload = [
    'from_number'=>normalize_phone_batch(RETELL_FROM_NUMBER),
    'to_number'=>$to,
    'agent_id'=>$agent,
    'contact_dynamic_variables'=>$dynamic,
    'metadata'=>$dynamic
  ];

  $ch = curl_init('https://api.retellai.com/v2/create-phone-call');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($payload),
    CURLOPT_HTTPHEADER=>[
      'Authorization: Bearer '.RETELL_API_KEY,
      'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT=>20
  ]);

  $body = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $decoded=json_decode($body,true);

  return ['ok'=>$http>=200 && $http<300,'http'=>$http,'body'=>$body,'error'=>$err,'call_id'=>$decoded['call_id'] ?? '', 'payload'=>$payload];
}

$msg='';
$batchResults=[];

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $ids = $_POST['homeowner_ids'] ?? [];
  $action = $_POST['action'] ?? '';

  if ($action === 'send_batch' && is_array($ids)) {
    $max = min(25, count($ids));
    $ids = array_slice($ids, 0, $max);

    foreach ($ids as $id) {
      $get = sb_batch('GET','homeowner_intelligence?select=*&id=eq.'.rawurlencode($id).'&limit=1');
      if (!$get['ok'] || empty($get['data'][0])) {
        $batchResults[]=['id'=>$id,'ok'=>false,'status'=>'load_failed'];
        continue;
      }

      $r = $get['data'][0];
      $phone = normalize_phone_batch($r['phone'] ?? '');

      $dnc = function_exists('mp_is_dnc_number') && $phone ? mp_is_dnc_number($phone) : ['is_dnc'=>false,'reason'=>'not_checked'];
      if (!empty($dnc['is_dnc'])) {
        sb_batch('PATCH','homeowner_intelligence?id=eq.'.rawurlencode($id),[
          'status'=>'dnc_suppressed',
          'dnc_status'=>'listed',
          'dnc_reason'=>$dnc['reason'] ?? 'matched',
          'last_contacted_at'=>date('c'),
          'updated_at'=>date('c')
        ]);
        $batchResults[]=['name'=>$r['owner_name'] ?? '', 'phone'=>$phone, 'ok'=>false, 'status'=>'dnc_suppressed'];
        continue;
      }

      $call = retell_batch_call($r);
      sb_batch('PATCH','homeowner_intelligence?id=eq.'.rawurlencode($id),[
        'status'=>$call['ok'] ? 'jessica_batch_registered' : 'retell_error',
        'dnc_status'=>'clear',
        'last_contacted_at'=>date('c'),
        'notes'=>trim(($r['notes'] ?? '') . "\n\nBatch Jessica call: " . ($call['call_id'] ?? '') . " " . ($call['ok'] ? 'OK' : ($call['body'] ?? $call['error'] ?? 'error'))),
        'updated_at'=>date('c')
      ]);

      $batchResults[]=[
        'name'=>$r['owner_name'] ?? '',
        'phone'=>$phone,
        'ok'=>$call['ok'],
        'status'=>$call['ok'] ? 'registered' : 'retell_error',
        'call_id'=>$call['call_id'] ?? '',
        'http'=>$call['http'] ?? ''
      ];
    }

    $msg = 'Batch processed: '.count($batchResults).' records.';
  }

  if ($action === 'mark_reviewed' && is_array($ids)) {
    foreach($ids as $id) {
      sb_batch('PATCH','homeowner_intelligence?id=eq.'.rawurlencode($id),['status'=>'review_queue','updated_at'=>date('c')]);
    }
    $msg='Selected homeowners moved to review queue.';
  }
}

$filter=$_GET['filter'] ?? 'high';
if($filter==='all') $endpoint='homeowner_intelligence?select=*&order=lead_score.desc&limit=200';
elseif($filter==='review') $endpoint='homeowner_intelligence?select=*&status=eq.review_queue&order=lead_score.desc&limit=200';
else $endpoint='homeowner_intelligence?select=*&lead_score=gte.55&dnc_status=neq.listed&order=lead_score.desc&limit=200';

$res=sb_batch('GET',$endpoint);
$rows=$res['data'];
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Jessica Batch Caller — Mark Pires</title>
<style>
:root{--navy:#10101a;--gold:#c8a96e;--bg:#f5f3ef;--line:#e7e1d8}
body{margin:0;background:var(--bg);color:var(--navy);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px 28px}.inner{max-width:1350px;margin:0 auto;display:flex;justify-content:space-between;gap:18px}.brand{font-family:Georgia,serif;color:var(--gold);font-size:34px}.sub{color:rgba(255,255,255,.68)}.header a{color:#fff;text-decoration:none;opacity:.8}
.wrap{max-width:1350px;margin:0 auto;padding:26px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.05);overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px 20px;border-bottom:1px solid var(--line)}
.controls{padding:16px 20px;border-bottom:1px solid #eee;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.btn{border:0;background:#10101a;color:#fff;text-decoration:none;border-radius:8px;padding:9px 11px;font-size:13px;font-weight:800;cursor:pointer}.gold{background:var(--gold);color:#111}.light{background:#f2efe8;color:#111}.danger{background:#ffeaea;color:#9b1c1c}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px 14px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#777;background:#faf9f6}
.badge{display:inline-block;border-radius:999px;padding:5px 8px;font-size:11px;text-transform:uppercase}.hot{background:#2b2110;color:#ffd36b}.high{background:#fff4d7;color:#8a5a00}.watch{background:#e9f2ff;color:#174ea6}.nurture{background:#eee;color:#555}.clear{background:#e6f7ec;color:#14783c}.listed{background:#ffeaea;color:#9b1c1c}
.msg{background:#e6f7ec;color:#14783c;border-radius:10px;padding:12px;margin-bottom:16px}.muted{color:#777;font-size:13px}.tablewrap{overflow:auto}
@media(max-width:900px){.inner{display:block}.wrap{padding:14px}}
</style>
<script>
function toggleAll(master){
  document.querySelectorAll('.rowcheck').forEach(cb => cb.checked = master.checked);
}
function confirmBatch(){
  const count=document.querySelectorAll('.rowcheck:checked').length;
  if(count>25){ alert('Select 25 or fewer at a time.'); return false; }
  return confirm('Send '+count+' reviewed homeowners to Jessica now? DNC will be checked before each call.');
}
</script>
</head>
<body>
<header class="header"><div class="inner"><div><div class="brand">Jessica Batch Caller</div><div class="sub">Reviewed homeowner outreach with DNC suppression and Retell calling.</div></div><div><a href="/dashboard/operations.php">Operations</a> · <a href="/dashboard/homeowner-intelligence.php">Homeowner Intelligence</a></div></div></header>
<main class="wrap">
<?php if($msg): ?><div class="msg"><?=h($msg)?></div><?php endif; ?>
<?php if(!$res['ok']): ?><div class="msg" style="background:#ffeaea;color:#9b1c1c"><?=h($res['body'] ?: $res['error'])?></div><?php endif; ?>

<?php if($batchResults): ?>
<section class="panel" style="margin-bottom:18px"><h2>Batch Results</h2><div class="tablewrap"><table><thead><tr><th>Name</th><th>Phone</th><th>Status</th><th>Call ID</th></tr></thead><tbody>
<?php foreach($batchResults as $br): ?><tr><td><?=h($br['name'] ?? '')?></td><td><?=h($br['phone'] ?? '')?></td><td><?=h($br['status'] ?? '')?></td><td><?=h($br['call_id'] ?? '')?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>

<section class="panel">
<h2>Homeowner Call Queue</h2>
<div class="controls">
  <a class="btn light" href="?filter=high">High/Watch Clear</a>
  <a class="btn light" href="?filter=review">Review Queue</a>
  <a class="btn light" href="?filter=all">All</a>
  <span class="muted">Loaded <?=h(count($rows))?> records. Max 25 calls per batch.</span>
</div>
<form method="post" onsubmit="return confirmBatch();">
<div class="controls">
  <button class="btn gold" name="action" value="send_batch">Send Selected To Jessica</button>
  <button class="btn light" name="action" value="mark_reviewed" onclick="return confirm('Move selected to review queue?');">Move To Review Queue</button>
</div>
<div class="tablewrap">
<table>
<thead><tr><th><input type="checkbox" onclick="toggleAll(this)"></th><th>Priority</th><th>Owner</th><th>Property</th><th>DNC</th><th>Status</th><th>Notes</th></tr></thead>
<tbody>
<?php foreach($rows as $r):
  $score=(int)($r['lead_score'] ?? 0);
  $pri=$score>=90?'hot':($score>=75?'high':($score>=55?'watch':'nurture');
  $dnc=$r['dnc_status'] ?: 'unknown';
?>
<tr>
<td><input class="rowcheck" type="checkbox" name="homeowner_ids[]" value="<?=h($r['id'])?>"></td>
<td><span class="badge <?=h($pri)?>"><?=h($pri)?> <?=h($score)?></span></td>
<td><strong><?=h($r['owner_name'] ?: 'Unknown')?></strong><div class="muted"><?=h($r['phone'])?> · <?=h($r['email'])?></div></td>
<td><?=h($r['address'])?><div class="muted"><?=h($r['town'])?> · <?=h($r['property_type'])?> · <?=h($r['years_owned'])?> yrs</div></td>
<td><span class="badge <?=h($dnc)?>"><?=h($dnc)?></span></td>
<td><?=h($r['status'])?><div class="muted"><?=h($r['last_contacted_at'])?></div></td>
<td><?=h($r['motivation_signal'])?><div class="muted"><?=h($r['notes'])?></div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</form>
</section>
</main>
</body>
</html>
