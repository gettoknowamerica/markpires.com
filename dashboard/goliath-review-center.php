<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/../lead-engine/config.php';
require_once __DIR__.'/../lead-engine/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){
 header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-review-center.php'));exit;
}
function h1165($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function r1165_all($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];}}
function r1165_one($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];}}

$id=max(0,(int)($_GET['artifact_id']??0));
$exec=strtolower(trim((string)($_GET['exec']??'')));
$embed=(string)($_GET['embed']??'')==='1';

$filter="a.status IN ('ready_for_founder_review','review','approved','published')";
$params=[];
if($exec!==''){$filter.=" AND (LOWER(a.executive_key)=? OR LOWER(m.originator_key)=?)";$params=[$exec,$exec];}

$items=r1165_all(
 "SELECT a.id,a.mission_id,a.stage_id,a.executive_key,a.artifact_type,a.title,a.content_text,a.content_html,
         a.artifact_url,a.artifact_path,a.status,a.created_at,a.metadata_json,m.originator_key,m.title mission_title
  FROM goliath_v112_artifacts a LEFT JOIN goliath_v112_missions m ON m.id=a.mission_id
  WHERE $filter ORDER BY a.id DESC LIMIT 100",$params
);

if($id<1&&!empty($items))$id=(int)$items[0]['id'];

$selected=$id?r1165_one(
 "SELECT a.*,m.title mission_title,m.originator_key,s.stage_key,s.input_artifact_id
  FROM goliath_v112_artifacts a
  LEFT JOIN goliath_v112_missions m ON m.id=a.mission_id
  LEFT JOIN goliath_v112_stages s ON s.id=a.stage_id
  WHERE a.id=? LIMIT 1",[$id]
):[];

// Safety net for older records: if a Goliath final artifact is merely an overview,
// show the approved input artifact as the primary deliverable.
if($selected&&($selected['stage_key']??'')==='goliath_publish_deliver'&&!empty($selected['input_artifact_id'])){
 $visibleLength=mb_strlen(trim(strip_tags((string)($selected['content_html']?:$selected['content_text']))));
 $looksLikeOverview=preg_match('/executive overview|closing package|mission summary|thank you letter/i',(string)($selected['content_text']??''));
 if($visibleLength<500||$looksLikeOverview){
  $approved=r1165_one("SELECT * FROM goliath_v112_artifacts WHERE id=? LIMIT 1",[(int)$selected['input_artifact_id']]);
  if($approved){
   foreach(['artifact_type','title','content_text','content_html','artifact_url','artifact_path','evidence_json'] as $field){
    if(array_key_exists($field,$approved))$selected[$field]=$approved[$field];
   }
   $selected['displaying_approved_source']=true;
  }
 }
}
?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta charset="utf-8">
<title>Goliath Review Center</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33">
<style>
body{margin:0;background:#02050b;color:#fff}.reviewShell{display:grid;grid-template-columns:330px minmax(0,1fr);gap:14px;padding:14px}
.reviewList,.reviewMain{background:#07111f;border:1px solid #29445f;border-radius:18px;overflow:hidden}
.reviewHead{padding:14px;border-bottom:1px solid #ffffff18}.reviewHead h1,.reviewHead h2{margin:0;color:#f3cd68}
.reviewItems{max-height:calc(100vh - 120px);overflow:auto}.reviewItem{display:block;padding:12px;border-bottom:1px solid #ffffff12;color:#fff;text-decoration:none}
.reviewItem:hover,.reviewItem.active{background:#101d30}.reviewItem b{display:block}.reviewItem small{color:#9fb0c7}
.asset{padding:18px;max-height:calc(100vh - 110px);overflow:auto}.asset h1{color:#f3cd68}.meta{color:#94a3b8}
.content{line-height:1.65;font-size:16px}.content img,.content video,.content iframe{max-width:100%;height:auto}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}.actions a{padding:9px 12px;border-radius:10px;background:#8a6514;color:#fff;text-decoration:none;font-weight:900}
.sourceNotice{padding:9px 12px;background:#12351f;border:1px solid #2aa75d;border-radius:10px;margin:10px 0;color:#bfffd2}
body.embed .reviewShell{display:block;padding:0}.embed .reviewList{display:none}.embed .reviewMain{border:0;border-radius:0;min-height:100vh}.embed .reviewHead{padding:10px 14px}.embed .asset{max-height:none;padding:14px}
@media(max-width:760px){.reviewShell{grid-template-columns:1fr;padding:8px}.reviewItems{max-height:230px}.asset{max-height:none}}
</style></head><body class="<?=$embed?'embed':''?>">
<div class="reviewShell">
<aside class="reviewList"><div class="reviewHead"><h2>Ready for Review</h2><small><?=count($items)?> completed assets</small></div><div class="reviewItems">
<?php foreach($items as $item): ?>
<a class="reviewItem <?=$id===(int)$item['id']?'active':''?>" href="/dashboard/goliath-review-center.php?artifact_id=<?=(int)$item['id']?><?= $exec!==''?'&exec='.rawurlencode($exec):'' ?>">
<b><?=h1165($item['title']?:$item['mission_title']?:'Completed asset')?></b>
<small><?=h1165(ucfirst($item['originator_key']?:$item['executive_key']))?> · <?=h1165($item['artifact_type'])?> · #<?=(int)$item['id']?></small>
</a>
<?php endforeach; ?>
<?php if(!$items):?><div style="padding:18px">No completed assets are waiting for review.</div><?php endif;?>
</div></aside>
<main class="reviewMain">
<?php if($selected): ?>
<div class="reviewHead"><h1><?=h1165($selected['title']?:$selected['mission_title']?:'Completed asset')?></h1>
<div class="meta"><?=h1165(ucfirst($selected['originator_key']?:$selected['executive_key']))?> · <?=h1165($selected['artifact_type'])?> · Ready for review</div></div>
<article class="asset">
<?php if(!empty($selected['displaying_approved_source'])):?><div class="sourceNotice">Displaying the originator-approved deliverable. Goliath’s overview has been kept as supporting metadata only.</div><?php endif;?>
<?php if(!$embed):?><div class="actions">
<a href="/dashboard/goliath-mission-control.php">Mission Control</a>
<?php if(!empty($selected['artifact_url'])):?><a target="_blank" href="<?=h1165($selected['artifact_url'])?>">Open Published Output</a><?php endif;?>
<?php if(!empty($selected['artifact_path'])):?><a target="_blank" href="<?=h1165($selected['artifact_path'])?>">Open Deliverable File</a><?php endif;?>
</div><?php endif;?>
<div class="content">
<?php if(!empty($selected['content_html'])): echo $selected['content_html']; else: ?>
<?=nl2br(h1165($selected['content_text']??''))?>
<?php endif;?>
</div>
</article>
<?php else: ?>
<div class="reviewHead"><h1>No completed asset selected</h1></div>
<?php endif;?>
</main>
</div></body></html>