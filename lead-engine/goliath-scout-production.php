<?php
/**
 * Goliath Scout Production v53
 * Creates immediate Scout lead deliverables from real owned/approved records already in Supabase.
 * This does not scrape or fabricate data.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/goliath-v53-lib.php';

if(!g53_key_ok()){
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'bad_key']);
  exit;
}

$limit = max(1, min(250, (int)($_GET['limit'] ?? 50)));
$hours = max(1, min(720, (int)($_GET['hours'] ?? 72)));
$since = gmdate('c', time() - $hours*3600);
$sources = [];
$leads = [];

function scout_load($table,$select,$order='created_at.desc',$limit=50,$since=null){
  $ep = $table.'?select='.$select.'&order='.$order.'&limit='.$limit;
  if($since) $ep .= '&created_at=gte.'.rawurlencode($since);
  $r = g53_req('GET',$ep);
  return $r['ok'] && is_array($r['data']) ? $r['data'] : [];
}
function scout_add_leads(&$leads,$records,$source){
  foreach($records as $r){
    $lead = [
      'id'=>$r['id'] ?? null,
      'name'=>$r['name'] ?? ($r['owner_name'] ?? ($r['lead_name'] ?? '')),
      'phone'=>$r['phone'] ?? ($r['lead_phone'] ?? ''),
      'email'=>$r['email'] ?? ($r['lead_email'] ?? ''),
      'address'=>$r['address'] ?? '',
      'town'=>$r['town'] ?? '',
      'lead_type'=>$r['type'] ?? ($r['lead_type'] ?? 'lead'),
      'source'=>$r['source'] ?? $source,
      'confidence'=>$r['lead_score'] ?? ($r['priority_score'] ?? 80),
      'reason'=>$r['message'] ?? ($r['notes'] ?? ($r['goal'] ?? 'Existing approved record in Goliath.')),
      'next_action'=> (!empty($r['phone']) || !empty($r['lead_phone'])) ? 'call' : ((!empty($r['email']) || !empty($r['lead_email'])) ? 'email' : 'research')
    ];
    if(g53_valid_real_lead($lead)) $leads[] = $lead;
  }
}

$website = scout_load('leads','id,name,email,phone,address,town,type,source,message,goal,lead_score,created_at','created_at.desc',$limit,$since);
$sources['leads'] = count($website);
scout_add_leads($leads,$website,'website_leads');

$imports = scout_load('compliant_lead_imports','id,name,email,phone,address,town,lead_type,source_name,source_type,lead_score,approval_status,notes,created_at','created_at.desc',$limit,null);
$sources['compliant_lead_imports'] = count($imports);
foreach($imports as &$i){ $i['source'] = trim(($i['source_name'] ?? '').' '.($i['source_type'] ?? '')); }
unset($i);
scout_add_leads($leads,$imports,'compliant_imports');

$homeowners = scout_load('homeowner_intelligence','id,owner_name,email,phone,address,town,lead_score,priority,notes,source,created_at','created_at.desc',$limit,null);
$sources['homeowner_intelligence'] = count($homeowners);
scout_add_leads($leads,$homeowners,'homeowner_intelligence');

// Deduplicate by phone/email/address.
$seen=[]; $dedup=[];
foreach($leads as $l){
  $key = strtolower(trim(($l['phone'] ?? '').'|'.($l['email'] ?? '').'|'.($l['address'] ?? '')));
  if(!$key || isset($seen[$key])) continue;
  $seen[$key]=true; $dedup[]=$l;
}
$leads = array_slice($dedup,0,$limit);

$json = [
  'status'=>count($leads) ? 'completed' : 'blocked',
  'summary'=>count($leads) ? 'Scout found real owned/approved lead records ready for review.' : 'No usable owned/approved lead records found yet.',
  'leads'=>$leads,
  'sources_checked'=>$sources,
  'missing_sources'=>count($leads) ? [] : ['website form submissions','approved MLS/expired export','FSBO CSV','vendor/public-record import','homeowner_intelligence records with phone/email/address'],
  'next_actions'=>count($leads) ? ['Open Scout workdesk','Call highest-score phone numbers','Run Jessica communication queue'] : ['Add or connect an approved lead source','Test website valuation form','Import compliant CSV']
];
$text = count($leads) ? g53_format_lead_batch($leads) : "Scout checked owned/approved sources but found no usable lead records.\n\nSources checked:\n".json_encode($sources,JSON_PRETTY_PRINT)."\n\nNext: feed Scout an approved lead source or submit/test the website valuation funnel.";
$title = count($leads) ? 'Scout Found '.count($leads).' Real Lead Record(s)' : 'Scout Needs Approved Lead Source';
$deliverable = g53_create_deliverable('Scout','lead_batch',$title,$text,$json,['source'=>'goliath-scout-production-v53','sources_checked'=>$sources],'critical');
$link = '/dashboard/goliath-deliverables.php?agent=Scout';
if($deliverable['ok'] && is_array($deliverable['data']) && isset($deliverable['data'][0]['id'])) $link = '/dashboard/goliath-deliverables.php?id='.rawurlencode($deliverable['data'][0]['id']);
g53_event('Scout',$title,count($leads) ? 'Open Scout deliverable for phone/email/address.' : 'Scout needs a real source before phone numbers can appear.',$link,['leads_count'=>count($leads),'sources'=>$sources],95, count($leads)*3500);

echo json_encode([
  'success'=>true,
  'script'=>'goliath-scout-production-v53',
  'leads_count'=>count($leads),
  'sources_checked'=>$sources,
  'deliverable_ok'=>$deliverable['ok'],
  'deliverable_url'=>$link,
  'next'=>count($leads) ? 'Open the deliverable URL to see phone/email/address immediately.' : 'Submit/test the valuation form or import an approved list, then run this again.'
], JSON_PRETTY_PRINT);
