<?php
declare(strict_types=1);ini_set('display_errors','0');header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';require_once __DIR__.'/goliath-db.php';
function jc_key(){if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;return 'timetomakethedonuts';}
$in=json_decode(file_get_contents('php://input'),true)?:$_POST;$key=(string)($in['key']??$_GET['key']??'');
if(!hash_equals(jc_key(),$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
$action=(string)($in['action']??$_GET['action']??'get');$type=(string)($in['lead_type']??$_GET['lead_type']??'absentee_owner');
$campaign=gdb_one("SELECT * FROM jessica_campaigns_v111 WHERE lead_type=? ORDER BY id DESC LIMIT 1",[$type]);
if(!$campaign&&in_array($type,['absentee_owner','expired_listing'],true)){
 $t=gdb_one("SELECT * FROM jessica_outreach_templates_v110 WHERE lead_type=? AND is_active=1 ORDER BY variation_no LIMIT 1",[$type]);
 if($t){$id=gdb_insert('jessica_campaigns_v111',['campaign_uid'=>function_exists('gdb_uid')?gdb_uid('campaign'):uniqid('campaign_'),'lead_type'=>$type,'title'=>ucwords(str_replace('_',' ',$type)).' Master Campaign','subject_line'=>$t['subject_line'],'body_text'=>$t['body_text'],'sender_name'=>'Mark Pires','sender_email'=>'mark@markpires.com','approval_status'=>'pending_approval','status'=>'draft','batch_size'=>25,'drip_plan_json'=>gdb_json(['initial'=>'approved campaign','follow_up_1_days'=>7,'follow_up_2_days'=>21,'long_term_days'=>45]),'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);$campaign=gdb_one("SELECT * FROM jessica_campaigns_v111 WHERE id=?",[$id]);}
}
if($action==='save'||$action==='approve'){
 if(!$campaign){echo json_encode(['ok'=>false,'error'=>'campaign_missing']);exit;}
 $row=['subject_line'=>(string)($in['subject_line']??$campaign['subject_line']),'body_text'=>(string)($in['body_text']??$campaign['body_text']),'updated_at'=>gdb_now()];
 if($action==='approve'){$row['approval_status']='approved';$row['status']='active';$row['approved_at']=gdb_now();}
 gdb_update('jessica_campaigns_v111',$row,'id=:id',['id'=>$campaign['id']]);$campaign=gdb_one("SELECT * FROM jessica_campaigns_v111 WHERE id=?",[$campaign['id']]);
}
$count=0;if($campaign)$count=(int)(gdb_one("SELECT COUNT(*) c FROM jessica_campaign_recipients_v111 WHERE campaign_id=?",[$campaign['id']])['c']??0);
echo json_encode(['ok'=>true,'campaign'=>$campaign,'recipient_count'=>$count],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>