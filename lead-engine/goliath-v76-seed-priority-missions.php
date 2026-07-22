<?php
/**
 * Goliath V76.2.2 — Priority Mission Seeder
 * Fixes duplicate commission_uid by generating UID when column exists.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

function v762_table($t){
  try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}
  catch(Throwable $e){return false;}
}
function v762_col($t,$c){
  try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}
  catch(Throwable $e){return false;}
}
function v762_uid($prefix){
  if(function_exists('gdb_uid')) return gdb_uid($prefix);
  return $prefix.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
}
function v762_json($a){return json_encode($a,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
function v762_safe_insert($table,$row){
  $safe=[];
  foreach($row as $k=>$v){
    if(v762_col($table,$k)) $safe[$k]=$v;
  }
  if(!$safe) return null;
  return gdb_insert($table,$safe);
}
function v762_insert_commission($exec,$title,$prompt,$priority=200,$meta=[]){
  if(!v762_table('executive_commissions')) return null;

  // Avoid duplicates if this exact V76.2 mission was already seeded.
  $existing = null;
  try {
    $existing = gdb_one("SELECT id FROM executive_commissions WHERE executive_key=? AND title=? AND status IN ('queued','working','in_progress','processing','claimed') ORDER BY id DESC LIMIT 1",[$exec,$title]);
  } catch(Throwable $e) {}
  if($existing && !empty($existing['id'])) return (int)$existing['id'];

  $row=[
    'commission_uid'=>v762_uid('com'),
    'executive_key'=>$exec,
    'executive'=>$exec,
    'title'=>$title,
    'prompt'=>$prompt,
    'description'=>$prompt,
    'status'=>'queued',
    'progress'=>0,
    'priority'=>$priority,
    'current_task'=>$title,
    'current_step'=>'V76.2.2 priority mission queued by Mark',
    'commission_type'=>'v76_priority_mission',
    'type'=>'v76_priority_mission',
    'metadata'=>v762_json($meta),
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
  ];
  return v762_safe_insert('executive_commissions',$row);
}
function v762_insert_task($exec,$title,$prompt,$commissionId,$priority=200,$meta=[]){
  if(!v762_table('local_ai_tasks')) return null;

  // Avoid duplicate local task for same commission.
  if($commissionId){
    try{
      $existing=gdb_one("SELECT id FROM local_ai_tasks WHERE commission_id=? AND status IN ('queued','working') ORDER BY id DESC LIMIT 1",[$commissionId]);
      if($existing && !empty($existing['id'])) return (int)$existing['id'];
    }catch(Throwable $e){}
  }

  $row=[
    'task_uid'=>v762_uid('lat'),
    'commission_id'=>$commissionId,
    'agent'=>ucfirst($exec),
    'task_type'=>'v76_priority_mission',
    'model'=>'goliath-local-worker',
    'title'=>$title,
    'prompt'=>$prompt,
    'status'=>'queued',
    'priority'=>$priority,
    'progress'=>0,
    'metadata'=>v762_json($meta),
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
  ];
  return v762_safe_insert('local_ai_tasks',$row);
}

$missions=[];

$scoutPrompt=<<<PROMPT
YOU ARE SCOUT.

MARK'S PRIORITY:
Go through the uploaded/internal list of approximately 5,000 homeowners who expired or never sold and find every legally usable phone number, email, mailing address, property address, and source reference you can for Mark to call or review.

ABSOLUTE RULES:
1. Do not invent any homeowner, phone number, email, address, lead, source, or status.
2. Only use internal records, uploaded contact lists, public records, permitted lead data, or verified source URLs.
3. Respect DNC/compliance flags. If a phone source has compliance uncertainty, mark it as NEEDS_COMPLIANCE_REVIEW.
4. Every lead row must include evidence: internal record ID, source URL, upload filename, or database row ID.
5. If phone numbers cannot be found with current tools/data, produce a NEEDS_TOOL_ACCESS report listing exactly what source/tool is missing.

REQUIRED OUTPUT:
DELIVERABLE_TYPE: lead_list
EXECUTIVE: Scout
ACTIONABLE_OUTPUT: Verified expired/never-sold homeowner phone/contact enrichment list.
EVIDENCE: Internal row IDs, source URLs, upload filenames, or public-record source references.
CLICKABLE_OUTPUTS: CSV path, dashboard URL, or database record IDs.
HANDOFFS: Jessica receives verified callable contacts. Einstein receives SEO/funnel improvement notes if discovered.
NEXT_ACTION: Mark opens the verified lead list and starts highest-priority calls.

OUTPUT COLUMNS REQUIRED:
lead_id, owner_name, property_address, town, source_status, phone_1, phone_1_source, phone_1_compliance_status, phone_2, email, mailing_address, evidence_url_or_record_id, confidence_score, priority_score, recommended_script_angle, next_action.
PROMPT;

$einsteinPrompt=<<<PROMPT
YOU ARE EINSTEIN.

MARK'S PRIORITY:
Increase funnel leads on MarkPires.com through schema, AEO, SEO, indexed pages, Google visibility, and stronger local luxury real estate authority.

ABSOLUTE RULES:
1. Do not invent rankings, traffic, Search Console data, or competitors.
2. Use actual page URLs from MarkPires.com and output exact code or page fixes.
3. Every recommendation must include page URL, issue, fix, priority, expected impact, and implementation file if known.
4. If Search Console/Analytics access is unavailable, state NEEDS_VERIFIED_SEARCH_DATA and still produce on-page technical fixes from available site pages.

REQUIRED OUTPUT:
DELIVERABLE_TYPE: seo_audit
EXECUTIVE: Einstein
ACTIONABLE_OUTPUT: Funnel-lead SEO/AEO action plan with exact pages and schema/code improvements.
EVIDENCE: Page URLs, crawl findings, code references, or tool output.
CLICKABLE_OUTPUTS: Dashboard URL, report path, schema JSON, or file path.
HANDOFFS: Shakespeare turns fixes into content. Scout monitors lead/funnel impact.
NEXT_ACTION: Mark approves the highest-priority homepage/funnel schema fixes.
PROMPT;

$shakespearePrompt=<<<PROMPT
YOU ARE SHAKESPEARE.

MARK'S PRIORITY:
Create content that helps Google and AI answer engines recognize Mark Pires as the best Fairfield County luxury seller/buyer Realtor and drives leads into the home valuation funnel.

ABSOLUTE RULES:
1. Do not invent market stats. Use verified local facts or label placeholders as NEEDS_VERIFIED_DATA.
2. Every page/blog must include title, slug, meta description, H1, sections, FAQ schema, CTA, and internal links.
3. Content must connect to Mark's real differentiators: 20 years in Fairfield County, Discover CT, House Detective, white-glove service, local video authority.
4. Output must be publish-ready or clearly identify missing data.

REQUIRED OUTPUT:
DELIVERABLE_TYPE: blog_article
EXECUTIVE: Shakespeare
ACTIONABLE_OUTPUT: Publish-ready SEO/AEO page or blog designed to drive valuation/funnel leads.
EVIDENCE: Source URLs or NEEDS_VERIFIED_DATA placeholders.
CLICKABLE_OUTPUTS: HTML file path, draft page path, or dashboard review URL.
HANDOFFS: Einstein reviews schema and SEO. Scorsese creates matching short video.
NEXT_ACTION: Mark publishes or approves the draft.
PROMPT;

$items=[
  ['scout','Priority: Enrich 5,000 expired/never-sold homeowners with verified phone numbers',$scoutPrompt,250,'expired_homeowner_phone_enrichment','lead_list_csv'],
  ['einstein','Priority: Improve MarkPires.com funnel leads through SEO/AEO/schema',$einsteinPrompt,240,'seo_aeo_funnel_growth','seo_audit'],
  ['shakespeare','Priority: Create SEO/AEO luxury seller content tied to valuation funnel',$shakespearePrompt,230,'seo_content_funnel_growth','blog_article']
];

foreach($items as $it){
  [$exec,$title,$prompt,$priority,$mission,$required]=$it;
  $meta=['source'=>'mark_priority','mission'=>$mission,'required_output'=>$required,'no_fabrication'=>true,'version'=>'V76.2.2'];
  $cid=v762_insert_commission($exec,$title,$prompt,$priority,$meta);
  $tid=v762_insert_task($exec,$title,$prompt,$cid,$priority,$meta);
  $missions[]=['executive'=>$exec,'commission_id'=>$cid,'task_id'=>$tid,'title'=>$title];
}

if(v762_table('goliath_notifications')){
  @v762_safe_insert('goliath_notifications',[
    'notification_uid'=>v762_uid('note'),
    'executive'=>'Goliath',
    'title'=>'V76.2.2 priority missions queued',
    'message'=>'Scout phone enrichment, Einstein SEO/AEO funnel growth, and Shakespeare luxury content missions are queued.',
    'priority'=>'high',
    'metadata'=>v762_json(['missions'=>$missions]),
    'created_at'=>gdb_now()
  ]);
}

echo json_encode([
  'ok'=>true,
  'version'=>'V76.2.2 Priority Mission Seeder',
  'missions'=>$missions,
  'tables'=>[
    'executive_commissions'=>v762_table('executive_commissions'),
    'local_ai_tasks'=>v762_table('local_ai_tasks')
  ],
  'next'=>'Run the local worker. Then open /dashboard/goliath-deliverables.php and /dashboard/goliath-worker-output.php?exec=scout',
  'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>