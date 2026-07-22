<?php
ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
try{
 require_once __DIR__.'/config.php'; require_once __DIR__.'/goliath-db.php';
 $key=$_POST['key']??$_GET['key']??''; $expected=defined('AFTER_HOURS_CRON_KEY')?AFTER_HOURS_CRON_KEY:(defined('RETELL_WEBHOOK_KEY')?RETELL_WEBHOOK_KEY:'timetomakethedonuts');
 if(!hash_equals((string)$expected,(string)$key)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'bad_key']);exit;}
 $type=strtolower(trim($_POST['listing_type']??$_GET['listing_type']??''));
 $allowed=['active','closed','expired','withdrawn','canceled'];
 if(!in_array($type,$allowed,true)){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'bad_listing_type','allowed'=>$allowed]);exit;}
 if(empty($_FILES['csv_file'])||!is_uploaded_file($_FILES['csv_file']['tmp_name'])){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'csv_file_required']);exit;}
 function uid105($p){return function_exists('gdb_uid')?gdb_uid($p):$p.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));}
 function col105($t,$c){try{$r=gdb_one("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?",[$t,$c]);return ((int)($r['c']??0))>0;}catch(Throwable $e){return false;}}
 function ins105($t,$row){$safe=[];foreach($row as $k=>$v){if(col105($t,$k))$safe[$k]=$v;}return $safe?gdb_insert($t,$safe):null;}
 function upd105($t,$where,$params,$row){$safe=[];foreach($row as $k=>$v){if(col105($t,$k))$safe[$k]=$v;}if($safe)gdb_update($t,$safe,$where,$params);}
 function one105($s,$p=[]){try{return gdb_one($s,$p)?:null;}catch(Throwable $e){return null;}}
 function normkey($s){return strtolower(preg_replace('/[^a-z0-9]+/','_',trim((string)$s)));}
 function val($row,$map,$keys){foreach($keys as $k){foreach($map as $mk=>$idx){if($mk===$k||strpos($mk,$k)!==false){return trim((string)($row[$idx]??''));}}}return '';}
 function money($v){$v=preg_replace('/[^0-9.]/','',(string)$v);return $v===''?0:(float)$v;}
 function naddr($addr,$town='',$zip=''){$s=strtolower(trim($addr.' '.$town.' '.$zip));$s=preg_replace('/\b(street|st)\b/','st',$s);$s=preg_replace('/\b(avenue|ave)\b/','ave',$s);$s=preg_replace('/\b(road|rd)\b/','rd',$s);$s=preg_replace('/\b(drive|dr)\b/','dr',$s);$s=preg_replace('/[^a-z0-9]+/',' ',$s);return trim(preg_replace('/\s+/',' ',$s));}
 $orig=$_FILES['csv_file']['name']??'mls.csv';
 $dir=dirname(__DIR__).'/data/mls/'.$type; if(!is_dir($dir))mkdir($dir,0775,true);
 $safe=preg_replace('/[^a-zA-Z0-9._-]+/','-',pathinfo($orig,PATHINFO_FILENAME));
 $dest=$dir.'/'.date('Ymd-His').'-'.$type.'-'.$safe.'.csv';
 if(!move_uploaded_file($_FILES['csv_file']['tmp_name'],$dest)){throw new Exception('could_not_save_upload');}
 $batchUid=uid105('mls_batch');
 ins105('mls_import_batches',['batch_uid'=>$batchUid,'listing_type'=>$type,'original_filename'=>$orig,'stored_path'=>$dest,'status'=>'importing','created_at'=>gdb_now()]);
 $fh=fopen($dest,'r'); if(!$fh)throw new Exception('cannot_open_csv');
 $headers=fgetcsv($fh); if(!$headers)throw new Exception('empty_csv');
 $map=[]; foreach($headers as $i=>$h)$map[normkey($h)]=$i;
 $rows=0;$imported=0;
 while(($row=fgetcsv($fh))!==false){
   $rows++;
   $addr=val($row,$map,['property_address','address','street_address','full_address','location','situs_address']);
   $town=val($row,$map,['town','city','municipality']);
   $state=val($row,$map,['state']);
   $zip=val($row,$map,['zip','postal']);
   $mls=val($row,$map,['mls','mls_id','list_number','listing_id']);
   $status=val($row,$map,['status','listing_status','mls_status']);
   $list=money(val($row,$map,['list_price','asking_price','price']));
   $sold=money(val($row,$map,['sold_price','sale_price','closed_price']));
   $date=val($row,$map,['status_date','close_date','expiration_date','expired_date','list_date','contract_date']);
   $norm=naddr($addr,$town,$zip);
   if(!$norm)continue;
   ins105('mls_property_events',['event_uid'=>uid105('mls_evt'),'batch_uid'=>$batchUid,'listing_type'=>$type,'mls_id'=>$mls,'normalized_address'=>$norm,'property_address'=>$addr,'town'=>$town,'state'=>$state,'zip'=>$zip,'list_price'=>$list,'sold_price'=>$sold,'status_text'=>$status,'status_date'=>$date?:null,'raw_json'=>json_encode(array_combine($headers,array_pad($row,count($headers),'')),JSON_UNESCAPED_SLASHES),'created_at'=>gdb_now()]);
   $master=one105("SELECT * FROM mls_master_properties WHERE normalized_address=? LIMIT 1",[$norm]);
   $flags=['has_active'=>0,'has_closed'=>0,'has_expired'=>0,'has_withdrawn'=>0,'has_canceled'=>0];
   $flags['has_'.$type]=1;
   $mrow=['property_address'=>$addr,'town'=>$town,'state'=>$state,'zip'=>$zip,'last_mls_id'=>$mls,'last_status'=>$type,'last_event_at'=>gdb_now(),'updated_at'=>gdb_now()];
   foreach($flags as $k=>$v)if($v)$mrow[$k]=1;
   if($master){$mrow['event_count']=(int)($master['event_count']??0)+1;upd105('mls_master_properties','id=:id',['id'=>$master['id']],$mrow);}
   else {$mrow['property_uid']=uid105('prop');$mrow['normalized_address']=$norm;$mrow['created_at']=gdb_now();$mrow['event_count']=1;ins105('mls_master_properties',$mrow);}
   $imported++;
 }
 fclose($fh);
 upd105('mls_import_batches','batch_uid=:b',['b'=>$batchUid],['row_count'=>$rows,'imported_count'=>$imported,'status'=>'imported','notes'=>'Imported as '.$type]);
 echo json_encode(['ok'=>true,'version'=>'V105.0 MLS Upload','listing_type'=>$type,'batch_uid'=>$batchUid,'rows'=>$rows,'imported'=>$imported,'next'=>'Run /lead-engine/scout-mls-reconcile-v105.php?key='.$key,'time'=>date('c')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo json_encode(['ok'=>false,'version'=>'V105.0 MLS Upload','error'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],JSON_PRETTY_PRINT);}
?>