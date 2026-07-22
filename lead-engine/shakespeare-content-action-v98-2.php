<?php
/**
 * V98.2 Shakespeare Action Upgrade
 * save / approve / publish / send_to_einstein / send_to_social / mark_viewed
 */
ini_set('display_errors',0);
header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php';
 require_once __DIR__.'/goliath-db.php';
 $key=$_POST['key']??($_GET['key']??'');
 $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 function sh982_col($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function sh982_table($t){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?",[$t]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function sh982_uid($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function sh982_update($t,$id,$row){$safe=[];foreach($row as $k=>$v){if(sh982_col($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,'id=:id',['id'=>(int)$id]);}
 function sh982_insert($t,$row){$safe=[];foreach($row as $k=>$v){if(sh982_col($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function sh982_slug($s){$s=strtolower(trim($s));$s=preg_replace('/[^a-z0-9]+/','-',$s);return trim($s,'-');}

 $id=(int)($_POST['package_id']??($_GET['package_id']??0));
 $action=$_POST['action']??($_GET['action']??'save');
 if(!$id){echo json_encode(['ok'=>false,'error'=>'missing_package_id']);exit;}
 $row=gdb_one("SELECT * FROM shakespeare_content_packages WHERE id=?",[$id]);
 if(!$row){echo json_encode(['ok'=>false,'error'=>'not_found']);exit;}

 $title=trim($_POST['title']??$row['title']);
 $html=$_POST['html_content']??$row['html_content'];
 $metaTitle=trim($_POST['meta_title']??$row['meta_title']);
 $metaDescription=trim($_POST['meta_description']??$row['meta_description']);
 $emailBlurb=trim($_POST['email_blurb']??$row['email_blurb']);
 $notes=trim($_POST['viewer_notes']??($row['viewer_notes']??''));

 $data=['title'=>$title,'html_content'=>$html,'text_content'=>strip_tags($html),'meta_title'=>$metaTitle,'meta_description'=>$metaDescription,'email_blurb'=>$emailBlurb,'viewer_notes'=>$notes,'updated_at'=>gdb_now()];
 $extra=[];

 if($action==='mark_viewed'){$data['last_viewed_at']=gdb_now();}
 if($action==='approve'){$data['approval_status']='approved';$data['status']='approved';$data['approved_at']=gdb_now();}
 if($action==='send_to_einstein'){
   $data['einstein_status']='queued';
   if(sh982_table('executive_initiatives')) sh982_insert('executive_initiatives',['initiative_uid'=>sh982_uid('init'),'executive_key'=>'einstein','title'=>'Review Shakespeare package: '.$title,'business_goal'=>'SEO/AEO and asset compounding','recommendation'=>'Score keyword coverage, schema, internal links, FAQ, EEAT, and AEO readiness. Return fixes to Shakespeare.','proposed_next_action'=>'Open package #'.$id.' and perform optimization review.','priority'=>850,'status'=>'proposed','source_type'=>'shakespeare_content_packages','source_id'=>$id,'created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
 }
 if($action==='send_to_social'){
   $captions=json_decode($row['social_captions_json']??'{}',true);
   if(!is_array($captions))$captions=[];
   $sid=sh982_insert('goliath_social_queue',['social_uid'=>sh982_uid('soc'),'source_table'=>'shakespeare_content_packages','source_id'=>$id,'title'=>$title,'content_type'=>$row['content_type'],'target_channels'=>'facebook,instagram,linkedin,google_business,pinterest','caption_json'=>json_encode($captions,JSON_UNESCAPED_SLASHES),'asset_url'=>$row['published_path'],'status'=>'queued','priority'=>800,'created_by'=>'shakespeare','created_at'=>gdb_now(),'updated_at'=>gdb_now()]);
   $data['social_status']='queued';$data['social_queue_id']=$sid;$extra['social_queue_id']=$sid;
 }
 if($action==='publish'){
   $slug=$row['slug']?:sh982_slug($title);
   $isTown=($row['content_type']==='town_authority_page');
   $publicPath=$isTown ? '/'.str_replace('-ct','',ucwords($slug,'-')).'CT.php' : '/blog/'.$slug.'.html';
   $publicPath=str_replace('-','',$publicPath);
   if(!$isTown)$publicPath='/blog/'.$slug.'.html';
   $path=$_SERVER['DOCUMENT_ROOT'].$publicPath;
   $page='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.htmlspecialchars($metaTitle).'</title><meta name="description" content="'.htmlspecialchars($metaDescription).'"><script type="application/ld+json">'.($row['schema_json']?:'{}').'</script><style>body{margin:0;font-family:Arial,sans-serif;line-height:1.65;color:#111827;background:#f8fafc}article{max-width:1100px;margin:auto;padding:32px;background:#fff}.eyebrow{color:#a77a17;font-weight:900;text-transform:uppercase}.lede{font-size:21px;color:#374151}.cta,.video-placeholder{background:#111827;color:#fff;padding:22px;border-radius:18px}.guide-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.guide-grid>div{background:#f3f4f6;border-radius:16px;padding:16px}a{color:#0f766e}</style></head><body>'.$html.'</body></html>';
   if(!is_dir(dirname($path))) mkdir(dirname($path),0755,true);
   file_put_contents($path,$page);
   $data['published_path']=$publicPath;$data['status']='published';$data['approval_status']='approved';$data['published_at']=gdb_now();
   $extra['published_path']=$publicPath;
 }
 sh982_update('shakespeare_content_packages',$id,$data);

 if(sh982_table('relationship_timeline')) sh982_insert('relationship_timeline',['event_uid'=>sh982_uid('rel'),'executive_key'=>'shakespeare','event_type'=>'content_'.$action,'title'=>'Shakespeare '.$action.': '.$title,'details'=>'Package #'.$id.' '.$action,'metadata'=>json_encode($extra),'priority'=>70,'is_new'=>1,'created_at'=>gdb_now()]);

 echo json_encode(['ok'=>true,'version'=>'V98.2 Shakespeare Content Action','action'=>$action,'package_id'=>$id,'extra'=>$extra,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V98.2 Shakespeare Content Action','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>