<?php
/**
 * V18.4 Hostinger Raw Video Upload Patch
 * Upload: /public_html/dashboard/jessica-media-director.php
 *
 * Saves uploaded video/audio files directly to:
 * /public_html/uploads/media/raw/
 *
 * Stores only the filename/path in Supabase.
 */
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
if(file_exists(__DIR__ . '/includes/goliath-nav.php')) require_once __DIR__ . '/includes/goliath-nav.php';

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb($m,$ep,$p=null){
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
    CURLOPT_TIMEOUT=>60
  ]);
  if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$http>=200&&$http<300,'data'=>is_array($d)?$d:[],'body'=>$b,'http'=>$http];
}

$msg='';
$uploadInfo='';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $title=trim($_POST['title']??'');
  $brand=$_POST['brand_pillar']??'discover_ct';
  $town=trim($_POST['town']??'');
  $notes=trim($_POST['notes']??'');
  $source_file=trim($_POST['existing_file']??'');
  $source_url='';

  if(!empty($_FILES['media_file']['name'])){
    $dir=__DIR__.'/../uploads/media/raw';
    if(!is_dir($dir)) mkdir($dir,0755,true);

    $original=basename($_FILES['media_file']['name']);
    $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
    $allowed=['mp4','mov','m4v','avi','webm','mkv','mp3','wav','m4a','aac','aiff','txt','srt','vtt','pdf','doc','docx'];
    if(!in_array($ext,$allowed,true)){
      $msg='Upload blocked: unsupported file type.';
    } elseif(!empty($_FILES['media_file']['error'])){
      $msg='Upload failed. PHP upload error code: '.$_FILES['media_file']['error'];
    } else {
      $safe=preg_replace('/[^a-zA-Z0-9._-]/','_',pathinfo($original,PATHINFO_FILENAME));
      $source_file=date('Ymd_His').'_'.$safe.'.'.$ext;
      $target=$dir.'/'.$source_file;
      if(move_uploaded_file($_FILES['media_file']['tmp_name'],$target)){
        @chmod($target,0644);
        $source_url='/uploads/media/raw/'.$source_file;
        $uploadInfo='Uploaded to '.$source_url;
      } else {
        $msg='Upload failed while moving file to /uploads/media/raw/. Check folder permissions.';
      }
    }
  } elseif($source_file){
    $source_url='/uploads/media/raw/'.basename($source_file);
  }

  if(!$msg && $title){
    $r=sb('POST','media_projects',[[
      'title'=>$title,
      'brand_pillar'=>$brand,
      'town'=>$town,
      'source_file'=>basename($source_file),
      'source_url'=>$source_url,
      'notes'=>$notes,
      'status'=>'uploaded',
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ]]);
    $msg=$r['ok']?'Media project saved. '.$uploadInfo:'Supabase error: '.$r['body'];
  } elseif(!$title && !$msg) {
    $msg='Please enter a title.';
  }
}

$projects=sb('GET','media_projects?select=*&order=created_at.desc&limit=200')['data'];
$clips=sb('GET','media_clip_plans?select=*&order=viral_score.desc,created_at.desc&limit=300')['data'];
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'YOUR_KEY';

