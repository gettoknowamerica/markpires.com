<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb153d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if($action==='add'){
    sb153d('POST','content_mine_assets',[[
      'archive_source'=>$_POST['archive_source']??'manual',
      'brand_pillar'=>$_POST['brand_pillar']??'mark_pires',
      'original_title'=>$_POST['original_title']??'',
      'source_url'=>$_POST['source_url']??'',
      'source_file'=>$_POST['source_file']??'',
      'source_platform'=>$_POST['source_platform']??'',
      'town'=>$_POST['town']??'',
      'topic'=>$_POST['topic']??'',
      'content_type'=>$_POST['content_type']??'video',
      'transcript'=>$_POST['transcript']??'',
      'notes'=>$_POST['notes']??'',
      'emotional_moment'=>$_POST['emotional_moment']??'',
      'best_quote'=>$_POST['best_quote']??'',
      'seller_angle'=>$_POST['seller_angle']??'',
      'buyer_angle'=>$_POST['buyer_angle']??'',
      'local_angle'=>$_POST['local_angle']??'',
      'production_effort_score'=>(int)($_POST['production_effort_score']??20),
      'status'=>'active',
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ]]);
    $msg='Content mine asset added.';
  } else {
    $id=$_POST['id']??''; $status=$_POST['status']??'';
    if($id && in_array($status,['active','approved','archived'],true)){
      sb153d('PATCH','content_mine_assets?id=eq.'.rawurlencode($id),['status'=>$status,'approved_for_distribution'=>$status==='approved','updated_at'=>date('c')]);
      $msg='Content mine asset updated.';
    }
  }
}
$rows=sb153d('GET','content_mine_assets?select=*&status=eq.active&order=total_content_mine_score.desc,created_at.desc&limit=400');
$briefs=sb153d('GET','content_mine_briefings?select=*&order=created_at.desc&limit=5');
$brief=$briefs[0]??[];
$stats=['total'=>count($rows),'ready'=>0,'discover'=>0,'detective'=>0,'seller'=>0,'beatseat'=>0,'pushed'=>0];
foreach($rows as $r){
  if((int)($r['total_content_mine_score']??0)>=72)$stats['ready']++;
  if(($r['brand_pillar']??'')==='discover_ct')$stats['discover']++;
  if(($r['brand_pillar']??'')==='house_detective')$stats['detective']++;
  if(($r['brand_pillar']??'')==='seller_authority')$stats['seller']++;
  if(($r['brand_pillar']??'')==='beatseat')$stats['beatseat']++;
  if(!empty($r['pushed_to_creative_intelligence']))$stats['pushed']++;
}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V15.3 Content Mine Director</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1800px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(7,1fr);gap:14px}.kpi,.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:26px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .36fr;gap:18px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top}th{font-size:11px;text-transform:uppercase;color:#777;background:#faf9f6}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}.score{font-size:30px;font-weight:900;color:#c8a96e}input,select,textarea{width:100%;padding:9px;border:1px solid #ddd;border-radius:8px;margin:4px 0}.badge{background:#111;color:#fff;border-radius:999px;padding:3px 7px;font-size:11px}@media(max-width:1000px){.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">V15.3 Content Mine Director</div><div>Jessica mines your existing Discover CT, House Detective, seller tips, videos, blogs, and BeatSeat archive for repurpose winners</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-content-mine-director.php?key=<?=h($cronKey)?>">Build Content Mine</a><a class="btn light" href="/dashboard/creative-intelligence-director.php">Creative Director</a><a class="btn light" href="/dashboard/traffic-scaling-director.php">Traffic Director</a><a class="btn light" href="/dashboard/jessica-master-control.php">Master</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div><div class="kpi"><div class="n"><?=h($stats['ready'])?></div>Ready</div><div class="kpi"><div class="n"><?=h($stats['discover'])?></div>Discover</div><div class="kpi"><div class="n"><?=h($stats['detective'])?></div>Detective</div><div class="kpi"><div class="n"><?=h($stats['seller'])?></div>Seller</div><div class="kpi"><div class="n"><?=h($stats['beatseat'])?></div>BeatSeat</div><div class="kpi"><div class="n"><?=h($stats['pushed'])?></div>Pushed</div></section>
<div class="layout"><section class="panel"><h2>Top Archive Assets To Repurpose</h2><table><tr><th>Score</th><th>Asset</th><th>Angles</th><th>Plan</th><th>Action</th></tr><?php foreach($rows as $r):?><tr><td><div class="score"><?=h($r['total_content_mine_score'])?></div><div class="muted"><?=h($r['recommended_use'])?></div></td><td><strong><?=h($r['original_title'])?></strong> <?php if(!empty($r['pushed_to_creative_intelligence'])):?><span class="badge">CREATIVE</span><?php endif;?><div class="muted"><?=h($r['brand_pillar'])?> / <?=h($r['content_type'])?><br><?=h($r['town'])?><br><?php if($r['source_url']):?><a target="_blank" href="<?=h($r['source_url'])?>">Open Source</a><?php endif;?></div></td><td><strong><?=h($r['recommended_hook'])?></strong><div class="muted">Best quote: <?=h($r['best_quote'])?><br>Seller: <?=h($r['seller_angle'])?><br>Local: <?=h($r['local_angle'])?></div></td><td><?=h($r['recommended_plan'])?><div class="muted">Caption: <?=h($r['recommended_caption'])?></div></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn" name="status" value="approved">Approve</button><button class="btn light" name="status" value="archived">Archive</button></form></td></tr><?php endforeach;?></table></section><section class="panel"><h2>Add Archive Asset</h2><div style="padding:16px"><form method="post"><input type="hidden" name="action" value="add"><select name="brand_pillar"><option value="discover_ct">Discover CT</option><option value="house_detective">House Detective</option><option value="seller_authority">Seller Authority</option><option value="buyer_authority">Buyer Authority</option><option value="beatseat">BeatSeat</option><option value="mark_pires">Mark Pires</option></select><select name="content_type"><option value="video">Video</option><option value="short">Short</option><option value="interview">Interview</option><option value="listing_video">Listing Video</option><option value="market_update">Market Update</option><option value="blog">Blog</option><option value="music">Music</option></select><input name="original_title" placeholder="Original title"><input name="source_url" placeholder="Source URL"><input name="source_file" placeholder="File name / drive path"><input name="source_platform" placeholder="YouTube / TikTok / Drive / Local"><input name="town" placeholder="Town"><input name="topic" placeholder="Topic"><input name="emotional_moment" placeholder="Best emotional moment"><input name="best_quote" placeholder="Best quote"><textarea name="notes" placeholder="Notes / what happens in the clip"></textarea><textarea name="transcript" placeholder="Transcript or rough notes"></textarea><input name="seller_angle" placeholder="Seller angle"><input name="buyer_angle" placeholder="Buyer angle"><input name="local_angle" placeholder="Local angle"><input name="production_effort_score" value="20" placeholder="Effort 0-100"><button class="btn">Add To Mine</button></form></div><h2>Jessica Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Build Content Mine to create briefing.')?></pre></div></section></div>
</main></body></html>