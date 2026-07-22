<?php
session_start();
require_once __DIR__ . '/../lead-engine/config.php';
require_once __DIR__ . '/../lead-engine/goliath-db.php';
require_once __DIR__ . '/../lead-engine/goliath-company-brain.php';
if(empty($_SESSION['mp_dashboard_auth'])){header('Location:/dashboard/?next='.rawurlencode($_SERVER['REQUEST_URI']??'/dashboard/goliath-mission-control.php'));exit;}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function normalize_exec_key($d){
  $k=strtolower(trim((string)$d));
  $k=preg_replace('/[^a-z]/','',$k);
  $aliases=[
    'oliath'=>'goliath',
    'essica'=>'jessica',
    'cout'=>'scout',
    'corsese'=>'scorsese',
    'ozart'=>'mozart',
    'hakespeare'=>'shakespeare',
    'instein'=>'einstein',
    'olumbo'=>'columbo',
    'rospector'=>'prospector',
    'ockefeller'=>'rockefeller',
    'andora'=>'pandora',
    'herlock'=>'sherlock'
  ];
  return $aliases[$k]??$k;
}
function pretty_exec_name($d){
  $k=normalize_exec_key($d);
  $names=[
    'goliath'=>'Goliath','jessica'=>'Jessica','scout'=>'Scout','scorsese'=>'Scorsese','mozart'=>'Mozart',
    'shakespeare'=>'Shakespeare','einstein'=>'Einstein','columbo'=>'Columbo','prospector'=>'Prospector',
    'rockefeller'=>'Rockefeller','pandora'=>'Pandora','sherlock'=>'Sherlock'
  ];
  return $names[$k]??ucfirst($k);
}
function iconx($d){$d=normalize_exec_key($d);return ['goliath'=>'🏛️','jessica'=>'✉️','scout'=>'🕵️','scorsese'=>'🎬','mozart'=>'🎼','shakespeare'=>'✒️','einstein'=>'📊','columbo'=>'🕵️‍♂️','prospector'=>'⛏️','rockefeller'=>'💰','pandora'=>'🌍','sherlock'=>'🔎'][$d]??'⚡';}
function agent_url($d){
  $k=normalize_exec_key($d);
  $map=[
    'goliath'=>'#executive-council',
    'scout'=>'/dashboard/scout-ready-contacts.php',
    'jessica'=>'/dashboard/jessica-relationship-center.php',
    'shakespeare'=>'/dashboard/shakespeare-authority-center.php',
    'scorsese'=>'/dashboard/scorsese-studio-pro.php',
    'einstein'=>'/dashboard/einstein-intelligence-center.php',
    'sherlock'=>'/dashboard/sherlock-strategy-lab.php',
    'pandora'=>'/dashboard/pandora-design-studio.php',
    'mozart'=>'/dashboard/mozart-audio-studio.php',
    'columbo'=>'/dashboard/goliath-mission-control.php#executive-council',
    'prospector'=>'/dashboard/goliath-mission-control.php#executive-council',
    'rockefeller'=>'/dashboard/goliath-mission-control.php#executive-council'
  ];
  return $map[$k]??('/dashboard/goliath-deliverables.php?exec='.rawurlencode($k));
}

function mc_rows($sql,$p=[]){try{return gdb_all($sql,$p)?:[];}catch(Throwable $e){return [];} }
function mc_one($sql,$p=[]){try{return gdb_one($sql,$p)?:[];}catch(Throwable $e){return [];} }
$brain=gcb_company_snapshot();
/* V81.1 safety: older/newer dispatchers sometimes stripped the first letter from executive keys.
   Normalize those keys for display, links, icons, and meters so Mission Control always shows the real names. */
if(isset($brain['by_executive']) && is_array($brain['by_executive'])){
  $fixed=[];
  foreach($brain['by_executive'] as $raw=>$stats){
    $key=normalize_exec_key($raw);
    if(!isset($fixed[$key])) $fixed[$key]=$stats;
    else{
      $fixed[$key]['total']=(int)($fixed[$key]['total']??0)+(int)($stats['total']??0);
      $fixed[$key]['verified']=(int)($fixed[$key]['verified']??0)+(int)($stats['verified']??0);
      $fixed[$key]['needs']=(int)($fixed[$key]['needs']??0)+(int)($stats['needs']??0);
      $fixed[$key]['score']=max((int)($fixed[$key]['score']??0),(int)($stats['score']??0));
      if(!empty($stats['latest'])) $fixed[$key]['latest']=$stats['latest'];
    }
  }
  $brain['by_executive']=$fixed;
}
if(isset($brain['opportunity_radar']) && is_array($brain['opportunity_radar'])){
  foreach($brain['opportunity_radar'] as &$opp){
    if(isset($opp['executive'])) $opp['executive']=normalize_exec_key($opp['executive']);
  }
  unset($opp);
}
$agents=[
 'goliath'=>['name'=>'Goliath','role'=>'Chief Executive OS & Council Chair','shirt'=>'#d4af37','room'=>'goliath-command.png','special'=>'goliath'],
 'scout'=>['name'=>'Scout','role'=>'Lead Discovery & Market Intelligence','shirt'=>'#22c55e','room'=>'scout-office.png'],
 'jessica'=>['name'=>'Jessica','role'=>'Communications, Scheduling & Follow-Up','shirt'=>'#ef4444','room'=>'jessica-office.jpeg'],
 'shakespeare'=>['name'=>'Shakespeare','role'=>'Authority Content & Storytelling','shirt'=>'#7f1d1d','room'=>'shakespeare-publishing.png'],
 'scorsese'=>['name'=>'Scorsese','role'=>'Film Director & Creative Production','shirt'=>'#a855f7','room'=>'scorsese-studio.png'],
 'einstein'=>['name'=>'Einstein','role'=>'Data Science, SEO, AEO & Analytics','shirt'=>'#3b82f6','room'=>'einstein-analytics.png'],
 'columbo'=>['name'=>'Columbo','role'=>'Archive Intelligence & YouTube Growth','shirt'=>'#facc15','room'=>'columbo-office.png'],
 'prospector'=>['name'=>'Prospector','role'=>'Opportunity Discovery & Trend Mining','shirt'=>'#166534','room'=>'prospector-office.png'],
 'rockefeller'=>['name'=>'Rockefeller','role'=>'Revenue Optimization & Financial Decisions','shirt'=>'#f97316','room'=>'rockefeller-office.png'],
 'pandora'=>['name'=>'Pandora','role'=>'Brand, Design & Visual Systems','shirt'=>'#c026d3','room'=>'pandora-office.png'],
 'mozart'=>['name'=>'Mozart','role'=>'Music, Voice & Audio Production','shirt'=>'#e5e7eb','room'=>'mozart-office.png'],
 'sherlock'=>['name'=>'Sherlock','role'=>'Strategy, Verification & Opportunity QA','shirt'=>'#38bdf8','room'=>'sherlock-lab.png']
];
$key=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';

$mc_videos=mc_rows("SELECT id,title,status,progress,output_url,thumbnail_url,output_path,created_at,updated_at,completed_at FROM scorsese_comfy_jobs WHERE status IN ('complete','completed','ready') AND output_url IS NOT NULL AND output_url<>'' AND output_url NOT LIKE 'http://127.0.0.1:%' ORDER BY completed_at DESC, updated_at DESC, id DESC LIMIT 8");
$mc_render_queue=mc_rows("SELECT id,title,status,progress,updated_at,error_message FROM scorsese_comfy_jobs WHERE status IN ('queued','retry','working','rendering','failed','error') ORDER BY updated_at DESC,id DESC LIMIT 8");
$mc_datasets=mc_rows("SELECT * FROM internal_contact_sources ORDER BY id DESC LIMIT 8");
$mc_scout_counts=mc_one("SELECT COUNT(*) total, SUM(CASE WHEN COALESCE(best_phone,'')<>'' OR COALESCE(phone_1,'')<>'' OR COALESCE(phone,'')<>'' OR COALESCE(existing_phone,'')<>'' THEN 1 ELSE 0 END) phones, SUM(CASE WHEN COALESCE(best_email,'')<>'' OR COALESCE(email_1,'')<>'' OR COALESCE(email,'')<>'' OR COALESCE(existing_email,'')<>'' THEN 1 ELSE 0 END) emails FROM internal_crm_contacts");
$mc_council=mc_one("SELECT * FROM executive_council_sessions ORDER BY session_date DESC,id DESC LIMIT 1");
$mc_payload=!empty($mc_council['replay_json'])?json_decode($mc_council['replay_json'],true):[];
$mc_actions=$mc_payload['actions']??[];
$mc_metrics=$mc_payload['metrics']??[];
$mc_jessica=mc_rows("SELECT id,to_name,subject,status,created_at FROM jessica_email_drafts WHERE status IN ('pending_approval','approved') ORDER BY created_at DESC LIMIT 5");
$mc_shakespeare=mc_rows("SELECT id,title,content_type,approval_status,status,created_at FROM shakespeare_content_packages ORDER BY CASE WHEN approval_status='needs_review' THEN 0 ELSE 1 END, created_at DESC LIMIT 5");
$mc_scout_ready=mc_rows("SELECT id,owner_name,property_address,town,source_label,updated_at FROM scout_intel_dossiers WHERE handoff_status='ready_for_mark' ORDER BY updated_at DESC LIMIT 5");
$mc_activity=[];
foreach($mc_videos as $v){$mc_activity[]=['icon'=>'🎬','exec'=>'Scorsese','title'=>$v['title']?:'New Video Rendered','detail'=>'Video ready for review','url'=>'/dashboard/scorsese-studio-pro.php#job-'.$v['id'],'time'=>$v['completed_at']?:$v['updated_at']];}
foreach($mc_shakespeare as $p){$mc_activity[]=['icon'=>'✒️','exec'=>'Shakespeare','title'=>$p['title'],'detail'=>$p['content_type'].' · '.$p['approval_status'],'url'=>'/dashboard/shakespeare-authority-center.php#pkg-'.$p['id'],'time'=>$p['created_at']];}
foreach($mc_scout_ready as $s){$mc_activity[]=['icon'=>'🕵️','exec'=>'Scout','title'=>$s['owner_name']?:'Lead Dossier Ready','detail'=>trim(($s['source_label']?:'Lead').' · '.($s['property_address']?:''),' ·'),'url'=>'/dashboard/scout-ready-contacts.php#contact-'.$s['id'],'time'=>$s['updated_at']];}
foreach($mc_jessica as $j){$mc_activity[]=['icon'=>'✉️','exec'=>'Jessica','title'=>'Email Draft Ready','detail'=>($j['to_name']?:'Contact').' · '.$j['subject'],'url'=>'/dashboard/jessica-relationship-center.php#draft-'.$j['id'],'time'=>$j['created_at']];}
usort($mc_activity,function($a,$b){return strcmp((string)($b['time']??''),(string)($a['time']??''));});
$mc_brief=$mc_council['meeting_summary']??'Good morning, Mark. Say Hey Goliath or type a mission. I will assign the right executives and bring finished work back to this screen.';