$rawFiles=[];
$dir=__DIR__.'/../uploads/media/raw';
if(is_dir($dir)){
  foreach(array_reverse(glob($dir.'/*')) as $f){
    if(is_file($f)) $rawFiles[]=basename($f);
    if(count($rawFiles)>=200) break;
  }
}
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Jessica Media Director</title><style>
body{margin:0;background:#f5f3ef;color:#111;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.header{background:#111827;color:white;padding:30px}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:38px}.wrap{max-width:1800px;margin:auto;padding:24px}.panel{background:#fff;border-radius:16px;box-shadow:0 2px 12px #0001;margin-top:18px;overflow:hidden}.panel h2{font-family:Georgia,serif;margin:0;padding:18px;border-bottom:1px solid #eee}.inner{padding:16px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.btn{border:0;background:#c8a96e;color:#111;padding:10px 13px;border-radius:9px;font-weight:900;font-size:12px;margin:2px;cursor:pointer;text-decoration:none;display:inline-block}.light{background:#f2efe8}input,select,textarea{width:100%;padding:11px;border:1px solid #ddd;border-radius:10px;margin:6px 0 12px;font-size:14px;box-sizing:border-box}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid #eee;vertical-align:top;font-size:14px}th{font-size:11px;color:#777;text-transform:uppercase;background:#faf9f6}.score{font-size:28px;font-weight:900;color:#c8a96e}.muted{color:#777;font-size:13px}pre{white-space:pre-wrap;background:#111;color:#fff;padding:12px;border-radius:10px;max-height:260px;overflow:auto}.notice{background:#fff8e8;border-left:5px solid #c8a96e;padding:14px;border-radius:10px}@media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{padding:14px}}</style></head><body>
<div class="header"><div class="brand">V18.4 Jessica Media Director</div><div>Uploads raw video/audio directly to Hostinger: /uploads/media/raw/</div></div>
<main class="wrap">
<p><a class="btn" target="_blank" href="/lead-engine/build-media-director.php?key=<?=h($key)?>">Run Jessica Media Director</a><a class="btn light" href="/dashboard/jessica-shorts-factory.php">Shorts Factory</a><a class="btn light" href="/dashboard/jessica-content-intelligence.php">Content Intelligence</a><a class="btn light" href="/commandcenter.php">Goliath OS</a></p>
<?php if($msg):?><div class="panel"><div class="inner notice"><?=h($msg)?></div></div><?php endif;?>
<div class="grid">
<section class="panel"><h2>Upload Raw Media To Hostinger</h2><div class="inner"><form method="post" enctype="multipart/form-data">
<input type="hidden" name="MAX_FILE_SIZE" value="1073741824">
<label>Title</label><input name="title" placeholder="Discover CT — Best Pizza in Fairfield">
<label>Brand Pillar</label><select name="brand_pillar"><option value="discover_ct">Discover CT</option><option value="house_detective">House Detective</option><option value="seller_authority">Seller Authority</option><option value="buyer_authority">Buyer Authority</option><option value="beatseat">BeatSeat</option></select>
<label>Town</label><input name="town" placeholder="Fairfield">
<label>Upload Raw Video / Audio / Transcript</label><input type="file" name="media_file" accept="video/*,audio/*,.txt,.srt,.vtt,.pdf,.doc,.docx">
<div class="muted">Destination: /public_html/uploads/media/raw/. For very large files, upload via Hostinger File Manager/SFTP and select below.</div>
<label>Or use an existing raw file</label><select name="existing_file"><option value="">Choose existing uploaded file...</option><?php foreach($rawFiles as $f):?><option value="<?=h($f)?>"><?=h($f)?></option><?php endforeach;?></select>
<label>Notes / Transcript / Best Moments</label><textarea name="notes" rows="8" placeholder="Paste transcript notes, best moments, location, story angle, CTA idea..."></textarea>
<button class="btn">Save Media Project</button></form></div></section>
<section class="panel"><h2>Storage Setup</h2><div class="inner"><pre>Raw media path:
/public_html/uploads/media/raw/

Public URL:
/uploads/media/raw/filename.mp4

Recommended for large files:
1. Upload by Hostinger File Manager or SFTP.
2. Return here.
3. Select file from Existing Raw File.
4. Save project.
5. Run Media Director → Shorts Factory → Content Intelligence.

Database stores:
filename + URL only

Video file stays on Hostinger storage.</pre></div></section>
</div>
<section class="panel"><h2>Raw Files On Hostinger</h2><div class="inner"><?php foreach($rawFiles as $f):?><div><a href="/uploads/media/raw/<?=h($f)?>" target="_blank"><?=h($f)?></a></div><?php endforeach;?></div></section>
<section class="panel"><h2>Media Projects</h2><table><tr><th>Score</th><th>Project</th><th>Brand</th><th>Status</th><th>Source</th><th>Jessica Angle</th></tr><?php foreach($projects as $p):?><tr><td><div class="score"><?=h($p['viral_score']??0)?></div></td><td><strong><?=h($p['title'])?></strong><div class="muted"><?=h($p['town'])?></div></td><td><?=h($p['brand_pillar'])?></td><td><?=h($p['status'])?></td><td><?php if(!empty($p['source_url'])):?><a target="_blank" href="<?=h($p['source_url'])?>"><?=h($p['source_file'])?></a><?php else:?><?=h($p['source_file'])?><?php endif;?></td><td><?=h($p['recommended_angle'])?><div class="muted"><?=h($p['recommended_cta'])?></div></td></tr><?php endforeach;?></table></section>
<section class="panel"><h2>Clip Plans</h2><table><tr><th>Score</th><th>Clip</th><th>Hook</th><th>Style</th><th>CTA</th></tr><?php foreach($clips as $c):?><tr><td><div class="score"><?=h($c['viral_score'])?></div></td><td><strong><?=h($c['clip_title'])?></strong><div class="muted"><?=h($c['clip_type'])?> / <?=h($c['status'])?><br><?=h($c['start_seconds'])?>s - <?=h($c['end_seconds'])?>s</div></td><td><?=h($c['hook'])?></td><td><?=h($c['caption_style'])?><div class="muted"><?=h($c['visual_style'])?><br>Ken Burns: <?=!empty($c['ken_burns'])?'yes':'no'?></div></td><td><?=h($c['cta_text'])?></td></tr><?php endforeach;?></table></section>
</main></body></html>