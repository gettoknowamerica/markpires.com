<?php
declare(strict_types=1);ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';require_once __DIR__.'/goliath-db.php';
$key=(string)($_GET['key']??'');$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
function cols($t){$r=gdb_all("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?",[$t])?:[];$o=[];foreach($r as $x)$o[$x['column_name']]=true;return $o;}
$c=cols('jessica_email_drafts');if(!$c){echo json_encode(['ok'=>false,'error'=>'jessica_email_drafts_missing']);exit;}
$drafts=gdb_all("SELECT * FROM jessica_email_drafts WHERE status IN ('pending_approval','approved','draft') ORDER BY id ASC LIMIT 500")?:[];
$updated=0;$skipped=0;
$templates=[];
if((int)(gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='jessica_outreach_templates_v110'")['c']??0)>0){
 foreach(gdb_all("SELECT * FROM jessica_outreach_templates_v110 WHERE is_active=1 ORDER BY variation_no")?:[] as $t)$templates[$t['lead_type']][]=$t;
}
foreach($drafts as $d){
 $hay=strtolower(($d['subject']??'').' '.($d['body_text']??$d['body']??$d['message']??'').' '.($d['source_label']??''));
 $type=str_contains($hay,'expired')?'expired_listing':(str_contains($hay,'absentee')?'absentee_owner':'');
 if(!$type||empty($templates[$type])){$skipped++;continue;}
 $t=$templates[$type][0];$row=[];
 foreach(['from_name','sender_name'] as $f)if(isset($c[$f]))$row[$f]='Mark Pires';
 foreach(['from_email','sender_email'] as $f)if(isset($c[$f]))$row[$f]='mark@markpires.com';
 if(isset($c['subject']))$row['subject']=$t['subject_line'];
 foreach(['body_text','body','message'] as $f)if(isset($c[$f]))$row[$f]=$t['body_text'];
 if(isset($c['status']))$row['status']='pending_approval';
 if(isset($c['updated_at']))$row['updated_at']=gdb_now();
 if($row){gdb_update('jessica_email_drafts',$row,'id=:id',['id'=>$d['id']]);$updated++;}
}
echo json_encode(['ok'=>true,'version'=>'V111.2 Jessica Legacy Revision','updated'=>$updated,'skipped_unclassified'=>$skipped,'identity'=>'All revised drafts are from Mark Pires.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>