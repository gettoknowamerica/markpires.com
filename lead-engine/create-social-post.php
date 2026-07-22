<?php
require_once __DIR__.'/social/social-core.php';
header('Content-Type: application/json');
if(!gds_key_ok()){http_response_code(403); echo json_encode(['success'=>false,'error'=>'bad key']); exit;}
$raw=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$title=trim($raw['title']??'Goliath Post'); $caption=$raw['caption']??''; $description=$raw['description']??''; $cta=$raw['cta']??'Call or text Mark Pires at 203-247-2655.';
$platforms=$raw['platforms']??['facebook','instagram','linkedin','youtube','tiktok','google-business']; if(is_string($platforms)) $platforms=array_map('trim',explode(',',$platforms));
$when=$raw['scheduled_at']??gmdate('c',time()+900);
$post=gds_sb('POST','social_posts',[['title'=>$title,'caption'=>$caption,'description'=>$description,'cta'=>$cta,'status'=>'queued','source_agent'=>$raw['source_agent']??'Goliath','review_required'=>true]]);
$pid=$post['data'][0]['id']??null; $made=[];
if($pid){ foreach($platforms as $p){
  $pp=gds_sb('POST','social_post_platforms',[['post_id'=>$pid,'platform'=>$p,'platform_caption'=>$caption,'platform_status'=>'queued','scheduled_at'=>$when]]); $ppid=$pp['data'][0]['id']??null;
  if($ppid){ gds_sb('POST','social_calendar',[['post_platform_id'=>$ppid,'scheduled_at'=>$when,'slot_label'=>$title,'status'=>'scheduled','priority'=>50]]); $made[]=$p; }
}}
echo json_encode(['success'=>!!$pid,'post_id'=>$pid,'platforms'=>$made]);
