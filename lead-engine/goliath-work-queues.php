<?php
/**
 * Goliath Omni V54 — Work Queues API
 * Purpose: return real work items for each Executive, separated into ready work and queued/in-progress work.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

function gw_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function gw_req($endpoint){
  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
      'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
      'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 25
  ]);
  $raw = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $data = json_decode($raw, true);
  if ($http < 200 || $http >= 300 || !is_array($data)) return [];
  if (isset($data['code']) && isset($data['message'])) return [];
  return $data;
}
function gw_safe($row, $keys, $fallback=''){
  foreach ((array)$keys as $k) if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) return $row[$k];
  return $fallback;
}
function gw_json($v){
  if (is_array($v)) return $v;
  if (is_string($v)) { $j=json_decode($v,true); return is_array($j)?$j:[]; }
  return [];
}
function gw_item($agent,$kind,$title,$subtitle,$status,$score,$source,$row,$url=''){
  $status = $status ?: 'ready';
  $id = gw_safe($row, ['id','uuid','source_task_id','call_id'], '');
  $url = $url ?: '/dashboard/goliath-agent-detail.php?department=' . rawurlencode($agent) . '&item=' . rawurlencode((string)$id);
  return [
    'id'=>(string)$id,
    'agent'=>$agent,
    'department'=>$agent,
    'kind'=>$kind,
    'type'=>$kind,
    'title'=>mb_substr((string)$title,0,140),
    'subtitle'=>mb_substr((string)$subtitle,0,240),
    'summary'=>mb_substr((string)$subtitle,0,700),
    'status'=>$status,
    'score'=>(int)$score,
    'source'=>$source,
    'url'=>$url,
    'created_at'=>gw_safe($row,['created_at','updated_at'],''),
    'payload'=>$row
  ];
}
function gw_add_deliverables(&$items,$agent){
  $q = 'goliath_deliverables?select=*&order=created_at.desc&limit=80';
  if ($agent !== 'All') $q = 'goliath_deliverables?select=*&agent=eq.'.rawurlencode($agent).'&order=created_at.desc&limit=80';
  foreach(gw_req($q) as $r){
    $json = gw_json($r['content_json'] ?? []);
    $summary = gw_safe($r,['summary','content_text'], 'Open finished work');
    if (!$summary && $json) $summary = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $items[] = gw_item($r['agent'] ?? $agent, $r['deliverable_type'] ?? 'deliverable', $r['title'] ?? 'Finished Deliverable', $summary, $r['status'] ?? 'ready', $r['score'] ?? 0, 'goliath_deliverables', $r, $r['action_url'] ?? '');
  }
}
function gw_agent_rows($agent){
  $items=[];
  gw_add_deliverables($items,$agent);

  if ($agent==='Scout' || $agent==='All') {
    foreach(gw_req('leads?select=*&order=created_at.desc&limit=60') as $r){
      $title = trim((gw_safe($r,['name'],'New Lead')).' · '.gw_safe($r,['town','address'],'Lead'));
      $sub = trim('Phone: '.gw_safe($r,['phone'],'needed').' · Email: '.gw_safe($r,['email'],'needed').' · '.gw_safe($r,['address','message'],'No address yet'));
      $items[] = gw_item('Scout','lead_file',$title,$sub,'ready',(int)($r['lead_score']??0),'leads',$r);
    }
    foreach(gw_req('homeowner_intelligence?select=*&order=lead_score.desc,created_at.desc&limit=60') as $r){
      $title = trim((gw_safe($r,['owner_name'],'Owner')).' · '.gw_safe($r,['town','address'],'Homeowner'));
      $sub = trim('Phone: '.gw_safe($r,['phone'],'needed').' · Equity: '.gw_safe($r,['estimated_equity'],'unknown').' · '.gw_safe($r,['motivation_signal','notes'],'research file'));
      $items[] = gw_item('Scout','owner_intelligence',$title,$sub,gw_safe($r,['status'],'ready'),(int)($r['lead_score']??0),'homeowner_intelligence',$r);
    }
    foreach(gw_req('hunter_queue?select=*&order=hunter_score.desc,created_at.desc&limit=60') as $r){
      $title = trim((gw_safe($r,['owner_name'],'Hunter Target')).' · '.gw_safe($r,['town','address'],'Opportunity'));
      $sub = trim('Phone: '.gw_safe($r,['phone'],'needed').' · '.gw_safe($r,['reason','priority'],'hunter queue'));
      $items[] = gw_item('Scout','hunter_target',$title,$sub,gw_safe($r,['status'],'queued'),(int)($r['hunter_score']??0),'hunter_queue',$r);
    }
    foreach(gw_req('owner_research_queue?select=*&order=created_at.desc&limit=60') as $r){
      $title = trim((gw_safe($r,['owner_name','name'],'Owner Research')).' · '.gw_safe($r,['town','property_address','address'],'Research'));
      $sub = trim('Phone: '.gw_safe($r,['phone','found_phone'],'needed').' · '.gw_safe($r,['property_address','address','notes'],'research queue'));
      $items[] = gw_item('Scout','research_file',$title,$sub,gw_safe($r,['status'],'queued'),(int)gw_safe($r,['confidence','score'],0),'owner_research_queue',$r);
    }
  }

  if ($agent==='Jessica' || $agent==='All') {
    foreach(gw_req('jessica_priority_queue?select=*&order=priority_score.desc,created_at.desc&limit=80') as $r){
      $title = trim((gw_safe($r,['name'],'Jessica Priority')).' · '.gw_safe($r,['town','source'],'Follow Up'));
      $sub = trim(gw_safe($r,['suggested_action','reason'],'Ready for relationship follow-up').' · Phone: '.gw_safe($r,['phone'],'needed').' · Email: '.gw_safe($r,['email'],'needed'));
      $items[] = gw_item('Jessica','relationship_priority',$title,$sub,gw_safe($r,['status'],'pending'),(int)($r['priority_score']??0),'jessica_priority_queue',$r);
    }
    foreach(gw_req('lead_followup_queue?select=*&order=scheduled_for.asc,created_at.desc&limit=80') as $r){
      $title = trim((gw_safe($r,['lead_name'],'Follow Up')).' · '.gw_safe($r,['channel'],'email'));
      $sub = trim(gw_safe($r,['subject','message'],'Communication ready').' · '.gw_safe($r,['lead_email','lead_phone'],'recipient needed'));
      $items[] = gw_item('Jessica','communication',$title,$sub,gw_safe($r,['status'],'queued'),0,'lead_followup_queue',$r);
    }
    foreach(gw_req('appointment_requests?select=*&order=created_at.desc&limit=60') as $r){
      $title = trim((gw_safe($r,['name'],'Appointment')).' · '.gw_safe($r,['town','appointment_type'],'Requested'));
      $sub = trim(gw_safe($r,['requested_window','preferred_time','notes'],'appointment request').' · Phone: '.gw_safe($r,['phone'],'needed'));
      $items[] = gw_item('Jessica','appointment',$title,$sub,gw_safe($r,['status'],'requested'),(int)($r['lead_score']??0),'appointment_requests',$r);
    }
  }

  if ($agent==='Shakespeare' || $agent==='All') {
    foreach(gw_req('seo_aeo_content_opportunities?select=*&order=priority_score.desc,created_at.desc&limit=80') as $r){
      $title = gw_safe($r,['title','keyword_primary'],'Content Draft');
      $sub = gw_safe($r,['meta_description','search_intent','notes'],'Draft or content opportunity ready');
      $items[] = gw_item('Shakespeare','content_draft',$title,$sub,gw_safe($r,['status'],'draft'),(int)($r['priority_score']??0),'seo_aeo_content_opportunities',$r);
    }
    foreach(gw_req('content_calendar?select=*&order=created_at.desc&limit=60') as $r){
      $items[] = gw_item('Shakespeare','content_calendar',gw_safe($r,['title','topic'],'Content Calendar Item'),gw_safe($r,['summary','caption','notes'],'Scheduled content'),gw_safe($r,['status'],'ready'),0,'content_calendar',$r);
    }
  }

  if ($agent==='Scorsese' || $agent==='All') {
    foreach(gw_req('media_projects?select=*&order=created_at.desc&limit=80') as $r){
      $title = gw_safe($r,['title','project_title'],'Media Project');
      $sub = gw_safe($r,['source_url','summary','prompt'],'Video package ready for review');
      $items[] = gw_item('Scorsese','video_package',$title,$sub,gw_safe($r,['status'],'review_ready'),(int)gw_safe($r,['viral_score','score'],0),'media_projects',$r,'/dashboard/goliath-studio.php?project='.rawurlencode((string)($r['id']??'')) );
    }
    foreach(gw_req('creative_generation_queue?select=*&order=created_at.desc&limit=60') as $r){
      $items[] = gw_item('Scorsese','creative_queue',gw_safe($r,['title','prompt'],'Creative Queue Item'),gw_safe($r,['prompt','notes'],'Creative production queued'),gw_safe($r,['status'],'queued'),0,'creative_generation_queue',$r);
    }
  }

  if ($agent==='Mozart' || $agent==='All') {
    foreach(gw_req('music_projects?select=*&order=created_at.desc&limit=80') as $r){
      $items[] = gw_item('Mozart','music_project',gw_safe($r,['title','song_title'],'Music Project'),gw_safe($r,['summary','notes','prompt'],'Song/audio package'),gw_safe($r,['status'],'ready'),(int)gw_safe($r,['emotion_score','score'],0),'music_projects',$r);
    }
  }

  if ($agent==='Columbo' || $agent==='All') {
    foreach(gw_req('columbo_content_finds?select=*&order=repurpose_score.desc,created_at.desc&limit=80') as $r){
      $title = gw_safe($r,['title','video_title'],'Archive Gold');
      $sub = trim(gw_safe($r,['platform'],'Archive').' · '.gw_safe($r,['recommended_format'],'clip').' · '.gw_safe($r,['recommended_hook','notes'],'gold moment'));
      $items[] = gw_item('Columbo','archive_gold',$title,$sub,gw_safe($r,['status'],'found'),(int)($r['repurpose_score']??0),'columbo_content_finds',$r);
    }
  }

  if ($agent==='Prospector' || $agent==='All') {
    foreach(gw_req('prospector_opportunities?select=*&order=opportunity_score.desc,created_at.desc&limit=80') as $r){
      $title = gw_safe($r,['title','venue_name','owner_name','address'],'Opportunity');
      $sub = trim(gw_safe($r,['opportunity_type','category'],'prospect').' · '.gw_safe($r,['town','location'],'').' · Contact: '.gw_safe($r,['phone','email','contact_name'],'needed'));
      $items[] = gw_item('Prospector','opportunity',$title,$sub,gw_safe($r,['status'],'review'),(int)($r['opportunity_score']??0),'prospector_opportunities',$r);
    }
    foreach(gw_req('hunter_campaigns?select=*&order=created_at.desc&limit=60') as $r){
      $items[] = gw_item('Prospector','campaign',gw_safe($r,['name'],'Hunter Campaign'),gw_safe($r,['notes','campaign_segment'],'Opportunity campaign'),gw_safe($r,['status'],'active'),(int)gw_safe($r,['min_hunter_score'],0),'hunter_campaigns',$r);
    }
  }

  if ($agent==='Rockefeller' || $agent==='All') {
    foreach(gw_req('mark_action_queue?select=*&order=due_at.asc,created_at.desc&limit=80') as $r){
      $title = trim(gw_safe($r,['recommended_action','action_type'],'Founder Action').' · '.gw_safe($r,['name','town'],'Priority'));
      $sub = trim('Phone: '.gw_safe($r,['phone'],'needed').' · '.gw_safe($r,['notes','source'],'action queue'));
      $items[] = gw_item('Rockefeller','priority_action',$title,$sub,gw_safe($r,['status'],'open'),0,'mark_action_queue',$r);
    }
    foreach(gw_req('hot_lead_alerts?select=*&order=lead_score.desc,created_at.desc&limit=60') as $r){
      $title = trim((gw_safe($r,['name'],'Hot Lead')).' · '.gw_safe($r,['town','source'],'Alert'));
      $sub = trim(gw_safe($r,['reason','recommended_action'],'High priority').' · Phone: '.gw_safe($r,['phone'],'needed'));
      $items[] = gw_item('Rockefeller','hot_alert',$title,$sub,gw_safe($r,['status'],'new'),(int)($r['lead_score']??0),'hot_lead_alerts',$r);
    }
  }

  if ($agent==='Pandora' || $agent==='All') {
    foreach(gw_req('business_expansion_opportunities?select=*&order=created_at.desc&limit=80') as $r){
      $items[] = gw_item('Pandora','expansion',gw_safe($r,['title','name'],'Expansion Opportunity'),gw_safe($r,['summary','notes','description'],'Expansion idea'),gw_safe($r,['status'],'review'),(int)gw_safe($r,['score','opportunity_score'],0),'business_expansion_opportunities',$r);
    }
  }

  if ($agent==='Einstein' || $agent==='All') {
    foreach(gw_req('adaptive_intelligence_rules?select=*&order=updated_at.desc&limit=80') as $r){
      $title = trim(gw_safe($r,['rule_key'],'Intelligence Rule').' · '.gw_safe($r,['confidence'],'confidence'));
      $sub = trim(gw_safe($r,['recommendation'],'Decision intelligence').' · Conversion: '.gw_safe($r,['conversion_rate'],'0'));
      $items[] = gw_item('Einstein','analysis',$title,$sub,'ready',(int)gw_safe($r,['score_adjustment'],0),'adaptive_intelligence_rules',$r);
    }
    foreach(gw_req('conversion_events?select=*&order=created_at.desc&limit=60') as $r){
      $items[] = gw_item('Einstein','conversion_event',gw_safe($r,['event_type'],'Conversion Event'),gw_safe($r,['page_url','town','source'],'analytics event'),'ready',0,'conversion_events',$r);
    }
  }

  if ($agent==='Goliath' || $agent==='All') {
    foreach(gw_req('goliath_events?select=*&order=created_at.desc&limit=80') as $r){
      $items[] = gw_item('Goliath','council_event',gw_safe($r,['title'],'Executive Council Event'),gw_safe($r,['detail','event_type'],'Council activity'),gw_safe($r,['status'],'active'),(int)gw_safe($r,['confidence'],0),'goliath_events',$r,gw_safe($r,['link_url'],''));
    }
    foreach(gw_req('goliath_missions?select=*&order=created_at.desc&limit=60') as $r){
      $items[] = gw_item('Goliath','mission',gw_safe($r,['title','mission_title'],'Goliath Mission'),gw_safe($r,['summary','mission_type','status'],'Mission'),gw_safe($r,['status'],'open'),0,'goliath_missions',$r,'/dashboard/goliath-mission.php?mission_id='.rawurlencode((string)gw_safe($r,['id','mission_id'],'')) );
    }
    foreach(gw_req('local_ai_tasks?select=*&order=created_at.desc&limit=80') as $r){
      $m = gw_json($r['metadata'] ?? []);
      $a = $m['agent'] ?? 'Goliath';
      $title = $a.' · '.gw_safe($r,['task_type'],'AI Task');
      $sub = mb_substr(gw_safe($r,['prompt'],'Queued work'),0,240);
      $items[] = gw_item($a,'queued_task',$title,$sub,gw_safe($r,['status'],'queued'),(int)gw_safe($r,['priority'],0),'local_ai_tasks',$r);
    }
  }
  return $items;
}

$agent = $_GET['department'] ?? $_GET['agent'] ?? $_GET['dept'] ?? 'All';
$valid = ['All','Jessica','Scout','Scorsese','Mozart','Shakespeare','Einstein','Columbo','Prospector','Rockefeller','Pandora','Goliath'];
if (!in_array($agent,$valid,true)) $agent='All';
$items = gw_agent_rows($agent);
// Deduplicate similar IDs/sources
$seen=[]; $out=[];
foreach($items as $it){
  $key=($it['source']??'').'|'.($it['id']??'').'|'.($it['title']??'');
  if(isset($seen[$key])) continue; $seen[$key]=1; $out[]=$it;
}
usort($out,function($a,$b){
  $sa=(int)($a['score']??0); $sb=(int)($b['score']??0);
  if($sa!==$sb) return $sb<=>$sa;
  return strcmp((string)($b['created_at']??''),(string)($a['created_at']??''));
});
$ready=[]; $queued=[];
foreach($out as $it){
  $s=strtolower((string)($it['status']??''));
  if(in_array($s,['queued','pending','running','working','review','draft','open','active','requested'],true)) $queued[]=$it;
  else $ready[]=$it;
}
echo json_encode(['success'=>true,'department'=>$agent,'count'=>count($out),'ready_count'=>count($ready),'queued_count'=>count($queued),'items'=>$out,'ready'=>$ready,'queued'=>$queued], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
