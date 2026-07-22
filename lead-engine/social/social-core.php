<?php
require_once __DIR__ . '/../config.php';
function gds_h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function gds_key_ok(){ $k=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts'; return isset($_GET['key']) && hash_equals($k,(string)$_GET['key']); }
function gds_sb($method,$endpoint,$payload=null){
  $url=rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/');
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
  $ch=curl_init($url); $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>35,CURLOPT_CUSTOMREQUEST=>$method];
  if($payload!==null){$opts[CURLOPT_POSTFIELDS]=json_encode($payload);} curl_setopt_array($ch,$opts);
  $body=curl_exec($ch); $err=curl_error($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $data=json_decode($body,true); return ['code'=>$code,'error'=>$err,'body'=>$body,'data'=>is_array($data)?$data:[]];
}
function gds_due_items($limit=10){
  $now=urlencode(gmdate('c'));
  $ep="social_calendar?select=*,social_post_platforms(*,social_posts(*))&status=eq.scheduled&scheduled_at=lte.$now&order=scheduled_at.asc&limit=".(int)$limit;
  return gds_sb('GET',$ep)['data'];
}
function gds_log($ppid,$platform,$action,$status,$response=[],$error=''){
  return gds_sb('POST','social_publish_logs',[['post_platform_id'=>$ppid,'platform'=>$platform,'action'=>$action,'status'=>$status,'response'=>$response,'error'=>$error]]);
}
function gds_mark_calendar($id,$status){return gds_sb('PATCH','social_calendar?id=eq.'.rawurlencode($id),['status'=>$status,'updated_at'=>gmdate('c')]);}
function gds_mark_platform($id,$fields){$fields['updated_at']=gmdate('c'); return gds_sb('PATCH','social_post_platforms?id=eq.'.rawurlencode($id),$fields);}
function gds_draft_publish($item){
  $pp=$item['social_post_platforms']??[]; $post=$pp['social_posts']??[]; $platform=$pp['platform']??'unknown';
  $caption=$pp['platform_caption'] ?: ($post['caption']??'');
  $payload=['title'=>$post['title']??'', 'caption'=>$caption, 'description'=>$post['description']??'', 'cta'=>$post['cta']??'', 'tags'=>$post['tags']??[], 'platform'=>$platform, 'mode'=>defined('GOLIATH_SOCIAL_LIVE') && GOLIATH_SOCIAL_LIVE ? 'live':'draft'];
  if(!(defined('GOLIATH_SOCIAL_LIVE') && GOLIATH_SOCIAL_LIVE)) return ['ok'=>true,'draft'=>true,'payload'=>$payload,'message'=>'Draft mode: queued as ready, not posted live.'];
  return ['ok'=>false,'draft'=>false,'payload'=>$payload,'message'=>'Live connector not configured for '.$platform];
}
