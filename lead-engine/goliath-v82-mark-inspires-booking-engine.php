<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
if(file_exists(__DIR__.'/goliath-v77-1-knowledge-loader.php')) require_once __DIR__.'/goliath-v77-1-knowledge-loader.php';
header('Content-Type: application/json; charset=utf-8');

$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

function t($x){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$x]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function c($t,$x){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$x]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
function uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
function js($v){return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
function ins($table,$row){$safe=[];foreach($row as $k=>$v){if(c($table,$k))$safe[$k]=$v;}return $safe?gdb_insert($table,$safe):null;}
function px($exec,$title,$instructions,$meta=[]){return function_exists('gv771_prompt')?gv771_prompt($exec,$title,$instructions,$meta):$instructions;}

$title='V82 Mark insPires Speaking Booking Engine';
$goal='Launch Mark insPires as a motivational speaking offer and build a verified booking pipeline for corporate, school, chamber, real estate, entrepreneurship, innovation, community and creative events. Use the private EPK page as Jessica’s outreach destination. Never invent contacts, pay rates, or event interest.';

$commission_id=ins('executive_commissions',[
  'commission_uid'=>uid('commission'),
  'executive_key'=>'goliath',
  'executive'=>'Goliath',
  'title'=>$title,
  'description'=>$goal,
  'prompt'=>$goal,
  'status'=>'queued',
  'priority'=>345,
  'progress'=>0,
  'current_step'=>'Mark insPires speaker booking engine seeded',
  'metadata'=>js(['epk_url'=>'/mark-inspires.php?key=timetomakethedonuts','youtube'=>'https://youtube.com/live/pkXAGKMrBnY']),
  'created_at'=>gdb_now(),
  'updated_at'=>gdb_now()
]);

$team=[
  'prospector'=>['asset'=>'verified_speaking_pipeline','role'=>'Find high-value speaking opportunities in CT, NY, NJ, MA: chambers, corporate events, schools, real estate conferences, entrepreneurship groups, podcasts, radio and innovation events.'],
  'scout'=>['asset'=>'verified_contact_pack','role'=>'Verify organizers, booking emails, speaker submission pages, contacts, forms and event calendars.'],
  'jessica'=>['asset'=>'speaker_outreach_email_drip','role'=>'Write warm Jessica Gregory outreach email, follow-up after 1 week, and short LinkedIn/DM variations using the EPK link.'],
  'shakespeare'=>['asset'=>'speaker_epk_copy_upgrade','role'=>'Improve speaker bio, topic descriptions, one-sheet copy, CTA, intro paragraph, and booking pitch language.'],
  'scorsese'=>['asset'=>'speaker_media_package','role'=>'Create video short prompts, thumbnail concepts, montage plan and visual assets for the Mark insPires EPK.'],
  'columbo'=>['asset'=>'youtube_speaker_growth_package','role'=>'Create YouTube title/description/chapters/shorts ideas from the full Mark insPires episode.'],
  'rockefeller'=>['asset'=>'speaker_revenue_priority_plan','role'=>'Rank opportunities by likely fee, prestige, repeatability, travel time, and referral value.'],
  'pandora'=>['asset'=>'creative_speaking_angles','role'=>'Find creative angles: innovation, positive change, authentic self, BeatSeat, Realtor influencer, creator economy, music/speaking hybrid.'],
  'einstein'=>['asset'=>'speaker_seo_aeo_plan','role'=>'Create SEO/AEO strategy for Mark insPires, schema, FAQ targets, and answer-engine positioning.'],
  'mozart'=>['asset'=>'speaker_audio_music_package','role'=>'Recommend intro/outro music, live music integration, audience participation, and BeatSeat demo sequence.'],
  'goliath'=>['asset'=>'speaker_council_brief','role'=>'Final briefing: best targets, first 25 Jessica contacts, needed media assets, and next approvals.']
];

$tasks=[];
foreach($team as $exec=>$cfg){
  $instructions="COMMISSION ID: {$commission_id}\nMISSION: {$title}\n\nGOAL:\n{$goal}\n\nEPK URL:\nhttps://www.markpires.com/mark-inspires.php?key=timetomakethedonuts\nFULL SPEECH:\nhttps://youtube.com/live/pkXAGKMrBnY\n\nYOUR ROLE:\n{$cfg['role']}\n\nREQUIRED ASSET:\n{$cfg['asset']}\n\nMARK INSPIRES FACTS:\n- Theme: Affecting a Positive Change One Person At A Time.\n- Social media Realtor influencer entertaining 100,000+ followers across platforms.\n- Inventor/patent holder of The BeatSeat.\n- Former MTV artist; Song of the Year finalist; 209 original songs; one-man-band performer.\n- First Realtor in CT to offer drone aerial photography in 2013.\n- First podcast studio built by a real estate firm in CT in 2019.\n- Creator of Discover CT, The House Detective, American Renovation, and Get To Know America.\n- Speech topics: never giving up, entrepreneurship, inventing/patenting, power of a smile, authentic self, positive change.\n\nRULES:\n- No fabricated contacts, booking agents, fees, or events.\n- Every opportunity needs evidence URL/contact page or mark NEEDS_VERIFIED_CONTACT.\n- Output must be actionable for Mark/Jessica.";
  $task_id=ins('local_ai_tasks',[
    'task_uid'=>uid('lat'),
    'commission_id'=>$commission_id,
    'agent'=>ucfirst($exec),
    'task_type'=>'v82_mark_inspires_speaker_booking_engine',
    'model'=>'goliath-local-worker',
    'prompt'=>px($exec,$title,$instructions,['commission_id'=>$commission_id,'expected_asset_type'=>$cfg['asset'],'epk_url'=>'/mark-inspires.php?key=timetomakethedonuts']),
    'status'=>'queued',
    'priority'=>($exec==='goliath'?260:350),
    'progress'=>0,
    'metadata'=>js(['commission_id'=>$commission_id,'expected_asset_type'=>$cfg['asset'],'epk_url'=>'/mark-inspires.php?key=timetomakethedonuts']),
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
  ]);
  $tasks[]=['executive'=>$exec,'asset'=>$cfg['asset'],'task_id'=>$task_id,'commission_id'=>$commission_id];
}

echo json_encode(['ok'=>true,'version'=>'V82 Mark insPires Speaker Booking Engine','commission_id'=>$commission_id,'tasks'=>$tasks,'epk_url'=>'/mark-inspires.php?key=timetomakethedonuts','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>