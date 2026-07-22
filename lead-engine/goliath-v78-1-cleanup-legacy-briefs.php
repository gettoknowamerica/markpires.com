<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
header('Content-Type: application/json; charset=utf-8');
$key=$_GET['key']??'';
$expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:'timetomakethedonuts';
if(!hash_equals($expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
function colx($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
$sets=[];
foreach(['evidence_status'=>'legacy_archive','review_status'=>'archive','status'=>'legacy_archive','deliverable_type'=>'legacy_brief'] as $c=>$v){ if(colx('goliath_deliverables',$c)) $sets[]="$c='$v'"; }
if(colx('goliath_deliverables','updated_at')) $sets[]="updated_at=NOW()";
$where="(title LIKE '%Production Mission:%' OR output_summary LIKE 'Legacy worker completion backfilled%' OR output_summary LIKE '%Daily Scorsese Production Report%' OR output_summary LIKE '%sample output based on the provided mission%' OR output_summary LIKE '%Ranked Opportunity List%' OR deliverable_type='legacy_completion')";
try{gdb_exec("UPDATE goliath_deliverables SET ".implode(',',$sets)." WHERE ".$where);$updated=(int)((gdb_one("SELECT ROW_COUNT() c")?:['c'=>0])['c']);}
catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);exit;}
echo json_encode(['ok'=>true,'version'=>'V78.1 Legacy Brief Cleanup','updated'=>$updated,'message'=>'Old text briefs archived. Workbench now shows real assets by default.','time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>