?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Cache-Control" content="no-store"><title>Goliath Mission Control V81.2</title>
<link rel="stylesheet" href="/dashboard/assets/goliath-v33.css?v=33"><link rel="stylesheet" href="/dashboard/assets/goliath-v45-final.css?v=456"><link rel="stylesheet" href="/dashboard/assets/goliath-mission-control-v62.css?v=723">
<style>
:root{--gold:#d4af37}body{background:radial-gradient(circle at top,#111827 0,#05070d 44%,#020308 100%);color:#f8f1df}.top{gap:16px;align-items:center}.brandbar{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}.btn{display:inline-flex;align-items:center;gap:6px;border-radius:12px;padding:10px 14px;font-weight:900;text-decoration:none;border:1px solid #ffffff22;color:#fff;box-shadow:0 10px 28px #0005}.btn.gold{background:linear-gradient(135deg,#d4af37,#7a5b10);color:#111}.btn.green{background:linear-gradient(135deg,#16a34a,#064e3b)}.btn.blue{background:linear-gradient(135deg,#2563eb,#1e3a8a)}.btn.purple{background:linear-gradient(135deg,#9333ea,#581c87)}.btn.orange{background:linear-gradient(135deg,#f97316,#7c2d12)}.btn.red{background:linear-gradient(135deg,#dc2626,#7f1d1d)}
.commandFrame{display:grid;grid-template-columns:minmax(0,1fr) 285px;gap:18px;align-items:start}.leftColumn,.rightColumn{display:grid;gap:14px}.hqPanel,.sidePanel,.promptBox,.councilBox,.feedBox,.radarBox{background:#07111f;border:1px solid #22405f;border-radius:20px;box-shadow:0 18px 45px #0007}.hqPanel{padding:16px}.hqTitle{display:flex;justify-content:space-between;align-items:center;font-weight:1000;letter-spacing:.08em;text-transform:uppercase;color:#e8f0ff;margin:0 0 10px}.hqGrid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:16px}.agentCell{min-width:0}.agentName{height:26px;display:flex;align-items:center;gap:7px;font-weight:1000;color:#fff;text-shadow:0 2px 8px #000}.newBadge{background:linear-gradient(135deg,#22c55e,#16a34a,#065f46);color:#ecfdf5;border-radius:999px;font-size:10px;font-weight:1000;padding:4px 8px;box-shadow:0 0 18px #18e36b66;border:1px solid #bbf7d0aa}
.studioLive{position:absolute;right:10px;bottom:10px;z-index:8;letter-spacing:.08em}.office{position:relative;aspect-ratio:1/1;width:100%;min-height:190px;border:1px solid color-mix(in srgb,var(--shirt),#ffffff 18%);border-radius:18px;overflow:hidden;background-size:cover;background-position:center;display:block;color:#fff;text-decoration:none;box-shadow:0 18px 42px #0008}.office:before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,#0005,#0001 42%,#000c)}.roomTitle{position:absolute;top:10px;left:10px;right:10px;background:#050914cc;border:1px solid #ffffff18;border-radius:12px;padding:7px 9px;font-size:10.5px;line-height:1.12;font-weight:900;color:#f5e7bc;text-transform:uppercase;min-height:26px}.metric{position:absolute;top:52px;right:10px;background:#000c;border:1px solid #ffffff33;border-radius:12px;padding:5px 9px;font-weight:1000;color:var(--gold);font-size:12px}

.worker{
position:absolute;z-index:5;left:42%;top:48%;width:40px;height:50px;
filter:drop-shadow(0 10px 9px #000b);
transform-origin:50% 72%;
animation:walkPath1 34s linear infinite;
}
.worker .head{position:absolute;left:13px;top:0;width:16px;height:16px;border-radius:50%;background:radial-gradient(circle at 35% 28%,#ffd8ad,#b8794d 75%);border:2px solid #151515;z-index:4}
.worker .hair{position:absolute;left:10px;top:-2px;width:22px;height:13px;border-radius:50% 50% 42% 42%;background:#2b1a12;border:1px solid #111;z-index:5;opacity:.92}
.worker .neck{position:absolute;left:17px;top:14px;width:8px;height:7px;background:#c98b62;border-left:1px solid #111;border-right:1px solid #111;z-index:2}
.worker .torso{position:absolute;left:8px;top:19px;width:26px;height:24px;border-radius:12px 12px 8px 8px;background:linear-gradient(180deg,color-mix(in srgb,var(--shirt),#fff 22%),var(--shirt));border:2px solid #111;z-index:3}
.worker .shoulders{position:absolute;left:3px;top:19px;width:36px;height:14px;border-radius:16px 16px 8px 8px;background:linear-gradient(180deg,color-mix(in srgb,var(--shirt),#fff 14%),var(--shirt));border:2px solid #111;z-index:2}
.worker .arm{position:absolute;top:25px;width:7px;height:22px;border-radius:8px;background:#c98b62;border:1px solid #111;z-index:1;transform-origin:50% 4px;animation:armSwing .72s ease-in-out infinite alternate}
.worker .arm.left{left:0}.worker .arm.right{right:0;animation-delay:.36s}
.worker .leg{position:absolute;bottom:0;width:8px;height:18px;border-radius:8px;background:#111827;border:1px solid #050505;z-index:1;transform-origin:50% 2px;animation:legSwing .72s ease-in-out infinite alternate}
.worker .leg.left{left:11px}.worker .leg.right{right:11px;animation-delay:.36s}
.agentCell:nth-child(2n) .worker{animation-name:walkPath2;animation-duration:38s}
.agentCell:nth-child(3n) .worker{animation-name:walkPath3;animation-duration:36s}
.agentCell:nth-child(4n) .worker{animation-name:walkPath4;animation-duration:40s}
.agentCell:nth-child(5n) .worker{animation-name:walkPath5;animation-duration:42s}
@keyframes armSwing{from{transform:rotate(23deg)}to{transform:rotate(-23deg)}}
@keyframes legSwing{from{transform:rotate(-18deg) translateY(1px)}to{transform:rotate(18deg) translateY(-1px)}}

/* Corrected: every waypoint is biased LEFT/CENTER and safely inside each room. */
@keyframes walkPath1{
0%,10%{left:30%;top:46%;transform:rotate(0deg)} 20%,28%{left:48%;top:38%;transform:rotate(82deg)}
38%,46%{left:52%;top:24%;transform:rotate(0deg)} 56%,64%{left:28%;top:28%;transform:rotate(-90deg)}
74%,82%{left:24%;top:58%;transform:rotate(180deg)} 94%,100%{left:30%;top:46%;transform:rotate(-130deg)}
}
@keyframes walkPath2{
0%,10%{left:46%;top:48%;transform:rotate(0deg)} 20%,28%{left:24%;top:56%;transform:rotate(-95deg)}
38%,46%{left:24%;top:30%;transform:rotate(0deg)} 56%,64%{left:46%;top:26%;transform:rotate(90deg)}
74%,82%{left:54%;top:56%;transform:rotate(180deg)} 94%,100%{left:46%;top:48%;transform:rotate(135deg)}
}
@keyframes walkPath3{
0%,10%{left:30%;top:34%;transform:rotate(0deg)} 20%,28%{left:54%;top:34%;transform:rotate(90deg)}
38%,46%{left:48%;top:58%;transform:rotate(180deg)} 56%,64%{left:22%;top:56%;transform:rotate(-90deg)}
74%,82%{left:38%;top:46%;transform:rotate(45deg)} 94%,100%{left:30%;top:34%;transform:rotate(-135deg)}
}
@keyframes walkPath4{
0%,10%{left:50%;top:32%;transform:rotate(0deg)} 20%,28%{left:28%;top:28%;transform:rotate(-90deg)}
38%,46%{left:22%;top:50%;transform:rotate(180deg)} 56%,64%{left:40%;top:58%;transform:rotate(90deg)}
74%,82%{left:54%;top:50%;transform:rotate(0deg)} 94%,100%{left:50%;top:32%;transform:rotate(-180deg)}
}
@keyframes walkPath5{
0%,10%{left:26%;top:38%;transform:rotate(0deg)} 20%,28%{left:40%;top:26%;transform:rotate(60deg)}
38%,46%{left:56%;top:42%;transform:rotate(130deg)} 56%,64%{left:50%;top:58%;transform:rotate(190deg)}
74%,82%{left:26%;top:56%;transform:rotate(-80deg)} 94%,100%{left:26%;top:38%;transform:rotate(-150deg)}
}
.meterDock{position:absolute;left:10px;right:10px;bottom:10px;background:#050914e8;border:1px solid #ffffff22;border-radius:14px;padding:8px}.meterLine{display:flex;align-items:center;gap:7px}.battery{height:9px;flex:1;background:#000;border:1px solid #ffffff33;border-radius:999px;overflow:hidden}.battery b{display:block;height:100%;background:linear-gradient(90deg,var(--shirt,#d4af37),#fff2);box-shadow:0 0 12px var(--shirt,#d4af37)}.meterLine em{font-size:11px;font-weight:1000}.taskText{display:block;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:10.5px;color:#dbe3f2}.taskText strong{color:var(--shirt,#d4af37);text-transform:uppercase}
.sidePanel{overflow:hidden}.sideHead{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid #ffffff18}.sideHead b{padding:13px 14px;color:#e8f0ff;letter-spacing:.08em;text-transform:uppercase}.scoreboard,.goliathMini,.radarBox,.feedBox,.promptBox,.councilBox{padding:14px}.scoreboard h3,.radarBox h3,.promptBox h3,.councilBox h3,.feedBox h3{margin:0 0 8px;color:#d4af37;text-transform:uppercase;letter-spacing:.06em}.total{font-size:36px;font-weight:1000;color:#fff;margin:8px 0}.scoreRow,.radarItem,.eventMini{display:flex;justify-content:space-between;gap:8px;border-bottom:1px solid #ffffff12;padding:6px 0;font-size:12px;color:#fff;text-decoration:none}.radarItem{display:block}.radarItem b{color:#fff}.radarItem small{color:#d4af37}.scoreRow span{color:#d4af37;font-weight:1000}.promptBox textarea{width:100%;min-height:92px;background:#050914;color:#fff;border:1px solid #ffffff22;border-radius:14px;padding:12px;font-family:Arial,sans-serif;resize:vertical}.promptActions{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}.councilGrid{display:grid;grid-template-columns:1.1fr 1fr 1fr;gap:12px}.councilCard{background:#050914;border:1px solid #ffffff16;border-radius:14px;padding:12px;min-height:92px}.councilHero{background:linear-gradient(135deg,#111827,#3b2c0b);border-color:#d4af3750}.councilCard strong{color:#fff}.councilCard p{color:#c9d6ea;margin:6px 0 0}

/* V81.4 layout polish: header/name bar + external meter footer */
.hqGrid{gap:18px 16px}
.agentCell{background:#07111f;border:1px solid color-mix(in srgb,var(--shirt),#ffffff 14%);border-radius:18px;padding:0 0 10px;box-shadow:0 18px 42px #0007;overflow:hidden}
.agentName{height:30px;padding:0 10px;background:linear-gradient(180deg,#1a2333e8,#080d18e8);border-bottom:1px solid color-mix(in srgb,var(--shirt),#ffffff 18%);justify-content:center;letter-spacing:.04em;position:relative}
.agentName .newBadge{display:none}
.office{border-radius:0;border:0;box-shadow:none;min-height:178px}
.roomTitle{top:8px;left:9px;right:9px;min-height:0;font-size:9px;line-height:1.08;padding:5px 7px;font-weight:700;letter-spacing:.02em;background:#050914b5;text-transform:uppercase;opacity:.92}
.metric{top:42px;right:9px;font-size:12px}
.office .meterDock{display:none}
.agentFooter{display:block}
.agentFooter .meterDock{position:relative;left:auto;right:auto;bottom:auto;display:block;margin:8px 10px 0;background:#050914f0;border:1px solid color-mix(in srgb,var(--shirt),#ffffff 15%);border-radius:13px;padding:8px}
.taskText{font-size:10px}


/* V81.2 Columbo gold gradient/readability polish */
.agentCell[style*="#facc15"]{
  --shirt:#facc15;
}
.agentCell[style*="#facc15"] .agentName{
  background:linear-gradient(180deg,#713f12,#422006 58%,#0b1020);
  border-bottom-color:#facc15;
  box-shadow:inset 0 -1px 0 #facc1555;
}
.agentCell[style*="#facc15"] .office{
  border-color:#facc1555;
}
.agentCell[style*="#facc15"] .worker .torso,
.agentCell[style*="#facc15"] .worker .shoulders{
  background:linear-gradient(180deg,#fde68a 0%,#facc15 45%,#ca8a04 100%);
}
.agentCell[style*="#facc15"] .battery b{
  background:linear-gradient(90deg,#fef3c7 0%,#facc15 42%,#eab308 72%,#854d0e 100%);
  box-shadow:0 0 16px #facc1590;
}
.agentCell[style*="#facc15"] .meterDock{
  border-color:#facc1555;
  box-shadow:0 0 18px #facc1518;
}
.agentCell[style*="#facc15"] .meterLine em,
.agentCell[style*="#facc15"] .taskText strong,
.agentCell[style*="#facc15"] .metric{
  color:#facc15;
  text-shadow:0 1px 8px #000;
}


/* V82.2 Mission Control additions: mini media center + Scout intake */
.mediaBox,.scoutIntake{background:#07111f;border:1px solid #22405f;border-radius:20px;box-shadow:0 18px 45px #0007;padding:14px}.mediaBox h3,.scoutIntake h3{margin:0 0 8px;color:#d4af37;text-transform:uppercase;letter-spacing:.06em}.mediaGrid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:14px}.videoScreen{background:#000;border:1px solid #d4af3744;border-radius:16px;overflow:hidden;min-height:300px;display:flex;align-items:center;justify-content:center}.videoScreen video{width:100%;max-height:390px;display:block;background:#000}.emptyMedia{color:#94a3b8;text-align:center;padding:35px}.mediaList{max-height:390px;overflow:auto;display:grid;gap:8px}.mediaItem{display:block;background:#050914;border:1px solid #ffffff14;border-radius:12px;padding:10px;color:#fff;text-decoration:none}.mediaItem b{color:#fff}.mediaItem small{color:#d4af37}.mediaBtns{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.scoutGrid{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:14px}.intakeForm{display:grid;grid-template-columns:1fr 1fr;gap:10px}.intakeForm .full{grid-column:1/-1}.intakeForm input,.intakeForm select,.intakeForm textarea{width:100%;background:#050914;color:#fff;border:1px solid #ffffff22;border-radius:12px;padding:10px}.datasetList{max-height:310px;overflow:auto;display:grid;gap:8px}.datasetCard{background:#050914;border:1px solid #ffffff14;border-radius:12px;padding:10px}.datasetCard b{color:#fff}.datasetCard small{color:#94a3b8}.datasetStats{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:8px 0}.datasetStats div{background:#050914;border:1px solid #ffffff14;border-radius:12px;padding:9px}.datasetStats b{display:block;color:#d4af37;font-size:18px}.datasetStats span{font-size:10px;color:#94a3b8;text-transform:uppercase;font-weight:900}.newBadge{font-size:8px!important;padding:2px 5px!important}.studioLive{right:8px!important;bottom:8px!important}
@media(max-width:1180px){.mediaGrid,.scoutGrid{grid-template-columns:1fr}}@media(max-width:620px){.intakeForm{grid-template-columns:1fr}.intakeForm .full{grid-column:auto}.datasetStats{grid-template-columns:repeat(2,1fr)}}


/* V99.3 final Mission Control hero: vintage TV + Ask Goliath + Activity Feed */
body{
  background:
    radial-gradient(circle at 12% 0,rgba(212,175,55,.12),transparent 32%),
    radial-gradient(circle at 92% 4%,rgba(37,99,235,.10),transparent 28%),
    repeating-linear-gradient(45deg,#070b12 0,#070b12 8px,#0a1019 8px,#0a1019 16px)!important;
}
.top.osTop{display:none!important}
.mcHero{display:grid;grid-template-columns:190px minmax(0,1fr) 310px;gap:12px;margin:0 0 14px;align-items:stretch}
.askGoliath,.activityHero{background:linear-gradient(180deg,#0b111d,#05070d);border:1px solid #3b2b12;border-radius:16px;padding:12px;box-shadow:0 18px 45px #0008}
.askGoliath .askTitle,.activityHero .feedTitle{display:flex;align-items:center;gap:7px;color:#f6d679;text-transform:uppercase;letter-spacing:.08em;font-weight:1000;font-size:12px;margin-bottom:10px}
.askGoliath textarea{width:100%;min-height:118px;background:#05070d;color:#fff;border:1px solid #ffffff22;border-radius:12px;padding:10px;resize:vertical}
.voiceBtn{width:100%;margin-top:8px;background:linear-gradient(135deg,#0ea5e9,#075985);border:1px solid #38bdf8;color:#e0f2fe;border-radius:12px;padding:9px;font-weight:1000}
.tvCabinet{position:relative;background:linear-gradient(145deg,#3b2315,#130a06 42%,#4b2b16 70%,#1b0e08);border:1px solid #7c5128;border-radius:24px;padding:16px 114px 16px 18px;box-shadow:inset 0 0 0 2px #0008,0 26px 80px #000d;min-height:292px}
.tvCabinet:before{content:"";position:absolute;inset:4px;border-radius:20px;border:1px solid #d4af3728;pointer-events:none}
.tvControls{position:absolute;right:15px;top:16px;bottom:16px;width:82px;background:#080b10;border:1px solid #aa6b28;border-radius:14px;box-shadow:inset 0 0 18px #000}
.knob{width:52px;height:52px;border-radius:50%;margin:16px auto;background:radial-gradient(circle at 34% 28%,#f7d084,#23160d 36%,#0b0b0b 68%);border:2px solid #9a6b33;box-shadow:0 0 0 4px #0007}
.speaker{position:absolute;left:14px;right:14px;bottom:16px;height:82px;border-radius:10px;background:repeating-linear-gradient(0deg,#140b05 0,#140b05 5px,#b26b26 5px,#b26b26 7px);border:1px solid #9a5b24}
.tvTabs{position:absolute;left:28px;right:126px;bottom:-28px;display:flex;gap:6px;overflow-x:auto;z-index:4}
.tvTab{border:1px solid #8b5d2c;background:linear-gradient(180deg,#3b2418,#160c07);color:#f8e8bc;border-radius:12px 12px 0 0;padding:8px 12px;font-size:11px;font-weight:1000;white-space:nowrap;box-shadow:0 -8px 22px #0008;cursor:pointer}
.tvTab.active{background:linear-gradient(180deg,#f6d679,#9f7418);color:#111}
.crtScreen{position:relative;min-height:252px;border-radius:34px;background:radial-gradient(circle at 50% 35%,rgba(150,255,235,.11),transparent 55%),linear-gradient(180deg,rgba(11,31,34,.94),rgba(4,12,17,.96));border:5px solid #0b0f12;box-shadow:inset 0 0 42px #000,inset 0 0 18px rgba(120,255,235,.14),0 0 0 4px #b98944,0 0 0 8px #2a190d;overflow:hidden;padding:24px 30px;color:#e8fff8}
.crtScreen:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(255,255,255,.08),transparent 18%,transparent 82%,rgba(255,255,255,.05)),repeating-linear-gradient(0deg,rgba(255,255,255,.035) 0,rgba(255,255,255,.035) 1px,transparent 1px,transparent 4px);mix-blend-mode:screen;opacity:.62;pointer-events:none}
.crtScreen h2{margin:0 0 6px;color:#f4f1c7;font-size:28px;letter-spacing:.06em;text-align:center}
.crtSub{text-align:center;color:#b8d7d8;font-size:12px;margin-bottom:16px}
.crtRows{display:grid;gap:8px;position:relative;z-index:2}
.crtRow{display:grid;grid-template-columns:24px 120px 1fr 60px;gap:10px;align-items:center;border-bottom:1px solid rgba(154,255,238,.12);padding:6px 0;color:#dbeafe;text-decoration:none}
.crtRow b{text-transform:uppercase}.crtRow small{color:#6ee7f9}.crtClick{text-align:center;color:#f6d679;font-weight:1000;font-size:12px;margin-top:14px}
.activityHero{max-height:292px;overflow:hidden}.activityList{display:grid;gap:8px;max-height:236px;overflow:auto}.activityItem{display:grid;grid-template-columns:34px 1fr auto;gap:9px;align-items:center;background:#080b10;border:1px solid #ffffff14;border-radius:12px;padding:8px;color:#fff;text-decoration:none}.activityItem .aiIcon{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#251a0b;border:1px solid #d4af3740}.activityItem b{font-size:12px}.activityItem small{display:block;color:#94a3b8;font-size:10px}.activityItem em{font-style:normal;background:#dc2626;border-radius:999px;padding:2px 5px;font-size:8px;font-weight:1000}
.hqGrid{grid-template-columns:repeat(4,minmax(0,1fr))!important}.hqTitle span{font-size:0}.hqTitle span:after{content:'12 Executive Agents — 4 × 3 Command Rooms';font-size:12px}.goliathAvatar{position:absolute;left:36%;top:38%;width:54px;height:54px;border-radius:16px;background:linear-gradient(135deg,#f6d679,#6b4b0b);border:2px solid #111;box-shadow:0 12px 24px #000b;display:grid;place-items:center;font-size:30px;z-index:5;animation:goliathPulse 3.8s ease-in-out infinite}.goliathAvatar:after{content:"";position:absolute;left:45px;top:22px;width:34px;height:20px;border-radius:5px;background:#0ea5e9;border:2px solid #020617;box-shadow:0 0 18px #38bdf866}@keyframes goliathPulse{50%{transform:translateY(-4px);box-shadow:0 18px 34px #d4af3742}}@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
@media(max-width:1180px){.mcHero{grid-template-columns:1fr}.tvCabinet{padding-right:18px}.tvControls{display:none}.tvTabs{right:28px}.hqGrid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
@media(max-width:620px){.mcHero{gap:10px}.crtScreen{padding:18px 16px;border-radius:24px}.crtRow{grid-template-columns:22px 92px 1fr}.crtRow small{display:none}.tvCabinet{border-radius:18px;padding:12px}.tvTabs{position:relative;left:auto;right:auto;bottom:auto;margin-top:8px}.hqGrid{grid-template-columns:1fr!important}}

@media(max-width:1180px){.commandFrame{grid-template-columns:1fr}.hqGrid{grid-template-columns:repeat(2,minmax(0,1fr))}.office{min-height:260px}}@media(max-width:620px){.hqGrid,.councilGrid{grid-template-columns:1fr}.brandbar{justify-content:flex-start}.office{aspect-ratio:1/1;min-height:0}}

/* V99.4 mobile mission-control lock: true 9:16 layout, TV first, 4×3 executive rooms */
@media(max-width:620px){
  .shell,.main{width:100%!important;max-width:100%!important;overflow-x:hidden!important}
  .mcHero{grid-template-columns:1fr!important;gap:8px!important;margin-bottom:10px!important}
  .askGoliath{order:2;padding:8px!important}
  .askGoliath textarea{min-height:62px!important;font-size:13px!important}
  .activityHero{order:3;max-height:164px!important;padding:8px!important}
  .tvCabinet{order:1;padding:8px!important;border-radius:18px!important;min-height:0!important}
  .crtScreen{min-height:210px!important;border-radius:22px!important;padding:14px 12px!important;border-width:3px!important}
  .crtScreen h2{font-size:18px!important;line-height:1.05!important}
  .crtSub{font-size:10px!important;margin-bottom:8px!important}
  .crtRow{grid-template-columns:18px 66px 1fr!important;gap:5px!important;font-size:10px!important;padding:4px 0!important}
  .crtRow small{display:none!important}
  .crtClick{font-size:9px!important;margin-top:8px!important}
  .tvTabs{position:relative!important;left:auto!important;right:auto!important;bottom:auto!important;margin-top:7px!important}
  .tvTab{font-size:9px!important;padding:6px 8px!important}
  .commandFrame{grid-template-columns:1fr!important}
  .hqPanel{padding:8px!important;border-radius:16px!important}
  .hqTitle{font-size:11px!important;margin-bottom:7px!important}
  .hqGrid{grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:6px!important}
  .agentCell{border-radius:10px!important;padding-bottom:4px!important;min-width:0!important}
  .agentName{height:20px!important;font-size:8px!important;padding:0 3px!important;gap:2px!important;letter-spacing:0!important}
  .agentName span:first-child{display:none!important}
  .office{aspect-ratio:1/1!important;min-height:0!important;border-radius:0!important}
  .roomTitle{top:3px!important;left:3px!important;right:3px!important;font-size:5.8px!important;padding:2px 3px!important;border-radius:5px!important;line-height:1!important}
  .metric{top:19px!important;right:3px!important;font-size:7px!important;padding:2px 4px!important;border-radius:6px!important}
  .worker{width:20px!important;height:25px!important;transform:scale(.62);transform-origin:50% 72%!important;filter:drop-shadow(0 5px 5px #000b)!important}
  .worker .head{left:6px!important;width:9px!important;height:9px!important;border-width:1px!important}
  .worker .hair{left:5px!important;width:11px!important;height:7px!important}
  .worker .neck{left:8px!important;top:8px!important;width:5px!important;height:4px!important}
  .worker .torso{left:4px!important;top:11px!important;width:14px!important;height:13px!important;border-width:1px!important}
  .worker .shoulders{left:1px!important;top:11px!important;width:20px!important;height:8px!important;border-width:1px!important}
  .worker .arm{top:14px!important;width:4px!important;height:12px!important}
  .worker .leg{width:4px!important;height:10px!important}
  .worker .leg.left{left:6px!important}.worker .leg.right{right:6px!important}
  .goliathAvatar{width:26px!important;height:26px!important;font-size:14px!important;border-radius:8px!important}
  .goliathAvatar:after{left:22px!important;top:10px!important;width:16px!important;height:10px!important}
  .agentFooter .meterDock{margin:4px 4px 0!important;padding:4px!important;border-radius:7px!important}
  .meterLine{gap:3px!important}
  .battery{height:5px!important}
  .meterLine em{font-size:7px!important}
  .taskText{font-size:6.5px!important;margin-top:2px!important}
  .promptBox,.mediaBox,.scoutIntake,.councilBox,.sidePanel,.radarBox{border-radius:16px!important;padding:9px!important}
  .mediaGrid,.scoutGrid,.councilGrid{grid-template-columns:1fr!important}
  .videoScreen{min-height:180px!important}
  .datasetStats{grid-template-columns:repeat(2,1fr)!important}
}


/* V116.1 exact Goliath transparent character */
.goliathAvatar{
 position:absolute!important;
 left:28%;bottom:7%;top:auto!important;
 width:42%!important;height:72%!important;
 border:0!important;border-radius:0!important;background:transparent!important;
 box-shadow:none!important;display:block!important;z-index:6!important;
 transform:translateX(-50%);transform-origin:50% 100%;
 animation:none!important;pointer-events:none;
}
.goliathAvatar:after{display:none!important}
.goliathAvatar img{
 width:100%;height:100%;object-fit:contain;object-position:center bottom;
 filter:drop-shadow(0 12px 10px #000c);
}
.goliathAvatar:not(.goliathStopped) img{animation:goliathWalkBob .55s ease-in-out infinite alternate}
.goliathAvatar.goliathStopped img{animation:goliathIdleBreath 2.2s ease-in-out infinite}
@keyframes goliathWalkBob{from{transform:translateY(0)}to{transform:translateY(-3px)}}
@keyframes goliathIdleBreath{0%,100%{transform:scale(1)}50%{transform:scale(1.015)}}
@media(max-width:700px){
 .goliathAvatar{width:48%!important;height:68%!important;bottom:5%!important}
}


/* V116.3 persistent Mission Control monitor */
.v1163MonitorFrame{width:100%;height:100%;min-height:218px;border:0;background:#fff;border-radius:10px}
.v1163MonitorWrap{height:100%;min-height:218px;overflow:auto;background:#050914;border-radius:10px}
.v1163MonitorBar{display:flex;gap:8px;align-items:center;justify-content:space-between;margin-bottom:8px;position:sticky;top:0;z-index:3;background:#07111f;padding:6px;border-radius:8px}
.v1163MonitorBar a,.v1163MonitorBar button{border:1px solid #d4af3766;background:#15100a;color:#f8e8bc;border-radius:9px;padding:7px 9px;font-weight:900;text-decoration:none;cursor:pointer}
@media(max-width:620px){
 .v1163MonitorFrame,.v1163MonitorWrap{min-height:245px}
 .crtScreen{min-height:285px!important;max-height:66vh!important;overflow:auto!important}
}


/* V116.4 mobile command room + full-screen monitor */
.v1164MonitorShell{position:relative;width:100%;height:100%;min-height:230px;overflow:hidden;border-radius:14px;background:#02050b}
.v1164MonitorFrame{display:block;width:100%;height:100%;min-height:230px;border:0;background:#fff}
.v1164MonitorExpand{position:absolute;right:8px;bottom:8px;z-index:5;width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#0b1220dd;color:#f5d47a;border:1px solid #d4af3788;text-decoration:none;font-weight:1000}
.v111TvDetailBar{display:none!important}.v111TvDetailBody{padding:0!important;height:100%!important;overflow:hidden!important}
@media(max-width:700px){
 .hqGrid{grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:4px!important}
 .agentCell{min-width:0!important;border-radius:8px!important;padding-bottom:3px!important}
 .agentName{font-size:6.8px!important;height:17px!important;padding:0 2px!important}
 .office{aspect-ratio:1/1!important;min-height:0!important}
 .roomTitle{font-size:4.8px!important;line-height:1!important;padding:2px!important;left:2px!important;right:2px!important;top:2px!important}
 .metric{font-size:6px!important;top:15px!important;right:2px!important;padding:1px 3px!important}
 .agentFooter .meterDock{margin:2px!important;padding:2px!important}
 .taskText{font-size:5.2px!important;line-height:1.05!important;max-height:13px!important;overflow:hidden!important}
 .meterLine em{font-size:6px!important}.battery{height:4px!important}
 .worker{transform:scale(.5)!important}
 .goliathAvatar{width:54%!important;height:72%!important}
 .v1164MonitorShell,.v1164MonitorFrame{min-height:250px}
 .crtScreen{min-height:250px!important;max-height:58vh!important;padding:6px!important}
 .commandFrame{display:block!important}.leftColumn,.rightColumn{display:block!important}
 .promptBox,.mediaBox,.scoutIntake,.councilBox,.sidePanel,.radarBox,.v111CampaignCard,.v111CalendarCard{margin-top:9px!important}
}

</style>
<link rel="stylesheet" href="/dashboard/assets/goliath-live-v111.css?v=1161">
<script>window.GOLIATH_V111_KEY=<?=json_encode($key)?>;</script>
<script src="/dashboard/assets/goliath-live-v118-2.js?v=1182" defer></script>
<script src="/dashboard/assets/goliath-v112-truth.js?v=1180" defer></script>
<style id="v113-full-os-css">
.hqGrid{grid-template-columns:repeat(6,minmax(0,1fr))!important}
.mediaUploadBox{background:#07111f;border:1px solid #9333ea66;border-radius:20px;box-shadow:0 18px 45px #0007;padding:14px}
.mediaUploadBox h3{margin:0 0 7px;color:#e9d5ff;text-transform:uppercase;letter-spacing:.06em}
.mediaUploadForm{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.mediaUploadForm textarea,.mediaUploadForm input[type=file],.mediaUploadForm button{grid-column:1/-1}
.mediaUploadForm input,.mediaUploadForm select,.mediaUploadForm textarea{width:100%;box-sizing:border-box;background:#050914;color:#fff;border:1px solid #ffffff22;border-radius:12px;padding:10px}
@media(max-width:1180px) and (min-width:701px){.hqGrid{grid-template-columns:repeat(3,minmax(0,1fr))!important}}
@media(max-width:700px){
 .hqGrid{grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:5px!important}
 .agentName{font-size:7px!important;height:19px!important}
 .office{aspect-ratio:1/1!important}
 .mediaUploadForm{grid-template-columns:1fr}
 .mediaUploadForm>*{grid-column:1!important}
}
@media(max-width:950px) and (orientation:landscape){
 .hqGrid{grid-template-columns:repeat(6,minmax(0,1fr))!important}
}
</style>

<style id="v115-mobile-css">
.v115Longform{background:linear-gradient(135deg,#07111f,#130b25);border:1px solid #9333ea66;border-radius:20px;padding:14px;margin:14px 0}
.v115Longform h3{margin:0;color:#e9d5ff}.v115Longform p{color:#94a3b8}
.v115UploadGrid{display:grid;grid-template-columns:1fr 1fr;gap:8px}.v115UploadGrid textarea,.v115UploadGrid input[type=file],.v115UploadGrid button{grid-column:1/-1}
.v115UploadGrid input,.v115UploadGrid select,.v115UploadGrid textarea{width:100%;box-sizing:border-box;background:#020617;color:#fff;border:1px solid #ffffff22;border-radius:10px;padding:10px}
.v115Progress{height:12px;background:#020617;border-radius:999px;overflow:hidden;margin-top:10px}.v115Progress div{height:100%;width:0;background:linear-gradient(90deg,#7c3aed,#d4af37);transition:.15s}
.hqGrid{grid-template-columns:repeat(6,minmax(0,1fr))!important}
@media(max-width:700px){.hqGrid{grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:5px!important}.v115UploadGrid{grid-template-columns:1fr}.v115UploadGrid>*{grid-column:1!important}}
@media(max-width:950px) and (orientation:landscape){.hqGrid{grid-template-columns:repeat(6,minmax(0,1fr))!important}}
</style>
<style id="v1182-tv-size">
.tvCabinet{min-height:560px!important}
.crtScreen{min-height:510px!important;height:510px!important;max-height:none!important}
.v1164MonitorShell,.v1164MonitorFrame,.v1163MonitorWrap,.v1163MonitorFrame{min-height:500px!important;height:500px!important}
@media(max-width:700px){
 .tvCabinet{min-height:500px!important}
 .crtScreen{min-height:450px!important;height:450px!important}
 .v1164MonitorShell,.v1164MonitorFrame,.v1163MonitorWrap,.v1163MonitorFrame{min-height:440px!important;height:440px!important}
}
</style>
</head><body><div class="shell"><script>
let goliathFormDirty = false;

document.addEventListener('input', e => {
  if (e.target.closest('form')) goliathFormDirty = true;
});

document.addEventListener('change', e => {
  if (e.target.closest('form')) goliathFormDirty = true;
});

/* V111 live feed replaces full-page reload.
setInterval(() => {
  const active = document.activeElement;
  const isTyping = active && ['INPUT','TEXTAREA','SELECT'].includes(active.tagName);
  const hasFileSelected = document.querySelector('input[type="file"]')?.files?.length > 0;

  if (goliathFormDirty || isTyping || hasFileSelected) {
    console.log('Mission Control refresh paused while form is active.');
    return;
  }

  location.reload();
}, 60000); */
</script><?php if(file_exists(__DIR__.'/includes/goliath-sidebar-v33.php')) require __DIR__.'/includes/goliath-sidebar-v33.php'; ?><main class="main">
<section class="top osTop"><div></div><div class="brandbar"><a class="btn gold" href="/dashboard/goliath-deliverables.php">📦 Deliverables</a><a class="btn green" href="/dashboard/goliath-executive-memory.php">🧠 Memory</a><a class="btn purple" href="/dashboard/scorsese-studio-pro.php">🎬 Scorsese</a><a class="btn blue" href="/dashboard/goliath-worker-output.php">✅ Finished Assets</a><a class="btn orange" href="/dashboard/goliath-system-health.php?key=<?=h($key)?>">🟢 Health</a></div></section>

<section class="mcHero">
  <aside class="askGoliath v111Ask">
    <div class="askTitle">🎙 Ask Goliath Live <span id="v111Connection" class="v111Connection">CONNECTING</span></div>
    <div id="v111Conversation" class="v111Conversation">
      <div class="v111Bubble goliath"><b>Goliath</b><span>Hey Mark — I’m live. Talk naturally. I’ll coordinate the executive team and keep Mission Control updated.</span></div>
    </div>
    <form id="v111ChatForm" class="v111ChatForm">
      <textarea id="v111ChatInput" placeholder="Talk to Goliath…"></textarea>
      <div class="v111ChatActions">
        <button class="btn gold" type="submit">Send</button>
        <button class="voiceBtn" id="v111VoiceButton" type="button">🎙️ Enable Hands-Free Goliath</button>
        <button class="btn red" id="v111StopVoice" type="button">■ Stop</button>
      </div>
    </form>
    <div id="v111VoiceState" class="v111VoiceState">Tap once to enable. After that, say “Hey Goliath” anytime while Mission Control is open.</div><audio id="v111AudioPlayer" preload="auto" playsinline></audio>
  </aside>

  <section class="tvCabinet">
    <div class="crtScreen" id="crtScreen">
      <div id="v111TvHome" class="v111TvHome">
        <h2 id="v111TvTitle">GOOD MORNING, MARK</h2>
        <div class="crtSub" id="v111TvSub">Live Executive Briefing · <?=h(date('M j, Y'))?></div>
        <div class="crtRows" id="crtRows"></div>
        <div class="crtClick">SELECT AN UPDATE TO OPEN IT INSIDE THIS MONITOR</div>
      </div>
      <div id="v111TvDetail" class="v111TvDetail" hidden>
        <div class="v111TvDetailBar">
          <button type="button" onclick="GoliathTV.home()">← LIVE FEED</button>
          <strong id="v111TvDetailExec">GOLIATH</strong>
          <span id="v111TvDetailStatus">LIVE</span>
        </div>
        <div id="v111TvDetailBody" class="v111TvDetailBody"></div>
      </div>
    </div>
    <div class="tvControls"><div class="knob"></div><div class="knob"></div><div class="speaker"></div></div>
    <div class="tvTabs" id="tvTabs"><button class="tvTab active" onclick="showWelcome()">WELCOME</button></div>
  </section>

  <aside class="activityHero">
    <div class="feedTitle">⚡ Activity Feed</div>
    <div class="activityList">
      <?php foreach(array_slice($mc_activity,0,7) as $a): ?>
        <a class="activityItem" href="<?=h($a['url'])?>" data-tv-url="<?=h($a['url'])?>" data-tv-title="<?=h($a['title'])?>" data-tv-detail="<?=h($a['detail'])?>" onclick="return GoliathV113.openActivity(this)">
          <span class="aiIcon"><?=h($a['icon'])?></span>
          <span><b><?=h($a['title'])?></b><small><?=h($a['exec'])?> · <?=h($a['detail'])?></small></span>
          <em>NEW</em>
        </a>
      <?php endforeach; ?>
      <?php if(!count($mc_activity)): ?><div class="activityItem"><span class="aiIcon">🏛️</span><span><b>Awaiting Council</b><small>Run the nightly council to populate activity.</small></span></div><?php endif; ?>
    </div>
  </aside>
</section>
<section class="commandFrame"><div class="leftColumn"><div class="hqPanel"><div class="hqTitle"><b>Goliath HQ</b><span>10 Executive Agents — 1:1 Command Rooms</span></div><div class="hqGrid">
<?php foreach($agents as $k=>$info): $s=$brain['by_executive'][$k]??['total'=>0,'verified'=>0,'needs'=>0,'score'=>0,'latest'=>'No deliverables yet']; /* Meter percent comes from gcb_company_snapshot()['by_executive'][$k]['score']; it updates when the company brain snapshot reflects new verified/needs-evidence deliverables. */ $p=(int)($s['score']??0); $active=(int)($s['total']??0); $roomUrl='/dashboard/assets/rooms/'.$info['room'].'?v=761'; ?>
<div class="agentCell" data-executive="<?=h($k)?>" style="--shirt:<?=h($info['shirt'])?>;"><div class="agentName"><span><?=iconx($k)?></span><span><?=h($info['name'])?></span></div><a class="office" style="background-image:url('<?=h($roomUrl)?>')" href="<?=h(agent_url($k))?>"><span class="roomTitle"><?=h($info['role'])?></span><span class="metric"><?=h($active)?></span><?php if($active):?><span class="newBadge studioLive">LIVE</span><?php endif;?><?php if(($info['special']??'')==='goliath'): ?><span class="goliathAvatar"><img src="/dashboard/assets/goliath-character-transparent-v116-1.png?v=1161" alt="Goliath"></span><?php else: ?><span class="worker"><span class="hair"></span><span class="head"></span><span class="neck"></span><span class="shoulders"></span><span class="torso"></span><span class="arm left"></span><span class="arm right"></span><span class="leg left"></span><span class="leg right"></span></span><?php endif; ?></a><div class="agentFooter"><span class="meterDock"><span class="meterLine"><span class="battery"><b style="width:<?=$p?>%"></b></span><em><?=$p?>%</em></span><span class="taskText"><strong><?=h($s['verified']??0)?> verified:</strong> <?=h($s['latest']??'No deliverables yet')?></span></span></div></div>
<?php endforeach; ?></div></div>
<div class="promptBox" id="founder-priority-intake"><h3>Executive Team Priority Request</h3>
<form id="v118FounderPriorityForm" method="post" action="/lead-engine/goliath-executive-prompt-v118.php">
<input type="hidden" name="key" value="<?=h($key)?>">
<input type="hidden" name="priority" value="5000">
<textarea name="prompt" required placeholder="@Team — describe exactly what you need. This becomes a Founder-priority mission and moves to the top of the live V112 work queue without leaving Mission Control."></textarea>
<div class="promptActions">
<button class="btn gold" type="submit">Share with Team — Priority</button>
<a class="btn purple monitor-link" href="/dashboard/goliath-review-center.php?embed=1">Review Finished Work</a>
</div>
<div id="v118FounderPriorityStatus" style="margin-top:9px;color:#f5d47a;font-weight:900"></div>
</form></div>
<section class="mediaBox">
  <h3>Scorsese Studio Pro Preview</h3>
  <div class="mediaGrid">
    <div>
      <div class="videoScreen" id="missionVideoScreen">
        <?php if(count($mc_videos)): $v=$mc_videos[0]; ?>
          <video controls src="<?=h($v['output_url'])?>"></video>
        <?php else: ?>
          <div class="emptyMedia"><div style="font-size:42px">🎬</div><b>No finished Hostinger MP4s yet</b><br><span>Queue from Scorsese Studio Pro or run the Comfy worker.</span></div>
        <?php endif; ?>
      </div>
      <div class="mediaBtns"><a class="btn purple" href="/dashboard/scorsese-studio-pro.php">Open Full Media Center</a><a class="btn green" target="_blank" href="/lead-engine/scorsese-comfy-status.php?key=<?=h($key)?>">Render Status</a><button class="btn gold" onclick="alert('Social scheduler handoff is next.')">Approve / Queue Social</button><button class="btn orange" onclick="alert('Open the full Media Center to revise this asset.')">Revise</button></div>
    </div>
    <div class="mediaList">
      <?php foreach($mc_videos as $v): ?><a class="mediaItem" href="#" onclick="playMissionVideo('<?=h($v['output_url'])?>');return false;"><b><?=h($v['title']?:'Scorsese video')?></b><br><small>Job #<?=h($v['id'])?> · <?=h($v['completed_at']?:$v['updated_at'])?></small></a><?php endforeach; ?>
      <?php if(count($mc_render_queue)): ?><div class="mediaItem"><b>Render Queue</b></div><?php endif; ?>
      <?php foreach($mc_render_queue as $q): ?><div class="mediaItem"><b><?=h($q['title'])?></b><br><small><?=h($q['status'])?> · <?=h($q['progress'])?>%</small></div><?php endforeach; ?>
    </div>
  </div>
</section>

<section class="mediaUploadBox" id="media-intake">
  <h3>📱 Scorsese Raw Media Intake</h3>
  <p class="muted">Upload directly from iPhone or desktop. Originals remain untouched. Scorsese prepares 16:9, 9:16, clips, captions, chapters, metadata and bold thumbnail concepts.</p>
  <form class="mediaUploadForm" method="post" enctype="multipart/form-data" action="/lead-engine/goliath-media-upload-v113.php">
    <input type="hidden" name="key" value="<?=h($key)?>">
    <input name="title" required placeholder="Media title">
    <select name="brand_key">
      <option value="discover_ct">Discover Connecticut</option>
      <option value="markpires_real_estate">Mark Pires Real Estate</option>
      <option value="house_detective">The House Detective</option>
      <option value="mark_inspires">Mark Inspires</option>
      <option value="beatseat">BeatSeat</option>
      <option value="legacy_saved">LegacySaved</option>
      <option value="music">Mark Pires Music</option>
    </select>
    <textarea name="instructions" rows="4" placeholder="Tell Scorsese what you filmed and what you want. He will still analyze the full source and identify the strongest hooks, clips and exits."></textarea>
    <input type="file" name="media_file" accept="video/*,audio/*,image/*" capture="environment" required>
    <button class="btn purple" type="submit">Upload to Scorsese</button>
  </form>
</section>


<section class="v115Longform" id="longform-upload">
 <div><h3>🎬 Long-Form Video Upload</h3><p>Upload large iPhone or desktop footage in 20 MB chunks. The completed original is preserved and automatically enters Scorsese’s fixed production funnel.</p></div>
 <div class="v115UploadGrid">
  <input id="v115MediaTitle" placeholder="Project title">
  <select id="v115MediaBrand"><option value="discover_ct">Discover Connecticut</option><option value="markpires_real_estate">Mark Pires Real Estate</option><option value="house_detective">House Detective</option><option value="music">Music</option><option value="beatseat">BeatSeat</option><option value="legacy_saved">LegacySaved</option></select>
  <textarea id="v115MediaInstructions" placeholder="What did you film? What should Scorsese emphasize?"></textarea>
  <input type="file" id="v115MediaFile" accept="video/*,audio/*,.mp4,.mov,.m4v,.webm,.mkv,.mp3,.wav,.m4a">
  <button class="btn purple" id="v115UploadButton" type="button">Upload Long-Form in Chunks</button>
 </div>
 <div class="v115Progress"><div id="v115UploadBar"></div></div><div id="v115UploadStatus">Choose a file.</div>
</section>

<section class="scoutIntake">
  <h3>Scout Intelligence Intake</h3>
  <div class="scoutGrid">
    <div>
      <form class="intakeForm" method="post" enctype="multipart/form-data" action="/lead-engine/scout-csv-intake.php">
        <input type="hidden" name="key" value="<?=h($key)?>"><div class="full"><input name="dataset_title" required placeholder="Dataset title, e.g. July Absentee Owners / New Canaan Expireds"></div>
        <div><select name="dataset_type"><option value="expired">Expired Listings</option><option value="absentee">Absentee Owners</option><option value="probate">Probate</option><option value="luxury">Luxury Sellers</option><option value="buyers">Buyers</option><option value="sellers">Sellers</option><option value="investors">Investors</option><option value="music_booking">Music Booking</option><option value="speaking_booking">Speaking Booking</option><option value="custom">Custom</option></select></div>
        <div><select name="priority"><option value="5">★★★★★ Priority</option><option value="4">★★★★ High</option><option value="3">★★★ Normal</option><option value="2">★★ Low</option></select></div>
        <div class="full"><textarea name="description" rows="3" placeholder="Notes for Scout: what this list is, where it came from, and what you want done with it."></textarea></div>
        <div class="full"><input type="file" name="csv_file" accept=".csv,.txt" required></div>
        <div class="full promptActions"><button class="btn gold" type="submit">Upload + Queue Scout</button><a class="btn green" target="_blank" href="/lead-engine/run-scout-data-cycle.php?key=<?=h($key)?>&limit=20&mode=dossiers">Generate Today's 20 Dossiers</a><a class="btn blue" href="/dashboard/scout-contact-workspace.php">Open Scout Workspace</a></div>
      </form>
      <div class="datasetStats"><div><b><?=h($mc_scout_counts['total']??0)?></b><span>CRM Records</span></div><div><b><?=h($mc_scout_counts['phones']??0)?></b><span>Phones</span></div><div><b><?=h($mc_scout_counts['emails']??0)?></b><span>Emails</span></div><div><b>20</b><span>Daily Dossier Goal</span></div></div>
    </div>
    <aside><h3 style="font-size:14px">Active Datasets</h3><div class="datasetList"><?php if(!count($mc_datasets)): ?><div class="datasetCard"><b>No datasets cataloged yet.</b><br><small>Upload a CSV and Scout will take it from there.</small></div><?php endif; ?><?php foreach($mc_datasets as $d): ?><div class="datasetCard"><b><?=h($d['title']??$d['source_name']??$d['file_name']??('Dataset #'.$d['id']))?></b><br><small><?=h($d['source_type']??$d['dataset_type']??'dataset')?> · <?=h($d['created_at']??'')?></small><div class="promptActions"><a class="btn green" style="padding:7px 9px;font-size:11px" target="_blank" href="/lead-engine/run-scout-data-cycle.php?key=<?=h($key)?>&source_id=<?=h($d['id'])?>&limit=20&mode=dossiers">Research Now</a></div></div><?php endforeach; ?></div></aside>
  </div>
</section>
</div>
<div class="rightColumn"><aside class="sidePanel"><div class="sideHead"><b>Company Brain</b><b>V76.1</b></div><div class="goliathMini"><h3 style="color:#d4af37;margin:0 0 8px">Goliath OS</h3><p>Deliverables: <b><?=h($brain['counts']['deliverables']??0)?></b><br>Verified: <b><?=h($brain['counts']['verified']??0)?></b><br>Needs Evidence: <b><?=h($brain['counts']['needs_evidence']??0)?></b><br>Handoffs: <b><?=h($brain['counts']['handoffs']??0)?></b></p></div><div class="scoreboard"><h3>Finished Today</h3><div class="total"><span data-v112-finished>0</span></div><?php foreach($brain['by_executive'] as $name=>$s): ?><a class="scoreRow" href="/dashboard/goliath-review-center.php?exec=<?=h($name)?>"><b><?=iconx($name)?> <?=h(pretty_exec_name($name))?></b><span><?=h($s['total']??0)?></span></a><?php endforeach;?></div></aside>
<div class="radarBox"><h3>Opportunity Radar</h3><?php foreach(array_slice($brain['opportunity_radar']??[],0,7) as $o): ?><a class="radarItem" href="<?=h($o['url']??'#')?>"><b><?=h($o['title']??'Opportunity')?></b><br><small>Score <?=h($o['score']??0)?> · <?=h(ucfirst($o['executive']??'Goliath'))?></small><br><span><?=h($o['next_action']??'Review next action.')?></span></a><?php endforeach; ?></div></div></section>


<section class="v111CampaignCard">
  <div>
    <h3>✉️ Jessica Campaign Approval</h3>
    <p>Approve one master message per audience. Jessica then personalizes and sends the approved campaign continuously in controlled batches as Mark Pires.</p>
  </div>
  <div class="v111CampaignActions">
    <button class="btn red" type="button" onclick="GoliathTV.openCampaign('expired_listing')">Expired Campaign</button>
    <button class="btn gold" type="button" onclick="GoliathTV.openCampaign('absentee_owner')">Absentee Campaign</button>
  </div>
</section>
<div id="v111CampaignModal" class="v111Modal" aria-hidden="true">
  <div class="v111ModalPanel v111CampaignPanel">
    <button class="v111ModalClose" onclick="GoliathTV.closeCampaign()">×</button>
    <iframe id="v111CampaignFrame" title="Jessica Campaign Approval" src="about:blank"></iframe>
  </div>
</div>

<section class="v111CalendarCard" id="v111CalendarCard">
  <div>
    <h3>📅 Social Publishing Calendar</h3>
    <p>Approved blogs, videos, shorts, emails and social posts flow into the distribution calendar.</p>
  </div>
  <button class="btn purple" type="button" onclick="GoliathV111.openCalendar()">Open Calendar</button>
  <div id="v111CalendarPreview" class="v111CalendarPreview"></div>
</section>
<div id="v111CalendarModal" class="v111Modal" aria-hidden="true">
  <div class="v111ModalPanel">
    <button class="v111ModalClose" onclick="GoliathV111.closeCalendar()">×</button>
    <iframe title="Goliath Social Calendar" src="/dashboard/goliath-social-calendar.php"></iframe>
  </div>
</div>

<section class="councilBox" id="executive-council"><h3>Goliath Executive Council</h3><div class="councilGrid"><div class="councilCard councilHero"><strong>Today's Command</strong><p>Focus on verified deliverables, not generic text. Anything without evidence moves to Needs Evidence.</p></div><div class="councilCard"><strong>Biggest Bottleneck</strong><p><?=h(($brain['counts']['needs_evidence']??0)).' deliverables need stronger proof or clickable outputs.'?></p></div><div class="councilCard"><strong>Highest Opportunity</strong><p><?=h(($brain['opportunity_radar'][0]['title']??'No high-value opportunity yet.'))?></p></div></div></section>
<script>
const crtItems = [
  <?php foreach(array_slice($mc_actions,0,5) as $a): ?>
  {icon:'🏛️', exec:'GOLIATH', text:<?=json_encode($a['label'].' — '.$a['detail'])?>, time:'NOW', url:<?=json_encode($a['target']??'#')?>},
  <?php endforeach; ?>
  <?php foreach(array_slice($mc_activity,0,8) as $a): ?>
  {icon:<?=json_encode($a['icon'])?>, exec:<?=json_encode(strtoupper($a['exec']))?>, text:<?=json_encode($a['title'].' — '.$a['detail'])?>, time:<?=json_encode(substr((string)($a['time']??''),11,5))?>, url:<?=json_encode($a['url'])?>},
  <?php endforeach; ?>
];
function renderCrt(items){
  const rows=document.getElementById('crtRows');
  const data=(items&&items.length)?items:[{icon:'🏛️',exec:'GOLIATH',text:<?=json_encode($mc_brief)?>,time:'READY',url:'#executive-council'}];
  rows.innerHTML=data.slice(0,6).map((x,i)=>`<a class="crtRow" href="${x.url||'#'}" style="animation:fadeIn .4s ease ${i*.08}s both"><span>${x.icon||'⚡'}</span><b>${x.exec||'GOLIATH'}</b><span>${x.text||''}</span><small>${x.time||''} ◉</small></a>`).join('');
}
function addTvTab(label, fn){
  document.querySelectorAll('.tvTab').forEach(t=>t.classList.remove('active'));
  const b=document.createElement('button'); b.className='tvTab active'; b.textContent=label; b.onclick=fn; document.getElementById('tvTabs').appendChild(b);
}
function showWelcome(){
  document.querySelectorAll('.tvTab').forEach(t=>t.classList.remove('active'));
  const first=document.querySelector('.tvTab'); if(first) first.classList.add('active');
  document.querySelector('#crtScreen h2').textContent='GOOD MORNING, MARK';
  renderCrt(crtItems);
}
function startGoliathVoice(){ if(window.GoliathV111) window.GoliathV111.startVoice(); }
function playMissionVideo(url){document.getElementById("missionVideoScreen").innerHTML='<video controls autoplay src="'+String(url).replace(/"/g,'&quot;')+'"></video>'; addTvTab('VIDEO',()=>playMissionVideo(url));}
showWelcome();
</script></main></div>
<script>
window.GoliathV113={
 openActivity:function(el){
   var home=document.getElementById('v111TvHome'),detail=document.getElementById('v111TvDetail');
   var body=document.getElementById('v111TvDetailBody'),exec=document.getElementById('v111TvDetailExec'),status=document.getElementById('v111TvDetailStatus');
   if(!home||!detail||!body)return true;
   home.hidden=true;detail.hidden=false;
   exec.textContent=(el.querySelector('small')?.textContent.split('·')[0]||'GOLIATH').trim().toUpperCase();
   status.textContent='LIVE';
   return GoliathMonitor.open(el.dataset.tvUrl||'#',el.dataset.tvTitle||'Update');
 },
 escape:function(s){return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]})}
};
document.addEventListener('DOMContentLoaded',function(){
 document.querySelectorAll('a[href*="scorsese-media-center"]').forEach(function(a){a.href='/dashboard/scorsese-studio-pro.php'});
});
</script>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const KEY=window.GOLIATH_V111_KEY||<?=json_encode($key)?>,SIZE=20*1024*1024;
 const btn=document.getElementById('v115UploadButton');if(!btn)return;
 btn.onclick=async function(){
  const file=document.getElementById('v115MediaFile').files[0],status=document.getElementById('v115UploadStatus'),bar=document.getElementById('v115UploadBar');
  if(!file){status.textContent='Choose a file first.';return}
  btn.disabled=true;const uploadId='up_'+Date.now()+'_'+Math.random().toString(36).slice(2),total=Math.ceil(file.size/SIZE);let finalData=null;
  try{
   for(let i=0;i<total;i++){
    const fd=new FormData();fd.append('key',KEY);fd.append('upload_id',uploadId);fd.append('filename',file.name);fd.append('chunk_index',i);fd.append('total_chunks',total);fd.append('chunk',file.slice(i*SIZE,Math.min(file.size,(i+1)*SIZE)),file.name+'.part'+i);
    const res=await fetch('/lead-engine/chunk-upload.php',{method:'POST',body:fd});const text=await res.text();let data;try{data=JSON.parse(text)}catch(e){throw new Error(text.slice(0,240))}
    if(!(data.success||data.ok))throw new Error(data.error||'Chunk upload failed');finalData=data;
    const pct=Math.round(((i+1)/total)*100);bar.style.width=pct+'%';status.textContent=`Uploaded ${i+1} of ${total} chunks (${pct}%)`;
   }
   if(finalData&&finalData.complete){
    const fd=new FormData();fd.append('key',KEY);fd.append('url',finalData.url);fd.append('filename',finalData.filename||file.name);fd.append('title',document.getElementById('v115MediaTitle').value||file.name);fd.append('brand_key',document.getElementById('v115MediaBrand').value);fd.append('instructions',document.getElementById('v115MediaInstructions').value);
    const r=await fetch('/lead-engine/goliath-register-longform-v115.php',{method:'POST',body:fd});const d=await r.json();if(!d.ok)throw new Error(d.error||'Mission registration failed');
    status.innerHTML=`Complete. Scorsese mission #${d.mission_id} entered the fixed funnel.`;bar.style.width='100%';
   }
  }catch(e){status.textContent='Upload failed: '+e.message}finally{btn.disabled=false}
 };
});
</script>
<script>
window.GoliathMonitor={
 escape:function(s){return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]})},
 open:function(url,title){
  if(!url||url==='#'||url.startsWith('javascript:'))return true;
  const home=document.getElementById('v111TvHome'),detail=document.getElementById('v111TvDetail');
  const body=document.getElementById('v111TvDetailBody'),exec=document.getElementById('v111TvDetailExec'),status=document.getElementById('v111TvDetailStatus');
  if(!home||!detail||!body)return true;
  home.hidden=true;detail.hidden=false;
  exec.textContent=String(title||'WORKSPACE').toUpperCase();status.textContent='MONITOR';
  body.innerHTML='<div class="v1164MonitorShell"><iframe class="v1164MonitorFrame" loading="eager" src="'+this.escape(url)+'"></iframe><a class="v1164MonitorExpand" target="_blank" title="Open full workspace" href="'+this.escape(url)+'">↗</a></div>';
  window.scrollTo({top:document.querySelector('.tvCabinet')?.offsetTop||0,behavior:'smooth'});
  return false;
 }
};
document.addEventListener('DOMContentLoaded',function(){
 const selector='.office,.brandbar a,.scoreRow,.radarItem,.crtRow,.mediaBtns a';
 document.querySelectorAll(selector).forEach(function(a){
  const href=a.getAttribute('href')||'';
  if(!href||href.startsWith('#')||a.target==='_blank'||href.includes('goliath-mission-control.php'))return;
  a.addEventListener('click',function(e){
   e.preventDefault();
   const title=a.closest('.agentCell')?.querySelector('.agentName')?.textContent.trim()||a.textContent.trim()||'Workspace';
   GoliathMonitor.open(href,title);
  });
 });
});
</script>


<script>
document.addEventListener('click',function(e){
 const a=e.target.closest('a[href]');
 if(!a)return;
 const href=a.getAttribute('href')||'';
 if(!href||href.startsWith('#')||href.startsWith('javascript:')||a.target==='_blank')return;
 if(!a.matches('.office,.brandbar a,.scoreRow,.radarItem,.crtRow,.mediaBtns a,.activityItem,.monitor-link'))return;
 if(href.includes('goliath-mission-control.php'))return;
 e.preventDefault();
 const title=a.closest('.agentCell')?.querySelector('.agentName')?.textContent.trim()||a.querySelector('b')?.textContent.trim()||a.textContent.trim()||'Workspace';
 window.GoliathMonitor?.open(href,title);
});
</script>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const form=document.getElementById('v118FounderPriorityForm');
 if(!form)return;
 const status=document.getElementById('v118FounderPriorityStatus');
 form.addEventListener('submit',async function(event){
  event.preventDefault();
  const button=form.querySelector('button[type="submit"]');
  const textarea=form.querySelector('textarea[name="prompt"]');
  const prompt=textarea.value.trim();
  if(!prompt){status.textContent='Describe the priority request first.';return}
  button.disabled=true;
  button.textContent='Adding Priority Mission…';
  status.textContent='Creating the mission and placing it at the top of the live queue…';
  try{
   const response=await fetch(form.action,{method:'POST',body:new FormData(form),cache:'no-store',headers:{'Accept':'application/json'}});
   const raw=await response.text();
   let data;try{data=JSON.parse(raw)}catch(_){throw new Error(raw.slice(0,260)||`HTTP ${response.status}`)}
   if(!response.ok||!data.ok)throw new Error(data.details?.message||data.error||'Priority mission failed');
   status.innerHTML=`✅ Mission #${data.mission_id} added for <b>${data.originator}</b>. It is now the top-priority item.`;
   textarea.value='';
   if(window.refreshV118Truth)await window.refreshV118Truth();
   const activity=document.querySelector('.activityHero');
   if(activity)activity.scrollIntoView({behavior:'smooth',block:'start'});
  }catch(error){
   status.textContent='Could not add priority mission: '+error.message;
  }finally{
   button.disabled=false;
   button.textContent='Share with Team — Priority';
  }
 });
});
</script>

</body></html>