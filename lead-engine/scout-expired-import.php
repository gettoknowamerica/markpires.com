<?php
/**
 * Goliath Omni OS v57.5.1
 * Scout CSV Upload Import
 *
 * Purpose:
 * - Save every CSV upload into /public_html/data/scout_uploads/
 * - Import expired listing CSV rows into Scout Opportunity Files
 * - Return clear diagnostics when the uploaded CSV is the wrong kind of file
 */
require_once __DIR__.'/config.php';
require_once __DIR__.'/scout-revenue-pipeline-lib.php';

$key = $_POST['key'] ?? $_GET['key'] ?? '';
if(!srp_key_ok($key)) srp_json(['success'=>false,'error'=>'Invalid key'],403);

if(empty($_FILES['csv']['tmp_name'])){
  srp_json(['success'=>false,'error'=>'Missing CSV file field named csv'],400);
}

function scout_upload_dir(){
  $dir = realpath(__DIR__.'/..'); // public_html
  if(!$dir) $dir = dirname(__DIR__);
  $target = $dir.'/data/scout_uploads';
  if(!is_dir($target)) @mkdir($target, 0755, true);
  return $target;
}
function scout_safe_file_name($name){
  $name = basename((string)$name);
  $name = preg_replace('/[^A-Za-z0-9._-]+/','-', $name);
  return trim($name,'-') ?: 'upload.csv';
}
function scout_header_has($keys,$needles){
  foreach($needles as $n){ if(in_array(srp_slug_header($n), $keys, true)) return true; }
  return false;
}

$fileName = scout_safe_file_name($_FILES['csv']['name'] ?? 'expired-listings.csv');
$uploadDir = scout_upload_dir();
$savedName = gmdate('Ymd-His').'-'.$fileName;
$savedPath = rtrim($uploadDir,'/').'/'.$savedName;
@copy($_FILES['csv']['tmp_name'], $savedPath);

$fh = fopen($_FILES['csv']['tmp_name'],'r');
if(!$fh) srp_json(['success'=>false,'error'=>'Could not read uploaded CSV','saved_path'=>$savedPath],400);

$headers = fgetcsv($fh);
if(!$headers) srp_json(['success'=>false,'error'=>'CSV has no header row','saved_path'=>$savedPath],400);
$keys = array_map('srp_slug_header',$headers);

$rows = [];
while(($cols = fgetcsv($fh)) !== false){
  if(count(array_filter($cols, fn($v)=>trim((string)$v)!==''))===0) continue;
  $row = [];
  foreach($keys as $i=>$k) $row[$k] = $cols[$i] ?? '';
  $rows[] = $row;
}
fclose($fh);

// Detect common wrong upload: agent/contact master list, not expired listings.
$looksAgentMaster = scout_header_has($keys,['full_name','userid','office_name','office_city','office_phone','mls_user_types','mls_agent_type']);
$hasExpiredSignals = scout_header_has($keys,[
  'property address','address','street address','listing address','mls','mls #','mls number','list price','expired date','expiration date','date expired','status date','dom','days on market'
]);

if($looksAgentMaster && !$hasExpiredSignals){
  srp_json([
    'success'=>false,
    'version'=>'57.5.1',
    'imported'=>0,
    'errors'=>0,
    'saved_to'=>'/data/scout_uploads/'.$savedName,
    'message'=>'CSV saved, but this looks like the agent/master contact export, not an expired listing CSV. Scout did not import it as expired listings because there is no property address or MLS listing data.',
    'detected_headers'=>$keys,
    'needed_headers'=>'For expired listings include Property Address or MLS #, plus Town, Owner Name, Phone/Email if available, List Price, Expired Date, Previous Agent/Brokerage, DOM, Notes.',
    'next'=>'Use the expired listing/CRV CSV for this importer. The saved file is preserved in public_html/data/scout_uploads for later contact-import wiring.'
  ]);
}

$import = srp_sb('POST','scout_revenue_imports', [[
  'import_type'=>'expired_listing',
  'file_name'=>$fileName,
  'rows_received'=>count($rows),
  'status'=>'started',
  'notes'=>'Scout expired listing import started. Saved original upload to /data/scout_uploads/'.$savedName,
  'raw_payload'=>['headers'=>$headers,'saved_file'=>'/data/scout_uploads/'.$savedName]
]]);
$importId = $import['ok'] && !empty($import['data'][0]['id']) ? $import['data'][0]['id'] : null;

