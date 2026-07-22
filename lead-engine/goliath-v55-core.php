<?php
/**
 * Goliath Omni V55 — Core Deliverables Engine
 * Additive helper layer. Requires /lead-engine/config.php.
 * Purpose: turn Executive work into deliverables + handoffs + morning brief inputs.
 */
if (!defined('GOLIATH_V55_CORE')) define('GOLIATH_V55_CORE', true);

function g55_req($method, $endpoint, $body=null, $extraHeaders=[]){
  $url = rtrim(SUPABASE_URL,'/') . '/rest/v1/' . ltrim($endpoint,'/');
  $headers = array_merge([
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',
    'Prefer: return=representation'
  ], $extraHeaders);
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 45
  ]);
  if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
  $raw = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  $data = json_decode($raw, true);
  return ['ok'=>($http>=200 && $http<300), 'http'=>$http, 'data'=>is_array($data)?$data:$raw, 'raw'=>$raw, 'error'=>$err];
}

function g55_now(){ return gmdate('c'); }
function g55_uuidish($prefix='G55'){ return $prefix.'-'.gmdate('Ymd-His').'-'.substr(bin2hex(random_bytes(4)),0,8); }

function g55_contract($agent){
  $agent = ucfirst(strtolower((string)$agent));
  $map = [
    'Scout' => ['type'=>'seller_intelligence_file','creates'=>'complete seller/buyer intelligence files with name, phone, email, address, town, reason, confidence, next action','next'=>'Jessica'],
    'Jessica' => ['type'=>'relationship_outreach_package','creates'=>'warm email, SMS/call notes, follow-up plan, relationship summary, send/hold recommendation','next'=>'Shakespeare'],
    'Shakespeare' => ['type'=>'content_package','creates'=>'blog/article, email newsletter, social posts, SEO title, meta description, CTA','next'=>'Scorsese'],
    'Scorsese' => ['type'=>'creative_production_package','creates'=>'storyboard, video concept, thumbnail prompt, ad concept, five spiderweb ideas','next'=>'Einstein'],
    'Mozart' => ['type'=>'emotional_experience_package','creates'=>'hook analysis, emotional score, pacing notes, audio/music recommendations','next'=>'Scorsese'],
    'Einstein' => ['type'=>'decision_intelligence_brief','creates'=>'priority ranking, evidence, confidence, risk, recommendation','next'=>'Rockefeller'],
    'Rockefeller' => ['type'=>'growth_roi_recommendation','creates'=>'ROI analysis, budget recommendation, expected value, resource allocation','next'=>'Goliath'],
    'Columbo' => ['type'=>'legacy_archive_entry','creates'=>'archive entry, clips found, dates, context, repurpose ideas, preservation notes','next'=>'Goliath'],
    'Prospector' => ['type'=>'opportunity_pipeline_package','creates'=>'venues, contacts, booking agents, events, podcasts, partnership opportunities, next action','next'=>'Jessica'],
    'Pandora' => ['type'=>'strategic_expansion_opportunity','creates'=>'new business idea, expansion path, opportunity vault item, spiderweb branches','next'=>'Rockefeller'],
    'Goliath' => ['type'=>'executive_brief','creates'=>'morning brief, decisions, priorities, handoffs, founder actions','next'=>null]
  ];
  return $map[$agent] ?? ['type'=>'work_product','creates'=>'usable business asset','next'=>'Goliath'];
}

function g55_commission_prompt($agent, $title, $context='', $commissionId=null){
  $c = g55_contract($agent);
  $next = $c['next'] ?: 'Founder';
  return "You are {$agent}, a commissioned Executive of Goliath Omni.\n\n".
    "MISSION: {$title}\n\n".
    "CONSTITUTIONAL STANDARD: Do not submit an executive review. Create a tangible asset Mark can open and use immediately. If you cannot create the full asset, create the best usable draft and clearly state what is missing.\n\n".
    "YOUR DELIVERABLE TYPE: {$c['type']}\n".
    "YOU MUST CREATE: {$c['creates']}\n".
    "NEXT HANDOFF: {$next}\n\n".
    "CONTEXT:\n{$context}\n\n".
    "Return ONLY valid JSON using this exact structure:\n".
    "{\n".
    "  \"status\": \"completed\",\n".
    "  \"title\": \"specific title of the asset created\",\n".
    "  \"summary\": \"plain English summary of the finished work\",\n".
    "  \"business_impact\": 1,\n".
    "  \"asset\": {\"type\": \"{$c['type']}\", \"work_product\": \"the actual finished work Mark can use\"},\n".
    "  \"ready_for_founder\": true,\n".
    "  \"next_agent\": \"{$next}\",\n".
    "  \"next_action\": \"the next concrete action\",\n".
    "  \"handoff_notes\": \"what the next Executive needs to know\",\n".
    "  \"items\": []\n".
    "}\n\n".
    "Business impact scale: 1 low, 5 high. No filler. No generic report. Create the asset.";
}

