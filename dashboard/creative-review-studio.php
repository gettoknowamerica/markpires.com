<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb162d($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>30]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);curl_close($ch);$d=json_decode($b,true);return is_array($d)?$d:[];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??'';
  $status=$_POST['review_status']??'';
  if($id && in_array($status,['review','improve','approved','rejected','sent_to_blotato','archived'],true)){
    $patch=[
      'review_status'=>$status,
      'headline'=>$_POST['headline']??null,
      'subheadline'=>$_POST['subheadline']??null,
      'caption'=>$_POST['caption']??null,
      'hashtags'=>$_POST['hashtags']??null,
      'cta'=>$_POST['cta']??null,
      'landing_page_url'=>$_POST['landing_page_url']??null,
      'preview_image_url'=>$_POST['preview_image_url']??null,
      'edit_notes'=>$_POST['edit_notes']??null,
      'updated_at'=>date('c')
    ];
    sb162d('PATCH','creative_review_studio_items?id=eq.'.rawurlencode($id),$patch);
    $item=sb162d('GET','creative_review_studio_items?id=eq.'.rawurlencode($id).'&limit=1');
    $it=$item[0]??[];
    if($status==='sent_to_blotato' || $status==='approved'){
      if(!empty($it['blotato_queue_id'])){
        sb162d('PATCH','blotato_distribution_queue?id=eq.'.rawurlencode($it['blotato_queue_id']),[
          'approval_status'=>'approved',
          'distribution_status'=>'queued',
          'caption'=>$patch['caption'],
          'hashtags'=>$patch['hashtags'],
          'cta'=>$patch['cta'],
          'media_url'=>$patch['preview_image_url'],
          'landing_page_url'=>$patch['landing_page_url'],
          'updated_at'=>date('c')
        ]);
      } else {
        $platforms=is_array($it['platforms']??null)?$it['platforms']:[];
        $r=sb162d('POST','blotato_distribution_queue',[[
          'source_table'=>'creative_review_studio_items',
          'source_id'=>$id,
          'distribution_title'=>$it['review_title']??'Approved Creative',
          'brand_pillar'=>$it['brand_pillar']??'mark_pires',
          'content_type'=>$it['content_type']??'social_post',
          'platforms'=>$platforms,
          'caption'=>$patch['caption'],
          'hashtags'=>$patch['hashtags'],
          'cta'=>$patch['cta'],
          'media_url'=>$patch['preview_image_url'],
          'landing_page_url'=>$patch['landing_page_url'],
          'priority_score'=>(int)($it['review_score']??80),
          'distribution_score'=>(int)($it['review_score']??80),
          'approval_status'=>'approved',
          'distribution_status'=>'queued',
          'blotato_payload'=>['caption'=>$patch['caption'],'platforms'=>$platforms,'media_url'=>$patch['preview_image_url']],
          'created_at'=>date('c'),
          'updated_at'=>date('c')
        ]]);
        $qid=$r[0]['id']??'';
        if($qid) sb162d('PATCH','creative_review_studio_items?id=eq.'.rawurlencode($id),['blotato_queue_id'=>(string)$qid,'review_status'=>'sent_to_blotato','updated_at'=>date('c')]);
      }
    }
    $msg='Creative review updated.';
  }
}
$rows=sb162d('GET','creative_review_studio_items?select=*&review_status=in.(review,improve,approved,sent_to_blotato)&order=review_score.desc,created_at.desc&limit=300');
$briefs=sb162d('GET','creative_review_studio_briefings?select=*&order=created_at.desc&limit=3');
$brief=$briefs[0]??[];
$stats=['total'=>count($rows),'review'=>0,'approved'=>0,'improve'=>0,'sent'=>0];
foreach($rows as $r){if(($r['review_status']??'')==='review')$stats['review']++;if(($r['review_status']??'')==='approved')$stats['approved']++;if(($r['review_status']??'')==='improve')$stats['improve']++;if(($r['review_status']??'')==='sent_to_blotato')$stats['sent']++;}
$cronKey=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V16.2 Creative Review Studio</title><style>
body{margin:0;background:#f5f3ef;color:#111;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:#111827;color:white;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1900px;margin:auto;padding:24px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}.kpi,.panel,.card{background:white;border-radius:16px;box-shadow:0 2px 12px #0001}.kpi{padding:18px}.n{font-size:28px;font-weight:900}.panel{margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;background:#c8a96e;color:#111;padding:9px 11px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer;text-decoration:none;display:inline-block}.light{background:#f2efe8}.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.card{overflow:hidden}.preview{height:260px;background:linear-gradient(135deg,#111827,#2b2b3d);display:flex;align-items:center;justify-content:center;color:#c8a96e;text-align:center;padding:18px}.preview img{width:100%;height:100%;object-fit:cover}.body{padding:16px}.muted{color:#777;font-size:13px}.score{font-size:26px;font-weight:900;color:#c8a96e}input,textarea{width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin:4px 0}.caption{min-height:90px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:14px;border-radius:12px;max-height:320px;overflow:auto}.layout{display:grid;grid-template-columns:1fr .34fr;gap:18px}@media(max-width:1200px){.cards,.grid,.layout{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body><div class="header"><div class="brand">V16.2 Creative Review Studio</div><div>Visual approval layer for ads, graphics, thumbnails, Discover CT, House Detective, and Blotato-ready posts.</div></div><main class="wrap"><?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<p><a class="btn" target="_blank" href="/lead-engine/build-creative-review-studio.php?key=<?=h($cronKey)?>">Build Review Studio</a><a class="btn light" href="/dashboard/creative-generation-studio.php">Creative Studio</a><a class="btn light" href="/dashboard/blotato-distribution-director.php">Blotato Queue</a></p>
<section class="grid"><div class="kpi"><div class="n"><?=h($stats['total'])?></div>Total</div><div class="kpi"><div class="n"><?=h($stats['review'])?></div>Review</div><div class="kpi"><div class="n"><?=h($stats['improve'])?></div>Improve</div><div class="kpi"><div class="n"><?=h($stats['approved'])?></div>Approved</div><div class="kpi"><div class="n"><?=h($stats['sent'])?></div>To Blotato</div></section>
<div class="layout"><section><div class="cards"><?php foreach($rows as $r):?><div class="card"><div class="preview"><?php if($r['preview_image_url']):?><img src="<?=h($r['preview_image_url'])?>"><?php else:?><div><strong><?=h($r['brand_pillar'])?></strong><br><?=h($r['review_title'])?><br><span class="muted">No image yet — use prompt/variant request.</span></div><?php endif;?></div><div class="body"><div class="score"><?=h($r['review_score'])?></div><strong><?=h($r['review_title'])?></strong><div class="muted"><?=h($r['brand_pillar'])?> / <?=h($r['content_type'])?> / <?=h($r['review_status'])?><br>Visual <?=h($r['visual_score'])?> | Copy <?=h($r['copy_score'])?> | Brand <?=h($r['brand_score'])?> | Conv <?=h($r['conversion_score'])?></div><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><input name="preview_image_url" value="<?=h($r['preview_image_url'])?>" placeholder="Preview/generated image URL"><input name="headline" value="<?=h($r['headline'])?>" placeholder="Headline"><input name="subheadline" value="<?=h($r['subheadline'])?>" placeholder="Subheadline"><textarea class="caption" name="caption" placeholder="Caption"><?=h($r['caption'])?></textarea><input name="hashtags" value="<?=h($r['hashtags'])?>" placeholder="Hashtags"><input name="cta" value="<?=h($r['cta'])?>" placeholder="CTA"><input name="landing_page_url" value="<?=h($r['landing_page_url'])?>" placeholder="Landing page"><textarea name="edit_notes" placeholder="Edit notes"><?=h($r['edit_notes'])?></textarea><details><summary>Image Prompt</summary><pre><?=h($r['image_prompt'])?></pre></details><button class="btn" name="review_status" value="approved">Approve</button><button class="btn" name="review_status" value="sent_to_blotato">Send To Blotato</button><button class="btn light" name="review_status" value="improve">Improve</button><button class="btn light" name="review_status" value="rejected">Reject</button><button class="btn light" name="review_status" value="archived">Archive</button></form></div></div><?php endforeach;?></div></section><section class="panel"><h2>Jessica Creative Brief</h2><div style="padding:16px"><pre><?=h($brief['briefing_text']??'Run Build Review Studio to create briefing.')?></pre></div></section></div></main></body></html>