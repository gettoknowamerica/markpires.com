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

function sh118_table(string $table):bool{
 try{$row=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$table]);return (int)($row['c']??0)>0;}
 catch(Throwable $e){return false;}
}
function sh118_all(string $sql,array $params=[]):array{
 try{return gdb_all($sql,$params)?:[];}catch(Throwable $e){return [];}
}
function sh118_one(string $sql,array $params=[]):array{
 try{return gdb_one($sql,$params)?:[];}catch(Throwable $e){return [];}
}

$accounts=sh118_table('goliath_social_accounts')?sh118_all(
 "SELECT platform_key,platform_name,username,status,last_checked_at,updated_at
  FROM goliath_social_accounts ORDER BY platform_name"
):[];
$queue=sh118_table('goliath_social_queue')?sh118_one(
 "SELECT COUNT(*) total,
  SUM(status='draft') drafts,
  SUM(status IN ('approved','ready','ready_for_review')) ready,
  SUM(status='scheduled') scheduled,
  SUM(status='posted') posted,
  SUM(status='failed') failed
  FROM goliath_social_queue"
):[];

echo json_encode([
 'ok'=>true,
 'version'=>'V118 Goliath Social Health',
 'storage'=>'Hostinger MySQL',
 'supabase_required'=>false,
 'accounts'=>$accounts,
 'connected_count'=>count(array_filter($accounts,fn($row)=>($row['status']??'')==='connected')),
 'queue'=>$queue,
 'next'=>'Complete official OAuth/API credentials per platform, then keep Founder approval on until each live connector passes a real post test.',
 'time'=>date('c')
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>