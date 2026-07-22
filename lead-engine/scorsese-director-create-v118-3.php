<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'not_authenticated']);exit;}
$in=json_decode((string)file_get_contents('php://input'),true);if(!is_array($in))$in=$_POST;
try{
 $title=trim((string)($in['title']??''));if($title==='')throw new RuntimeException('Title is required.');
 $uid='director_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(18));
 $id=gdb_insert('scorsese_director_projects',[
  'project_uid'=>$uid,'mission_id'=>(int)($in['mission_id']??0)?:null,'asset_version_id'=>(int)($in['asset_version_id']??0)?:null,
  'title'=>$title,'production_mode'=>in_array(($in['production_mode']??''),['automatic_director','human_director'],true)?$in['production_mode']:'automatic_director',
  'production_type'=>(string)($in['production_type']??'episode'),'source_goal'=>(string)($in['source_goal']??''),
  'supplied_script'=>(string)($in['supplied_script']??''),'status'=>'ingest','progress'=>0,'current_phase'=>'Awaiting source media',
  'metadata_json'=>gdb_json(['created_by'=>'mark','version'=>'118.3']),'created_at'=>gdb_now(),'updated_at'=>gdb_now()
 ]);
 echo json_encode(['ok'=>true,'version'=>'V118.3 Scorsese Director Project','project_id'=>$id,'project_uid'=>$uid,'url'=>'/dashboard/scorsese-director-workstation-v118-3.php?project_id='.$id]);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
?>