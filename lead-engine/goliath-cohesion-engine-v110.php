<?php
declare(strict_types=1);
ini_set('display_errors','0');
set_time_limit(50);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function v110_key():string{
 if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
 if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
 return 'timetomakethedonuts';
}
function v110_one(string $s,array $p=[]):?array{try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
function v110_all(string $s,array $p=[]):array{try{return gdb_all($s,$p)?:[];}catch(Throwable $e){return [];}}
function v110_table(string $t):bool{$r=v110_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return (int)($r['c']??0)>0;}
function v110_col(string $t,string $c):bool{$r=v110_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return (int)($r['c']??0)>0;}
function v110_uid(string $p):string{return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
function v110_insert(string $t,array $r):?int{$safe=[];foreach($r as $k=>$v)if(v110_col($t,$k))$safe[$k]=$v;return $safe?gdb_insert($t,$safe):null;}
function v110_update(string $t,string $w,array $p,array $r):void{$safe=[];foreach($r as $k=>$v)if(v110_col($t,$k))$safe[$k]=$v;if($safe)gdb_update($t,$safe,$w,$p);}
function v110_upsert_activity(array $e,string $mode,string $action,?string $mission=null):void{
 $row=['display_name'=>$e[1],'department'=>$e[2],'current_mode'=>$mode,'current_mission_uid'=>$mission,'current_action'=>$action,'status'=>'active','last_heartbeat_at'=>gdb_now(),'updated_at'=>gdb_now()];
 $x=v110_one("SELECT id FROM goliath_executive_activity_v110 WHERE executive_key=?",[$e[0]]);
 if($x)v110_update('goliath_executive_activity_v110','id=:id',['id'=>$x['id']],$row);
 else{$row['executive_key']=$e[0];$row['created_at']=gdb_now();v110_insert('goliath_executive_activity_v110',$row);}
}
function v110_template(string $key,string $type,int $variation,string $subject,string $body):void{
 $row=['lead_type'=>$type,'variation_no'=>$variation,'subject_line'=>$subject,'body_text'=>$body,'sender_name'=>'Mark Pires','sender_email'=>'mark@markpires.com','outward_identity'=>'mark','internal_owner'=>'jessica','is_active'=>1,'updated_at'=>gdb_now()];
 $x=v110_one("SELECT id FROM jessica_outreach_templates_v110 WHERE template_key=?",[$key]);
 if($x)v110_update('jessica_outreach_templates_v110','id=:id',['id'=>$x['id']],$row);
 else{$row['template_key']=$key;$row['created_at']=gdb_now();v110_insert('jessica_outreach_templates_v110',$row);}
}
function v110_require(string $mission,string $from,string $to,string $key,string $title,string $instructions,int $priority=80):bool{
 $x=v110_one("SELECT id FROM goliath_required_handoffs_v110 WHERE mission_uid=? AND requirement_key=? AND to_executive=? AND status NOT IN ('complete','completed','waived') LIMIT 1",[$mission,$key,$to]);
 if($x)return false;
 v110_insert('goliath_required_handoffs_v110',['handoff_uid'=>v110_uid('handoff'),'mission_uid'=>$mission,'from_executive'=>$from,'to_executive'=>$to,'requirement_key'=>$key,'title'=>$title,'instructions'=>$instructions,'status'=>'required','priority'=>$priority,'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
 return true;
}

$key=(string)($_GET['key']??$_POST['key']??'');
if(!hash_equals(v110_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

$executives=[
 ['goliath','Goliath','CEO / Organization Coordination','Direct all outcomes, unblock work, enforce collaboration, and deliver finished packages to Mark.'],
 ['scout','Scout','Lead & Opportunity Intelligence','Find qualified MLS, sponsor, business, and revenue opportunities.'],
 ['jessica','Jessica','Invisible Relationship Assistant','Act internally as Jessica but communicate outwardly as Mark Pires from Mark’s accounts.'],
 ['shakespeare','Shakespeare','Authority, Blogs & Publishing','Create complete authority packages and publish approved blogs from the live template.'],
 ['scorsese','Scorsese','Video & Visual Production','Create new media and repurpose Mark’s footage into optimized deliverables.'],
 ['einstein','Einstein','SEO, AEO & Visibility','Research competitors and improve ranking, search visibility, schema, internal links, and conversion.'],
 ['columbo','Columbo','Content Archive & Gold Mining','Catalog Mark’s content library, transcribe, chapter, score, and send gold to Scorsese.'],
 ['prospector','Prospector','Sponsorships & Partnerships','Find sponsors, venues, podcasts, speaking, wineries, builders, and partnership opportunities.'],
 ['rockefeller','Rockefeller','Revenue & ROI','Score projects by revenue, probability, effort, lifetime value, and strategic impact.'],
 ['pandora','Pandora','Trends & Creative Strategy','Find timely, seasonal, viral, and platform-specific creative opportunities.'],
 ['mozart','Mozart','Audio, Music & Voice','Handle audio cleanup, narration, music, sonic branding, and podcast sound.'],
 ['sherlock','Sherlock','Property Investigation & Verification','Investigate ownership, LLCs, probate, trusts, tax records, repeat expirations, hidden opportunities, and verify publishable claims.']
];

$metrics=['executives_registered'=>0,'assignments_repaired'=>0,'handoffs_created'=>0,'templates_seeded'=>0,'missions_observed'=>0];

foreach($executives as $e){
 v110_upsert_activity($e,'observing',$e[3],null);
 $metrics['executives_registered']++;
 if(v110_table('executive_genomes')){
   $row=['display_name'=>$e[1],'role_title'=>$e[2],'department'=>$e[2],'mission'=>$e[3],'status'=>'active','updated_at'=>gdb_now()];
   if(v110_col('executive_genomes','always_rules_json'))$row['always_rules_json']=json_encode(['Read Founder Vision','Read executive constitution','Own outcomes','Collaborate before starting','One useful idea creates at least five valuable outputs','Never sit idle','Complete or hand off'],JSON_UNESCAPED_SLASHES);
   if(v110_col('executive_genomes','never_rules_json'))$row['never_rules_json']=json_encode(['Silently fail','Deliver placeholder work','Misrepresent identity','Bypass verification','Create duplicate queue floods'],JSON_UNESCAPED_SLASHES);
   $x=v110_one("SELECT id FROM executive_genomes WHERE executive_key=?",[$e[0]]);
   if($x)v110_update('executive_genomes','id=:id',['id'=>$x['id']],$row);
   else{$row['genome_uid']=v110_uid('genome');$row['executive_key']=$e[0];v110_insert('executive_genomes',$row);}
 }
}

/* Jessica is invisible externally: every message is from Mark. */
$signature="Warmest regards,\n\nMark Pires\nColdwell Banker Realty\n203-247-2655\nmark@markpires.com\nwww.markpires.com";

v110_template('absentee_owner_1','absentee_owner',1,'A quick introduction regarding your Fairfield County property',
"Hello [First Name],\n\nI'm Mark Pires with Coldwell Banker Realty, creator of Discover Connecticut and The House Detective. I wanted to introduce myself because I have been speaking with absentee property owners about the Fairfield County market.\n\nYou have likely heard that it has been a very strong seller's market for some time. Based on the recent increase in inventory, however, I am beginning to sense that the tide may be changing. Have you considered selling your property while values remain elevated?\n\nI would welcome a no-obligation conversation about how your property might perform in the current market. If now is not the right time, I hope we can speak when the timing is more appropriate.\n\n".$signature);

v110_template('absentee_owner_2','absentee_owner',2,'Have you considered the current value of your property?',
"Hello [First Name],\n\nI'm Mark Pires with Coldwell Banker Realty, creator of Discover Connecticut and The House Detective. I work throughout Fairfield County and wanted to reach out regarding your property at [Property Address].\n\nThe seller's market has remained unusually strong, although new inventory suggests conditions may begin to shift. Have you given any thought to selling while buyer demand and property values remain favorable?\n\nI would be happy to share a no-obligation market perspective and discuss how your property could perform. If selling is not currently part of your plans, I would still be glad to remain a resource for you.\n\n".$signature);

v110_template('absentee_owner_3','absentee_owner',3,'Fairfield County market question',
"Hello [First Name],\n\nI'm Mark Pires with Coldwell Banker Realty, creator of Discover Connecticut and The House Detective. I am reaching out to a small number of absentee property owners as the Fairfield County market begins showing signs of change.\n\nAlthough sellers still have considerable leverage, inventory has started to rise. This may create a valuable window for owners who have considered selling but have not yet taken the next step.\n\nWould you be open to a brief, no-obligation conversation about the current value and potential marketability of [Property Address]? If the timing is not right, I completely understand and hope we can connect in the future.\n\n".$signature);

v110_template('expired_listing_1','expired_listing',1,'Regarding your previous home sale',
"Hello [First Name],\n\nI'm Mark Pires with Coldwell Banker Realty, creator of Discover Connecticut and The House Detective. I wanted to introduce myself because I saw that you previously tried to sell your home, and I was curious whether you still have any interest in selling.\n\nIt remains a strong seller's market, although market conditions never last forever and recent inventory suggests the environment may begin to change. Have you considered bringing the property back to market while values remain elevated?\n\nAs I approach my twentieth year selling real estate, I have worked through many different markets. I would welcome a no-obligation conversation about why the home may not have sold previously and how it could perform today. If now is not the right time, I hope we can speak when the timing is more appropriate.\n\n".$signature);

v110_template('expired_listing_2','expired_listing',2,'Would you reconsider selling your home?',
"Hello [First Name],\n\nI'm Mark Pires with Coldwell Banker Realty, creator of Discover Connecticut and The House Detective. I noticed that [Property Address] was previously offered for sale and wanted to ask whether selling is still something you would consider.\n\nBuyer demand remains strong, but increased inventory could eventually change the balance of the market. A fresh pricing, presentation, and marketing strategy may produce a very different result today.\n\nI would be glad to review the property with you and offer a no-obligation assessment of its current market potential. If your plans have changed, I completely understand and would be happy to remain a resource.\n\n".$signature);

v110_template('expired_listing_3','expired_listing',3,'A fresh look at [Property Address]',
"Hello [First Name],\n\nI'm Mark Pires with Coldwell Banker Realty, creator of Discover Connecticut and The House Detective. I am reaching out because your home was previously listed but did not sell.\n\nThe current market may provide another opportunity, particularly with the right positioning, presentation, and launch strategy. After nearly twenty years in real estate, one lesson has remained constant: whether a market is good or bad, it never stays that way forever.\n\nWould you be open to a brief, no-obligation conversation about what happened during the prior listing and what could be done differently now? If the timing is not right, I hope we can connect in the future.\n\n".$signature);
$metrics['templates_seeded']=6;

/*
 * Repair collaboration around active missions.
 * Every mission is reviewed by the relevant team before Goliath delivery.
 */
if(v110_table('goliath_missions')){
 $missions=v110_all("SELECT * FROM goliath_missions WHERE status NOT IN ('complete','completed','delivered','archived','canceled') ORDER BY priority DESC,id ASC LIMIT 30");
 foreach($missions as $m){
   $metrics['missions_observed']++;
   $uid=(string)($m['mission_uid']??'');
   if($uid==='')continue;
   $type=strtolower((string)($m['mission_type']??''));
   $title=(string)($m['title']??'Mission');
   $team=json_decode((string)($m['assigned_executives_json']??'[]'),true);
   if(!is_array($team))$team=[];
   $required=['goliath'];

   if(str_contains($type,'authority')||str_contains(strtolower($title),'blog')){
     $required=array_merge($required,['einstein','sherlock','shakespeare','scorsese','pandora','jessica','rockefeller']);
   }elseif(str_contains($type,'media')||str_contains($type,'video')){
     $required=array_merge($required,['columbo','scorsese','mozart','shakespeare','einstein','jessica','rockefeller']);
   }elseif(str_contains($type,'lead')||str_contains($type,'seller')||str_contains($type,'mls')){
     $required=array_merge($required,['scout','sherlock','rockefeller','jessica','shakespeare']);
   }elseif(str_contains($type,'sponsor')||str_contains($type,'partnership')){
     $required=array_merge($required,['prospector','rockefeller','jessica','shakespeare','scorsese']);
   }else{
     $required=array_merge($required,['einstein','sherlock','shakespeare','scorsese','jessica','rockefeller']);
   }
   $required=array_values(array_unique($required));

   foreach($required as $exec){
     if(v110_table('executive_mission_assignments')){
       $a=v110_one("SELECT id FROM executive_mission_assignments WHERE mission_uid=? AND executive_key=? LIMIT 1",[$uid,$exec]);
       if(!$a){
         v110_insert('executive_mission_assignments',['mission_uid'=>$uid,'executive_key'=>$exec,'assignment_type'=>$type?:'collaboration','status'=>'assigned','instructions'=>'Review this mission through your constitution, improve the shared outcome, complete your department deliverable, then hand off.','requested_help_json'=>json_encode(['team'=>$required],JSON_UNESCAPED_SLASHES),'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
         $metrics['assignments_repaired']++;
       }
     }
     v110_upsert_activity(array_values(array_filter($executives,fn($e)=>$e[0]===$exec))[0]??[$exec,ucfirst($exec),'Executive','Advance mission.'],'working','Collaborating on: '.$title,$uid);
   }

   $handoffPlan=[];
   if(in_array('einstein',$required,true))$handoffPlan[]=['goliath','einstein','seo_aeo','SEO, AEO and competitor brief','Research search intent, top competitors, keywords, schema, internal links, visibility and conversion opportunities.'];
   if(in_array('sherlock',$required,true))$handoffPlan[]=['einstein','sherlock','verification','Verification and investigation','Verify facts, claims, ownership, LLCs, probate, trusts, tax data, repeat expirations and risk as appropriate.'];
   if(in_array('shakespeare',$required,true))$handoffPlan[]=['sherlock','shakespeare','authority_content','Finished authority package','Write the complete useful piece—not an introduction—including data, local detail, FAQs, calls to action and publishing-ready HTML.'];
   if(in_array('scorsese',$required,true))$handoffPlan[]=['shakespeare','scorsese','visual_media','Visual and video package','Create or repurpose imagery, video, shorts, thumbnail concepts and visual support for the finished content.'];
   if(in_array('mozart',$required,true))$handoffPlan[]=['scorsese','mozart','audio','Audio package','Provide narration, cleanup, music and sonic identity where useful.'];
   if(in_array('jessica',$required,true))$handoffPlan[]=['scorsese','jessica','distribution','Relationship and distribution action','Prepare outreach, CRM follow-up and distribution. Outward communication must be from Mark Pires, never from Jessica.'];
   if(in_array('rockefeller',$required,true))$handoffPlan[]=['jessica','rockefeller','roi','Revenue and priority review','Estimate revenue, probability, effort, strategic value and recommended priority.'];
   $handoffPlan[]=['rockefeller','goliath','final_delivery','Final Goliath review','Confirm every required contribution is complete, optimize the package, deliver to Mark and create the next five valuable opportunities.'];

   foreach($handoffPlan as $h)if(v110_require($uid,$h[0],$h[1],$h[2],$h[3],$h[4],85))$metrics['handoffs_created']++;
 }
}

echo json_encode(['ok'=>true,'version'=>'V110.0 Cohesive Executive OS','metrics'=>$metrics,'executives'=>array_map(fn($e)=>$e[0],$executives),'jessica_identity_rule'=>'Jessica works invisibly. Every external email, text, and contact is from Mark Pires using Mark’s identity and accounts.','next'=>'Open /dashboard/goliath-v110-status.php','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>