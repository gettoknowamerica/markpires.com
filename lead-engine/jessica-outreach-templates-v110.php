<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

$key=(string)($_GET['key']??$_POST['key']??'');
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
if(!hash_equals((string)$expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

$type=strtolower(trim((string)($_GET['lead_type']??$_POST['lead_type']??'')));
$allowed=['absentee_owner','expired_listing'];
if(!in_array($type,$allowed,true)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'invalid_lead_type','allowed'=>$allowed]);exit;}

$templates=gdb_all("SELECT template_key,lead_type,variation_no,subject_line,body_text,sender_name,sender_email,outward_identity,internal_owner FROM jessica_outreach_templates_v110 WHERE lead_type=? AND is_active=1 ORDER BY variation_no",[$type])?:[];
echo json_encode(['ok'=>true,'version'=>'V110.0 Jessica Invisible Assistant Templates','lead_type'=>$type,'templates'=>$templates,'rule'=>'External communication is always sent as Mark Pires. Jessica remains invisible and internal.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>