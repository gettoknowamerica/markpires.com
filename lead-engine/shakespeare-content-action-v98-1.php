<?php
/**
 * V98.1 Shakespeare Content Action
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_POST['key']??($_GET['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 $id=(int)($_POST['package_id']??0); $action=$_POST['action']??'save';
 if(!$id){echo json_encode(['ok'=>false,'error'=>'missing_package_id']);exit;}
 $row=gdb_one("SELECT * FROM shakespeare_content_packages WHERE id=?",[$id]);
 if(!$row){echo json_encode(['ok'=>false,'error'=>'not_found']);exit;}
 $data=[
  'title'=>trim($_POST['title']??$row['title']),
  'html_content'=>$_POST['html_content']??$row['html_content'],
  'meta_title'=>trim($_POST['meta_title']??$row['meta_title']),
  'meta_description'=>trim($_POST['meta_description']??$row['meta_description']),
  'email_blurb'=>trim($_POST['email_blurb']??$row['email_blurb']),
  'updated_at'=>gdb_now()
 ];
 if($action==='approve'){$data['approval_status']='approved';$data['status']='approved';$data['approved_at']=gdb_now();}
 if($action==='send_to_einstein'){$data['einstein_status']='queued';}
 if($action==='publish'){
   $slug=$row['slug']?:preg_replace('/[^a-z0-9]+/','-',strtolower($data['title']));
   $path=$_SERVER['DOCUMENT_ROOT'].'/blog/'.$slug.'.html';
   $page='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($data['meta_title']).'</title><meta name="description" content="'.htmlspecialchars($data['meta_description']).'"><style>body{font-family:Arial,sans-serif;line-height:1.6;margin:0;color:#111}article{max-width:980px;margin:auto;padding:28px}.eyebrow{color:#a77a17;font-weight:bold;text-transform:uppercase}.lede{font-size:20px}.cta,.video-placeholder{background:#111827;color:#fff;padding:20px;border-radius:18px}.guide-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}</style></head><body>'.$data['html_content'].'</body></html>';
   if(!is_dir(dirname($path))) mkdir(dirname($path),0755,true);
   file_put_contents($path,$page);
   $data['published_path']='/blog/'.$slug.'.html';$data['status']='published';$data['published_at']=gdb_now();
 }
 gdb_update('shakespeare_content_packages',$data,'id=:id',['id'=>$id]);
 echo json_encode(['ok'=>true,'version'=>'V98.1 Shakespeare Content Action','action'=>$action,'package_id'=>$id,'published_path'=>$data['published_path']??null,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V98.1 Shakespeare Content Action','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>