<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

if(empty($_SESSION['mp_dashboard_auth'])){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'not_authenticated']);exit;}
$input=json_decode((string)file_get_contents('php://input'),true);if(!is_array($input))$input=$_POST;
$missionId=(int)($input['mission_id']??0);$versionId=(int)($input['version_id']??0);$reason=trim((string)($input['reason']??'Founder selected this version.'));
if($missionId<1||$versionId<1){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'missing_ids']);exit;}
try{
 $version=gdb_one("SELECT * FROM goliath_v118_asset_versions WHERE id=? AND mission_id=? LIMIT 1",[$versionId,$missionId]);
 if(!$version)throw new RuntimeException('Version not found.');
 gdb()->beginTransaction();
 gdb_update('goliath_v118_asset_selections',['is_current'=>0],'mission_id=:mission_id',['mission_id'=>$missionId]);
 $uid='selection_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(18));
 $id=gdb_insert('goliath_v118_asset_selections',[
  'selection_uid'=>$uid,'mission_id'=>$missionId,'version_id'=>$versionId,'selected_by'=>'mark',
  'reason'=>$reason,'is_current'=>1,'created_at'=>gdb_now()
 ]);
 gdb_insert('goliath_v112_events',[
  'mission_id'=>$missionId,'executive_key'=>'goliath','event_type'=>'founder_version_selected',
  'title'=>'Founder selected version '.$version['stage_no'].' by '.ucfirst($version['executive_key']),
  'details'=>$reason,'url'=>'/dashboard/goliath-workflow-review-v118-3.php?mission_id='.$missionId.'&stage='.$version['stage_no'].'&embed=1','created_at'=>gdb_now()
 ]);
 gdb()->commit();
 echo json_encode(['ok'=>true,'version'=>'V118.3 Version Selection','selection_id'=>$id,'selected_version_id'=>$versionId,'selected_stage'=>$version['stage_no'],'executive'=>$version['executive_key']]);
}catch(Throwable $e){if(gdb()->inTransaction())gdb()->rollBack();http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
?>