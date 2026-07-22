<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
require_once __DIR__ . '/includes/goliath-ui.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/');exit;}
if(file_exists(__DIR__.'/includes/goliath-nav.php')) require_once __DIR__.'/includes/goliath-nav.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sb($m,$ep,$p=null){
 $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($ep,'/'));
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'],CURLOPT_TIMEOUT=>45]);
 if($p!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($p));
 $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$d=json_decode($b,true);
 return ['ok'=>$http>=200&&$http<300,'data'=>is_array($d)?$d:[],'body'=>$b];
}
function scan_dir_assets($dir,$urlBase,$type){
 $out=[]; if(is_dir($dir)){foreach(array_reverse(glob($dir.'/*')) as $f){if(is_file($f))$out[]=['name'=>basename($f),'url'=>$urlBase.'/'.basename($f),'size'=>filesize($f),'type'=>$type];}} return $out;
}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_project'){
 $logos=[]; for($i=1;$i<=4;$i++){ if(!empty($_POST['logo'.$i])) $logos[]=['url'=>$_POST['logo'.$i],'position'=>$_POST['logo'.$i.'_pos']??'top-right','opacity'=>$_POST['logo'.$i.'_opacity']??'1'];}
 $payload=[[
  'project_name'=>$_POST['project_name'] ?: 'Jessica Media Project '.date('M j H:i'),
  'raw_file_url'=>$_POST['raw_file_url']??'',
  'project_type'=>$_POST['project_type']??'shorts',
  'status'=>'draft',
  'director_notes'=>$_POST['director_notes']??'',
  'hook_notes'=>$_POST['hook_notes']??'',
  'caption_notes'=>$_POST['caption_notes']??'',
  'cta_notes'=>$_POST['cta_notes']??'',
  'logo_layers'=>$logos,
  'thumbnail_prompt'=>$_POST['thumbnail_prompt']??'',
  'canva_status'=>'needs_api',
  'created_at'=>date('c'),
  'updated_at'=>date('c')
 ]];
 $r=sb('POST','media_projects',$payload);
 $msg=$r['ok']?'Project saved to Jessica Media Manager.':'Save failed: '.$r['body'];
}
$raw=scan_dir_assets(__DIR__.'/../uploads/media/raw','/uploads/media/raw','raw');
$logos=scan_dir_assets(__DIR__.'/../uploads/media/logos','/uploads/media/logos','logo');
$thumbs=scan_dir_assets(__DIR__.'/../uploads/media/thumbs','/uploads/media/thumbs','thumbnail');
$projects=sb('GET','media_projects?select=*&order=created_at.desc&limit=50');
$selected=$_GET['file']??($raw[0]['name']??'');
$selectedUrl=$selected?'/uploads/media/raw/'.$selected:'';
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Large Media Manager</title><style>
body{margin:0;background:#151515;color:#e5e7eb;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{background:#0b0b0b;border-bottom:1px solid #333;padding:14px 22px;display:flex;gap:18px;align-items:center}.brand{font-family:Georgia,serif;color:#c8a96e;font-size:28px;font-weight:900}.wrap{display:grid;grid-template-columns:260px 1fr 380px;gap:12px;padding:12px}.panel{background:#202020;border:1px solid #333;border-radius:14px;overflow:hidden}.panel h2{font-size:13px;text-transform:uppercase;letter-spacing:.08em;color:#aaa;margin:0;padding:12px 14px;border-bottom:1px solid #333}.inner{padding:14px}.viewer{background:#000;min-height:56vh;display:flex;align-items:center;justify-content:center;border-radius:12px;position:relative;overflow:hidden}.viewer video{width:100%;max-height:70vh;background:#000}.btn{background:#c8a96e;color:#111;border:0;border-radius:8px;padding:10px 12px;font-weight:900;cursor:pointer;margin:3px}.btn.dark{background:#333;color:white}.asset{display:block;color:#ddd;text-decoration:none;padding:9px 12px;border-bottom:1px solid #333;font-size:13px}.asset:hover{background:#2b2b2b}input,textarea,select{width:100%;box-sizing:border-box;background:#111;color:#eee;border:1px solid #444;border-radius:8px;padding:9px;margin:5px 0 10px}.timeline{height:80px;background:#111;border:1px solid #333;border-radius:10px;margin-top:10px;padding:10px;color:#888}.logo-preview{position:absolute;max-width:120px;max-height:70px;opacity:.9}.top-right{right:20px;top:20px}.top-left{left:20px;top:20px}.bottom-right{right:20px;bottom:20px}.bottom-left{left:20px;bottom:20px}.msg{background:#12351f;border-left:4px solid #22c55e;padding:10px;margin:10px;border-radius:8px}@media(max-width:1100px){.wrap{grid-template-columns:1fr}.viewer{min-height:35vh}}</style><link rel="stylesheet" href="/dashboard/assets/goliath-os.css?v=4"><link rel="icon" href="/dashboard/assets/goliath-ai-full-logo.png?v=4"><?php goliath_ui_head(); ?></head><body><?php goliath_ui_open(); ?>
<div class="top"><div class="brand">Goliath Media Manager</div><a class="btn" href="/dashboard/large-media-upload.php">Large Upload</a><a class="btn" href="/dashboard/video-review-studio.php">Review Studio</a><a class="btn" href="/dashboard/jessica-shorts-factory.php">Shorts Factory</a></div>
<?php if($msg):?><div class="msg"><?=h($msg)?></div><?php endif;?>
<div class="wrap">
<aside class="panel"><h2>Raw Media</h2><div><?php foreach($raw as $a):?><a class="asset" href="?file=<?=h($a['name'])?>">🎬 <?=h($a['name'])?><br><small><?=number_format($a['size']/1048576,1)?> MB</small></a><?php endforeach;?></div><h2>Logos</h2><div><?php foreach($logos as $a):?><div class="asset">🖼 <?=h($a['name'])?></div><?php endforeach;?></div></aside>
<main class="panel"><h2>Black Screen Preview / Editor Foundation</h2><div class="inner">
<div class="viewer" id="viewer"><?php if($selectedUrl):?><video id="vid" controls preload="metadata" src="<?=h($selectedUrl)?>"></video><?php else:?><div>No raw media found. Use Large Upload first.</div><?php endif;?></div>
<div><button class="btn" onclick="mark('start')">Mark Start</button><button class="btn" onclick="mark('end')">Mark End</button><button class="btn dark" onclick="skip(-5)">-5 sec</button><button class="btn dark" onclick="skip(5)">+5 sec</button><button class="btn dark" onclick="toggleLogos()">Toggle Logos</button></div>
<div class="timeline">Timeline foundation: clip markers, captions, logo layers, thumbnail prompts, and render jobs will build from this project record.</div>
</div></main>
<aside class="panel"><h2>Project Controls</h2><div class="inner"><form method="post"><input type="hidden" name="action" value="save_project"><input type="hidden" name="raw_file_url" value="<?=h($selectedUrl)?>">
<label>Project Name</label><input name="project_name" value="<?=h(pathinfo($selected,PATHINFO_FILENAME))?>">
<label>Project Type</label><select name="project_type"><option>shorts</option><option>discover_ct</option><option>house_detective</option><option>listing</option><option>ad</option></select>
<label>Start / End</label><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><input id="start" name="start_time" placeholder="start"><input id="end" name="end_time" placeholder="end"></div>
<label>Jessica Director Notes</label><textarea name="director_notes" rows="4" placeholder="What should Jessica do with this?"></textarea>
<label>Hook Notes</label><textarea name="hook_notes" rows="3"></textarea>
<label>Caption Notes / Corrections</label><textarea name="caption_notes" rows="4"></textarea>
<label>CTA Notes</label><textarea name="cta_notes" rows="3" placeholder="Call/text Mark..."></textarea>
<label>Logo Layer 1</label><select name="logo1" id="logo1" onchange="refreshLogos()"><option value="">None</option><?php foreach($logos as $l):?><option value="<?=h($l['url'])?>"><?=h($l['name'])?></option><?php endforeach;?></select><select name="logo1_pos"><option>top-right</option><option>top-left</option><option>bottom-right</option><option>bottom-left</option></select>
<label>Logo Layer 2</label><select name="logo2" id="logo2" onchange="refreshLogos()"><option value="">None</option><?php foreach($logos as $l):?><option value="<?=h($l['url'])?>"><?=h($l['name'])?></option><?php endforeach;?></select><select name="logo2_pos"><option>bottom-left</option><option>top-right</option><option>top-left</option><option>bottom-right</option></select>
<label>Thumbnail / Canva Prompt</label><textarea name="thumbnail_prompt" rows="4" placeholder="Create a dramatic House Detective thumbnail..."></textarea>
<button class="btn" style="width:100%">Save Project For Jessica</button></form></div></aside>
</div>
<section class="panel" style="margin:12px"><h2>Recent Projects</h2><div class="inner"><?php foreach(($projects['data']??[]) as $p):?><div style="border-bottom:1px solid #333;padding:10px"><strong><?=h($p['project_name'])?></strong> — <?=h($p['project_type'])?> — <?=h($p['status'])?><br><small><?=h($p['director_notes'])?></small></div><?php endforeach;?></div></section>
<script>
const vid=document.getElementById('vid'), viewer=document.getElementById('viewer');let logosOn=true;
function mark(id){if(vid)document.getElementById(id).value=(Math.round(vid.currentTime*10)/10);}
function skip(s){if(vid)vid.currentTime=Math.max(0,vid.currentTime+s);}
function refreshLogos(){document.querySelectorAll('.logo-preview').forEach(x=>x.remove());['logo1','logo2'].forEach((id,i)=>{let el=document.getElementById(id);if(el&&el.value){let img=document.createElement('img');img.src=el.value;img.className='logo-preview '+(i===0?'top-right':'bottom-left');viewer.appendChild(img);}});}
function toggleLogos(){logosOn=!logosOn;document.querySelectorAll('.logo-preview').forEach(x=>x.style.display=logosOn?'block':'none');}
</script><?php goliath_ui_close(); ?></body></html>