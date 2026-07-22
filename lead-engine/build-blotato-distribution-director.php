<?php
ini_set('display_errors',0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key=$_GET['key']??'';
  if(!defined('AFTER_HOURS_CRON_KEY') || !hash_equals(AFTER_HOURS_CRON_KEY,$key)){
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb161($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_CUSTOMREQUEST=>$method,
      CURLOPT_HTTPHEADER=>[
        'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
        'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
      ],
      CURLOPT_TIMEOUT=>45
    ]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$h>=200&&$h<300,'http'=>$h,'body'=>$b,'data'=>is_array($d)?$d:[]];
  }
  function rows161($t,$q){$r=sb161('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
  function platforms161($brand,$type){
    if($brand==='house_detective') return ['Instagram','TikTok','YouTube Shorts','Facebook'];
    if($brand==='discover_ct') return ['Instagram','Facebook','TikTok','YouTube Shorts','LinkedIn'];
    if($type==='blog_share') return ['LinkedIn','Facebook','Google Business Profile'];
    return ['Instagram','Facebook','LinkedIn'];
  }
  function hashtags161($brand,$town=''){
    $tags=['#FairfieldCounty','#ConnecticutRealEstate','#MarkPires'];
    if($brand==='discover_ct') $tags=['#DiscoverCT','#Connecticut','#FairfieldCounty','#LocalStories'];
    if($brand==='house_detective') $tags=['#HouseDetective','#NoirRealtor','#ConnecticutRealEstate'];
    if($town) $tags[]='#'.preg_replace('/[^A-Za-z0-9]/','',$town);
    return implode(' ',$tags);
  }
  function makeRow161($sourceTable,$sourceId,$title,$brand,$type,$score,$caption,$mediaUrl,$imagePrompt,$raw,$town=''){
    $platforms=platforms161($brand,$type);
    $cta = ($brand==='discover_ct') ? 'Follow Discover CT for more local stories.' : 'Call or text Mark Pires at 203-247-2655.';
    if(strpos($caption,$cta)===false) $caption .= "\n\n".$cta;
    return [
      'queue_date'=>date('Y-m-d'),
      'source_table'=>$sourceTable,
      'source_id'=>(string)$sourceId,
      'distribution_title'=>$title,
      'brand_pillar'=>$brand,
      'content_type'=>$type,
      'platforms'=>$platforms,
      'caption'=>$caption,
      'hashtags'=>hashtags161($brand,$town),
      'cta'=>$cta,
      'media_url'=>$mediaUrl,
      'media_file_path'=>'',
      'image_prompt'=>$imagePrompt,
      'landing_page_url'=>($type==='ad'?'https://markpires.com/home-valuation.html':'https://markpires.com/'),
      'scheduled_for'=>null,
      'priority_score'=>(int)$score,
      'distribution_score'=>(int)$score,
      'approval_status'=>'review',
      'distribution_status'=>'draft',
      'blotato_payload'=>[
        'title'=>$title,'caption'=>$caption,'platforms'=>$platforms,'media_url'=>$mediaUrl,
        'hashtags'=>hashtags161($brand,$town),'cta'=>$cta
      ],
      'notes'=>'Created by V16.1 Blotato Distribution Director.',
      'raw_payload'=>$raw,
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
  }

  $existing=rows161('blotato_distribution_queue','select=source_table,source_id,distribution_title&limit=5000');
  $seen=[];
  foreach($existing as $e){$seen[strtolower(($e['source_table']??'').':'.($e['source_id']??'').':'.($e['distribution_title']??''))]=true;}

  $new=[];

  $jobs=rows161('creative_generation_jobs','select=*&status=in.(queued,generated,approved)&order=generation_score.desc,created_at.desc&limit=200');
  foreach($jobs as $j){
    $title=$j['job_name'] ?: ($j['headline'] ?: 'Jessica Creative Post');
    $sid=$j['id']??'';
    $k=strtolower('creative_generation_jobs:'.$sid.':'.$title);
    if(isset($seen[$k])) continue;
    $brand=$j['brand_pillar']??'mark_pires';
    $type=(($j['job_type']??'')==='ad_graphic')?'ad':'social_post';
    $caption=trim(($j['headline']?:$title)."\n\n".($j['prompt']??''));
    $media=$j['generated_image_url'] ?: ($j['upload_1']??'');
    $new[]=makeRow161('creative_generation_jobs',$sid,$title,$brand,$type,(int)($j['generation_score']??75),$caption,$media,$j['enhanced_prompt']??($j['prompt']??''),$j,$j['town']??'');
  }

  $cmds=rows161('campaign_command_center','select=*&status=eq.active&command_stage=in.(launch_today,generate_creative,distribute)&order=command_score.desc&limit=150');
  foreach($cmds as $c){
    $title=$c['campaign_name'] ?: 'Campaign Distribution Item';
    $sid=$c['id']??'';
    $k=strtolower('campaign_command_center:'.$sid.':'.$title);
    if(isset($seen[$k])) continue;
    $brand=$c['brand_pillar']??'seller_authority';
    $caption=trim(($c['recommended_daily_action']??$title)."\n\n".$title);
    $new[]=makeRow161('campaign_command_center',$sid,$title,$brand,'ad',(int)($c['command_score']??75),$caption,'',$c['recommended_creative_request']??'',$c,$c['target_town']??'');
  }

  $mine=rows161('content_mine_assets','select=*&status=eq.active&total_content_mine_score=gte.78&order=total_content_mine_score.desc&limit=100');
  foreach($mine as $m){
    $title=$m['recommended_title'] ?: ($m['original_title'] ?: 'Content Mine Post');
    $sid=$m['id']??'';
    $k=strtolower('content_mine_assets:'.$sid.':'.$title);
    if(isset($seen[$k])) continue;
    $brand=$m['brand_pillar']??'mark_pires';
    $use=$m['recommended_use']??'social_post';
    $type=($use==='short')?'short':(($use==='ad')?'ad':(($use==='blog')?'blog_share':'social_post'));
    $caption=$m['recommended_caption'] ?: (($m['recommended_hook']??'')."\n\n".$title);
    $new[]=makeRow161('content_mine_assets',$sid,$title,$brand,$type,(int)($m['total_content_mine_score']??80),$caption,$m['source_url']??'',$m['recommended_hook']??'',$m,$m['town']??'');
  }

  usort($new,function($a,$b){return $b['distribution_score']<=>$a['distribution_score'];});
  $inserted=[];$errors=[];
  foreach(array_chunk(array_slice($new,0,250),50) as $chunk){
    $keys=array_keys($chunk[0]);
    $norm=[];
    foreach($chunk as $row){$clean=[]; foreach($keys as $k){$clean[$k]=$row[$k]??null;} $norm[]=$clean;}
    $r=sb161('POST','blotato_distribution_queue',$norm);
    if($r['ok']) $inserted[]=['count'=>count($norm),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  $all=rows161('blotato_distribution_queue','select=*&distribution_status=in.(draft,queued,scheduled,posted)&order=distribution_score.desc,created_at.desc&limit=1000');
  $counts=['review'=>0,'approved'=>0,'scheduled'=>0,'posted'=>0];
  foreach($all as $x){
    if(($x['approval_status']??'')==='review')$counts['review']++;
    if(($x['approval_status']??'')==='approved')$counts['approved']++;
    if(($x['distribution_status']??'')==='scheduled')$counts['scheduled']++;
    if(($x['distribution_status']??'')==='posted')$counts['posted']++;
  }

  $brief="V16.1 BLOTATO DISTRIBUTION DIRECTOR\n========================================\n\n";
  $brief.="Total Queue Items: ".count($all)."\n";
  $brief.="Needs Review: ".$counts['review']."\n";
  $brief.="Approved: ".$counts['approved']."\n";
  $brief.="Scheduled: ".$counts['scheduled']."\n";
  $brief.="Posted: ".$counts['posted']."\n";
  $brief.="New Items Created: ".count($new)."\n\nTOP DISTRIBUTION ITEMS\n----------------------------------------\n";
  foreach(array_slice($all,0,20) as $i=>$x){
    $platforms=is_array($x['platforms']??null)?$x['platforms']:[];
    $brief.=($i+1).". ".$x['distribution_title']." — ".$x['brand_pillar']." — ".$x['approval_status']." — Score ".$x['distribution_score']."\n";
    $brief.="   Platforms: ".implode(', ',$platforms)."\n\n";
  }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),
    'total_items'=>count($all),
    'review_items'=>$counts['review'],
    'approved_items'=>$counts['approved'],
    'scheduled_items'=>$counts['scheduled'],
    'posted_items'=>$counts['posted'],
    'top_items'=>array_slice($all,0,30),
    'briefing_text'=>$brief,
    'recommendations'=>[
      'Approve only polished posts before Blotato posting.',
      'Connect Blotato API/MCP after queue approvals are working.',
      'Start with one seller post, one Discover CT post, and one House Detective post per day.'
    ],
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  $dr=sb161('POST','blotato_distribution_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb161('PATCH','blotato_distribution_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode(['success'=>empty($errors),'new_items_created'=>count($new),'total_queue_items'=>count($all),'needs_review'=>$counts['review'],'approved'=>$counts['approved'],'briefing'=>$brief,'inserted'=>$inserted,'errors'=>$errors],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>