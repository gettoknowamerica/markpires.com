<?php
/**
 * V11.2.1 Builder Matchmaker Dashboard — 500 Fix
 * Upload: /public_html/dashboard/builder-matchmaker.php
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

function sb112_fix($m,$ep,$p=null){
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
  if($p!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);
  $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  $d=json_decode($b,true);
  return['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}

$msg='';
$error='';

try{
  if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';
    if($action==='add_builder'){
      $payload=[[
        'name'=>$_POST['name']??'',
        'company'=>$_POST['company']??'',
        'phone'=>$_POST['phone']??'',
        'email'=>$_POST['email']??'',
        'website'=>$_POST['website']??'',
        'towns'=>$_POST['towns']??'',
        'buyer_profile'=>$_POST['buyer_profile']??'',
        'property_preferences'=>$_POST['property_preferences']??'',
        'notes'=>$_POST['notes']??'',
        'status'=>'active',
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ]];
      $r=sb112_fix('POST','builder_contacts',$payload);
      $msg=$r['ok']?'Builder contact added.':'Add failed: '.$r['body'];
    } elseif($action==='match_status'){
      $id=$_POST['id']??'';
      $status=$_POST['status']??'review';
      $r=sb112_fix('PATCH','builder_opportunity_matches?id=eq.'.rawurlencode($id),[
        'status'=>$status,
        'updated_at'=>date('c')
      ]);
      $msg=$r['ok']?'Match updated.':'Update failed: '.$r['body'];
    }
  }

  $matchesRes=sb112_fix('GET','builder_opportunity_matches?select=*&order=match_score.desc&limit=300');
  $buildersRes=sb112_fix('GET','builder_contacts?select=*&order=created_at.desc&limit=100');

  $matches=$matchesRes['data'];
  $builders=$buildersRes['data'];

  if(!$matchesRes['ok']) $error.='Matches table error: '.$matchesRes['body']."\n";
  if(!$buildersRes['ok']) $error.='Builder contacts table error: '.$buildersRes['body']."\n";

}catch(Throwable $e){
  $matches=[];
  $builders=[];
  $error='PHP exception: '.$e->getMessage().' on line '.$e->getLine();
}

$stats=['matches'=>count($matches),'builders'=>count($builders),'approved'=>0,'introduced'=>0];
foreach($matches as $m){
  if(($m['status']??'')==='approved')$stats['approved']++;
  if(($m['status']??'')==='introduced')$stats['introduced']++;
}

$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Builder Matchmaker V11.2</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}.wrap{max-width:1450px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:34px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.btn{display:inline-block;border:0;background:#10101a;color:#fff;text-decoration:none;padding:8px 10px;border-radius:8px;font-size:12px;font-weight:800;margin:2px;cursor:pointer}.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}input,textarea{padding:9px;border:1px solid #ddd;border-radius:8px;margin:4px;width:95%;max-width:270px}.muted{color:#777;font-size:13px}.layout{display:grid;grid-template-columns:1fr .45fr;gap:18px}.bad{background:#ffeaea;color:#9b1c1c;padding:14px;border-radius:12px;margin:12px 0;white-space:pre-wrap}.ok{background:#e6f7ec;color:#14783c;padding:14px;border-radius:12px;margin:12px 0}@media(max-width:900px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}
</style>
</head>
<body>
<div class="header"><div class="brand">Builder Contact Matchmaker V11.2</div><div>Match opportunities with builders, developers, investors</div></div>
<main class="wrap">
<?php if($msg):?><div class="ok"><?=h($msg)?></div><?php endif;?>
<?php if($error):?><div class="bad"><?=h($error)?></div><?php endif;?>

<p>
<a class="btn gold" target="_blank" href="/lead-engine/build-builder-matches.php?key=<?=h($cronKey)?>">Build Builder Matches</a>
<a class="btn light" href="/dashboard/builder-developer-radar.php">Builder Radar</a>
<a class="btn light" href="/dashboard/builder-intro-outreach.php">Intro Outreach</a>
</p>

<section class="grid">
  <div class="kpi"><div class="n"><?=h($stats['builders'])?></div>Builder Contacts</div>
  <div class="kpi"><div class="n"><?=h($stats['matches'])?></div>Matches</div>
  <div class="kpi"><div class="n"><?=h($stats['approved'])?></div>Approved</div>
  <div class="kpi"><div class="n"><?=h($stats['introduced'])?></div>Introduced</div>
</section>

<div class="layout">
<section class="panel"><h2>Opportunity Matches</h2><table>
<tr><th>Score</th><th>Opportunity</th><th>Builder</th><th>Reason</th><th>Actions</th></tr>
<?php foreach($matches as $m):?>
<tr>
<td><strong><?=h($m['match_score']??'')?></strong><div class="muted"><?=h($m['status']??'')?></div></td>
<td><?=h($m['opportunity_address']??'')?><div class="muted"><?=h($m['opportunity_town']??'')?><br><?=h($m['opportunity_type']??'')?></div></td>
<td><strong><?=h($m['builder_name']??'')?></strong><div class="muted"><?=h($m['company']??'')?><br><?=h($m['phone']??'')?><br><?=h($m['email']??'')?></div></td>
<td><?=h($m['reason']??'')?></td>
<td>
<form method="post">
<input type="hidden" name="action" value="match_status">
<input type="hidden" name="id" value="<?=h($m['id']??'')?>">
<button class="btn gold" name="status" value="approved">Approve</button>
<button class="btn light" name="status" value="introduced">Introduced</button>
<button class="btn" name="status" value="contacted">Contacted</button>
<button class="btn light" name="status" value="dead">Dead</button>
</form>
</td>
</tr>
<?php endforeach;?>
</table></section>

<section class="panel"><h2>Add Builder Contact</h2>
<form method="post" style="padding:16px">
<input type="hidden" name="action" value="add_builder">
<input name="name" placeholder="Name">
<input name="company" placeholder="Company">
<input name="phone" placeholder="Phone">
<input name="email" placeholder="Email">
<input name="website" placeholder="Website">
<textarea name="towns" placeholder="Towns served"></textarea>
<textarea name="buyer_profile" placeholder="Buyer profile: land, teardown, luxury, etc."></textarea>
<textarea name="property_preferences" placeholder="Property preferences"></textarea>
<textarea name="notes" placeholder="Notes"></textarea>
<button class="btn gold">Add Builder</button>
</form>
<h2>Builder Contacts</h2><table><tr><th>Builder</th><th>Profile</th></tr>
<?php foreach($builders as $b):?>
<tr><td><strong><?=h($b['name']??'')?></strong><div class="muted"><?=h($b['company']??'')?><br><?=h($b['phone']??'')?><br><?=h($b['email']??'')?></div></td><td><?=h($b['towns']??'')?><div class="muted"><?=h($b['buyer_profile']??'')?></div></td></tr>
<?php endforeach;?>
</table></section>
</div>
</main></body></html>