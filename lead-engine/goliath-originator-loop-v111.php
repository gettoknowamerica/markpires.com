<?php
declare(strict_types=1);ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';require_once __DIR__.'/goliath-db.php';
$key=(string)($_GET['key']??'');$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$sequence=['scout','sherlock','einstein','shakespeare','pandora','scorsese','mozart','prospector','jessica','rockefeller','columbo'];
$missions=gdb_all("SELECT * FROM goliath_missions WHERE status NOT IN ('complete','completed','delivered','archived','canceled') ORDER BY priority DESC,id ASC LIMIT 40")?:[];
$created=0;$originatorReviews=0;
foreach($missions as $m){
 $uid=$m['mission_uid'];$originator=$m['lead_executive']?:'goliath';$seq=array_values(array_unique(array_merge([$originator],$sequence,[$originator,'goliath'])));
 $x=gdb_one("SELECT * FROM goliath_mission_originator_flow_v111 WHERE mission_uid=?",[$uid]);
 if(!$x){gdb_insert('goliath_mission_originator_flow_v111',['mission_uid'=>$uid,'originator_key'=>$originator,'current_stage'=>'organization_review','originator_review_status'=>'pending','goliath_execution_status'=>'blocked','sequence_json'=>gdb_json($seq),'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);$created++;}
 $remaining=(int)(gdb_one("SELECT COUNT(*) c FROM goliath_required_handoffs_v110 WHERE mission_uid=? AND status NOT IN ('complete','completed','waived')",[$uid])['c']??0);
 if($remaining===0){
   $flow=gdb_one("SELECT * FROM goliath_mission_originator_flow_v111 WHERE mission_uid=?",[$uid]);
   if(($flow['originator_review_status']??'pending')==='pending'){
     $exists=gdb_one("SELECT id FROM goliath_required_handoffs_v110 WHERE mission_uid=? AND requirement_key='originator_final_review' LIMIT 1",[$uid]);
     if(!$exists){gdb_insert('goliath_required_handoffs_v110',['handoff_uid'=>function_exists('gdb_uid')?gdb_uid('handoff'):uniqid('handoff_'),'mission_uid'=>$uid,'from_executive'=>'organization','to_executive'=>$originator,'requirement_key'=>'originator_final_review','title'=>'Originator final review','instructions'=>'Review everything the organization added. Preserve the original idea, request revisions if necessary, then approve for Goliath execution.','status'=>'required','priority'=>100,'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);$originatorReviews++;}
   }
 }
}
echo json_encode(['ok'=>true,'version'=>'V111.2 Originator Return Loop','missions_seen'=>count($missions),'flows_created'=>$created,'originator_reviews_created'=>$originatorReviews,'rule'=>'Every mission returns to its creator before Goliath executes.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>