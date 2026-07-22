<?php
/**
 * V12.15.2 Mark Strategy Mode — 500 Fix
 * Upload over: /public_html/lead-engine/mark-strategy-mode.php
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $key = $_GET['key'] ?? $_POST['key'] ?? '';
  if (!defined('AFTER_HOURS_CRON_KEY') || !AFTER_HOURS_CRON_KEY || !hash_equals(AFTER_HOURS_CRON_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Invalid key']);
    exit;
  }

  function sb1522fix($method,$endpoint,$payload=null){
    $ch=curl_init(rtrim(SUPABASE_URL,'/').'/rest/v1/'.ltrim($endpoint,'/'));
    $headers=[
      'apikey: '.SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer '.SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json'
    ];
    $headers[]=$method==='POST'?'Prefer: return=representation':'Prefer: return=representation';

    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_CUSTOMREQUEST=>$method,
      CURLOPT_HTTPHEADER=>$headers,
      CURLOPT_TIMEOUT=>45
    ]);

    if($payload!==null){
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $b=curl_exec($ch);
    $http=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $err=curl_error($ch);
    curl_close($ch);
    $d=json_decode($b,true);

    return ['ok'=>$http>=200&&$http<300,'http'=>$http,'body'=>$b,'error'=>$err,'data'=>is_array($d)?$d:[]];
  }

  function rows1522fix($table,$query='select=*&limit=100'){
    $r=sb1522fix('GET',$table.'?'.$query);
    return $r['ok']?$r['data']:[];
  }

  function input1522fix($k,$default=''){
    if(isset($_POST[$k]))return $_POST[$k];
    if(isset($_GET[$k]))return $_GET[$k];
    $raw=file_get_contents('php://input');
    if($raw){
      $j=json_decode($raw,true);
      if(is_array($j)&&isset($j[$k]))return $j[$k];
    }
    return $default;
  }

  $phrase = trim((string)input1522fix('phrase',''));
  $topic = trim((string)input1522fix('topic','daily strategy'));
  $normalized = strtolower(preg_replace('/[^a-z0-9]+/',' ', $phrase));
  $validPhrases = [
    'jessica it s time to make the donuts',
    'jessica its time to make the donuts',
    'time to make the donuts',
    'jessica strategy mode',
    'open mark strategy mode'
  ];

  $authenticated = in_array(trim($normalized), $validPhrases, true);

  if(!$authenticated){
    sb1522fix('POST','mark_strategy_sessions',[[
      'trigger_phrase'=>$phrase,
      'authenticated'=>false,
      'requested_topic'=>$topic,
      'strategy_brief'=>'Strategy mode denied. Invalid trigger phrase.',
      'raw_payload'=>['phrase'=>$phrase,'topic'=>$topic],
      'created_at'=>date('c'),
      'updated_at'=>date('c')
    ]]);
    http_response_code(403);
    echo json_encode([
      'success'=>false,
      'authenticated'=>false,
      'error'=>'Invalid strategy trigger phrase',
      'hint'=>"Use: Jessica it's time to make the donuts"
    ], JSON_PRETTY_PRINT);
    exit;
  }

  $hunter = rows1522fix('hunter_priority_rankings','select=*&status=eq.active&order=hunter_score.desc,created_at.desc&limit=25');
  $command = rows1522fix('daily_command_center_snapshots','select=*&order=created_at.desc&limit=1');
  $heat = rows1522fix('market_heat_snapshots','select=*&order=total_heat.desc,created_at.desc&limit=10');
  $campaigns = rows1522fix('first_campaign_plan','select=*&order=priority_score.desc,created_at.desc&limit=10');
  $content = rows1522fix('seo_aeo_content_opportunities','select=*&order=priority_score.desc,created_at.desc&limit=10');
  $convo = rows1522fix('conversation_learning_briefings','select=*&order=created_at.desc&limit=1');
  $training = rows1522fix('mark_strategy_training_notes','select=*&active=eq.true&order=priority.desc,created_at.desc&limit=20');
  $appts = rows1522fix('appointment_requests','select=*&order=created_at.desc&limit=20');

  $hotLeads = array_slice(array_map(function($h){
    return [
      'name'=>($h['name'] ?? '') ?: (($h['town']??'').' '.($h['hunter_type']??'')),
      'type'=>$h['hunter_type']??'',
      'town'=>$h['town']??'',
      'score'=>(int)($h['hunter_score']??0),
      'recommendation'=>$h['call_recommendation']??'',
      'eligible'=>!empty($h['call_eligible']),
      'reason'=>$h['reason']??'',
      'next_action'=>$h['next_action']??''
    ];
  }, $hunter), 0, 10);

  $topTowns = array_slice(array_map(function($h){
    return [
      'town'=>$h['town']??'',
      'heat'=>(int)($h['total_heat']??0),
      'band'=>$h['heat_band']??'',
      'action'=>$h['recommended_action']??'',
      'budget'=>$h['recommended_budget']??0
    ];
  }, $heat), 0, 10);

  $topCampaigns = array_slice(array_map(function($c){
    return [
      'campaign'=>$c['campaign_name']??'',
      'town'=>$c['town']??'',
      'score'=>(int)($c['priority_score']??0),
      'landing'=>$c['landing_page']??'',
      'cta'=>$c['cta']??''
    ];
  }, $campaigns), 0, 10);

  $topContent = array_slice(array_map(function($c){
    return [
      'title'=>$c['title']??'',
      'town'=>$c['town']??'',
      'type'=>$c['content_type']??'',
      'score'=>(int)($c['priority_score']??0),
      'slug'=>$c['slug']??''
    ];
  }, $content), 0, 10);

  $calendarSummary = [
    'recent_appointments'=>array_slice(array_map(function($a){
      return [
        'name'=>$a['name']??($a['lead_name']??''),
        'phone'=>$a['phone']??'',
        'status'=>$a['status']??'',
        'calendar_status'=>$a['calendar_status']??'',
        'created_at'=>$a['created_at']??''
      ];
    }, $appts),0,10),
    'count'=>count($appts)
  ];

  $recs = [];
  if(!empty($topTowns[0])) $recs[] = 'Today focus town: '.$topTowns[0]['town'].' — '.$topTowns[0]['action'];
  if(!empty($topCampaigns[0])) $recs[] = 'Campaign to review first: '.$topCampaigns[0]['campaign'].' with CTA '.$topCampaigns[0]['cta'].'.';
  if(!empty($topContent[0])) $recs[] = 'Content to approve first: '.$topContent[0]['title'].'.';
  if(!empty($hotLeads[0])) $recs[] = 'Top hunter item: '.$hotLeads[0]['name'].' — score '.$hotLeads[0]['score'].'.';
  if(!empty($convo[0]['briefing_text'])) $recs[] = 'Conversation learning is active. Review objections and script performance before calls.';
  if(empty(array_filter($hotLeads,function($x){return !empty($x['eligible']);}))) $recs[] = 'No major call-eligible list yet. Keep calls limited to approved opt-in/DNC-clear contacts and focus on campaigns/content/import review.';

  $brief = "MARK STRATEGY MODE — ".date('Y-m-d H:i')."\n";
  $brief .= "Trigger accepted: Jessica is now in internal assistant / CEO strategy mode.\n\n";
  $brief .= "TOP TOWNS\n";
  foreach(array_slice($topTowns,0,5) as $i=>$t){ $brief .= ($i+1).". ".$t['town']." — ".$t['band']." — Heat ".$t['heat']." — ".$t['action']."\n"; }
  $brief .= "\nTOP CAMPAIGNS\n";
  foreach(array_slice($topCampaigns,0,5) as $i=>$c){ $brief .= ($i+1).". ".$c['campaign']." — Score ".$c['score']." — ".$c['cta']."\n"; }
  $brief .= "\nTOP CONTENT\n";
  foreach(array_slice($topContent,0,5) as $i=>$c){ $brief .= ($i+1).". ".$c['title']." — ".$c['type']." — Score ".$c['score']."\n"; }
  $brief .= "\nHOT LEADS / HUNTER ITEMS\n";
  foreach(array_slice($hotLeads,0,5) as $i=>$h){ $brief .= ($i+1).". ".$h['name']." — ".$h['type']." — Score ".$h['score']." — ".$h['recommendation']."\n"; }
  $brief .= "\nJESSICA RECOMMENDS\n";
  foreach($recs as $i=>$r){ $brief .= ($i+1).". ".$r."\n"; }
  $brief .= "\nMARK STYLE NOTES ACTIVE: ".count($training)."\n";
  foreach(array_slice($training,0,5) as $i=>$t){ $brief .= "- ".$t['title'].": ".$t['note']."\n"; }

  $payload=[[
    'trigger_phrase'=>$phrase,
    'authenticated'=>true,
    'session_type'=>'strategy',
    'requested_topic'=>$topic,
    'hot_leads'=>$hotLeads,
    'top_towns'=>$topTowns,
    'top_campaigns'=>$topCampaigns,
    'top_content'=>$topContent,
    'calendar_summary'=>$calendarSummary,
    'recommendations'=>$recs,
    'strategy_brief'=>$brief,
    'raw_payload'=>[
      'command'=>$command[0]??null,
      'conversation_briefing'=>$convo[0]??null,
      'training_notes'=>$training
    ],
    'created_at'=>date('c'),
    'updated_at'=>date('c')
  ]];

  $res=sb1522fix('POST','mark_strategy_sessions',$payload);

  echo json_encode([
    'success'=>$res['ok'],
    'authenticated'=>true,
    'mode'=>'Mark Strategy Mode',
    'strategy_brief'=>$brief,
    'recommendations'=>$recs,
    'hot_leads'=>$hotLeads,
    'top_towns'=>$topTowns,
    'top_campaigns'=>$topCampaigns,
    'top_content'=>$topContent,
    'supabase_http'=>$res['http'],
    'body'=>$res['ok']?'':$res['body']
  ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'success'=>false,
    'error'=>'PHP exception',
    'message'=>$e->getMessage(),
    'file'=>$e->getFile(),
    'line'=>$e->getLine()
  ], JSON_PRETTY_PRINT);
}
?>