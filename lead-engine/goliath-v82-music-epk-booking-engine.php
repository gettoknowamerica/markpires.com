<?php
/**
 * V82 Music EPK + Booking Engine Seeder
 * Creates a real commission for the team:
 * - build/promote Mark Pires Music EPK
 * - research highest-paying CT/NY/NJ/MA gigs
 * - prepare Jessica booking email + drip
 */
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
function promptx($exec,$title,$instructions,$meta=[]){return function_exists('gv771_prompt')?gv771_prompt($exec,$title,$instructions,$meta):$instructions;}

$title='V82 Music EPK Booking Engine: highest-paying CT/NY/NJ/MA gigs and Jessica outreach';
$goal='Create and activate Mark Pires Music EPK as a booking tool. Research high-paying live music, speaking/music hybrid, winery, venue, corporate, festival, arts-center and private event opportunities in CT, NY, NJ and MA. Prepare Jessica Gregory booking outreach and one-week drip follow-up. Never invent contacts or opportunities.';

$commission_id=null;
if(t('executive_commissions')){
  $commission_id=ins('executive_commissions',[
    'commission_uid'=>uid('commission'),
    'executive_key'=>'goliath',
    'executive'=>'Goliath',
    'title'=>$title,
    'description'=>$goal,
    'prompt'=>$goal,
    'status'=>'queued',
    'priority'=>340,
    'progress'=>0,
    'current_step'=>'Music EPK booking engine seeded',
    'metadata'=>js(['epk_url'=>'/mark-pires-music.php?key=timetomakethedonuts','regions'=>['CT','NY','NJ','MA']]),
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
  ]);
}

$team=[
  'prospector'=>['asset'=>'verified_booking_pipeline','role'=>'Find highest-paying venues, wineries, festivals, arts centers, corporate event planners, booking agents, podcasts/radio and private-event contacts across CT, NY, NJ, MA. Must include real URL/contact page/email when available.'],
  'scout'=>['asset'=>'booking_contact_research_pack','role'=>'Verify contact names, booking emails, forms, social links, and event requirements. No invented contacts.'],
  'jessica'=>['asset'=>'booking_email_and_drip_campaign','role'=>'Write warm Jessica Gregory outreach email using EPK link, plus 1-week follow-up drip, relationship-first not spammy.'],
  'shakespeare'=>['asset'=>'epk_copy_upgrade','role'=>'Improve EPK copy, artist bio, show description, one-sheet language, booking CTA and talking points.'],
  'scorsese'=>['asset'=>'epk_video_visual_package','role'=>'Create EPK montage/video player recommendations, image list, short promo video prompts, thumbnails and social visuals.'],
  'mozart'=>['asset'=>'music_setlist_sampler_strategy','role'=>'Recommend 10 originals to feature, song order, live show arc, audio polish and BeatSeat music positioning.'],
  'columbo'=>['asset'=>'youtube_booking_growth_package','role'=>'Use YouTube/shorts strategy to support bookings: titles, clips, demo reel structure, retention hooks.'],
  'rockefeller'=>['asset'=>'booking_revenue_priority_plan','role'=>'Rank opportunities by likely pay, travel effort, prestige, repeatability and revenue potential.'],
  'pandora'=>['asset'=>'creative_partnership_angles','role'=>'Find creative partnership angles: wineries, innovation talks, BeatSeat demos, songwriter workshops, Discover CT crossover.'],
  'einstein'=>['asset'=>'music_epk_seo_aeo_plan','role'=>'Create SEO/AEO strategy for hidden/public EPK, schema ideas, searches booking agents ask, and pages to create.'],
  'goliath'=>['asset'=>'executive_council_booking_brief','role'=>'Final council brief: top targets, who Jessica contacts first, what Mark needs to supply, and next actions.']
];

$tasks=[];
foreach($team as $exec=>$cfg){
  $instructions="COMMISSION ID: {$commission_id}\nMISSION: {$title}\n\nGOAL:\n{$goal}\n\nEPK URL:\nhttps://www.markpires.com/mark-pires-music.php?key=timetomakethedonuts\n\nYOUR ROLE:\n{$cfg['role']}\n\nREQUIRED ASSET:\n{$cfg['asset']}\n\nIMPORTANT MARK FACTS:\n- Inventor and patent holder of The BeatSeat.\n- Former MTV artist.\n- Song of the Year finalist.\n- 209 original songs.\n- Daily live creation show ran from 12/31/2018 to 6/9/2025.\n- Booking concept: live songwriting concert series + BeatSeat performance + creative entrepreneurship/speaking hybrid.\n\nRULES:\n- Never invent booking contacts, emails, pay rates, or venue interest.\n- Every opportunity needs source URL/contact page or mark NEEDS_VERIFIED_CONTACT.\n- Jessica outreach should be human, warm, concise, and easy to reply to.\n- Finished output must be actionable: contacts to email, email copy, follow-up schedule, EPK improvements, or video/media assets.";
  $p=promptx($exec,$title,$instructions,['commission_id'=>$commission_id,'expected_asset_type'=>$cfg['asset'],'epk_url'=>'/mark-pires-music.php?key=timetomakethedonuts']);
  $task_id=ins('local_ai_tasks',[
    'task_uid'=>uid('lat'),
    'commission_id'=>$commission_id,
    'agent'=>ucfirst($exec),
    'task_type'=>'v82_music_epk_booking_engine',
    'model'=>'goliath-local-worker',
    'prompt'=>$p,
    'status'=>'queued',
    'priority'=>($exec==='goliath'?260:345),
    'progress'=>0,
    'metadata'=>js(['commission_id'=>$commission_id,'expected_asset_type'=>$cfg['asset'],'epk_url'=>'/mark-pires-music.php?key=timetomakethedonuts']),
    'created_at'=>gdb_now(),
    'updated_at'=>gdb_now()
  ]);
  $tasks[]=['executive'=>$exec,'asset'=>$cfg['asset'],'task_id'=>$task_id,'commission_id'=>$commission_id];
}

echo json_encode([
  'ok'=>true,
  'version'=>'V82 Music EPK Booking Engine Seeder',
  'commission_id'=>$commission_id,
  'tasks'=>$tasks,
  'epk_url'=>'/mark-pires-music.php?key=timetomakethedonuts',
  'next'=>'Run local worker and open Workbench for booking/EPK outputs.',
  'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>