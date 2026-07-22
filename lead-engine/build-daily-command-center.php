<?php
/**
 * V12.13 Daily Command Center Builder
 * Upload: /public_html/lead-engine/build-daily-command-center.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Invalid key']);
  exit;
}

function sb1213($method,$endpoint,$payload=null){
  $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
  $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
  $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
  if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
  $b=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  $d=json_decode($b,true);
  return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
}
function rows1213($table,$query='select=*&limit=1000'){
  $r=sb1213('GET',$table.'?'.$query);
  return $r['ok']?$r['data']:[];
}
function count1213($table,$query='select=id&limit=5000'){ return count(rows1213($table,$query)); }
function top_towns1213($sets){
  $towns=[];
  foreach($sets as $rows){
    foreach($rows as $r){
      $town=$r['town'] ?? ($r['opportunity_town'] ?? '');
      if(!$town) continue;
      $towns[$town]=($towns[$town]??0)+1;
    }
  }
  arsort($towns);
  $out=[];
  foreach(array_slice($towns,0,10,true) as $k=>$v)$out[]=['town'=>$k,'score'=>$v];
  return $out;
}

$today=date('Y-m-d');

$discovery=rows1213('discovery_opportunity_queue','select=*&order=priority_score.desc,created_at.desc&limit=1000');
$imports=rows1213('compliant_lead_imports','select=*&order=lead_score.desc,created_at.desc&limit=1000');
$hunter=rows1213('hunter_priority_rankings','select=*&status=eq.active&order=hunter_score.desc,created_at.desc&limit=1000');
$campaigns=rows1213('first_campaign_plan','select=*&order=priority_score.desc,created_at.desc&limit=500');
$assets=rows1213('campaign_launch_assets','select=*&order=created_at.desc&limit=1000');
$seo=rows1213('seo_aeo_content_opportunities','select=*&order=priority_score.desc,created_at.desc&limit=1000');
$liveAds=rows1213('live_ad_launch_checklists','select=*&order=created_at.desc&limit=500');
$roi=rows1213('roi_attribution_snapshots','select=*&order=created_at.desc&limit=1');

$callEligible=0;
foreach($imports as $i){ if(!empty($i['call_eligible']))$callEligible++; }

$callFirst=0;$callToday=0;
foreach($hunter as $h){
  if(($h['call_recommendation']??'')==='call_first')$callFirst++;
  if(($h['call_recommendation']??'')==='call_today')$callToday++;
}

$campaignDrafts=0;
foreach($campaigns as $c){ if(($c['status']??'')==='draft')$campaignDrafts++; }

$liveReady=0;
foreach($liveAds as $a){ if(($a['launch_status']??'')==='ready')$liveReady++; }

$topCampaigns=array_slice(array_map(function($c){
  return [
    'campaign_name'=>$c['campaign_name']??'',
    'town'=>$c['town']??'',
    'market'=>$c['market']??'',
    'score'=>(int)($c['priority_score']??0),
    'cta'=>$c['cta']??'',
    'landing_page'=>$c['landing_page']??''
  ];
},$campaigns),0,10);

$topContent=array_slice(array_map(function($s){
  return [
    'title'=>$s['title']??'',
    'town'=>$s['town']??'',
    'type'=>$s['content_type']??'',
    'score'=>(int)($s['priority_score']??0),
    'slug'=>$s['slug']??'',
    'cta'=>$s['cta']??''
  ];
},$seo),0,10);

$topHunter=array_slice(array_map(function($h){
  return [
    'name'=>$h['name'] ?: (($h['town']??'').' '.($h['hunter_type']??'')),
    'type'=>$h['hunter_type']??'',
    'town'=>$h['town']??'',
    'score'=>(int)($h['hunter_score']??0),
    'recommendation'=>$h['call_recommendation']??'',
    'eligible'=>!empty($h['call_eligible']),
    'reason'=>$h['reason']??''
  ];
},$hunter),0,15);

$topTowns=top_towns1213([$discovery,$imports,$hunter,$seo]);

$roiSnap=$roi[0]??[];
$recommendations=[];
if(!empty($topTowns[0]))$recommendations[]='Focus town today: '.$topTowns[0]['town'].' — highest combined opportunity signal.';
if(!empty($topCampaigns[0]))$recommendations[]='Review campaign first: '.$topCampaigns[0]['campaign_name'].' — strongest campaign score.';
if(!empty($topContent[0]))$recommendations[]='Create/approve content first: '.$topContent[0]['title'].'.';
if($callEligible>0)$recommendations[]='There are '.$callEligible.' call-eligible compliant imports. Review and call only during approved daytime windows.';
if($callEligible===0)$recommendations[]='No call-eligible imports yet. Keep prospecting in review/approval mode and feed opt-in/vendor/DNC-clear data.';
if($liveReady>0)$recommendations[]=$liveReady.' ad campaigns are marked ready. Pick one small-budget test first.';
if($liveReady===0 && $campaignDrafts>0)$recommendations[]='Campaign drafts exist, but launch checklist still needs review before ads go live.';

$readiness=[
  'discovery_engine'=>count($discovery)>0?'ready':'needs_data',
  'hunter_engine'=>count($hunter)>0?'ready':'needs_data',
  'campaign_engine'=>count($campaigns)>0?'ready':'needs_data',
  'seo_aeo_engine'=>count($seo)>0?'ready':'needs_data',
  'roi_engine'=>!empty($roiSnap)?'ready':'needs_snapshot',
  'live_ads'=>($liveReady>0?'ready':'drafting'),
  'calling'=>($callEligible>0?'ready_with_guardrails':'needs_approved_contacts')
];

$brief="Daily Command Center — {$today}\n\n";
$brief.="OVERNIGHT / SYSTEM OUTPUT\n";
$brief.="Discovery opportunities: ".count($discovery)."\n";
$brief.="Compliant imports: ".count($imports)." ({$callEligible} call eligible)\n";
$brief.="Hunter rankings: ".count($hunter)."\n";
$brief.="Campaign drafts: {$campaignDrafts}\n";
$brief.="Campaign assets: ".count($assets)."\n";
$brief.="SEO/AEO opportunities: ".count($seo)."\n";
$brief.="Live ad ready: {$liveReady}\n\n";

$brief.="TODAY'S FOCUS\n";
$brief.="Top town: ".($topTowns[0]['town']??'n/a')."\n";
$brief.="Top campaign: ".($topCampaigns[0]['campaign_name']??'n/a')."\n";
$brief.="Top content: ".($topContent[0]['title']??'n/a')."\n\n";

$brief.="JESSICA RECOMMENDS\n";
foreach($recommendations as $i=>$r){ $brief.=($i+1).". {$r}\n"; }

$payload=[[
  'snapshot_date'=>$today,
  'snapshot_type'=>$_GET['type'] ?? 'morning',
  'overnight_opportunities'=>count1213('overnight_research_missions'),
  'discovery_opportunities'=>count($discovery),
  'compliant_imports'=>count($imports),
  'call_eligible_imports'=>$callEligible,
  'hunter_rankings'=>count($hunter),
  'call_first'=>$callFirst,
  'call_today'=>$callToday,
  'campaign_drafts'=>$campaignDrafts,
  'campaign_assets'=>count($assets),
  'seo_opportunities'=>count($seo),
  'live_ad_ready'=>$liveReady,
  'roi_spend'=>(float)($roiSnap['total_spend']??0),
  'roi_leads'=>(int)($roiSnap['total_leads']??0),
  'roi_appointments'=>(int)($roiSnap['total_appointments']??0),
  'top_towns'=>$topTowns,
  'top_campaigns'=>$topCampaigns,
  'top_content'=>$topContent,
  'top_hunter_items'=>$topHunter,
  'jessica_recommendations'=>$recommendations,
  'readiness'=>$readiness,
  'command_brief'=>$brief,
  'created_at'=>date('c')
]];

$res=sb1213('POST','daily_command_center_snapshots',$payload);

echo json_encode([
  'success'=>$res['ok'],
  'snapshot'=>$payload[0],
  'supabase_http'=>$res['http'],
  'body'=>$res['ok']?'':$res['body']
],JSON_PRETTY_PRINT);
?>