$created=[]; $skipped=[]; $errors=[]; $imported=0;
foreach($rows as $idx=>$r){
  $owner = srp_pick($r,['owner name','owner','seller','name','client name','owner_name','seller name','tax owner']);
  $addr  = srp_pick($r,['property address','address','street address','listing address','full address','property_address','location','street']);
  $town  = srp_pick($r,['town','city','municipality','area','property city']);
  $phone = srp_pick($r,['phone','phone number','cell','mobile','owner phone','seller phone','contact phone','telephone']);
  $email = srp_pick($r,['email','owner email','seller email','contact email']);
  $mls   = srp_pick($r,['mls','mls #','mls number','listing id','list number','mls_number']);
  $price = srp_num(srp_pick($r,['list price','price','asking price','original price','current price','list_price']));
  $expired = srp_date(srp_pick($r,['expired date','expiration date','date expired','status date','off market date','expired_date']));
  $agent = srp_pick($r,['previous agent','listing agent','agent','list agent','la name','list_agent']);
  $broker = srp_pick($r,['previous brokerage','brokerage','office','listing office','lo name','brokerage name','office_name']);
  $dom = (int)srp_num(srp_pick($r,['dom','days on market','market days','cdom','days_on_market'],0));
  $notes = srp_pick($r,['notes','remarks','description','public remarks','private remarks','comments']);

  if(!$addr && !$mls){
    $skipped[]=['row'=>$idx+2,'reason'=>'missing_property_address_and_mls','sample'=>$r];
    continue;
  }

  $hash = srp_source_hash('expired_csv',$addr,$town,$mls);
  $score = srp_score('expired_listing',$price,$phone,$email,$town,$dom);
  $base = [
    'source'=>'expired_csv',
    'source_url'=>null,
    'source_hash'=>$hash,
    'opportunity_type'=>'expired_listing',
    'owner_name'=>$owner,
    'property_address'=>$addr,
    'town'=>$town,
    'state'=>'CT',
    'phone'=>$phone,
    'email'=>$email,
    'list_price'=>$price,
    'expired_date'=>$expired,
    'mls_number'=>$mls,
    'previous_agent'=>$agent,
    'previous_brokerage'=>$broker,
    'days_on_market'=>$dom ?: null,
    'lead_score'=>$score,
    'priority'=>$score>=85?'hot':($score>=70?'high':'review'),
    'status'=>'new',
    'next_executive'=>'Jessica',
    'scout_summary'=>"Expired listing opportunity imported for speed-to-lead follow-up. Scout priority score {$score}.",
    'recommended_action'=>'Call first thing, prepare second-opinion pitch, door-drop package, and follow-up sequence.',
    'raw_payload'=>$r,
    'import_id'=>$importId,
    'updated_at'=>gmdate('c')
  ];
  $scripts = srp_scripts($base);
  $base = array_merge($base,$scripts);
  $up = srp_sb('POST','scout_opportunity_files?on_conflict=source_hash', [$base], 'resolution=merge-duplicates,return=representation');
  if(!$up['ok']){
    $errors[]=['row'=>$idx+2,'stage'=>'opportunity_upsert','response'=>$up];
    continue;
  }
  $opp = $up['data'][0] ?? null;
  $oppId = $opp['id'] ?? null;

  $leadRow = [
    'name'=>$owner ?: 'Expired Listing Owner',
    'email'=>$email,
    'phone'=>$phone,
    'address'=>$addr,
    'town'=>$town,
    'type'=>'expired_listing',
    'tag'=>'Scout Expired Import',
    'message'=>$notes ?: 'Expired listing imported by Scout.',
    'price_range'=>$price ? (string)$price : null,
    'source'=>'scout_expired_csv',
    'lead_score'=>$score,
    'route'=>$score>=80?'mark_priority':'jessica_followup',
    'mls_number'=>$mls,
    'expired_date'=>$expired,
    'previous_agent'=>$agent,
    'previous_brokerage'=>$broker,
    'days_on_market'=>$dom ?: null,
    'created_at'=>gmdate('c'),
    'updated_at'=>gmdate('c')
  ];
  $lead = srp_sb('POST','leads', [$leadRow]);
  $leadId = ($lead['ok'] && !empty($lead['data'][0]['id'])) ? $lead['data'][0]['id'] : null;

  $queueRow = [
    'source'=>'expired_csv',
    'lead_id'=>$leadId,
    'owner_name'=>$owner,
    'property_address'=>$addr,
    'town'=>$town,
    'state'=>'CT',
    'price'=>$price,
    'status'=>'queued',
    'priority'=>$score,
    'recommended_action'=>'Verify owner/contact information and package for Jessica speed-to-lead outreach.',
    'source_url'=>null,
    'opportunity_type'=>'expired_listing',
    'mls_number'=>$mls,
    'import_id'=>$importId,
    'opportunity_file_id'=>$oppId,
    'metadata'=>['expired_date'=>$expired,'previous_agent'=>$agent,'previous_brokerage'=>$broker,'dom'=>$dom,'notes'=>$notes]
  ];
  $queue = srp_sb('POST','scout_research_queue', [$queueRow]);
  $queueId = ($queue['ok'] && !empty($queue['data'][0]['id'])) ? $queue['data'][0]['id'] : null;

  $jPrompt = srp_jessica_prompt(array_merge($base,['lead_score'=>$score]));
  $task = srp_sb('POST','local_ai_tasks', [[
    'task_type'=>'v55_deliverable_commission',
    'model'=>'llama3.1:8b',
    'prompt'=>$jPrompt,
    'status'=>'queued',
    'priority'=>$score,
    'metadata'=>[
      'agent'=>'Jessica',
      'source'=>'scout_expired_import',
      'version'=>'57.5.1',
      'next_agent'=>'Mark',
      'commission_id'=>'SCOUT-EXPIRED-'.date('Ymd-His').'-'.substr($hash,0,8),
      'deliverable_type'=>'expired_listing_outreach_package',
      'opportunity_file_id'=>$oppId,
      'lead_id'=>$leadId,
      'scout_queue_id'=>$queueId
    ]
  ]]);
  $taskId = ($task['ok'] && !empty($task['data'][0]['id'])) ? $task['data'][0]['id'] : null;

  if($oppId){
    srp_sb('PATCH','scout_opportunity_files?id=eq.'.rawurlencode($oppId), [
      'lead_id'=>$leadId,
      'scout_queue_id'=>$queueId,
      'jessica_task_id'=>$taskId,
      'updated_at'=>gmdate('c')
    ]);
  }

  srp_sb('POST','goliath_events', [[
    'department'=>'Scout',
    'event_type'=>'expired_listing_imported',
    'title'=>'Expired listing imported: '.($addr ?: $mls),
    'detail'=>"Scout scored {$score}. Jessica outreach package queued.",
    'roi_estimate'=>($price ? round($price * 0.025) : 10000),
    'confidence'=>92,
    'status'=>'new',
    'phase'=>'speed_to_lead',
    'progress'=>100,
    'link_url'=>'/dashboard/scout-expired-upload.php',
    'metadata'=>['opportunity_file_id'=>$oppId,'lead_id'=>$leadId,'jessica_task_id'=>$taskId,'score'=>$score]
  ]]);

  $imported++;
  $created[]=['row'=>$idx+2,'address'=>$addr,'town'=>$town,'score'=>$score,'opportunity_file_id'=>$oppId,'lead_id'=>$leadId,'jessica_task_id'=>$taskId];
}

