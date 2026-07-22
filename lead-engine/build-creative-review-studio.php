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

  function sb162($method,$endpoint,$payload=null){
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
  function rows162($t,$q){$r=sb162('GET',$t.'?'.$q);return $r['ok']?$r['data']:[];}
  function scoreWords162($txt,$words,$pts){$s=0;$txt=strtolower($txt);foreach($words as $w){if(strpos($txt,strtolower($w))!==false)$s+=$pts;}return min(100,$s);}
  function visualScore162($row){
    $txt=implode(' ',[$row['review_title']??'',$row['headline']??'',$row['caption']??'',$row['brand_pillar']??'',$row['image_prompt']??'']);
    $visual=35+scoreWords162($txt,['premium','luxury','noir','house detective','discover ct','home value','seller','local','fairfield county','thumbnail','graphic'],6);
    $copy=35+scoreWords162($txt,['mistake','secret','truth','worth','value','guide','timing','equity','local','private','review'],6);
    $brand=40+scoreWords162($txt,['mark pires','discover ct','house detective','beatseat','fairfield county','connecticut'],7);
    $conv=30+scoreWords162($txt,['call','text','home value','get my','private','valuation','seller','cta'],8);
    $total=round($visual*.25+$copy*.25+$brand*.25+$conv*.25);
    return [min(100,$visual),min(100,$copy),min(100,$brand),min(100,$conv),min(100,$total)];
  }
  function platforms162($x){
    if(is_array($x)) return $x;
    if(!$x) return ['Instagram','Facebook','LinkedIn'];
    $d=json_decode($x,true);
    return is_array($d)?$d:['Instagram','Facebook','LinkedIn'];
  }

  $existing=rows162('creative_review_studio_items','select=source_table,source_id,review_title&limit=5000');
  $seen=[];
  foreach($existing as $e){$seen[strtolower(($e['source_table']??'').':'.($e['source_id']??'').':'.($e['review_title']??''))]=true;}

  $new=[];

  $gen=rows162('creative_generation_jobs','select=*&status=in.(queued,generated,approved)&order=generation_score.desc,created_at.desc&limit=300');
  foreach($gen as $g){
    $title=$g['job_name'] ?: ($g['headline'] ?: 'Creative Job');
    $sid=$g['id']??'';
    $k=strtolower('creative_generation_jobs:'.$sid.':'.$title);
    if(isset($seen[$k])) continue;
    $row=[
      'review_date'=>date('Y-m-d'),
      'source_table'=>'creative_generation_jobs',
      'source_id'=>(string)$sid,
      'review_title'=>$title,
      'brand_pillar'=>$g['brand_pillar']??'mark_pires',
      'content_type'=>$g['job_type']??'social_post',
      'preview_image_url'=>$g['generated_image_url'] ?: ($g['upload_1']??''),
      'media_url'=>$g['generated_image_url'] ?: ($g['upload_1']??''),
      'media_file_path'=>$g['generated_file_path']??'',
      'headline'=>$g['headline'] ?: $title,
      'subheadline'=>$g['subheadline']??'',
      'caption'=>trim(($g['headline']?:$title)."\n\n".($g['prompt']??'')),
      'hashtags'=>'#FairfieldCounty #ConnecticutRealEstate #MarkPires',
      'cta'=>$g['cta']??'',
      'landing_page_url'=>'https://markpires.com/home-valuation.html',
      'platforms'=>['Instagram','Facebook','LinkedIn'],
      'image_prompt'=>$g['enhanced_prompt'] ?: ($g['prompt']??''),
      'edit_notes'=>'Review image, tighten headline, confirm brand/logo placement.',
      'variant_request'=>'Create 2 variants: one premium seller ad and one more emotional/local version.',
      'review_status'=>'review',
      'raw_payload'=>$g,
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
    [$v,$c,$b,$cv,$total]=visualScore162($row);
    $row['visual_score']=$v; $row['copy_score']=$c; $row['brand_score']=$b; $row['conversion_score']=$cv; $row['review_score']=max($total,(int)($g['generation_score']??0));
    $new[]=$row;
  }

  $dist=rows162('blotato_distribution_queue','select=*&approval_status=eq.review&order=distribution_score.desc,created_at.desc&limit=300');
  foreach($dist as $d){
    $title=$d['distribution_title'] ?: 'Distribution Item';
    $sid=$d['id']??'';
    $k=strtolower('blotato_distribution_queue:'.$sid.':'.$title);
    if(isset($seen[$k])) continue;
    $row=[
      'review_date'=>date('Y-m-d'),
      'source_table'=>'blotato_distribution_queue',
      'source_id'=>(string)$sid,
      'review_title'=>$title,
      'brand_pillar'=>$d['brand_pillar']??'mark_pires',
      'content_type'=>$d['content_type']??'social_post',
      'preview_image_url'=>$d['media_url'] ?: ($d['media_file_path']??''),
      'media_url'=>$d['media_url']??'',
      'media_file_path'=>$d['media_file_path']??'',
      'headline'=>$title,
      'subheadline'=>'',
      'caption'=>$d['caption']??'',
      'hashtags'=>$d['hashtags']??'',
      'cta'=>$d['cta']??'',
      'landing_page_url'=>$d['landing_page_url']??'',
      'platforms'=>platforms162($d['platforms']??[]),
      'image_prompt'=>$d['image_prompt']??'',
      'edit_notes'=>'Confirm visual/media exists before approving for distribution.',
      'variant_request'=>'Create a stronger visual if media is missing.',
      'review_status'=>'review',
      'blotato_queue_id'=>(string)$sid,
      'raw_payload'=>$d,
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
    [$v,$c,$b,$cv,$total]=visualScore162($row);
    $row['visual_score']=$v; $row['copy_score']=$c; $row['brand_score']=$b; $row['conversion_score']=$cv; $row['review_score']=max($total,(int)($d['distribution_score']??0));
    $new[]=$row;
  }

  usort($new,function($a,$b){return $b['review_score']<=>$a['review_score'];});
  $inserted=[];$errors=[];
  foreach(array_chunk(array_slice($new,0,350),50) as $chunk){
    $keys=array_keys($chunk[0]);
    $norm=[];
    foreach($chunk as $row){$clean=[];foreach($keys as $k){$clean[$k]=$row[$k]??null;}$norm[]=$clean;}
    $r=sb162('POST','creative_review_studio_items',$norm);
    if($r['ok'])$inserted[]=['count'=>count($norm),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  $all=rows162('creative_review_studio_items','select=*&review_status=in.(review,improve,approved,sent_to_blotato)&order=review_score.desc,created_at.desc&limit=1000');
  $counts=['review'=>0,'approved'=>0,'improve'=>0,'sent'=>0];
  foreach($all as $x){
    if(($x['review_status']??'')==='review')$counts['review']++;
    if(($x['review_status']??'')==='approved')$counts['approved']++;
    if(($x['review_status']??'')==='improve')$counts['improve']++;
    if(($x['review_status']??'')==='sent_to_blotato')$counts['sent']++;
  }

  $brief="V16.2 CREATIVE REVIEW STUDIO\n========================================\n\n";
  $brief.="Total Review Items: ".count($all)."\n";
  $brief.="Needs Review: ".$counts['review']."\n";
  $brief.="Approved: ".$counts['approved']."\n";
  $brief.="Needs Improvement: ".$counts['improve']."\n";
  $brief.="Sent To Blotato: ".$counts['sent']."\n";
  $brief.="New Items Created: ".count($new)."\n\nTOP VISUAL REVIEW ITEMS\n----------------------------------------\n";
  foreach(array_slice($all,0,20) as $i=>$x){
    $brief.=($i+1).". ".$x['review_title']." — ".$x['brand_pillar']." — ".$x['review_status']." — Score ".$x['review_score']."\n";
    $brief.="   Visual ".$x['visual_score']." | Copy ".$x['copy_score']." | Brand ".$x['brand_score']." | Conversion ".$x['conversion_score']."\n\n";
  }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),
    'total_items'=>count($all),
    'review_items'=>$counts['review'],
    'approved_items'=>$counts['approved'],
    'improve_items'=>$counts['improve'],
    'sent_to_blotato'=>$counts['sent'],
    'top_items'=>array_slice($all,0,30),
    'briefing_text'=>$brief,
    'recommendations'=>[
      'Approve only assets with media/preview confirmed.',
      'Use Improve for missing image, weak headline, or poor CTA.',
      'Send approved assets to Blotato queue before direct publishing.',
      'V17 Media Director will feed shorts/thumbnails into this same review studio.'
    ],
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];
  $dr=sb162('POST','creative_review_studio_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb162('PATCH','creative_review_studio_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode(['success'=>empty($errors),'new_items_created'=>count($new),'total_review_items'=>count($all),'needs_review'=>$counts['review'],'approved'=>$counts['approved'],'briefing'=>$brief,'inserted'=>$inserted,'errors'=>$errors],JSON_PRETTY_PRINT);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>