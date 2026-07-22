<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb133d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>25]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??''; $status=$_POST['status']??'';
  if($id && in_array($status,['review','approved','rejected','revise','launched','archived'],true)){
    $patch=['status'=>$status,'updated_at'=>date('c')];
    if($status==='approved') $patch += ['launch_ready'=>true,'approved_by'=>'Mark','approved_at'=>date('c')];
    if($status==='launched') $patch += ['launch_ready'=>true];
    sb133d('PATCH','creative_review_items?id=eq.'.rawurlencode($id),$patch);
    $msg='Creative marked '.$status.'.';
  }
}
$items=sb133d('GET','creative_review_items?select=*&order=priority_score.desc,created_at.desc&limit=300');
$briefs=sb133d('GET','creative_review_briefings?select=*&order=created_at.desc&limit=5');
$brief=$briefs[0]??[];
$stats=['total'=>count($items),'review'=>0,'approved'=>0,'ads'=>0,'content'=>0,'video'=>0];
foreach($items as $i){ if(($i['status']??'')==='review')$stats['review']++; if(($i['status']??'')==='approved')$stats['approved']++; if(($i['creative_type']??'')==='ad')$stats['ads']++; if(in_array(($i['creative_type']??''),['content','source_hunter'],true))$stats['content']++; if(!empty($i['video_prompt']))$stats['video']++; }
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V13.3 Creative Review</title><style>
body{margin:0;background:#f5f3ef;color:#10101a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:linear-gradient(135deg,#10101a,#1a1a2e);color:#fff;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1700px;margin:auto;padding:26px}.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px}.kpi,.panel,.card{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:28px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;display:inline-block;background:#c8a96e;color:#111;text-decoration:none;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer}.light{background:#f2efe8;color:#111}.layout{display:grid;grid-template-columns:1fr .38fr;gap:18px}.cards{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;padding:16px}.card{padding:18px}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:16px;border-radius:12px}.pill{display:inline-block;padding:4px 8px;border-radius:999px;background:#f2efe8;font-size:12px;font-weight:800;margin:2px}.headline{font-family:Georgia,serif;font-size:22px;font-weight:800}.copy{line-height:1.45}.prompt{background:#faf9f6;border:1px solid #eee;border-radius:12px;padding:10px;margin-top:8px;font-size:13px}@media(max-width:1000px){.grid,.layout,.cards{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">V13.3 Creative Review Center</div><div>Review Jessica’s ads, content ideas, image prompts, video prompts, and launch concepts before anything goes live</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-creative-review.php?key=<?=h($cronKey)?>">Build Creative Review</a><a class="btn light" href="/dashboard/source-hunter-center.php">Source Hunter</a><a class="btn light" href="/dashboard/jessica-deliverables.php">Deliverables</a><a class="btn light" href="/dashboard/daily-command-center.php">Command</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div><div class="kpi"><div class="n"><?=h($stats['review'])?></div>Review</div><div class="kpi"><div class="n"><?=h($stats['approved'])?></div>Approved</div><div class="kpi"><div class="n"><?=h($stats['ads'])?></div>Ads</div><div class="kpi"><div class="n"><?=h($stats['content'])?></div>Content</div><div class="kpi"><div class="n"><?=h($stats['video'])?></div>Video</div></section>
<div class="layout"><section class="panel"><h2>Creative Cards</h2><div class="cards"><?php foreach($items as $i):?><article class="card"><div><span class="pill"><?=h($i['creative_type'])?></span><span class="pill"><?=h($i['status'])?></span><span class="pill">Score <?=h($i['priority_score'])?></span></div><div class="headline"><?=h($i['headline'])?></div><p class="muted"><?=h($i['town'])?> · <?=h($i['audience'])?></p><p class="copy"><?=h($i['primary_text'])?></p><p><strong>CTA:</strong> <?=h($i['cta'])?></p><?php if($i['landing_page']):?><p><a target="_blank" href="<?=h($i['landing_page'])?>">Landing Page</a></p><?php endif;?><div class="prompt"><strong>Image Prompt</strong><br><?=h($i['image_prompt'])?></div><div class="prompt"><strong>Video Prompt</strong><br><?=h($i['video_prompt'])?></div><div class="prompt"><strong>Design Notes</strong><br><?=h($i['design_notes'])?></div><form method="post"><input type="hidden" name="id" value="<?=h($i['id'])?>"><button class="btn" name="status" value="approved">Approve</button><button class="btn light" name="status" value="revise">Revise</button><button class="btn light" name="status" value="rejected">Reject</button><button class="btn light" name="status" value="launched">Mark Launched</button></form></article><?php endforeach;?></div></section>
<section class="panel"><h2>Creative Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Build Creative Review to create briefing.')?></pre></div></section></div>
</main></body></html>