function g55_create_commission($agent, $title, $context='', $priority=100, $source='v55', $dueAt=null){
  $commissionId = g55_uuidish('COMMISSION');
  $payload = [
    'commission_id' => $commissionId,
    'agent' => $agent,
    'title' => $title,
    'context' => $context,
    'priority' => (int)$priority,
    'status' => 'queued',
    'source' => $source,
    'due_at' => $dueAt ?: gmdate('c', strtotime('+8 hours')),
    'metadata' => ['version'=>'55.0','contract'=>g55_contract($agent)]
  ];
  $r = g55_req('POST','goliath_commissions',$payload);
  return [$commissionId, $r];
}

function g55_queue_local_task($agent, $title, $context='', $priority=100, $commissionId=null){
  if (!$commissionId) $commissionId = g55_uuidish('COMMISSION');
  $prompt = g55_commission_prompt($agent, $title, $context, $commissionId);
  $task = [
    'task_type' => 'v55_deliverable_commission',
    'model' => 'llama3.1:8b',
    'prompt' => $prompt,
    'status' => 'queued',
    'priority' => (int)$priority,
    'metadata' => [
      'version'=>'55.0',
      'agent'=>$agent,
      'commission_id'=>$commissionId,
      'title'=>$title,
      'deliverable_type'=>g55_contract($agent)['type'],
      'next_agent'=>g55_contract($agent)['next']
    ]
  ];
  return g55_req('POST','local_ai_tasks',$task);
}

function g55_first_json($text){
  if (is_array($text)) return $text;
  $text = trim((string)$text);
  if ($text === '') return null;
  $j = json_decode($text,true); if (is_array($j)) return $j;
  if (preg_match('/```json\s*([\s\S]*?)```/i',$text,$m)){ $j=json_decode(trim($m[1]),true); if(is_array($j))return $j; }
  $s=strpos($text,'{'); $e=strrpos($text,'}'); if($s!==false && $e>$s){$j=json_decode(substr($text,$s,$e-$s+1),true); if(is_array($j))return $j;}
  return null;
}

function g55_create_deliverable($agent, $commissionId, $title, $summary, $contentText, $contentJson=[], $status='ready', $priority='normal', $businessImpact=3, $nextAgent=null, $nextAction='Review'){
  $contract = g55_contract($agent);
  $type = $contract['type'];
  $nextAgent = $nextAgent ?: $contract['next'];
  $body = [
    'agent'=>$agent,
    'deliverable_type'=>$type,
    'title'=>$title ?: ($agent.' Deliverable'),
    'status'=>$status,
    'priority'=>$priority,
    'content_text'=>mb_substr((string)$contentText,0,120000),
    'content_json'=>is_array($contentJson) ? $contentJson : new stdClass(),
    'action_url'=>'/dashboard/goliath-agent-detail.php?department='.rawurlencode($agent),
    'metadata'=>[
      'version'=>'55.0',
      'commission_id'=>$commissionId,
      'summary'=>$summary,
      'business_impact'=>(int)$businessImpact,
      'next_agent'=>$nextAgent,
      'next_action'=>$nextAction,
      'created_by'=>'goliath-v55-core'
    ]
  ];
  // Optional V55 columns may exist after SQL install; we try them by metadata only to avoid breaking older schemas.
  $r = g55_req('POST','goliath_deliverables',$body);
  if ($r['ok']) {
    g55_req('POST','goliath_events',[
      'department'=>$agent,
      'event_type'=>'deliverable_ready',
      'title'=>$title ?: ($agent.' Deliverable Ready'),
      'detail'=>mb_substr((string)$summary,0,700),
      'status'=>'active',
      'confidence'=>95,
      'metadata'=>['version'=>'55.0','commission_id'=>$commissionId,'next_agent'=>$nextAgent]
    ]);
  }
  return $r;
}

function g55_handoff($fromAgent, $toAgent, $deliverableId, $commissionId, $notes=''){
  if (!$toAgent) return ['ok'=>true,'data'=>['message'=>'No next agent']];
  $body = [
    'from_agent'=>$fromAgent,
    'to_agent'=>$toAgent,
    'deliverable_id'=>$deliverableId,
    'commission_id'=>$commissionId,
    'status'=>'queued',
    'handoff_notes'=>$notes,
    'metadata'=>['version'=>'55.0']
  ];
  return g55_req('POST','goliath_handoffs',$body);
}
?>
