<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb($m,$ep,$p=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>45]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$h=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
  return ['ok'=>$h>=200&&$h<300,'data'=>is_array($d)?$d:[],'body'=>$b,'http'=>$h];
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=$_POST['id']??'';
  $status=$_POST['approval_status']??'needs_review';
  $patch=[
    'approval_status'=>$status,
    'canva_url'=>$_POST['canva_url']??'',
    'export_asset_url'=>$_POST['export_asset_url']??'',
    'headline'=>$_POST['headline']??'',
    'subheadline'=>$_POST['subheadline']??'',
    'cta_text'=>$_POST['cta_text']??'',
    'updated_at'=>date('c')
  ];
  sb('PATCH','media_canva_briefs?id=eq.'.rawurlencode($id),$patch);

  if($status==='sent_to_blotato' || $status==='approved'){
    $brief=sb('GET','media_canva_briefs?select=*&id=eq.'.rawurlencode($id).'&limit=1')['data'][0]??[];
    if($brief && empty($brief['blotato_queue_id'])){
      $payload=[[
        'source_table'=>'media_canva_briefs',
        'source_id'=>$id,
        'distribution_title'=>$brief['brief_title'],
        'brand_pillar'=>$brief['brand_pillar'],
        'content_type'=>'short_form_video',
        'platforms'=>['Instagram','TikTok','YouTube Shorts','Facebook'],
        'caption'=>trim(($brief['headline']??'')."\n\n".($brief['subheadline']??'')."\n\n".($brief['cta_text']??'')),
        'hashtags'=>'#DiscoverCT #FairfieldCounty #ConnecticutRealEstate #MarkPyres',
        'cta'=>$brief['cta_text'],
        'media_url'=>$brief['export_asset_url'],
        'landing_page_url'=>'https://markpires.com/home-valuation.php',
        'priority_score'=>90,
        'distribution_score'=>90,
        'approval_status'=>'approved',
        'distribution_status'=>'queued',
        'blotato_payload'=>['canva_url'=>$brief['canva_url'],'asset_url'=>$brief['export_asset_url']],
        'created_at'=>date('c'),
        'updated_at'=>date('c')
      ]];
      $r=sb('POST','blotato_distribution_queue',$payload);
      $qid=$r['data'][0]['id']??'';
      if($qid) sb('PATCH','media_canva_briefs?id=eq.'.rawurlencode($id),['blotato_queue_id'=>(string)$qid,'approval_status'=>'sent_to_blotato','updated_at'=>date('c')]);
    }
  }
  $msg='Updated.';
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
$briefs=sb('GET','media_canva_briefs?select=*&order=created_at.desc&limit=200')['data'];
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>V17.5 Canva Bridge</title><style>
body{margin:0;background:#f5f3ef;color:#111;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:#111827;color:white;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1900px;margin:auto;padding:24px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.btn{border:0;background:#c8a96e;color:#111;padding:10px 13px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;text-decoration:none;display:inline-block}.light{background:#f2efe8}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid #eee;vertical-align:top;font-size:14px}th{font-size:11px;color:#777;text-transform:uppercase;background:#faf9f6}input,textarea{width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin:4px 0}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:12px;border-radius:10px;max-height:280px;overflow:auto}</style></head><body><div class="header"><div class="brand">V17.5 Canva + Approval + Blotato Bridge</div><div>Turns Jessica clip intelligence into Canva-ready briefs and approved distribution posts.</div></div><main class="wrap">
<p><a class="btn" target="_blank" href="/lead-engine/build-canva-bridge.php?key=<?=h($key)?>">Build Canva Briefs</a><a class="btn light" href="/dashboard/jessica-content-intelligence.php">Content Intelligence</a><a class="btn light" href="/dashboard/blotato-distribution-director.php">Blotato Queue</a></p>
<?php if($msg):?><div class="panel" style="padding:16px"><?=h($msg)?></div><?php endif;?>
<section class="panel"><h2>Canva Briefs / Approval Queue</h2><table><tr><th>Status</th><th>Brief</th><th>Canva Prompt</th><th>Approve / Send</th></tr><?php foreach($briefs as $b):?><tr><td><strong><?=h($b['approval_status'])?></strong><div class="muted"><?=h($b['brand_pillar'])?><br><?=h($b['design_type'])?></div></td><td><strong><?=h($b['brief_title'])?></strong><div class="muted"><?=h($b['color_direction'])?><br><?=h($b['logo_direction'])?></div></td><td><pre><?=h($b['canva_prompt'])?></pre><div class="muted"><?=nl2br(h($b['asset_notes']))?></div></td><td><form method="post"><input type="hidden" name="id" value="<?=h($b['id'])?>"><input name="headline" value="<?=h($b['headline'])?>" placeholder="Headline"><input name="subheadline" value="<?=h($b['subheadline'])?>" placeholder="Subheadline"><textarea name="cta_text"><?=h($b['cta_text'])?></textarea><input name="canva_url" value="<?=h($b['canva_url'])?>" placeholder="Canva design URL"><input name="export_asset_url" value="<?=h($b['export_asset_url'])?>" placeholder="Exported media URL"><button class="btn" name="approval_status" value="approved">Approve</button><button class="btn" name="approval_status" value="sent_to_blotato">Send To Blotato</button><button class="btn light" name="approval_status" value="rejected">Reject</button></form></td></tr><?php endforeach;?></table></section>
</main></body></html>