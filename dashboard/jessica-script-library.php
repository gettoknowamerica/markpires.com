<?php
/**
 * V10.5.1 Jessica Script Library Dashboard — 500 Fix
 * Upload: /public_html/dashboard/jessica-script-library.php
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

function sb105_fixed($m,$ep,$p=null){
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
    $id=$_POST['id']??'';
    $payload=[
      'script_name'=>$_POST['script_name']??'',
      'opening_line'=>$_POST['opening_line']??'',
      'core_script'=>$_POST['core_script']??'',
      'voicemail_script'=>$_POST['voicemail_script']??'',
      'sms_followup'=>$_POST['sms_followup']??'',
      'email_subject'=>$_POST['email_subject']??'',
      'email_body'=>$_POST['email_body']??'',
      'tone_notes'=>$_POST['tone_notes']??'',
      'compliance_notes'=>$_POST['compliance_notes']??'',
      'updated_at'=>date('c')
    ];
    if($id){
      $r=sb105_fixed('PATCH','jessica_script_library?id=eq.'.rawurlencode($id),$payload);
      $msg=$r['ok']?'Script updated.':'Update failed: '.$r['body'];
    }
  }

  $res=sb105_fixed('GET','jessica_script_library?select=*&order=script_key.asc&limit=200');
  $scripts=$res['data'];
  if(!$res['ok']) $error='Supabase error: '.$res['body'];

}catch(Throwable $e){
  $scripts=[];
  $error='PHP exception: '.$e->getMessage().' on line '.$e->getLine();
}

$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?>
<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Jessica Script Library V10.5</title>
<style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}
.brand{font-family:Georgia,serif;color:#c8a96e;font-size:36px}
.wrap{max-width:1450px;margin:auto;padding:26px}
.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin-top:18px;overflow:hidden}
.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}
.btn{display:inline-block;background:#10101a;color:#fff;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;border:0;cursor:pointer}
.gold{background:#c8a96e;color:#111}.light{background:#f2efe8;color:#111}
table{width:100%;border-collapse:collapse}
td,th{text-align:left;padding:11px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}
th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}
textarea,input{width:100%;padding:9px;border:1px solid #ddd;border-radius:8px;margin:4px 0;font-family:inherit}
.muted{color:#777;font-size:13px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.ok{background:#e6f7ec;color:#14783c;padding:12px;border-radius:12px;margin:12px 0}.bad{background:#ffeaea;color:#9b1c1c;padding:12px;border-radius:12px;margin:12px 0;white-space:pre-wrap}
@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{padding:14px}}
</style>
</head>
<body>
<div class="header"><div class="brand">Jessica Script Library V10.5</div><div>Segment-specific scripts, voicemail, SMS, email, compliance notes</div></div>
<main class="wrap">
<?php if($msg):?><div class="ok"><?=h($msg)?></div><?php endif;?>
<?php if($error):?><div class="bad"><?=h($error)?></div><?php endif;?>

<p>
<a class="btn gold" target="_blank" href="/lead-engine/apply-jessica-scripts.php?key=<?=h($cronKey)?>">Apply Scripts To Queues</a>
<a class="btn light" href="/dashboard/autonomous-hunter-calling.php">Autonomous Hunter</a>
</p>

<section class="panel"><h2>Scripts</h2>
<?php if(empty($scripts)): ?>
<div style="padding:18px">No scripts found. Run the V10.5 SQL first.</div>
<?php else: ?>
<table><tr><th>Script</th><th>Content</th></tr>
<?php foreach($scripts as $s):?>
<tr>
<td><strong><?=h($s['script_key']??'')?></strong><div class="muted"><?=h($s['lead_segment']??'')?><br><?=h($s['channel']??'')?><br><?=h($s['tone_notes']??'')?></div></td>
<td>
<form method="post">
<input type="hidden" name="id" value="<?=h($s['id']??'')?>">
<div class="grid"><div><label>Name</label><input name="script_name" value="<?=h($s['script_name']??'')?>"></div><div><label>Email Subject</label><input name="email_subject" value="<?=h($s['email_subject']??'')?>"></div></div>
<label>Opening Line</label><textarea name="opening_line" rows="2"><?=h($s['opening_line']??'')?></textarea>
<label>Core Script</label><textarea name="core_script" rows="4"><?=h($s['core_script']??'')?></textarea>
<label>Voicemail</label><textarea name="voicemail_script" rows="3"><?=h($s['voicemail_script']??'')?></textarea>
<label>SMS Follow-up</label><textarea name="sms_followup" rows="2"><?=h($s['sms_followup']??'')?></textarea>
<label>Email Body</label><textarea name="email_body" rows="3"><?=h($s['email_body']??'')?></textarea>
<div class="grid"><div><label>Tone Notes</label><textarea name="tone_notes" rows="2"><?=h($s['tone_notes']??'')?></textarea></div><div><label>Compliance Notes</label><textarea name="compliance_notes" rows="2"><?=h($s['compliance_notes']??'')?></textarea></div></div>
<button class="btn gold">Save Script</button>
</form>
</td>
</tr>
<?php endforeach;?>
</table>
<?php endif; ?>
</section>
</main>
</body>
</html>