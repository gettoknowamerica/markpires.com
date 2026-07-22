<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb161d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(($_POST['action']??'')==='add'){
    $platforms=array_values(array_filter(array_map('trim',explode(',',$_POST['platforms']??''))));
    sb161d('POST','blotato_distribution_queue',[[
      'distribution_title'=>$_POST['distribution_title']??'Manual Post',
      'brand_pillar'=>$_POST['brand_pillar']??'mark_pires',
      'content_type'=>$_POST['content_type']??'social_post',
      'platforms'=>$platforms,
      'caption'=>$_POST['caption']??'',
      'hashtags'=>$_POST['hashtags']??'',
      'cta'=>$_POST['cta']??'',
      'media_url'=>$_POST['media_url']??'',
      'landing_page_url'=>$_POST['landing_page_url']??'',
      'scheduled_for'=>$_POST['scheduled_for']?:null,
      'priority_score'=>(int)($_POST['priority_score']??80),
      'distribution_score'=>(int)($_POST['priority_score']??80),
      'approval_status'=>'review',
      'distribution_status'=>'draft',
      'blotato_payload'=>['caption'=>$_POST['caption']??'','platforms'=>$platforms],
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ]]);
    $msg='Post added.';
  } else {
    $id=$_POST['id']??'';
    if($id){
      $patch=['updated_at'=>date('c')];
      if(!empty($_POST['approval_status']))$patch['approval_status']=$_POST['approval_status'];
      if(!empty($_POST['distribution_status']))$patch['distribution_status']=$_POST['distribution_status'];
      if(!empty($_POST['scheduled_for']))$patch['scheduled_for']=$_POST['scheduled_for'];
      if(!empty($_POST['blotato_post_id']))$patch['blotato_post_id']=$_POST['blotato_post_id'];
      if(($patch['distribution_status']??'')==='posted')$patch['posted_at']=date('c');
      sb161d('PATCH','blotato_distribution_queue?id=eq.'.rawurlencode($id),$patch);
      $msg='Item updated.';
    }
  }
}
$rows=sb161d('GET','blotato_distribution_queue?select=*&distribution_status=in.(draft,queued,scheduled,posted)&order=distribution_score.desc,created_at.desc&limit=300');
$briefs=sb161d('GET','blotato_distribution_briefings?select=*&order=created_at.desc&limit=3');
$brief=$briefs[0]??[];
$stats=['total'=>count($rows),'review'=>0,'approved'=>0,'scheduled'=>0,'posted'=>0];
foreach($rows as $r){if(($r['approval_status']??'')==='review')$stats['review']++;if(($r['approval_status']??'')==='approved')$stats['approved']++;if(($r['distribution_status']??'')==='scheduled')$stats['scheduled']++;if(($r['distribution_status']??'')==='posted')$stats['posted']++;}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V16.1 Blotato Distribution Director</title><style>
body{margin:0;background:#f5f3ef;color:#111;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:#111827;color:white;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1800px;margin:auto;padding:24px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.kpi,.panel{background:white;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:28px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.layout{display:grid;grid-template-columns:1fr .36fr;gap:18px}.btn{border:0;background:#c8a96e;color:#111;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer;text-decoration:none;display:inline-block}.light{background:#f2efe8}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid #eee;vertical-align:top;font-size:14px}th{font-size:11px;color:#777;text-transform:uppercase;background:#faf9f6}.muted{color:#777;font-size:13px}.score{font-size:28px;font-weight:900;color:#c8a96e}pre,.caption{white-space:pre-wrap;background:#111;color:#fff;padding:14px;border-radius:12px;max-height:320px;overflow:auto}input,select,textarea{width:100%;padding:9px;border:1px solid #ddd;border-radius:8px;margin:4px 0}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">V16.1 Blotato Distribution Director</div><div>Approve, schedule, and prepare Jessica’s social content for Blotato distribution.</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-blotato-distribution-director.php?key=<?=h($cronKey)?>">Build Distribution Queue</a><a class="btn light" href="/dashboard/creative-generation-studio.php">Creative Studio</a><a class="btn light" href="/dashboard/campaign-command-center.php">Campaign Command</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div><div class="kpi"><div class="n"><?=h($stats['review'])?></div>Review</div><div class="kpi"><div class="n"><?=h($stats['approved'])?></div>Approved</div><div class="kpi"><div class="n"><?=h($stats['scheduled'])?></div>Scheduled</div></section>
<div class="layout"><section class="panel"><h2>Distribution Queue</h2><table><tr><th>Score</th><th>Item</th><th>Caption</th><th>Controls</th></tr><?php foreach($rows as $r):?><tr><td><div class="score"><?=h($r['distribution_score'])?></div><div class="muted"><?=h($r['approval_status'])?><br><?=h($r['distribution_status'])?></div></td><td><strong><?=h($r['distribution_title'])?></strong><div class="muted"><?=h($r['brand_pillar'])?> / <?=h($r['content_type'])?><br><?=h(implode(', ',is_array($r['platforms']??null)?$r['platforms']:[]))?><br><?php if($r['media_url']):?><a target="_blank" href="<?=h($r['media_url'])?>">Media</a><?php endif;?></div></td><td><div class="caption"><?=h($r['caption'])?></div><div class="muted"><?=h($r['hashtags'])?></div></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><input type="datetime-local" name="scheduled_for"><input name="blotato_post_id" placeholder="Blotato post ID"><button class="btn" name="approval_status" value="approved">Approve</button><button class="btn light" name="distribution_status" value="scheduled">Schedule</button><button class="btn light" name="distribution_status" value="posted">Mark Posted</button><button class="btn light" name="approval_status" value="rejected">Reject</button></form></td></tr><?php endforeach;?></table></section><section class="panel"><h2>Add Manual Post</h2><div style="padding:16px"><form method="post"><input type="hidden" name="action" value="add"><input name="distribution_title" placeholder="Title"><select name="brand_pillar"><option value="seller_authority">Seller Authority</option><option value="discover_ct">Discover CT</option><option value="house_detective">House Detective</option><option value="mark_pires">Mark Pires</option></select><select name="content_type"><option value="social_post">Social Post</option><option value="short">Short/Reel</option><option value="ad">Ad</option><option value="blog_share">Blog Share</option></select><input name="platforms" value="Instagram,Facebook,LinkedIn"><textarea name="caption" placeholder="Caption"></textarea><input name="hashtags" placeholder="Hashtags"><input name="cta" placeholder="CTA"><input name="media_url" placeholder="Media URL"><input name="landing_page_url" placeholder="Landing page"><input type="datetime-local" name="scheduled_for"><input name="priority_score" value="80"><button class="btn">Add Post</button></form></div><h2>Jessica Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Run Build Distribution Queue to create briefing.')?></pre></div></section></div></main></body></html>