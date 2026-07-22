<?php
declare(strict_types=1);
session_start();header('Content-Type: application/json; charset=utf-8');require_once __DIR__.'/config.php';require_once __DIR__.'/goliath-db.php';
if(empty($_SESSION['mp_dashboard_auth'])){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'not_authenticated']);exit;}
$id=(int)($_GET['project_id']??0);
try{
 $project=$id?gdb_one("SELECT * FROM scorsese_director_projects WHERE id=? LIMIT 1",[$id]):null;
 $projects=$id?[]:(gdb_all("SELECT * FROM scorsese_director_projects ORDER BY id DESC LIMIT 50")?:[]);
 echo json_encode(['ok'=>true,'version'=>'V118.3 Scorsese Director Data','project'=>$project,'projects'=>$projects,
  'sources'=>$id?(gdb_all("SELECT * FROM scorsese_media_sources WHERE project_id=? ORDER BY id",[$id])?:[]):[],
  'scenes'=>$id?(gdb_all("SELECT * FROM scorsese_scenes WHERE project_id=? ORDER BY scene_no",[$id])?:[]):[],
  'takes'=>$id?(gdb_all("SELECT t.* FROM scorsese_takes t JOIN scorsese_scenes s ON s.id=t.scene_id WHERE s.project_id=? ORDER BY s.scene_no,t.take_no",[$id])?:[]):[],
  'notes'=>$id?(gdb_all("SELECT * FROM scorsese_director_notes WHERE project_id=? ORDER BY id DESC",[$id])?:[]):[],
  'edl'=>$id?(gdb_all("SELECT * FROM scorsese_edl_items WHERE project_id=? ORDER BY version_no,sequence_no",[$id])?:[]):[],
  'renders'=>$id?(gdb_all("SELECT * FROM scorsese_renders WHERE project_id=? ORDER BY id DESC",[$id])?:[]):[]
 ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
?>