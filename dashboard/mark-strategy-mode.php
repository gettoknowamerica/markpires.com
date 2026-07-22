<?php
/**
 * V12.15.2 Mark Strategy Mode Dashboard — 500 Fix
 * Upload over: /public_html/dashboard/mark-strategy-mode.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../lead-engine/config.php';

if(empty($_SESSION['mp_dashboard_auth'])){
  header('Location:/dashboard/');
  exit;
}

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}

function sb1522dfix($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CUSTOMREQUEST=>$m,
    CURLOPT_HTTPHEADER=>[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json',
      'Prefer: return=representation'
    ],
    CURLOPT_TIMEOUT=>25
  ]);
  if($p!==null){
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($p));
  }
  $b=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  $d=json_decode($b,true);
  return is_array($d)?$d:[['error'=>'Supabase error','http'=>$http,'curl_error'=>$err,'body'=>$b]];
}

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $payload=[[
    'note_type'=>$_POST['note_type']??'mark_style',
    'title'=>$_POST['title']??'Mark Strategy Note',
    'note'=>$_POST['note']??'',
    'applies_to'=>$_POST['applies_to']??'all',
    'priority'=>(int)($_POST['priority']??75),
    'active'=>true,
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  if(trim($payload[0]['note'])){
    sb1522dfix('POST','mark_strategy_training_notes',$payload);
    $msg='Training note saved.';
  }
}

$sessions=sb1522dfix('GET','mark_strategy_sessions?select=*&order=created_at.desc&limit=20');
$notes=sb1522dfix('GET','mark_strategy_training_notes?select=*&active=eq.true&order=priority.desc,created_at.desc&limit=100');
$s=$sessions[0]??[];
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mark Strategy Mode V12.15.2</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}
.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}
.wrap{max-width:1500px;margin:auto;padding:26px}
.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin-top:18px;overflow:hidden}
.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}
.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}
.light{background:#f2efe8;color:#111}
.layout{display:grid;grid-template-columns:1fr .45fr;gap:18px}
table{width:100%;border-collapse:collapse}
td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}
th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}
.muted{color:#777;font-size:13px}
pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}
input,select,textarea{width:95%;padding:9px;border:1px solid #ddd;border-radius:8px;margin:5px 0}
textarea{min-height:90px}
@media(max-width:1000px){.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style>
</head>
<body>
<div class="header">
  <div class="brand">Mark Strategy Mode V12.15.2</div>
  <div>Secret phrase internal mode for strategy, hot leads, calendar, and Mark-style training</div>
</div>
<main class="wrap">
<?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>

<p>
  <a class="btn" target="_blank" href="/lead-engine/mark-strategy-mode.php?key=<?=h($cronKey)?>&phrase=Jessica%20it%27s%20time%20to%20make%20the%20donuts">Open Strategy Mode</a>
  <a class="btn light" href="/dashboard/daily-command-center.php">Command Center</a>
  <a class="btn light" href="/dashboard/conversation-intelligence.php">Conversation Intel</a>
</p>

<div class="layout">
<section class="panel">
<h2>Latest Strategy Brief</h2>
<div style="padding:16px"><pre><?=h($s['strategy_brief']??'Click Open Strategy Mode to create a strategy session.')?></pre></div>
<h2>Session History</h2>
<table>
<tr><th>Time</th><th>Authenticated</th><th>Topic</th></tr>
<?php foreach($sessions as $x): if(isset($x['error'])) continue; ?>
<tr>
  <td><?=h($x['created_at']??'')?></td>
  <td><?=h(!empty($x['authenticated'])?'yes':'no')?></td>
  <td><?=h($x['requested_topic']??'')?></td>
</tr>
<?php endforeach;?>
</table>
</section>

<section class="panel">
<h2>Add Mark Training Note</h2>
<form method="post" style="padding:16px">
<select name="note_type">
<option value="mark_style">Mark Style</option>
<option value="objection">Objection</option>
<option value="script">Script</option>
<option value="strategy">Strategy</option>
<option value="lead_quality">Lead Quality</option>
</select>
<input name="title" placeholder="Title">
<input name="applies_to" placeholder="Applies to: seller, buyer, relocation, builder, all" value="all">
<input name="priority" type="number" value="75">
<textarea name="note" placeholder="Example: I like to lead with local context first, then ask about timing."></textarea>
<button class="btn">Save Training Note</button>
</form>

<h2>Active Training Notes</h2>
<table>
<tr><th>Priority</th><th>Note</th></tr>
<?php foreach($notes as $n): if(isset($n['error'])) continue; ?>
<tr>
  <td><?=h($n['priority']??'')?><div class="muted"><?=h($n['note_type']??'')?></div></td>
  <td><strong><?=h($n['title']??'')?></strong><br><?=h($n['note']??'')?><div class="muted"><?=h($n['applies_to']??'')?></div></td>
</tr>
<?php endforeach;?>
</table>
</section>
</div>
</main>
</body>
</html>