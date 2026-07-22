<?php
/**
 * V12.16.1 Queue Intelligence Layer — Key Fix
 * Upload over: /public_html/lead-engine/build-queue-intelligence.php
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

  function sb161($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=['apikey: '.SUPABASE_SERVICE_ROLE_KEY,'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,'Content-Type: application/json'];
    $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
    $b=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    $d=json_decode($b,true);
    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }

  function rows161($table,$query){ $r=sb161('GET',$table.'?'.$query); return $r['ok']?$r['data']:[]; }

  function seller_script161(){
    return "Hi, this is Jessica calling on behalf of Mark Pires. We are checking in with local homeowners because Fairfield County is still showing unusually strong seller conditions. Many homeowners have more equity and financial flexibility than they realize, especially if they are considering downsizing, relocating, or making a move out of state. Mark has watched this market for 20 years, and this cycle has created a rare window for sellers. I am not calling to pressure you to sell. I am calling to see if it would be helpful for Mark to prepare a quick, no-obligation market position review so you know what your options look like before conditions change.";
  }

  function action161($overrides){
    $base=[
      'queue_date'=>date('Y-m-d'),
      'source_table'=>'',
      'source_id'=>'',
      'queue_type'=>'watch',
      'priority_rank'=>0,
      'priority_score'=>0,
      'priority_band'=>'C',
      'name'=>'',
      'phone'=>'',
      'email'=>'',
      'town'=>'',
      'market'=>'',
      'lead_type'=>'',
      'action_title'=>'',
      'action_reason'=>'',
      'recommended_script'=>'',
      'compliance_status'=>'review',
      'call_eligible'=>false,
      'dnc_status'=>'',
      'consent_status'=>'',
      'approval_status'=>'',
      'status'=>'open',
      'raw_payload'=>[],
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ];
    return array_merge($base,$overrides);
  }

  $today=date('Y-m-d');
  $actions=[]; $rank=1;

  $imports=rows161('compliant_lead_imports','select=*&call_eligible=eq.true&order=lead_score.desc,created_at.desc&limit=100');
  foreach($imports as $i){
    $score=(int)($i['lead_score']??0);
    $actions[]=action161([
      'source_table'=>'compliant_lead_imports','source_id'=>(string)($i['id']??''),
      'queue_type'=>'call','priority_rank'=>$rank++,'priority_score'=>$score,'priority_band'=>$score>=85?'A':($score>=70?'B':'C'),
      'name'=>$i['name']??'','phone'=>$i['phone']??'','email'=>$i['email']??'','town'=>$i['town']??'','market'=>$i['market']??'','lead_type'=>$i['lead_type']??'',
      'action_title'=>'Call approved lead','action_reason'=>'Call eligible, approved/DNC-clear/consent-cleared contact.',
      'recommended_script'=>seller_script161(),
      'compliance_status'=>($i['approval_status']??'').' / '.($i['dnc_status']??'').' / '.($i['consent_status']??''),
      'call_eligible'=>true,'dnc_status'=>$i['dnc_status']??'','consent_status'=>$i['consent_status']??'','approval_status'=>$i['approval_status']??'',
      'raw_payload'=>$i
    ]);
  }

  $reviewImports=rows161('compliant_lead_imports','select=*&call_eligible=neq.true&order=lead_score.desc,created_at.desc&limit=100');
  foreach($reviewImports as $i){
    $score=(int)($i['lead_score']??0);
    $actions[]=action161([
      'source_table'=>'compliant_lead_imports','source_id'=>(string)($i['id']??''),
      'queue_type'=>'review','priority_rank'=>$rank++,'priority_score'=>$score,'priority_band'=>$score>=85?'A':($score>=70?'B':'C'),
      'name'=>$i['name']??'','phone'=>$i['phone']??'','email'=>$i['email']??'','town'=>$i['town']??'','market'=>$i['market']??'','lead_type'=>$i['lead_type']??'',
      'action_title'=>'Review before contact','action_reason'=>'Strong opportunity, but not call eligible yet. Review consent/DNC/source before outreach.',
      'recommended_script'=>'Do not call yet. Review compliance first. If approved, use Jessica seller market-position script.',
      'compliance_status'=>($i['approval_status']??'review').' / '.($i['dnc_status']??'unchecked').' / '.($i['consent_status']??'unknown'),
      'dnc_status'=>$i['dnc_status']??'','consent_status'=>$i['consent_status']??'','approval_status'=>$i['approval_status']??'',
      'raw_payload'=>$i
    ]);
  }

  $ads=rows161('live_ad_launch_checklists','select=*&launch_status=eq.ready&order=created_at.desc&limit=25');
  foreach($ads as $a){
    $actions[]=action161([
      'source_table'=>'live_ad_launch_checklists','source_id'=>(string)($a['id']??''),
      'queue_type'=>'ad_launch','priority_rank'=>$rank++,'priority_score'=>90,'priority_band'=>'A',
      'name'=>$a['campaign_name']??'','market'=>'Paid Ads','lead_type'=>'seller_campaign',
      'action_title'=>'Launch small-budget ad test',
      'action_reason'=>'Campaign is marked ready with UTM link. Start with $10-$20/day and monitor lead quality.',
      'recommended_script'=>'Use campaign copy from assets and route leads to Jessica valuation follow-up.',
      'compliance_status'=>'ad campaign ready','raw_payload'=>$a
    ]);
  }

  $content=rows161('seo_aeo_content_opportunities','select=*&order=priority_score.desc,created_at.desc&limit=50');
  foreach($content as $c){
    $score=(int)($c['priority_score']??75);
    $actions[]=action161([
      'source_table'=>'seo_aeo_content_opportunities','source_id'=>(string)($c['id']??''),
      'queue_type'=>'content','priority_rank'=>$rank++,'priority_score'=>$score,'priority_band'=>$score>=85?'A':'B',
      'name'=>$c['title']??'','town'=>$c['town']??'','market'=>$c['market']??'','lead_type'=>$c['content_type']??'',
      'action_title'=>'Approve/create content',
      'action_reason'=>'High-priority SEO/AEO opportunity from Jessica.',
      'recommended_script'=>'Create this content page or blog and connect CTA to home valuation / town match funnel.',
      'compliance_status'=>'content only','raw_payload'=>$c
    ]);
  }

  $hunter=rows161('hunter_priority_rankings','select=*&status=eq.active&order=hunter_score.desc,created_at.desc&limit=100');
  foreach($hunter as $h){
    if(!empty($h['call_eligible'])) continue;
    $score=(int)($h['hunter_score']??0);
    $actions[]=action161([
      'source_table'=>'hunter_priority_rankings','source_id'=>(string)($h['id']??''),
      'queue_type'=>'watch','priority_rank'=>$rank++,'priority_score'=>$score,'priority_band'=>$score>=85?'A':($score>=70?'B':'C'),
      'name'=>($h['name']??'') ?: (($h['town']??'').' '.($h['hunter_type']??'')),
      'town'=>$h['town']??'','market'=>$h['market']??'','lead_type'=>$h['hunter_type']??'',
      'action_title'=>'Watch / use for targeting',
      'action_reason'=>'High-scoring hunter item, but not call eligible. Use for ads, content, or compliance review.',
      'recommended_script'=>'Do not call until approved. Use insight to improve ads/content.',
      'compliance_status'=>$h['compliance_status']??'research_only','raw_payload'=>$h
    ]);
  }

  usort($actions,function($a,$b){
    $order=['call'=>1,'review'=>2,'ad_launch'=>3,'content'=>4,'watch'=>5,'nurture'=>6,'do_not_call'=>9];
    $oa=$order[$a['queue_type']]??9; $ob=$order[$b['queue_type']]??9;
    if($oa!==$ob) return $oa<=>$ob;
    return ($b['priority_score']<=>$a['priority_score']);
  });

  $inserted=[]; $errors=[];
  foreach(array_chunk(array_slice($actions,0,500),100) as $chunk){
    $r=sb161('POST','daily_action_queue',$chunk);
    if($r['ok']) $inserted[]=['count'=>count($chunk),'http'=>$r['http']];
    else $errors[]=['http'=>$r['http'],'body'=>$r['body']];
  }

  $counts=['call'=>0,'review'=>0,'ad_launch'=>0,'content'=>0,'watch'=>0];
  foreach($actions as $a){ if(isset($counts[$a['queue_type']])) $counts[$a['queue_type']]++; }

  $top=array_slice($actions,0,20);
  $brief="Queue Intelligence Briefing — {$today}\\n\\nTotal actions: ".count($actions)."\\nCall eligible actions: ".$counts['call']."\\nReview actions: ".$counts['review']."\\nAd launch actions: ".$counts['ad_launch']."\\nContent actions: ".$counts['content']."\\nWatch actions: ".$counts['watch']."\\n\\nTop actions:\\n";
  foreach($top as $i=>$a){ $brief.=($i+1).". [".$a['queue_type']."] ".$a['action_title']." — ".$a['name']." — Score ".$a['priority_score']."\\n"; }

  $daily=[[
    'briefing_date'=>$today,'total_actions'=>count($actions),'call_actions'=>$counts['call'],'review_actions'=>$counts['review'],
    'ad_actions'=>$counts['ad_launch'],'content_actions'=>$counts['content'],'watch_actions'=>$counts['watch'],
    'top_actions'=>$top,'briefing_text'=>$brief,'created_at'=>date('c'),'updated_at'=>date('c')
  ]];
  $dr=sb161('POST','queue_intelligence_briefings',$daily);
  if(!$dr['ok'] && str_contains($dr['body'],'duplicate key')){
    sb161('PATCH','queue_intelligence_briefings?briefing_date=eq.'.rawurlencode($today),$daily[0]);
  }

  echo json_encode([
    'success'=>empty($errors),
    'total_actions'=>count($actions),
    'call_actions'=>$counts['call'],
    'review_actions'=>$counts['review'],
    'ad_actions'=>$counts['ad_launch'],
    'content_actions'=>$counts['content'],
    'watch_actions'=>$counts['watch'],
    'inserted'=>$inserted,
    'briefing'=>$brief,
    'errors'=>$errors
  ],JSON_PRETTY_PRINT);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'PHP exception','message'=>$e->getMessage(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);
}
?>