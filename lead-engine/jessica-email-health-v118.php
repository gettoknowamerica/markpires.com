<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

$key=trim((string)($_GET['key']??''));
$expected=defined('AFTER_HOURS_CRON_KEY')?trim((string)AFTER_HOURS_CRON_KEY):
 (defined('RETELL_WEBHOOK_KEY')?trim((string)RETELL_WEBHOOK_KEY):'timetomakethedonuts');
if(!hash_equals($expected,$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}

function eh118_table(string $table):bool{
 try{$row=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]);return (int)($row['c']??0)>0;}
 catch(Throwable $e){return false;}
}
function eh118_one(string $sql,array $params=[]):array{
 try{return gdb_one($sql,$params)?:[];}catch(Throwable $e){return [];}
}

$drafts=eh118_table('jessica_email_drafts')?eh118_one(
 "SELECT COUNT(*) total,
  SUM(status='pending_approval') pending_approval,
  SUM(status='approved') approved,
  SUM(status='sent') sent,
  SUM(status='sent' AND DATE(sent_at)=CURRENT_DATE) sent_today
  FROM jessica_email_drafts"
):[];

echo json_encode([
 'ok'=>true,
 'version'=>'V118 Jessica Email Health',
 'outbound'=>[
  'resend_key_present'=>defined('RESEND_API_KEY')&&trim((string)RESEND_API_KEY)!=='',
  'from_email'=>defined('RESEND_FROM_EMAIL')?RESEND_FROM_EMAIL:null,
  'mark_email'=>defined('MARK_EMAIL')?MARK_EMAIL:null,
  'sender_identity'=>'Mark Pires',
  'reply_to'=>defined('MARK_EMAIL')?MARK_EMAIL:'mark@markpires.com',
  'draft_table_present'=>eh118_table('jessica_email_drafts'),
  'draft_counts'=>$drafts
 ],
 'inbound'=>[
  'status'=>'requires_mailbox_webhook_or_gmail_connection',
  'note'=>'Outbound sending is wired through Resend. Automatic reply ingestion requires the inbound mailbox/webhook connection to be configured and tested separately.'
 ],
 'test_url'=>'/lead-engine/resend-diagnostic.php?key=YOUR_KEY&to='.(defined('MARK_EMAIL')?rawurlencode(MARK_EMAIL):'mark%40markpires.com'),
 'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>