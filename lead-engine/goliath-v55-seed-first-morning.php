<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-v55-core.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??$_POST['key']??'';
if(defined('AFTER_HOURS_CRON_KEY') && AFTER_HOURS_CRON_KEY && !hash_equals(AFTER_HOURS_CRON_KEY,$key)){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Invalid key']);exit;}

$context = trim($_POST['context'] ?? $_GET['context'] ?? 'Build the first productive morning for Mark Pires. Focus on Fairfield County CT real estate, content, leads, follow-up, venue/opportunity discovery, and the Morning Executive Brief. Create usable assets only.');
$due = gmdate('c', strtotime('tomorrow 6:00 America/New_York'));
$plan = [
  ['Scout','First Productive Morning — Seller Intelligence File','Find or draft one high-quality seller intelligence file template using available lead/homeowner context. Include phone/email fields if known, suggested first call, and missing research list.',120],
  ['Jessica','First Productive Morning — Relationship Outreach Package','Create a warm outreach package for the Scout file: email draft, call notes, follow-up timing, and relationship tone.',115],
  ['Shakespeare','First Productive Morning — Content Package','Create one publish-ready Fairfield County homeowner blog/email/social package that can support today’s seller outreach.',110],
  ['Einstein','First Productive Morning — Priority Ranking','Rank today’s first actions and explain which opportunity Mark should pursue first, using evidence and confidence.',105],
  ['Rockefeller','First Productive Morning — ROI Recommendation','Recommend where the first $100 of attention/ad budget should go today and why.',100],
  ['Prospector','First Productive Morning — Venue/Opportunity Pipeline','Find or draft a venue/opportunity pipeline for music/speaking/wineries with contact fields and next action.',95],
  ['Pandora','First Productive Morning — Expansion Opportunity','Create one strategic expansion opportunity connected to Mark’s businesses with spiderweb branches.',90],
  ['Columbo','First Productive Morning — Archive Gold','Identify one archive/content angle worth preserving or repurposing today with title, hook, and next step.',85],
  ['Mozart','First Productive Morning — Emotional Hook Analysis','Create one emotional hook/pacing package for a BeatSeat, LegacySaved, or Discover CT clip.',80],
  ['Goliath','First Productive Morning — Executive Brief','Create the morning brief format: what was prepared, what Mark should do first, what is ready for review, and what must happen next.',75]
];
$out=[];
foreach($plan as [$agent,$title,$specific,$priority]){
  [$commissionId,$cr]=g55_create_commission($agent,$title,$context."\n\nSpecific assignment: ".$specific,$priority,'first_productive_morning',$due);
  $tr=g55_queue_local_task($agent,$title,$context."\n\nSpecific assignment: ".$specific,$priority,$commissionId);
  $out[]=['agent'=>$agent,'commission_id'=>$commissionId,'commission_ok'=>$cr['ok'],'task_ok'=>$tr['ok'],'task_response'=>$tr['data']];
}
echo json_encode(['success'=>true,'version'=>'55.0','message'=>'First Productive Morning commissions queued. Local worker should now produce deliverables, not reports.','queued'=>$out],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>
