<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';

if(empty($_SESSION['mp_dashboard_auth'])){
 header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-workflow-review-v118-1.php'));
 exit;
}

function w1181_h($value):string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function w1181_one(string $sql,array $params=[]):array{try{return gdb_one($sql,$params)?:[];}catch(Throwable $e){return [];}}
function w1181_all(string $sql,array $params=[]):array{try{return gdb_all($sql,$params)?:[];}catch(Throwable $e){return [];}}
function w1181_json($raw):array{
 if(is_array($raw))return $raw;
 $decoded=json_decode(trim((string)$raw),true);
 return is_array($decoded)?$decoded:[];
}
function w1181_pick(array $data,array $paths){
 foreach($paths as $path){
  $current=$data;
  foreach(explode('.',$path) as $part){
   if(!is_array($current)||!array_key_exists($part,$current)){continue 2;}
   $current=$current[$part];
  }
  if(is_string($current)&&trim($current)!=='')return $current;
  if(is_array($current)&&$current)return json_encode($current,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
 }
 return '';
}
function w1181_extract(array $artifact):array{
 $html=trim((string)($artifact['content_html']??''));
 $raw=trim((string)($artifact['content_text']??''));
 $json=w1181_json($raw);

 $work=w1181_pick($json,[
  'asset.work_product','asset.content_html','asset.content_text','work_product',
  'deliverable','final_deliverable','content_html','content_text','output','answer',
  'body','article','blog','script','email','campaign','result'
 ]);
 $change=w1181_pick($json,[
  'change_note','changes_made','improvements','summary','handoff_notes',
  'executive_notes','what_i_added','next_action'
 ]);

 if($html!=='')return ['html'=>$html,'text'=>'','change'=>$change,'raw'=>$raw];
 if($work!=='')return ['html'=>'','text'=>$work,'change'=>$change,'raw'=>$raw];
 return ['html'=>'','text'=>$raw,'change'=>$change,'raw'=>$raw];
}
function w1181_tangible(array $view):bool{
 return mb_strlen(trim(strip_tags($view['html']!==''?$view['html']:$view['text'])))>=120;
}

$artifactId=max(0,(int)($_GET['artifact_id']??0));
$missionId=max(0,(int)($_GET['mission_id']??0));
$stageNo=max(0,(int)($_GET['stage']??0));
$embed=(string)($_GET['embed']??'')==='1';

if($missionId<1&&$artifactId>0){
 $row=w1181_one("SELECT mission_id FROM goliath_v112_artifacts WHERE id=? LIMIT 1",[$artifactId]);
 $missionId=(int)($row['mission_id']??0);
}
$mission=$missionId?w1181_one("SELECT * FROM goliath_v112_missions WHERE id=? LIMIT 1",[$missionId]):[];

$stages=$missionId?w1181_all(
 "SELECT s.*,a.id artifact_id,a.artifact_type,a.title artifact_title,a.content_html,a.content_text,
         a.artifact_url,a.artifact_path,a.status artifact_status,a.created_at artifact_created
  FROM goliath_v112_stages s
  LEFT JOIN goliath_v112_artifacts a ON a.id=s.output_artifact_id
  WHERE s.mission_id=?
  ORDER BY s.stage_no ASC",[$missionId]
):[];

if($stageNo<1){
 if($artifactId>0){
  foreach($stages as $stage)if((int)($stage['artifact_id']??0)===$artifactId){$stageNo=(int)$stage['stage_no'];break;}
 }
 if($stageNo<1){
  foreach(array_reverse($stages) as $stage){
   if(!empty($stage['artifact_id'])){$stageNo=(int)$stage['stage_no'];break;}
  }
 }
}
$selected=[];
foreach($stages as $stage)if((int)$stage['stage_no']===$stageNo){$selected=$stage;break;}

$selectedView=$selected?w1181_extract($selected):['html'=>'','text'=>'','change'=>'','raw'=>''];
$displayView=$selectedView;
$fallbackStage=null;

// If an Executive returned only a brief/change note, keep the most recent tangible
// deliverable visible and show the current Executive's note separately.
if($selected&&!w1181_tangible($selectedView)){
 foreach(array_reverse($stages) as $candidate){
  if((int)$candidate['stage_no']>=(int)$selected['stage_no'])continue;
  if(empty($candidate['artifact_id']))continue;
  $candidateView=w1181_extract($candidate);
  if(w1181_tangible($candidateView)){
   $displayView=$candidateView;
   $fallbackStage=$candidate;
   break;
  }
 }
}

?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Goliath Professional Workflow Review</title>
<style>
:root{--gold:#d4af37;--ink:#07111f;--line:#27425e}
*{box-sizing:border-box}body{margin:0;background:#02050b;color:#eef5ff;font-family:Arial,sans-serif}
.workflow{display:grid;grid-template-columns:300px minmax(0,1fr);min-height:100vh}
.sidebar{background:#07111f;border-right:1px solid var(--line);overflow:auto;max-height:100vh}
.head{padding:14px;border-bottom:1px solid #ffffff18;position:sticky;top:0;background:#07111ff5;z-index:4}
.head h1,.head h2{margin:0;color:#f3d373}.head small{color:#9fb0c7}
.stage{display:block;padding:11px 12px;border-bottom:1px solid #ffffff12;color:#fff;text-decoration:none}
.stage:hover,.stage.active{background:#10213a}.stage b{display:block}.stage small{color:#9fb0c7}.stage em{float:right;font-style:normal;color:#f3d373}
.main{min-width:0;background:#050914}
.assetHead{padding:14px 18px;border-bottom:1px solid #ffffff18;background:#081322}
.assetHead h1{margin:0;color:#f3d373}.meta{color:#9fb0c7;margin-top:5px}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.actions a{color:#fff;background:#805f12;border-radius:10px;padding:8px 11px;text-decoration:none;font-weight:900}
.notice{margin:14px 18px 0;padding:10px 12px;border:1px solid #2aa75d;background:#0e2b1b;border-radius:10px;color:#c9ffdc}
.deliverable{padding:20px;line-height:1.65;font-size:16px;overflow:auto;max-height:calc(100vh - 120px)}
.deliverable img,.deliverable video,.deliverable iframe{max-width:100%;height:auto}
.deliverable pre{white-space:pre-wrap;font-family:inherit}
.change{margin:0 20px 20px;background:#111b2b;border:1px solid #3a526c;border-radius:14px;padding:14px}
.change h3{margin:0 0 8px;color:#f3d373}.raw{white-space:pre-wrap;color:#dbe8f8}
.empty{padding:30px;color:#9fb0c7}
body.embed .workflow{grid-template-columns:210px minmax(0,1fr)}
body.embed .head{padding:9px}.embed .stage{padding:8px;font-size:12px}.embed .assetHead{padding:9px 12px}.embed .deliverable{padding:12px;max-height:calc(100vh - 78px)}
@media(max-width:720px){.workflow,body.embed .workflow{grid-template-columns:1fr}.sidebar{max-height:190px;border-right:0;border-bottom:1px solid var(--line)}.assetHead h1{font-size:19px}.deliverable{max-height:none}}
</style>
</head>
<body class="<?=$embed?'embed':''?>">
<div class="workflow">
<aside class="sidebar">
 <div class="head">
  <h2><?=w1181_h($mission['title']??'Workflow Review')?></h2>
  <small>Mission #<?=w1181_h($missionId)?> · click any Executive version</small>
 </div>
 <?php foreach($stages as $stage): ?>
 <a class="stage <?=((int)$stage['stage_no']===$stageNo)?'active':''?>"
    href="/dashboard/goliath-workflow-review-v118-1.php?mission_id=<?=$missionId?>&stage=<?=(int)$stage['stage_no']?><?=$embed?'&embed=1':''?>">
  <em><?=w1181_h($stage['status'])?></em>
  <b><?=w1181_h((int)$stage['stage_no'].'. '.ucfirst((string)$stage['executive_key']))?></b>
  <small><?=w1181_h($stage['title']?:$stage['stage_key'])?><?=!empty($stage['artifact_id'])?' · deliverable #'.(int)$stage['artifact_id']:''?></small>
 </a>
 <?php endforeach;?>
</aside>
<main class="main">
 <?php if($selected): ?>
 <div class="assetHead">
  <h1><?=w1181_h($selected['artifact_title']?:$selected['title']?:'Executive deliverable')?></h1>
  <div class="meta"><?=w1181_h(ucfirst((string)$selected['executive_key']))?> · Stage <?=w1181_h($selected['stage_no'])?> · <?=w1181_h($selected['artifact_status']?:$selected['status'])?></div>
  <?php if(!$embed):?><div class="actions">
   <a href="/dashboard/goliath-mission-control.php">Mission Control</a>
   <?php if(!empty($selected['artifact_url'])):?><a target="_blank" href="<?=w1181_h($selected['artifact_url'])?>">Open Published Output</a><?php endif;?>
   <?php if(!empty($selected['artifact_path'])):?><a target="_blank" href="<?=w1181_h($selected['artifact_path'])?>">Open File</a><?php endif;?>
  </div><?php endif;?>
 </div>

 <?php if($fallbackStage):?>
 <div class="notice">
  <?=w1181_h(ucfirst((string)$selected['executive_key']))?> returned an enhancement note rather than a complete replacement.
  The last full deliverable from <?=w1181_h(ucfirst((string)$fallbackStage['executive_key']))?> remains visible below, and the current Executive’s addition appears separately.
 </div>
 <?php endif;?>

 <article class="deliverable">
  <?php if($displayView['html']!==''):?>
   <?=$displayView['html']?>
  <?php elseif(trim($displayView['text'])!==''):?>
   <pre><?=w1181_h($displayView['text'])?></pre>
  <?php else:?>
   <div class="empty">This stage has not produced a tangible deliverable yet.</div>
  <?php endif;?>
 </article>

 <?php
 $note=$selectedView['change'];
 if($fallbackStage&&$note==='')$note=$selectedView['raw'];
 ?>
 <?php if(trim($note)!==''):?>
 <section class="change">
  <h3>What <?=w1181_h(ucfirst((string)$selected['executive_key']))?> added or changed</h3>
  <div class="raw"><?=nl2br(w1181_h($note))?></div>
 </section>
 <?php endif;?>

 <?php else:?>
 <div class="empty">Choose a mission or completed artifact to review the professional workflow.</div>
 <?php endif;?>
</main>
</div>
</body>
</html>