if($importId){
  srp_sb('PATCH','scout_revenue_imports?id=eq.'.rawurlencode($importId), [
    'rows_imported'=>$imported,
    'rows_skipped'=>count($skipped),
    'status'=>count($errors)?'completed_with_errors':'completed',
    'notes'=>"Imported {$imported} expired listing opportunities. Saved file: /data/scout_uploads/{$savedName}",
    'raw_payload'=>['created'=>$created,'skipped'=>$skipped,'errors'=>$errors,'saved_file'=>'/data/scout_uploads/'.$savedName,'headers'=>$headers],
    'updated_at'=>gmdate('c')
  ]);
}

srp_json([
  'success'=>count($errors)===0,
  'version'=>'57.5.1',
  'saved_to'=>'/data/scout_uploads/'.$savedName,
  'received'=>count($rows),
  'imported'=>$imported,
  'skipped_count'=>count($skipped),
  'skipped'=>$skipped,
  'errors'=>$errors,
  'created'=>$created,
  'diagnostic'=>$imported===0 ? 'CSV was saved, but no rows contained a recognizable Property Address or MLS #. This usually means the uploaded file is not the expired listing/CRV export.' : 'Imported into Scout and Jessica commissions queued.',
  'next'=>'Leave the V55.2 local worker running. Jessica commissions were queued automatically for imported rows.'
]);
?>
