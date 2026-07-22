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
  if(($_POST['action']??'')==='asset'){
    $url='';
    if(!empty($_FILES['asset_file']['name'])){
      $dir=__DIR__.'/../uploads/media/editor-assets';
      if(!is_dir($dir)) mkdir($dir,0755,true);
      $safe=preg_replace('/[^a-zA-Z0-9._-]/','_',basename($_FILES['asset_file']['name']));
      $file=date('Ymd_His').'_'.$safe;
      move_uploaded_file($_FILES['asset_file']['tmp_name'],$dir.'/'.$file);
      $url='/uploads/media/editor-assets/'.$file;
    }
    sb('POST','media_editor_assets',[[
      'asset_name'=>$_POST['asset_name']??'Editor Asset',
      'asset_type'=>$_POST['asset_type']??'overlay',
      'asset_url'=>$url ?: ($_POST['asset_url']??''),
      'brand_pillar'=>$_POST['brand_pillar']??'discover_ct',
      'notes'=>$_POST['notes']??'',
      'created_at'=>date('c')
    ]]);
    $msg='Asset saved.';
  } else {
    $id=$_POST['id']??'';
    $patch=[
      'review_status'=>$_POST['review_status']??'needs_review',
      'editor_notes'=>$_POST['editor_notes']??'',
      'corrected_caption'=>$_POST['corrected_caption']??'',
      'corrected_cta'=>$_POST['corrected_cta']??'',
      'lower_third_text'=>$_POST['lower_third_text']??'',
      'title_card_text'=>$_POST['title_card_text']??'',
      'top_title_text'=>$_POST['top_title_text']??'',
      'logo_url'=>$_POST['logo_url']??'',
      'overlay_asset_url'=>$_POST['overlay_asset_url']??'',
      'music_notes'=>$_POST['music_notes']??'',
      'render_notes'=>$_POST['render_notes']??'',
      'updated_at'=>date('c')
    ];
    sb('PATCH','media_editor_reviews?id=eq.'.rawurlencode($id),$patch);

    if($patch['review_status']==='ready_to_render'){
      $review=sb('GET','media_editor_reviews?select=*&id=eq.'.rawurlencode($id).'&limit=1')['data'][0]??[];
      if($review){
        sb('POST','media_render_queue',[[
          'media_project_id'=>$review['media_project_id'],
          'media_clip_plan_id'=>null,
          'render_type'=>'short',
          'render_status'=>'ready_for_render',
          'output_format'=>'vertical_1080x1920',
          'caption_preset'=>'human_corrected_bold_kinetic',
          'logo_preset'=>'human_selected',
          'effect_stack'=>[
            'caption'=>$review['corrected_caption'],
            'cta'=>$review['corrected_cta'],
            'lower_third'=>$review['lower_third_text'],
            'title_card'=>$review['title_card_text'],
            'top_title'=>$review['top_title_text'],
            'logo_url'=>$review['logo_url'],
            'overlay_asset_url'=>$review['overlay_asset_url'],
            'music_notes'=>$review['music_notes']
          ],
          'render_instructions'=>$review['render_notes']."\n\nHuman editor notes:\n".$review['editor_notes'],
          'created_at'=>date('c'),
          'updated_at'=>date('c')
        ]]);
      }
    }
    $msg='Review updated.';
  }
}
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';
$reviews=sb('GET','media_editor_reviews?select=*&order=updated_at.desc,created_at.desc&limit=200')['data'];
$clips=sb('GET','media_clip_intelligence?select=*&order=viral_score.desc,created_at.desc&limit=200')['data'];
$assets=sb('GET','media_editor_assets?select=*&order=created_at.desc&limit=100')['data'];
function clipById($clips,$id){foreach($clips as $c){if(($c['id']??'')===$id)return $c;}return [];}
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Jessica OS Creative Command Center</title><style>
body{margin:0;background:#f5f3ef;color:#111;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.topbar{position:sticky;top:0;z-index:10;background:#111827;color:#fff;border-bottom:2px solid #c8a96e}.menu{display:flex;align-items:center;gap:0;max-width:1900px;margin:auto}.logo{font-family:Georgia,serif;color:#c8a96e;font-size:22px;font-weight:900;padding:13px 18px}.navitem{position:relative;padding:15px 14px;font-size:13px;font-weight:800;cursor:pointer}.navitem:hover{background:#1f2937}.drop{display:none;position:absolute;top:48px;left:0;background:#fff;color:#111;min-width:260px;box-shadow:0 8px 30px #0003;border-radius:0 0 12px 12px;overflow:hidden}.navitem:hover .drop{display:block}.drop a{display:block;color:#111;text-decoration:none;padding:10px 14px;border-bottom:1px solid #eee;font-weight:700}.drop a:hover{background:#f5f3ef}.header{background:#111827;color:white;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1900px;margin:auto;padding:24px}.grid{display:grid;grid-template-columns:1fr .42fr;gap:18px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.inner{padding:16px}.btn{border:0;background:#c8a96e;color:#111;padding:9px 12px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;text-decoration:none;display:inline-block;cursor:pointer}.light{background:#f2efe8}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid #eee;vertical-align:top;font-size:14px}th{font-size:11px;color:#777;text-transform:uppercase;background:#faf9f6}input,select,textarea{width:100%;box-sizing:border-box;padding:8px;border:1px solid #ddd;border-radius:8px;margin:4px 0 8px}.muted{color:#777;font-size:13px}.preview{background:#111;color:white;border-radius:18px;aspect-ratio:9/16;max-width:250px;padding:18px;display:flex;flex-direction:column;justify-content:space-between}.topTitle{font-size:22px;font-weight:900}.lower{font-size:13px;color:#c8a96e}.cta{font-size:14px;font-weight:900}.status{font-weight:900;color:#c8a96e}@media(max-width:1000px){.grid{grid-template-columns:1fr}.menu{overflow-x:auto}.wrap{padding:14px}}</style></head><body>
<div class="topbar"><div class="menu"><div class="logo">Jessica OS</div>
<div class="navitem">Hot Leads<div class="drop"><a href="/dashboard/seller-acquisition-director.php">Seller Acquisition</a><a href="/dashboard/traffic-scaling-director.php">Traffic Scaling</a><a href="/dashboard/internal-learning-brain.php">Learning Brain</a><a href="/dashboard/jessica-intelligence-connector.php">Voice Context</a></div></div>
<div class="navitem">Creator Center<div class="drop"><a href="/dashboard/jessica-media-director.php">Media Director</a><a href="/dashboard/jessica-shorts-factory.php">Shorts Factory</a><a href="/dashboard/jessica-render-kit.php">Render Kit</a><a href="/dashboard/jessica-content-intelligence.php">Content Intelligence</a><a href="/dashboard/jessica-canva-bridge.php">Canva Bridge</a><a href="/dashboard/jessica-creative-command-center.php">Creative Command Center</a></div></div>
<div class="navitem">Ad Suggestions<div class="drop"><a href="/dashboard/campaign-command-center.php">Campaign Command</a><a href="/dashboard/creative-review-studio.php">Creative Review</a><a href="/dashboard/blotato-distribution-director.php">Blotato Distribution</a><a href="/dashboard/blotato-direct-publishing.php">Direct Publishing</a></div></div>
<div class="navitem">Executive Assistant<div class="drop"><a href="/dashboard/jessica-mcp-server.php">MCP Server</a><a href="/dashboard/internal-learning-brain.php">Executive Learning</a><a href="/dashboard/jessica-content-intelligence.php">Content Intelligence</a></div></div>
</div></div>
<div class="header"><div class="brand">V17.6 Creative Command Center OS</div><div>Human final editor for Jessica clips: captions, lower thirds, title cards, logos, overlays, render notes.</div></div>
<main class="wrap"><p><a class="btn" target="_blank" href="/lead-engine/build-creative-command-center.php?key=<?=h($key)?>">Build Editor Reviews</a><a class="btn light" href="/dashboard/jessica-content-intelligence.php">Content Intelligence</a><a class="btn light" href="/dashboard/jessica-render-kit.php">Render Kit</a></p><?php if($msg):?><div class="panel"><div class="inner"><?=h($msg)?></div></div><?php endif;?>
<div class="grid"><section class="panel"><h2>Clip Review / Human Editor Controls</h2><table><tr><th>Preview</th><th>Clip</th><th>Editor Controls</th></tr><?php foreach($reviews as $r): $c=clipById($clips,$r['media_clip_intelligence_id']); ?><tr><td><div class="preview"><div class="topTitle"><?=h($r['top_title_text'])?></div><div><div class="lower"><?=h($r['lower_third_text'])?></div><div class="cta"><?=h($r['corrected_cta'])?></div></div></div><div class="muted status"><?=h($r['review_status'])?></div></td><td><strong><?=h($c['clip_title']??'Clip')?></strong><div class="muted"><?=h($c['hook_line']??'')?><br><?=h($c['platform']??'')?></div><p><?=nl2br(h($c['caption_text']??''))?></p></td><td><form method="post"><input type="hidden" name="id" value="<?=h($r['id'])?>"><label>Status</label><select name="review_status"><option <?=($r['review_status']==='needs_review'?'selected':'')?>>needs_review</option><option <?=($r['review_status']==='changes_requested'?'selected':'')?>>changes_requested</option><option <?=($r['review_status']==='approved'?'selected':'')?>>approved</option><option <?=($r['review_status']==='ready_to_render'?'selected':'')?>>ready_to_render</option><option <?=($r['review_status']==='sent_to_blotato'?'selected':'')?>>sent_to_blotato</option></select><label>Corrected Caption</label><textarea name="corrected_caption" rows="4"><?=h($r['corrected_caption'])?></textarea><label>CTA</label><input name="corrected_cta" value="<?=h($r['corrected_cta'])?>"><label>Top Title</label><input name="top_title_text" value="<?=h($r['top_title_text'])?>"><label>Lower Third</label><input name="lower_third_text" value="<?=h($r['lower_third_text'])?>"><label>Title Card</label><input name="title_card_text" value="<?=h($r['title_card_text'])?>"><label>Logo URL</label><input name="logo_url" value="<?=h($r['logo_url'])?>"><label>Overlay Asset URL</label><input name="overlay_asset_url" value="<?=h($r['overlay_asset_url'])?>"><label>Music Notes</label><input name="music_notes" value="<?=h($r['music_notes'])?>"><label>Render / Director Notes</label><textarea name="render_notes" rows="3"><?=h($r['render_notes'])?></textarea><label>Editor Notes</label><textarea name="editor_notes" rows="3"><?=h($r['editor_notes'])?></textarea><button class="btn">Save</button></form></td></tr><?php endforeach;?></table></section>
<aside><section class="panel"><h2>Upload Editor Asset</h2><div class="inner"><form method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="asset"><input name="asset_name" placeholder="Asset name"><select name="asset_type"><option>overlay</option><option>logo</option><option>lower_third</option><option>title_card</option><option>broll</option><option>music</option></select><select name="brand_pillar"><option>discover_ct</option><option>house_detective</option><option>seller_authority</option><option>buyer_authority</option></select><input type="file" name="asset_file"><input name="asset_url" placeholder="Or paste asset URL"><textarea name="notes" placeholder="Notes"></textarea><button class="btn">Save Asset</button></form></div></section><section class="panel"><h2>Asset Vault</h2><div class="inner"><?php foreach($assets as $a):?><p><strong><?=h($a['asset_name'])?></strong><br><span class="muted"><?=h($a['asset_type'])?> / <?=h($a['brand_pillar'])?></span><br><a href="<?=h($a['asset_url'])?>" target="_blank"><?=h($a['asset_url'])?></a></p><?php endforeach;?></div></section></aside></div>
</main></body></html>