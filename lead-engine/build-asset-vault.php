<?php
/**
 * V13.4 Asset Vault Builder
 * Upload: /public_html/lead-engine/build-asset-vault.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403); echo json_encode(['success'=>false,'error'=>'Invalid key']); exit;
  }

  function sb134($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json','Prefer: return=representation'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }
  function rows134($table,$query){ $r=sb134('GET',$table.'?'.$query); return $r['ok']?$r['data']:[]; }
  function asset134($o){
    $base=[
      'asset_date'=>date('Y-m-d'),'source_table'=>'','source_id'=>'','asset_type'=>'creative','title'=>'','town'=>'','market'=>'Lower Fairfield County',
      'audience'=>'','headline'=>'','body'=>'','cta'=>'','landing_page'=>'','image_prompt'=>'','video_prompt'=>'','design_notes'=>'',
      'final_asset_url'=>'','local_file_path'=>'','status'=>'vaulted','priority_score'=>0,'launch_ready'=>false,'raw_payload'=>[],
      'created_at'=>date('c'),'updated_at'=>date('c')
    ];
    return array_merge($base,$o);
  }

  $assets=[]; $seen=[];

  // Approved or high-value creative review items become vaulted assets.
  $creatives=rows134('creative_review_items','select=*&order=priority_score.desc,created_at.desc&limit=500');
  foreach($creatives as $c){
    $key='creative:'.($c['id']??md5(json_encode($c)));
    if(isset($seen[$key])) continue; $seen[$key]=true;
    $status=($c['status']??'review')==='approved'?'approved':'vaulted';
    $assets[]=asset134([
      'source_table'=>'creative_review_items','source_id'=>(string)($c['id']??''),'asset_type'=>$c['creative_type']??'creative',
      'title'=>$c['campaign_name']??($c['headline']??'Creative Asset'),'town'=>$c['town']??'','market'=>$c['market']??'Lower Fairfield County',
      'audience'=>$c['audience']??'','headline'=>$c['headline']??'','body'=>$c['primary_text']??'','cta'=>$c['cta']??'',
      'landing_page'=>$c['landing_page']??'','image_prompt'=>$c['image_prompt']??'','video_prompt'=>$c['video_prompt']??'',
      'design_notes'=>$c['design_notes']??'','status'=>$status,'priority_score'=>(int)($c['priority_score']??0),
      'launch_ready'=>!empty($c['launch_ready']) || ($c['status']??'')==='approved','raw_payload'=>$c
    ]);
  }

  // Existing campaign assets are also vaulted.
  $campaignAssets=rows134('campaign_launch_assets','select=*&order=created_at.desc&limit=300');
  foreach($campaignAssets as $a){
    $key='campaign_asset:'.($a['id']??md5(json_encode($a)));
    if(isset($seen[$key])) continue; $seen[$key]=true;
    $assets[]=asset134([
      'source_table'=>'campaign_launch_assets','source_id'=>(string)($a['id']??''),'asset_type'=>$a['asset_type']??'ad',
      'title'=>$a['campaign_name']??($a['headline']??'Campaign Asset'),'headline'=>$a['headline']??'','body'=>$a['body']??'',
      'cta'=>$a['cta']??'','landing_page'=>$a['landing_page']??'','status'=>($a['status']??'draft')==='approved'?'approved':'vaulted',
      'priority_score'=>70,'launch_ready'=>($a['status']??'')==='approved','raw_payload'=>$a
    ]);
  }

  // Daily deliverables become vaulted planning assets.
  $deliverables=rows134('jessica_daily_deliverables','select=*&order=priority_score.desc,created_at.desc&limit=300');
  foreach($deliverables as $d){
    $key='deliverable:'.($d['id']??md5(json_encode($d)));
    if(isset($seen[$key])) continue; $seen[$key]=true;
    $assets[]=asset134([
      'source_table'=>'jessica_daily_deliverables','source_id'=>(string)($d['id']??''),'asset_type'=>$d['deliverable_type']??'deliverable',
      'title'=>$d['title']??'Daily Deliverable','town'=>$d['town']??'','market'=>$d['market']??'Lower Fairfield County',
      'headline'=>$d['title']??'','body'=>$d['summary']??'','cta'=>'','landing_page'=>$d['action_url']??'',
      'design_notes'=>$d['recommended_action']??'','status'=>($d['status']??'open')==='approved'?'approved':'vaulted',
      'priority_score'=>(int)($d['priority_score']??0),'launch_ready'=>($d['status']??'')==='approved','raw_payload'=>$d
    ]);
  }

  usort($assets,function($a,$b){
    if($a['launch_ready']!==$b['launch_ready']) return $a['launch_ready']?-1:1;
    return $b['priority_score']<=>$a['priority_score'];
  });

  $inserted=[]; $errors=[];
  foreach(array_chunk(array_slice($assets,0,800),100) as $chunk){
    $r=sb134('POST','asset_vault_items',$chunk);
    if($r['ok']) $inserted[]=['count'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  $all=rows134('asset_vault_items','select=*&order=priority_score.desc,created_at.desc&limit=1000');
  $counts=['approved'=>0,'launch'=>0,'ad'=>0,'content'=>0,'image'=>0,'video'=>0];
  foreach($all as $a){
    if(($a['status']??'')==='approved')$counts['approved']++;
    if(!empty($a['launch_ready']))$counts['launch']++;
    if(in_array(($a['asset_type']??''),['ad','meta_ad','google_search'],true))$counts['ad']++;
    if(in_array(($a['asset_type']??''),['content','blog','seo','source_hunter'],true))$counts['content']++;
    if(!empty($a['image_prompt']))$counts['image']++;
    if(!empty($a['video_prompt']))$counts['video']++;
  }

  $recs=[
    'Use the Asset Vault as the permanent library of approved Jessica work.',
    'Approve only assets you would actually use; archive duplicates so the vault stays clean.',
    'Next best move: upload final generated images/videos and paste their URLs into final_asset_url.'
  ];

  $brief="V13.4 ASSET VAULT\\n========================================\\n\\n";
  $brief.="Total Assets:      ".count($all)."\\n";
  $brief.="Approved Assets:   ".$counts['approved']."\\n";
  $brief.="Launch Ready:      ".$counts['launch']."\\n";
  $brief.="Ad Assets:         ".$counts['ad']."\\n";
  $brief.="Content Assets:    ".$counts['content']."\\n";
  $brief.="Image Prompts:     ".$counts['image']."\\n";
  $brief.="Video Prompts:     ".$counts['video']."\\n\\n";
  $brief.="TOP ASSETS\\n----------------------------------------\\n";
  foreach(array_slice($all,0,15) as $i=>$a){
    $brief.=($i+1).". ".($a['title']??'Asset')." — ".($a['asset_type']??'')." — Score ".($a['priority_score']??0)."\\n";
  }
  $brief.="\\nJESSICA RECOMMENDS\\n----------------------------------------\\n";
  foreach($recs as $i=>$r){ $brief.=($i+1).". {$r}\\n"; }

  $daily=[[
    'briefing_date'=>date('Y-m-d'),'total_assets'=>count($all),'approved_assets'=>$counts['approved'],'launch_ready'=>$counts['launch'],
    'ad_assets'=>$counts['ad'],'content_assets'=>$counts['content'],'image_prompts'=>$counts['image'],'video_prompts'=>$counts['video'],
    'top_assets'=>array_slice($all,0,25),'recommendations'=>$recs,'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $dr=sb134('POST','asset_vault_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb134('PATCH','asset_vault_briefings?briefing_date=eq.'.rawurlencode(date('Y-m-d')),$daily[0]);
  }

  echo json_encode(['success'=>empty($errors),'assets_created'=>count($assets),'total_assets'=>count($all),'approved_assets'=>$counts['approved'],'launch_ready'=>$counts['launch'],'inserted'=>$inserted,'briefing'=>$brief,'errors'=>$errors],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>