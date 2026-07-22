<?php
require_once __DIR__.'/social/social-core.php';
header('Content-Type: application/json');
if(!gds_key_ok()){http_response_code(403); echo json_encode(['success'=>false,'error'=>'bad key']); exit;}
$items=gds_due_items(12); $out=[];
foreach($items as $item){
  $pp=$item['social_post_platforms']??[]; $platform=strtolower((string)($pp['platform']??'')); $file=__DIR__.'/social/'.str_replace('_','-',preg_replace('/[^a-z0-9_-]/','',$platform)).'.php';
  if(is_file($file)) require_once $file; else require_once __DIR__.'/social/social-core.php';
  $res=function_exists('gds_platform_publish')?gds_platform_publish($item):gds_draft_publish($item);
  $ppid=$pp['id']??null; $calid=$item['id']??null;
  if(!empty($res['ok'])){
    gds_log($ppid,$platform,'publish',!empty($res['draft'])?'ready_for_review':'published',$res,'');
    gds_mark_platform($ppid,['platform_status'=>!empty($res['draft'])?'ready_for_review':'published','published_at'=>!empty($res['draft'])?null:gmdate('c')]);
    gds_mark_calendar($calid,!empty($res['draft'])?'ready_for_review':'published');
  } else {
    gds_log($ppid,$platform,'publish','failed',$res,$res['message']??'failed');
    gds_mark_platform($ppid,['platform_status'=>'failed','last_error'=>$res['message']??'failed','attempts'=>(int)($pp['attempts']??0)+1]);
    gds_mark_calendar($calid,'failed');
  }
  $out[]=['calendar_id'=>$calid,'platform'=>$platform,'result'=>$res['message']??'processed'];
}
echo json_encode(['success'=>true,'processed'=>count($out),'items'=>$out]);
